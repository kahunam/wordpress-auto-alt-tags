# Auto Alt Tags v1.1.0 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add auto-generation on upload via WP Cron, Media Library integration (column/filter/CSV), and WordPress.org readiness (uninstall.php, i18n, Plugin Check).

**Architecture:** Each phase adds to the single `AutoAltTagGenerator` class in `auto-alt-tags.php` and a new `uninstall.php`. Queue logic extracted into small testable helper methods. No new database tables.

**Tech Stack:** PHP 7.4+, WordPress hooks API, WP Cron, jQuery (existing), PHPUnit (existing test suite at `composer test`).

---

## Orientation

### Key files

- `auto-alt-tags.php` — entire plugin lives here as `AutoAltTagGenerator` class (1500+ lines)
- `assets/js/admin.js` — jQuery admin UI
- `tests/mocks/wordpress-functions.php` — mock WP functions for standalone PHPUnit tests
- `tests/test-*.php` — existing PHPUnit test files; run with `composer test`
- `composer.json` — build/test commands

### Key existing methods (referenced in tasks below)

- `generate_alt_tag(int $attachment_id): array` — calls AI API, saves postmeta, returns `['success'=>bool, ...]`
- `get_images_without_alt(): array` — returns array of attachment IDs missing alt text
- `get_current_api_key(string $provider): string` — returns active API key

### Run tests

```bash
cd /path/to/wordpress-auto-alt-tags
composer test
```

Expected output: all tests pass, no errors.

---

## Phase 1: Auto-generation on Upload + WP Cron Background Processing

---

### Task 1: Queue helper methods with unit tests

**Files:**
- Modify: `auto-alt-tags.php` (add 4 private methods to `AutoAltTagGenerator`)
- Modify: `tests/mocks/wordpress-functions.php` (add mock for `update_option`)
- Create: `tests/test-queue.php`

**Step 1: Write the failing tests**

Create `tests/test-queue.php`:

```php
<?php
/**
 * Tests for queue helper methods
 *
 * @package AutoAltTags\Tests
 */

require_once __DIR__ . '/bootstrap.php';

class QueueTest extends PHPUnit\Framework\TestCase {

	private AutoAltTagGenerator $plugin;

	public function setUp(): void {
		global $mock_options;
		$mock_options['auto_alt_queue'] = json_encode( [] );
		$this->plugin = new AutoAltTagGenerator();
	}

	public function test_queue_push_adds_id(): void {
		$this->plugin->queue_push( 42 );
		$queue = $this->plugin->queue_get();
		$this->assertContains( 42, $queue );
	}

	public function test_queue_push_deduplicates(): void {
		$this->plugin->queue_push( 42 );
		$this->plugin->queue_push( 42 );
		$queue = $this->plugin->queue_get();
		$this->assertCount( 1, array_filter( $queue, fn( $id ) => $id === 42 ) );
	}

	public function test_queue_pop_returns_and_removes(): void {
		$this->plugin->queue_push( 10 );
		$this->plugin->queue_push( 20 );
		$batch = $this->plugin->queue_pop( 1 );
		$this->assertSame( [ 10 ], $batch );
		$this->assertNotContains( 10, $this->plugin->queue_get() );
		$this->assertContains( 20, $this->plugin->queue_get() );
	}

	public function test_queue_pop_returns_empty_when_queue_empty(): void {
		$batch = $this->plugin->queue_pop( 5 );
		$this->assertSame( [], $batch );
	}

	public function test_queue_cap_at_500(): void {
		for ( $i = 1; $i <= 510; $i++ ) {
			$this->plugin->queue_push( $i );
		}
		$this->assertCount( 500, $this->plugin->queue_get() );
	}

	public function test_queue_clear(): void {
		$this->plugin->queue_push( 1 );
		$this->plugin->queue_push( 2 );
		$this->plugin->queue_clear();
		$this->assertSame( [], $this->plugin->queue_get() );
	}
}
```

**Step 2: Run tests to verify they fail**

```bash
composer test -- --filter QueueTest
```

Expected: FAIL — `queue_push` method not found.

**Step 3: Add mock for `update_option` in test helpers**

Open `tests/mocks/wordpress-functions.php`. After the existing `get_option` mock (around line 50), verify `update_option` mock exists. If not, add:

```php
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		global $mock_options;
		$mock_options[ $option ] = $value;
		return true;
	}
}
```

**Step 4: Add queue helper methods to `AutoAltTagGenerator`**

In `auto-alt-tags.php`, add these four methods after the `get_current_api_key()` method (around line 201). Change visibility from `private` to `public` so PHPUnit can call them directly (they contain only pure data logic):

```php
/**
 * Get the current upload queue.
 *
 * @return int[]
 */
public function queue_get(): array {
	$raw = get_option( 'auto_alt_queue', '[]' );
	$ids = json_decode( $raw, true );
	return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
}

/**
 * Push an attachment ID onto the queue (deduplicated, capped at 500).
 *
 * @param int $attachment_id Attachment ID.
 */
public function queue_push( int $attachment_id ): void {
	$queue = $this->queue_get();
	if ( in_array( $attachment_id, $queue, true ) ) {
		return;
	}
	$queue[] = $attachment_id;
	if ( count( $queue ) > 500 ) {
		$queue = array_slice( $queue, count( $queue ) - 500 );
	}
	update_option( 'auto_alt_queue', wp_json_encode( $queue ) );
}

/**
 * Pop up to $count IDs from the front of the queue.
 *
 * @param int $count Number of IDs to pop.
 * @return int[]
 */
public function queue_pop( int $count ): array {
	$queue = $this->queue_get();
	if ( empty( $queue ) ) {
		return [];
	}
	$batch = array_splice( $queue, 0, $count );
	update_option( 'auto_alt_queue', wp_json_encode( array_values( $queue ) ) );
	return $batch;
}

/**
 * Clear the entire queue.
 */
public function queue_clear(): void {
	update_option( 'auto_alt_queue', '[]' );
}
```

**Step 5: Register `auto_alt_queue` option in `register_settings()`**

In `auto-alt-tags.php`, inside `register_settings()` (around line 296), add:

```php
register_setting( 'auto_alt_tags_settings', 'auto_alt_queue', array(
    'sanitize_callback' => 'sanitize_text_field',
    'default'           => '[]',
) );
```

**Step 6: Run tests to verify they pass**

```bash
composer test -- --filter QueueTest
```

Expected: all 6 tests PASS.

**Step 7: Commit**

```bash
git add auto-alt-tags.php tests/test-queue.php tests/mocks/wordpress-functions.php
git commit -m "feat: add queue helper methods with unit tests"
```

---

### Task 2: Auto-generate on upload via `add_attachment` hook

**Files:**
- Modify: `auto-alt-tags.php`

**Step 1: Write the failing test**

Add to `tests/test-queue.php` (inside `QueueTest` class):

```php
public function test_on_attachment_upload_queues_image(): void {
	global $mock_options;
	$mock_options['auto_alt_auto_generate'] = true;
	$mock_options['auto_alt_queue']          = json_encode( [] );

	// Simulate what on_attachment_upload does
	$this->plugin->on_attachment_upload( 99 );

	$this->assertContains( 99, $this->plugin->queue_get() );
}

public function test_on_attachment_upload_skips_when_disabled(): void {
	global $mock_options;
	$mock_options['auto_alt_auto_generate'] = false;
	$mock_options['auto_alt_queue']          = json_encode( [] );

	$this->plugin->on_attachment_upload( 99 );

	$this->assertNotContains( 99, $this->plugin->queue_get() );
}
```

**Step 2: Run to verify fail**

```bash
composer test -- --filter QueueTest
```

Expected: FAIL — `on_attachment_upload` method not found.

**Step 3: Add the hook registration to `__construct()`**

In `auto-alt-tags.php`, in the `__construct()` method (around line 227), add after the existing `add_action` calls:

```php
add_action( 'add_attachment', array( $this, 'on_attachment_upload' ) );
```

Also add the new setting to `__construct()` initialization block (after line 243):

```php
$this->auto_generate = (bool) get_option( 'auto_alt_auto_generate', true );
```

Add the property declaration near the top of the class (after `$debug_mode`, around line 215):

```php
/**
 * Auto-generate on upload flag
 *
 * @var bool
 */
private bool $auto_generate = true;
```

**Step 4: Add the `on_attachment_upload` method**

Add after `queue_clear()`:

```php
/**
 * Handle new image upload — push to queue if auto-generate is enabled.
 *
 * @param int $attachment_id Newly uploaded attachment ID.
 */
public function on_attachment_upload( int $attachment_id ): void {
	if ( ! $this->auto_generate ) {
		return;
	}

	// Only queue images, not other attachment types.
	if ( ! wp_attachment_is_image( $attachment_id ) ) {
		return;
	}

	// Skip if alt text already set (e.g. imported from another plugin).
	$existing = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
	if ( ! empty( $existing ) ) {
		return;
	}

	$this->queue_push( $attachment_id );
	$this->debug_log( sprintf( 'Queued uploaded image ID %d for background processing', $attachment_id ) );
}
```

**Step 5: Add `wp_attachment_is_image` and `get_post_meta` mocks to test helpers**

In `tests/mocks/wordpress-functions.php`, add if not already present:

```php
if ( ! function_exists( 'wp_attachment_is_image' ) ) {
	function wp_attachment_is_image( $post_id ) {
		return true; // default: treat all as images in tests
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		return ''; // default: no existing meta
	}
}
```

**Step 6: Run tests to verify pass**

```bash
composer test -- --filter QueueTest
```

Expected: all tests PASS.

**Step 7: Commit**

```bash
git add auto-alt-tags.php tests/test-queue.php tests/mocks/wordpress-functions.php
git commit -m "feat: queue new image uploads for background alt text generation"
```

---

### Task 3: WP Cron event — register, handler, deregister

**Files:**
- Modify: `auto-alt-tags.php`

**Step 1: Register cron event on plugin activation**

In `auto-alt-tags.php`, add a static activation method and hook it. After `load_textdomain()` method (around line 256), add:

```php
/**
 * Schedule WP Cron event on plugin activation.
 */
public static function activate(): void {
	if ( ! wp_next_scheduled( 'auto_alt_tags_process_queue' ) ) {
		wp_schedule_event( time(), 'every_five_minutes', 'auto_alt_tags_process_queue' );
	}
}

/**
 * Clear WP Cron event on plugin deactivation.
 */
public static function deactivate(): void {
	$timestamp = wp_next_scheduled( 'auto_alt_tags_process_queue' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'auto_alt_tags_process_queue' );
	}
}
```

At the bottom of `auto-alt-tags.php`, after the existing `new AutoAltTagGenerator();` line (around line 856+), add:

```php
register_activation_hook( AUTO_ALT_TAGS_PLUGIN_FILE, array( 'AutoAltTagGenerator', 'activate' ) );
register_deactivation_hook( AUTO_ALT_TAGS_PLUGIN_FILE, array( 'AutoAltTagGenerator', 'deactivate' ) );
```

**Step 2: Register custom cron interval**

In `__construct()`, add:

```php
add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
add_action( 'auto_alt_tags_process_queue', array( $this, 'process_queue_via_cron' ) );
```

**Step 3: Add the two new methods**

After `deactivate()`, add:

```php
/**
 * Register custom cron interval (every 5 minutes).
 *
 * @param array $schedules Existing WP cron schedules.
 * @return array
 */
public function add_cron_schedules( array $schedules ): array {
	if ( ! isset( $schedules['every_five_minutes'] ) ) {
		$schedules['every_five_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Every Five Minutes', 'auto-alt-tags' ),
		);
	}
	return $schedules;
}

/**
 * WP Cron callback — processes a batch of queued images.
 */
public function process_queue_via_cron(): void {
	$queue = $this->queue_get();
	if ( empty( $queue ) ) {
		return;
	}

	$provider = get_option( 'auto_alt_provider', 'gemini' );
	$api_key  = $this->get_current_api_key( $provider );

	if ( empty( $api_key ) ) {
		$this->debug_log( 'Cron: no API key configured, skipping queue processing' );
		return;
	}

	$batch_size = (int) get_option( 'auto_alt_batch_size', $this->batch_size );
	$batch      = $this->queue_pop( $batch_size );

	$this->debug_log( sprintf( 'Cron: processing batch of %d queued images', count( $batch ) ) );

	foreach ( $batch as $attachment_id ) {
		// Skip if alt text was set since it was queued.
		$existing = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( ! empty( $existing ) ) {
			continue;
		}

		$attempts_key = '_auto_alt_attempts';
		$attempts     = (int) get_post_meta( $attachment_id, $attempts_key, true );

		if ( $attempts >= 3 ) {
			$this->debug_log( sprintf( 'Cron: skipping image ID %d (3 failed attempts)', $attachment_id ) );
			continue;
		}

		$result = $this->generate_alt_tag( $attachment_id );

		if ( $result['success'] ) {
			delete_post_meta( $attachment_id, $attempts_key );
			$this->debug_log( sprintf( 'Cron: success for image ID %d', $attachment_id ) );
		} else {
			update_post_meta( $attachment_id, $attempts_key, $attempts + 1 );
			$this->debug_log( sprintf( 'Cron: failure for image ID %d (attempt %d)', $attachment_id, $attempts + 1 ) );

			// Re-queue for retry unless max attempts reached.
			if ( $attempts + 1 < 3 ) {
				$this->queue_push( $attachment_id );
			}
		}
	}
}
```

**Step 4: Run existing tests to check for regressions**

```bash
composer test
```

Expected: all existing tests still PASS.

**Step 5: Commit**

```bash
git add auto-alt-tags.php
git commit -m "feat: register WP Cron event to process upload queue every 5 minutes"
```

---

### Task 4: "Auto-generate on upload" setting + admin notice

**Files:**
- Modify: `auto-alt-tags.php`

**Step 1: Register the new setting**

In `register_settings()` (around line 296), add:

```php
register_setting( 'auto_alt_tags_settings', 'auto_alt_auto_generate', array(
    'sanitize_callback' => 'rest_sanitize_boolean',
    'default'           => true,
) );
```

**Step 2: Add setting to the Settings form**

In `admin_page()`, inside the `<table class="form-table">` (after the debug mode row, around line 718), add a new row:

```php
<tr>
    <th scope="row">
        <label for="auto_alt_auto_generate"><?php esc_html_e( 'Auto-generate on Upload', 'auto-alt-tags' ); ?></label>
    </th>
    <td>
        <label for="auto_alt_auto_generate">
            <input type="checkbox"
                   id="auto_alt_auto_generate"
                   name="auto_alt_auto_generate"
                   value="1"
                   <?php checked( get_option( 'auto_alt_auto_generate', true ) ); ?> />
            <?php esc_html_e( 'Automatically queue new image uploads for alt text generation', 'auto-alt-tags' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'When enabled, images uploaded to the Media Library are automatically queued and processed in the background every 5 minutes.', 'auto-alt-tags' ); ?>
        </p>
    </td>
</tr>
```

**Step 3: Add admin notice for active queue**

In `__construct()`, add:

```php
add_action( 'admin_notices', array( $this, 'admin_notice_queue' ) );
```

Add the method after `add_admin_menu()`:

```php
/**
 * Show admin notice when background queue has pending images.
 */
public function admin_notice_queue(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$queue = $this->queue_get();
	if ( empty( $queue ) ) {
		return;
	}

	printf(
		'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
		esc_html( sprintf(
			/* translators: %d: number of images in queue */
			_n(
				'Auto Alt Tags is processing %d image in the background.',
				'Auto Alt Tags is processing %d images in the background.',
				count( $queue ),
				'auto-alt-tags'
			),
			count( $queue )
		) )
	);
}
```

**Step 4: Run tests and check no regressions**

```bash
composer test
```

Expected: PASS.

**Step 5: Commit**

```bash
git add auto-alt-tags.php
git commit -m "feat: add auto-generate on upload setting and admin queue notice"
```

---

### Task 5: WP-CLI `retry-failed` command

**Files:**
- Modify: `includes/class-wp-cli-command.php`

**Step 1: Read the existing WP-CLI file to understand structure**

Open `includes/class-wp-cli-command.php` and find the `generate` command (it accepts `--dry-run`, `--batch-size`, `--limit`). The class is `Auto_Alt_CLI_Command` and uses `$this->plugin` to access the main class.

**Step 2: Add `retry_failed` command**

After the last command method in `includes/class-wp-cli-command.php`, add:

```php
/**
 * Re-queue all images that failed after 3 attempts.
 *
 * ## EXAMPLES
 *
 *     wp auto-alt retry-failed
 *
 * @when after_wp_load
 */
public function retry_failed(): void {
	global $wpdb;

	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			WHERE meta_key = %s AND meta_value >= %d",
			'_auto_alt_attempts',
			3
		)
	);

	if ( empty( $ids ) ) {
		\WP_CLI::success( 'No failed images found.' );
		return;
	}

	foreach ( $ids as $id ) {
		delete_post_meta( (int) $id, '_auto_alt_attempts' );
		$this->plugin->queue_push( (int) $id );
	}

	\WP_CLI::success( sprintf(
		/* translators: %d: number of re-queued images */
		_n( 'Re-queued %d failed image.', 'Re-queued %d failed images.', count( $ids ), 'auto-alt-tags' ),
		count( $ids )
	) );
}
```

**Step 3: Register the command**

Verify command registration at the bottom of `class-wp-cli-command.php`. There should be a line like:

```php
WP_CLI::add_command( 'auto-alt', 'Auto_Alt_CLI_Command' );
```

If it uses method-based registration, add `retry-failed` to the array. If it uses class-based registration, the method name `retry_failed` auto-maps to `retry-failed` — no change needed.

**Step 4: Run tests**

```bash
composer test
```

Expected: PASS.

**Step 5: Commit**

```bash
git add includes/class-wp-cli-command.php
git commit -m "feat: add wp auto-alt retry-failed CLI command"
```

---

## Phase 2: Media Library Integration

---

### Task 6: Alt text column in Media Library list view

**Files:**
- Modify: `auto-alt-tags.php`

**Step 1: Register hooks in `__construct()`**

Add these lines to `__construct()`:

```php
add_filter( 'manage_media_columns', array( $this, 'media_column_header' ) );
add_action( 'manage_media_custom_column', array( $this, 'media_column_content' ), 10, 2 );
add_action( 'wp_ajax_auto_alt_regenerate_single', array( $this, 'ajax_regenerate_single' ) );
```

**Step 2: Add the two column methods**

After `admin_notice_queue()`, add:

```php
/**
 * Register the Alt Text column in Media Library list view.
 *
 * @param string[] $columns Existing columns.
 * @return string[]
 */
public function media_column_header( array $columns ): array {
	$columns['auto_alt_text'] = __( 'Alt Text', 'auto-alt-tags' );
	return $columns;
}

/**
 * Render the Alt Text column content for each media item.
 *
 * @param string $column_name Column identifier.
 * @param int    $post_id     Attachment post ID.
 */
public function media_column_content( string $column_name, int $post_id ): void {
	if ( 'auto_alt_text' !== $column_name ) {
		return;
	}

	$alt = get_post_meta( $post_id, '_wp_attachment_image_alt', true );

	if ( ! empty( $alt ) ) {
		echo '<span style="color:#1e8a44;">' . esc_html( wp_trim_words( $alt, 10, '…' ) ) . '</span>';
	} else {
		echo '<span style="color:#cc1818;font-weight:bold;">' . esc_html__( 'Missing', 'auto-alt-tags' ) . '</span>';
	}

	echo '<br><a href="#" class="auto-alt-regenerate" data-id="' . esc_attr( $post_id ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'auto_alt_nonce' ) ) . '">'
		. esc_html__( 'Regenerate', 'auto-alt-tags' ) . '</a>';
}
```

**Step 3: Add AJAX handler for single-image regeneration**

```php
/**
 * AJAX: regenerate alt text for a single attachment.
 */
public function ajax_regenerate_single(): void {
	if ( ! check_ajax_referer( 'auto_alt_nonce', 'nonce', false ) ) {
		wp_send_json_error( __( 'Security check failed', 'auto-alt-tags' ) );
	}
	if ( ! current_user_can( 'upload_files' ) ) {
		wp_send_json_error( __( 'Unauthorized', 'auto-alt-tags' ) );
	}

	$attachment_id = (int) ( $_POST['id'] ?? 0 );
	if ( ! $attachment_id ) {
		wp_send_json_error( __( 'Invalid attachment ID', 'auto-alt-tags' ) );
	}

	// Clear any existing alt text so generate_alt_tag will process it.
	delete_post_meta( $attachment_id, '_wp_attachment_image_alt' );

	$result = $this->generate_alt_tag( $attachment_id );

	if ( $result['success'] ) {
		wp_send_json_success( array( 'alt_text' => $result['alt_text'] ) );
	} else {
		wp_send_json_error( $result['error'] );
	}
}
```

**Step 4: Enqueue JS for Media Library column**

The regenerate link needs a small JS handler. In `enqueue_admin_scripts()` (around line 263), the script is only loaded on the plugin's own page. Add a second enqueue for the Media Library:

```php
// Also enqueue on media upload page for the column regenerate button.
if ( 'upload.php' === $GLOBALS['pagenow'] || 'media_page_auto-alt-tags' === $hook ) {
    wp_enqueue_script(
        'ka-alt-tags-media',
        AUTO_ALT_TAGS_PLUGIN_URL . 'assets/js/media-column.js',
        array( 'jquery' ),
        AUTO_ALT_TAGS_VERSION,
        true
    );
    wp_localize_script( 'ka-alt-tags-media', 'autoAltMedia', array(
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'auto_alt_nonce' ),
    ) );
}
```

**Step 5: Create `assets/js/media-column.js`**

```javascript
(function($) {
    'use strict';

    $(document).on('click', '.auto-alt-regenerate', function(e) {
        e.preventDefault();

        var $link = $(this);
        var id    = $link.data('id');
        var nonce = $link.data('nonce');

        $link.text('Generating…').prop('disabled', true);

        $.post(autoAltMedia.ajaxurl, {
            action: 'auto_alt_regenerate_single',
            id:     id,
            nonce:  nonce
        }, function(response) {
            if (response.success) {
                var $cell = $link.closest('td');
                $cell.find('span').first()
                    .css('color', '#1e8a44')
                    .text(response.data.alt_text.substring(0, 60) + (response.data.alt_text.length > 60 ? '…' : ''));
                $link.text('Regenerate').prop('disabled', false);
            } else {
                alert('Error: ' + response.data);
                $link.text('Regenerate').prop('disabled', false);
            }
        });
    });
})(jQuery);
```

**Step 6: Run tests**

```bash
composer test
```

Expected: PASS.

**Step 7: Commit**

```bash
git add auto-alt-tags.php assets/js/media-column.js
git commit -m "feat: add Alt Text column to Media Library with per-image regenerate"
```

---

### Task 7: Media Library filter by alt text status

**Files:**
- Modify: `auto-alt-tags.php`

**Step 1: Register hooks in `__construct()`**

```php
add_action( 'restrict_manage_posts', array( $this, 'media_filter_dropdown' ) );
add_filter( 'parse_query', array( $this, 'media_filter_query' ) );
```

**Step 2: Add the two methods**

```php
/**
 * Add "Filter by Alt Text" dropdown to Media Library toolbar.
 *
 * @param string $post_type Current post type.
 */
public function media_filter_dropdown( string $post_type ): void {
	if ( 'attachment' !== $post_type ) {
		return;
	}

	$selected = sanitize_text_field( $_GET['auto_alt_filter'] ?? '' );
	?>
	<select name="auto_alt_filter" id="auto_alt_filter">
		<option value=""><?php esc_html_e( 'All Alt Text', 'auto-alt-tags' ); ?></option>
		<option value="has" <?php selected( $selected, 'has' ); ?>><?php esc_html_e( 'Has Alt Text', 'auto-alt-tags' ); ?></option>
		<option value="missing" <?php selected( $selected, 'missing' ); ?>><?php esc_html_e( 'Missing Alt Text', 'auto-alt-tags' ); ?></option>
	</select>
	<?php
}

/**
 * Apply alt text filter to the Media Library query.
 *
 * @param \WP_Query $query Current query.
 */
public function media_filter_query( \WP_Query $query ): void {
	global $pagenow;

	if ( 'upload.php' !== $pagenow || ! $query->is_main_query() ) {
		return;
	}

	$filter = sanitize_text_field( $_GET['auto_alt_filter'] ?? '' );
	if ( ! in_array( $filter, array( 'has', 'missing' ), true ) ) {
		return;
	}

	$meta_query = array(
		'key'     => '_wp_attachment_image_alt',
		'compare' => 'has' === $filter ? '!=' : '=',
		'value'   => '',
	);

	if ( 'missing' === $filter ) {
		// Images with no meta row at all also count as missing.
		$query->set( 'meta_query', array(
			'relation' => 'OR',
			array(
				'key'     => '_wp_attachment_image_alt',
				'value'   => '',
				'compare' => '=',
			),
			array(
				'key'     => '_wp_attachment_image_alt',
				'compare' => 'NOT EXISTS',
			),
		) );
	} else {
		$query->set( 'meta_query', array( $meta_query ) );
	}
}
```

**Step 3: Run tests**

```bash
composer test
```

Expected: PASS.

**Step 4: Commit**

```bash
git add auto-alt-tags.php
git commit -m "feat: add Media Library filter by alt text presence/absence"
```

---

### Task 8: CSV Export

**Files:**
- Modify: `auto-alt-tags.php`

**Step 1: Register AJAX action in `__construct()`**

```php
add_action( 'wp_ajax_auto_alt_export_csv', array( $this, 'ajax_export_csv' ) );
```

**Step 2: Add the export method**

```php
/**
 * AJAX: export all image alt text as a CSV download.
 */
public function ajax_export_csv(): void {
	if ( ! check_ajax_referer( 'auto_alt_nonce', 'nonce', false ) ) {
		wp_die( esc_html__( 'Security check failed', 'auto-alt-tags' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Unauthorized', 'auto-alt-tags' ) );
	}

	global $wpdb;

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT p.ID, p.post_title, pm.meta_value AS alt_text, p.guid
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm
				ON p.ID = pm.post_id AND pm.meta_key = %s
			WHERE p.post_type = %s
			AND p.post_mime_type LIKE %s
			ORDER BY p.ID ASC",
			'_wp_attachment_image_alt',
			'attachment',
			'image/%'
		),
		ARRAY_A
	);

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="alt-text-export-' . gmdate( 'Y-m-d' ) . '.csv"' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	$output = fopen( 'php://output', 'w' );
	fputcsv( $output, array( 'ID', 'Filename', 'Alt Text', 'URL' ) );

	foreach ( $rows as $row ) {
		fputcsv( $output, array(
			$row['ID'],
			$row['post_title'],
			$row['alt_text'] ?? '',
			$row['guid'],
		) );
	}

	fclose( $output );
	exit;
}
```

**Step 3: Add Export button to admin page**

In `admin_page()`, inside the Generate Alt Tags card (after the existing buttons, around line 450), add:

```php
<button id="ka_alt_export_csv" class="button button-secondary">
    <?php esc_html_e( 'Export Alt Text as CSV', 'auto-alt-tags' ); ?>
</button>
```

**Step 4: Add JS handler in `admin.js`**

In `assets/js/admin.js`, inside `$(document).ready()`, add:

```javascript
// Export CSV button
$('#ka_alt_export_csv').on('click', function(e) {
    e.preventDefault();
    var url = autoAltAjax.ajaxurl
        + '?action=auto_alt_export_csv&nonce=' + autoAltAjax.nonce;
    window.location.href = url;
});
```

**Step 5: Run tests**

```bash
composer test
```

Expected: PASS.

**Step 6: Commit**

```bash
git add auto-alt-tags.php assets/js/admin.js
git commit -m "feat: add CSV export for all image alt text"
```

---

### Task 9: CSV Import

**Files:**
- Modify: `auto-alt-tags.php`

**Step 1: Write a unit test for the CSV parsing/validation logic**

Create `tests/test-csv-import.php`:

```php
<?php
/**
 * Tests for CSV import parsing logic.
 *
 * @package AutoAltTags\Tests
 */

require_once __DIR__ . '/bootstrap.php';

class CsvImportTest extends PHPUnit\Framework\TestCase {

	private AutoAltTagGenerator $plugin;

	public function setUp(): void {
		$this->plugin = new AutoAltTagGenerator();
	}

	public function test_parse_valid_csv_row(): void {
		$rows = array(
			array( 'ID' => '5', 'Filename' => 'photo.jpg', 'Alt Text' => 'A red apple', 'URL' => 'http://example.com/photo.jpg' ),
		);
		$result = $this->plugin->parse_csv_rows( $rows );
		$this->assertSame( array( 5 => 'A red apple' ), $result['valid'] );
		$this->assertEmpty( $result['errors'] );
	}

	public function test_parse_csv_row_with_invalid_id(): void {
		$rows = array(
			array( 'ID' => 'abc', 'Filename' => 'x.jpg', 'Alt Text' => 'Hello', 'URL' => '' ),
		);
		$result = $this->plugin->parse_csv_rows( $rows );
		$this->assertEmpty( $result['valid'] );
		$this->assertNotEmpty( $result['errors'] );
	}

	public function test_parse_csv_row_skips_empty_alt(): void {
		$rows = array(
			array( 'ID' => '5', 'Filename' => 'x.jpg', 'Alt Text' => '', 'URL' => '' ),
		);
		$result = $this->plugin->parse_csv_rows( $rows );
		$this->assertEmpty( $result['valid'] );
		$this->assertNotEmpty( $result['errors'] );
	}
}
```

**Step 2: Run to verify fail**

```bash
composer test -- --filter CsvImportTest
```

Expected: FAIL — `parse_csv_rows` not found.

**Step 3: Add `parse_csv_rows()` as a public method**

```php
/**
 * Parse and validate CSV rows for import.
 *
 * @param array[] $rows Array of associative rows from CSV.
 * @return array { valid: array<int,string>, errors: string[] }
 */
public function parse_csv_rows( array $rows ): array {
	$valid  = array();
	$errors = array();

	foreach ( $rows as $i => $row ) {
		$line = $i + 2; // +2: 1-indexed + header row.

		$id       = isset( $row['ID'] ) ? (int) $row['ID'] : 0;
		$alt_text = isset( $row['Alt Text'] ) ? sanitize_text_field( trim( $row['Alt Text'] ) ) : '';

		if ( $id <= 0 ) {
			$errors[] = sprintf( __( 'Line %d: invalid ID "%s"', 'auto-alt-tags' ), $line, $row['ID'] ?? '' );
			continue;
		}

		if ( empty( $alt_text ) ) {
			$errors[] = sprintf( __( 'Line %d: empty alt text, skipped', 'auto-alt-tags' ), $line );
			continue;
		}

		$valid[ $id ] = $alt_text;
	}

	return compact( 'valid', 'errors' );
}
```

**Step 4: Run tests to verify pass**

```bash
composer test -- --filter CsvImportTest
```

Expected: PASS.

**Step 5: Add AJAX import handler**

Register in `__construct()`:

```php
add_action( 'wp_ajax_auto_alt_import_csv', array( $this, 'ajax_import_csv' ) );
```

Add the handler method:

```php
/**
 * AJAX: import alt text from uploaded CSV.
 */
public function ajax_import_csv(): void {
	if ( ! check_ajax_referer( 'auto_alt_nonce', 'nonce', false ) ) {
		wp_send_json_error( __( 'Security check failed', 'auto-alt-tags' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'Unauthorized', 'auto-alt-tags' ) );
	}

	if ( ! isset( $_FILES['csv_file'] ) || UPLOAD_ERR_OK !== $_FILES['csv_file']['error'] ) {
		wp_send_json_error( __( 'No file uploaded or upload error', 'auto-alt-tags' ) );
	}

	// Validate mime type.
	$file_type = wp_check_filetype( $_FILES['csv_file']['name'] );
	if ( ! in_array( $file_type['ext'], array( 'csv', 'txt' ), true ) ) {
		wp_send_json_error( __( 'Invalid file type. Please upload a CSV file.', 'auto-alt-tags' ) );
	}

	$handle = fopen( $_FILES['csv_file']['tmp_name'], 'r' );
	if ( ! $handle ) {
		wp_send_json_error( __( 'Failed to read uploaded file', 'auto-alt-tags' ) );
	}

	// Parse header row.
	$header = fgetcsv( $handle );
	if ( ! $header || ! in_array( 'ID', $header, true ) || ! in_array( 'Alt Text', $header, true ) ) {
		fclose( $handle );
		wp_send_json_error( __( 'Invalid CSV format. Expected columns: ID, Filename, Alt Text, URL', 'auto-alt-tags' ) );
	}

	$rows = array();
	while ( ( $line = fgetcsv( $handle ) ) !== false ) {
		$rows[] = array_combine( $header, $line );
	}
	fclose( $handle );

	$parsed  = $this->parse_csv_rows( $rows );
	$updated = 0;
	$skipped = 0;

	foreach ( $parsed['valid'] as $attachment_id => $alt_text ) {
		// Verify attachment exists.
		if ( ! get_post( $attachment_id ) ) {
			$parsed['errors'][] = sprintf( __( 'ID %d: attachment not found', 'auto-alt-tags' ), $attachment_id );
			continue;
		}
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
		$updated++;
	}

	wp_send_json_success( array(
		'updated' => $updated,
		'skipped' => $skipped,
		'errors'  => $parsed['errors'],
		'message' => sprintf(
			/* translators: %1$d: updated, %2$d: errors */
			__( 'Import complete: %1$d updated, %2$d errors.', 'auto-alt-tags' ),
			$updated,
			count( $parsed['errors'] )
		),
	) );
}
```

**Step 6: Add Import UI to admin page**

In `admin_page()`, after the Export button, add:

```php
<hr style="margin: 20px 0;">
<h3><?php esc_html_e( 'Import Alt Text from CSV', 'auto-alt-tags' ); ?></h3>
<form id="ka_alt_import_form" enctype="multipart/form-data">
    <input type="file" id="ka_alt_csv_file" name="csv_file" accept=".csv,.txt" />
    <button type="submit" class="button button-secondary" id="ka_alt_import_csv">
        <?php esc_html_e( 'Import CSV', 'auto-alt-tags' ); ?>
    </button>
</form>
<div id="ka_alt_import_result" style="margin-top:10px;"></div>
```

**Step 7: Add JS handler in `admin.js`**

Inside `$(document).ready()`:

```javascript
// Import CSV form
$('#ka_alt_import_form').on('submit', function(e) {
    e.preventDefault();

    var file = $('#ka_alt_csv_file')[0].files[0];
    if (!file) {
        alert('Please select a CSV file.');
        return;
    }

    var formData = new FormData();
    formData.append('action', 'auto_alt_import_csv');
    formData.append('nonce', autoAltAjax.nonce);
    formData.append('csv_file', file);

    $('#ka_alt_import_result').html('<em>Importing…</em>');

    $.ajax({
        url: autoAltAjax.ajaxurl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                var html = '<strong>' + response.data.message + '</strong>';
                if (response.data.errors && response.data.errors.length) {
                    html += '<ul style="color:#cc1818;margin-top:6px;">';
                    response.data.errors.forEach(function(err) {
                        html += '<li>' + err + '</li>';
                    });
                    html += '</ul>';
                }
                $('#ka_alt_import_result').html(html);
            } else {
                $('#ka_alt_import_result').html('<span style="color:#cc1818;">Error: ' + response.data + '</span>');
            }
        },
        error: function(xhr, status, error) {
            $('#ka_alt_import_result').html('<span style="color:#cc1818;">Import failed: ' + error + '</span>');
        }
    });
});
```

**Step 8: Run all tests**

```bash
composer test
```

Expected: PASS.

**Step 9: Commit**

```bash
git add auto-alt-tags.php assets/js/admin.js tests/test-csv-import.php
git commit -m "feat: add CSV import for bulk alt text updates"
```

---

## Phase 3: WordPress.org Readiness

---

### Task 10: `uninstall.php`

**Files:**
- Create: `uninstall.php`

**Step 1: Create the file**

```php
<?php
/**
 * Plugin uninstall handler.
 *
 * Cleans up all plugin data when the plugin is deleted from WordPress.
 * Preserves _wp_attachment_image_alt (standard WP core data).
 *
 * @package AutoAltTags
 */

// Security: only run during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete all plugin options.
$options = array(
	'auto_alt_provider',
	'auto_alt_gemini_api_key',
	'auto_alt_openai_api_key',
	'auto_alt_claude_api_key',
	'auto_alt_openrouter_api_key',
	'auto_alt_model_name',
	'auto_alt_batch_size',
	'auto_alt_image_size',
	'auto_alt_debug_mode',
	'auto_alt_custom_prompt',
	'auto_alt_auto_generate',
	'auto_alt_queue',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Delete all plugin transients.
global $wpdb;
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE %s
		OR option_name LIKE %s",
		'_transient_auto_alt_%',
		'_transient_timeout_auto_alt_%'
	)
);

// Delete failed-attempt tracking from all attachments.
$wpdb->delete(
	$wpdb->postmeta,
	array( 'meta_key' => '_auto_alt_attempts' ),
	array( '%s' )
);

// Clear the WP Cron event.
$timestamp = wp_next_scheduled( 'auto_alt_tags_process_queue' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'auto_alt_tags_process_queue' );
}
```

**Step 2: Run tests**

```bash
composer test
```

Expected: PASS.

**Step 3: Commit**

```bash
git add uninstall.php
git commit -m "feat: add uninstall.php to clean up all plugin data on deletion"
```

---

### Task 11: i18n audit and POT file

**Files:**
- Modify: `auto-alt-tags.php` (fix any missing `__()` wrappers found)
- Create: `languages/auto-alt-tags.pot`
- Modify: `composer.json`

**Step 1: Run WP-CLI to generate POT file**

```bash
wp i18n make-pot . languages/auto-alt-tags.pot --domain=auto-alt-tags --exclude=vendor,tests,dist
```

If WP-CLI is not available globally, install it:

```bash
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
php wp-cli.phar i18n make-pot . languages/auto-alt-tags.pot --domain=auto-alt-tags --exclude=vendor,tests,dist
```

**Step 2: Review the POT output**

Open `languages/auto-alt-tags.pot` and look for any strings in the generated file that appear with incorrect or missing context. Fix any found in `auto-alt-tags.php`.

Also check the `assets/js/admin.js` alert strings — JS strings are not i18n-able without `wp_set_script_translations()`. Note these for a future pass; do not block this task on them.

**Step 3: Add `i18n` command to `composer.json`**

In `composer.json`, find the `"scripts"` block. Add:

```json
"i18n": "wp i18n make-pot . languages/auto-alt-tags.pot --domain=auto-alt-tags --exclude=vendor,tests,dist"
```

**Step 4: Create `languages/` directory index file**

```bash
touch languages/index.php
```

Add to `languages/index.php`:

```php
<?php
// Silence is golden.
```

**Step 5: Run tests**

```bash
composer test
```

Expected: PASS.

**Step 6: Commit**

```bash
git add languages/ composer.json
git commit -m "feat: add POT file and composer i18n command for translations"
```

---

### Task 12: Plugin Check compliance and version bump

**Files:**
- Modify: `auto-alt-tags.php` (header)
- Create: `readme.txt`

**Step 1: Run Plugin Check**

```bash
composer check
```

Review output. Common issues to fix:
- Any direct `echo` without escaping → wrap with `esc_html()` or `wp_kses_post()`
- Any `$_POST`/`$_GET` access without sanitization → add `sanitize_text_field()`
- File headers missing fields

Fix each issue in `auto-alt-tags.php`.

**Step 2: Bump `Tested up to` in plugin header**

At the top of `auto-alt-tags.php`, change:

```php
 * Tested up to: 6.6
```

to:

```php
 * Tested up to: 6.7
```

**Step 3: Create `readme.txt`**

WordPress.org requires `readme.txt` in a specific format. Create it:

```text
=== Auto Alt Tags ===
Contributors: kahunam
Tags: alt text, accessibility, AI, images, SEO
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically generates descriptive alt tags for images using AI (Gemini, OpenAI, Claude, OpenRouter).

== Description ==

Auto Alt Tags uses AI to automatically generate descriptive, accessible alt text for images in your WordPress media library. Supports Google Gemini, OpenAI, Anthropic Claude, and OpenRouter.

**Features:**

* Multiple AI providers: Gemini, OpenAI, Claude, OpenRouter
* Auto-generation on upload (background WP Cron processing)
* Batch processing with real-time progress tracking
* Resume capability if interrupted
* Media Library column showing alt text status
* Filter Media Library by alt text presence
* Per-image regenerate button
* CSV export and import for bulk editing
* WP-CLI support
* Debug mode with real-time logs
* Custom prompt support
* Rate limit awareness

== Installation ==

1. Upload the plugin to `/wp-content/plugins/auto-alt-tags/`
2. Activate the plugin through the WordPress Plugins screen
3. Go to **Media → Auto Alt Tags**
4. Select your AI provider and enter your API key
5. Click **Test Key** to verify your setup
6. Click **Start Auto-Tagging All Images**

== Frequently Asked Questions ==

= Which AI provider should I use? =

Google Gemini is recommended. It offers a generous free tier and excellent image analysis.

= Does it overwrite existing alt text? =

No. The plugin only processes images that do not already have alt text.

= Can I run it from the command line? =

Yes. Use `wp auto-alt generate` for bulk processing, `wp auto-alt stats` for statistics.

== Changelog ==

= 1.1.0 =
* Added: Auto-generation on upload via WP Cron background processing
* Added: Alt Text column in Media Library list view
* Added: Filter Media Library by alt text status
* Added: Per-image Regenerate button
* Added: CSV export and import
* Added: uninstall.php for clean removal
* Added: Translation-ready POT file

= 1.0.1 =
* Updated Gemini models to current supported versions

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.1.0 =
Adds background auto-generation on upload and Media Library integration. No breaking changes.
```

**Step 4: Run Plugin Check again**

```bash
composer check
```

Expected: no errors or warnings (warnings about JS i18n are acceptable for now).

**Step 5: Run full test suite**

```bash
composer test
```

Expected: PASS.

**Step 6: Commit**

```bash
git add auto-alt-tags.php readme.txt
git commit -m "feat: bump tested-up-to to 6.7, add readme.txt for WordPress.org"
```

---

### Task 13: Version bump and final tag

**Files:**
- Modify: `auto-alt-tags.php` (version constant and header)
- Modify: `README.md` (version references)
- Modify: `CHANGELOG.md`

**Step 1: Update version in plugin header**

In `auto-alt-tags.php` lines 6 and 27, change `1.0.1` to `1.1.0`.

**Step 2: Update `CHANGELOG.md`**

Add at the top of the changelog (above the `## [1.2.0]` entry):

```markdown
## [1.1.0] - 2026-03-05

### Added
- Auto-generation on upload — new images are queued and processed via WP Cron every 5 minutes
- "Auto-generate on upload" setting (default: on) with admin queue notice
- Alt Text column in Media Library list view showing status and per-image Regenerate button
- Media Library filter by alt text presence/absence
- CSV export for all image alt text
- CSV import for bulk alt text updates
- `wp auto-alt retry-failed` WP-CLI command to re-queue failed images
- `uninstall.php` — cleans up all plugin data on deletion
- Translation-ready POT file at `languages/auto-alt-tags.pot`
- `composer i18n` command

### Changed
- Bumped `Tested up to` to WordPress 6.7
- Added `readme.txt` in WordPress.org format
```

**Step 3: Run all tests one final time**

```bash
composer test
```

Expected: PASS.

**Step 4: Run Plugin Check**

```bash
composer check
```

Expected: clean.

**Step 5: Final commit**

```bash
git add auto-alt-tags.php README.md CHANGELOG.md readme.txt
git commit -m "chore: bump version to 1.1.0"
```

---

## Summary of all files changed

| File | Action |
|------|--------|
| `auto-alt-tags.php` | Modify — queue helpers, cron, column, filter, CSV, import, settings |
| `assets/js/admin.js` | Modify — export/import button handlers |
| `assets/js/media-column.js` | Create — regenerate button handler |
| `includes/class-wp-cli-command.php` | Modify — add `retry-failed` command |
| `uninstall.php` | Create |
| `readme.txt` | Create |
| `languages/auto-alt-tags.pot` | Create |
| `languages/index.php` | Create |
| `tests/test-queue.php` | Create |
| `tests/test-csv-import.php` | Create |
| `tests/mocks/wordpress-functions.php` | Modify — add missing mock functions |
| `composer.json` | Modify — add `i18n` script |
| `CHANGELOG.md` | Modify — add v1.1.0 entry |
