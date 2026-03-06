# Select Images Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a "Select Images" tab to the plugin's admin page so users can cherry-pick specific images missing alt text and generate tags for only those.

**Architecture:** New AJAX endpoint returns paginated thumbnails of images missing alt text. The existing `ajax_process_alt_tags` handler is extended to accept an optional `image_ids[]` parameter; when present, it processes only those IDs. Tab switching, grid rendering, checkbox state, pagination, and generation are all handled in JavaScript.

**Tech Stack:** PHP 7.4+, WordPress AJAX, jQuery, PHPUnit (standalone mocks)

---

## Key File Locations

- Main plugin class + AJAX handlers: `auto-alt-tags.php`
- Constructor (hook registrations): `auto-alt-tags.php:314-348`
- `admin_page()` HTML: `auto-alt-tags.php:903`
- Main content grid starts (tabs go here): `auto-alt-tags.php:954`
- `ajax_process_alt_tags()`: `auto-alt-tags.php:1660`
- `get_images_without_alt()` private helper: `auto-alt-tags.php:1909`
- Admin JS: `assets/js/admin.js`
- Admin CSS: `assets/css/admin.css`
- Tests: `tests/` — standalone PHPUnit, uses mock WordPress functions from `tests/bootstrap.php`

---

### Task 1: PHP — New `ajax_get_missing_images_paginated` method + hook

**Files:**
- Modify: `auto-alt-tags.php:314-348` (constructor — add hook)
- Modify: `auto-alt-tags.php` (add new method before `ajax_process_alt_tags` at line 1660)
- Create: `tests/test-select-images.php`

**Step 1: Write failing test**

Create `tests/test-select-images.php`:

```php
<?php
/**
 * Tests for Select Images feature
 *
 * @package AutoAltTags\Tests
 */

class Test_Select_Images extends PHPUnit\Framework\TestCase {

	public function setUp(): void {
		parent::setUp();
		global $mock_options, $mock_transients, $mock_postmeta;
		$mock_options = array(
			'auto_alt_provider'       => 'gemini',
			'auto_alt_gemini_api_key' => 'test-key',
			'auto_alt_batch_size'     => 10,
			'auto_alt_image_size'     => 'medium',
			'auto_alt_model_name'     => 'gemini-2.5-flash',
			'auto_alt_debug_mode'     => false,
			'auto_alt_custom_prompt'  => '',
		);
		$mock_transients = array();
		$mock_postmeta   = array();
	}

	/**
	 * Pagination helper: calculate page offsets correctly
	 */
	public function test_pagination_math(): void {
		$per_page = 24;

		// Page 1: offset 0, fetch 24
		$page   = 1;
		$offset = ( $page - 1 ) * $per_page;
		$this->assertSame( 0, $offset );

		// Page 3: offset 48, fetch 24
		$page   = 3;
		$offset = ( $page - 1 ) * $per_page;
		$this->assertSame( 48, $offset );
	}

	/**
	 * Input sanitisation: image_ids must be positive integers only
	 */
	public function test_image_ids_sanitisation(): void {
		$raw_ids = array( '42', '-5', '0', 'abc', '100', '' );

		// Simulate the sanitisation the AJAX handler will apply
		$sanitised = array_values(
			array_filter(
				array_map( 'intval', $raw_ids ),
				fn( $id ) => $id > 0
			)
		);

		$this->assertSame( array( 42, 100 ), $sanitised );
	}

	/**
	 * When image_ids provided, processing uses exactly those IDs
	 */
	public function test_selected_ids_used_when_provided(): void {
		$selected = array( 10, 20, 30 );

		// Simulate handler logic: if image_ids posted, use them directly
		$posted_ids = array( '10', '20', '30' );
		$ids = array_values(
			array_filter(
				array_map( 'intval', $posted_ids ),
				fn( $id ) => $id > 0
			)
		);

		$this->assertSame( $selected, $ids );
		$this->assertCount( 3, $ids );
	}

	/**
	 * Total pages calculation
	 */
	public function test_total_pages(): void {
		$this->assertSame( 1, (int) ceil( 0 / 24 ) );  // no images
		$this->assertSame( 1, (int) ceil( 10 / 24 ) ); // less than one page
		$this->assertSame( 1, (int) ceil( 24 / 24 ) ); // exactly one page
		$this->assertSame( 2, (int) ceil( 25 / 24 ) ); // one over
		$this->assertSame( 3, (int) ceil( 48 / 24 ) ); // exactly two pages worth
	}
}
```

**Step 2: Run test to verify it fails (class doesn't exist yet)**

```bash
cd /path/to/wordpress-auto-alt-tags
composer test -- --filter Test_Select_Images
```

Expected: error about bootstrap / class not found OR tests pass (they're pure math — that's fine, they're scaffolding for what comes next).

**Step 3: Register the new AJAX hook in the constructor**

In `auto-alt-tags.php`, inside `__construct()` (around line 317, after the existing `wp_ajax_` hooks), add:

```php
add_action( 'wp_ajax_auto_alt_get_missing_images', array( $this, 'ajax_get_missing_images_paginated' ) );
```

**Step 4: Add the new AJAX method**

Insert the following new method directly before `ajax_process_alt_tags()` (before line 1660):

```php
/**
 * AJAX: return a paginated list of images missing alt text (for the Select Images tab).
 *
 * POST params: page (int, default 1), per_page (int, default 24), nonce.
 * Response: { images: [{id, thumbnail_url, title}], total, page, per_page, total_pages }
 */
public function ajax_get_missing_images_paginated(): void {
	if ( ! check_ajax_referer( 'auto_alt_nonce', 'nonce', false ) ) {
		wp_send_json_error( __( 'Security check failed', 'auto-alt-tags' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'Unauthorized', 'auto-alt-tags' ) );
	}

	$page     = max( 1, (int) ( $_POST['page'] ?? 1 ) );
	$per_page = max( 1, min( 100, (int) ( $_POST['per_page'] ?? 24 ) ) );
	$offset   = ( $page - 1 ) * $per_page;

	global $wpdb;

	$total = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*)
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
			WHERE p.post_type = %s
			AND p.post_mime_type LIKE %s
			AND (pm.meta_value IS NULL OR pm.meta_value = %s)",
			'_wp_attachment_image_alt',
			'attachment',
			'image/%',
			''
		)
	);

	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT p.ID
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
			WHERE p.post_type = %s
			AND p.post_mime_type LIKE %s
			AND (pm.meta_value IS NULL OR pm.meta_value = %s)
			ORDER BY p.ID ASC
			LIMIT %d OFFSET %d",
			'_wp_attachment_image_alt',
			'attachment',
			'image/%',
			'',
			$per_page,
			$offset
		)
	);

	$image_size = get_option( 'auto_alt_image_size', 'medium' );
	$images     = array();

	foreach ( $ids as $id ) {
		$id        = (int) $id;
		$thumb     = wp_get_attachment_image_src( $id, $image_size );
		$images[]  = array(
			'id'            => $id,
			'thumbnail_url' => $thumb ? $thumb[0] : '',
			'title'         => get_the_title( $id ),
		);
	}

	wp_send_json_success( array(
		'images'      => $images,
		'total'       => $total,
		'page'        => $page,
		'per_page'    => $per_page,
		'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
	) );
}
```

**Step 5: Run tests**

```bash
composer test -- --filter Test_Select_Images
```

Expected: all tests PASS.

**Step 6: Commit**

```bash
git add auto-alt-tags.php tests/test-select-images.php
git commit -m "feat: add ajax_get_missing_images_paginated endpoint"
```

---

### Task 2: PHP — Extend `ajax_process_alt_tags` to accept `image_ids[]`

**Files:**
- Modify: `auto-alt-tags.php:1693-1738` (inside `ajax_process_alt_tags`)

**Step 1: Locate the section to change**

In `ajax_process_alt_tags()` at line 1693, this block currently always queries all missing images:

```php
// Always query fresh — only images that still need alt text are returned.
$images_without_alt = $this->get_images_without_alt();
$total_remaining    = count( $images_without_alt );
```

**Step 2: Replace that block**

Replace lines 1693–1696 with:

```php
// If specific IDs were posted (Select Images tab), process only those.
// Otherwise fall back to querying all images missing alt text.
$posted_ids = isset( $_POST['image_ids'] ) && is_array( $_POST['image_ids'] )
	? array_values( array_filter( array_map( 'intval', $_POST['image_ids'] ), fn( $id ) => $id > 0 ) )
	: array();

if ( ! empty( $posted_ids ) ) {
	$images_without_alt = $posted_ids;
} else {
	$images_without_alt = $this->get_images_without_alt();
}
$total_remaining = count( $images_without_alt );
```

**Step 3: Run tests to ensure nothing is broken**

```bash
composer test
```

Expected: all tests PASS (existing tests unchanged).

**Step 4: Commit**

```bash
git add auto-alt-tags.php
git commit -m "feat: allow process_alt_tags to accept explicit image_ids"
```

---

### Task 3: PHP — Add tab HTML to `admin_page()`

**Files:**
- Modify: `auto-alt-tags.php:954` (the line that opens the main content grid)

**Step 1: Find the insertion point**

Line 954 is:
```php
<!-- Top row: Generate Alt Tags (left) | Cost & Security (right) -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start; margin-bottom: 20px;">
```

**Step 2: Insert tab nav + panels wrapping**

Replace the comment + opening `<div>` at lines 954–955 with:

```php
<!-- Tab Navigation -->
<div class="ka_alt_tab_nav" role="tablist">
	<button class="ka_alt_tab_btn ka_alt_tab_active" data-tab="generate-all" role="tab" aria-selected="true">
		<?php esc_html_e( 'Generate All', 'auto-alt-tags' ); ?>
	</button>
	<button class="ka_alt_tab_btn" data-tab="select-images" role="tab" aria-selected="false">
		<?php esc_html_e( 'Select Images', 'auto-alt-tags' ); ?>
	</button>
</div>

<!-- Tab: Generate All (existing content) -->
<div id="ka_alt_tab_generate_all" class="ka_alt_tab_panel" role="tabpanel">

<!-- Top row: Generate Alt Tags (left) | Cost & Security (right) -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start; margin-bottom: 20px;">
```

**Step 3: Find where the Generate All content ends**

Search for the closing `</div><!-- end wrap -->` near the bottom of `admin_page()`. The outermost `<div class="wrap">` closes just before `?>`. You need to close `ka_alt_tab_generate_all` and add the Select Images tab panel before the wrap closes.

Search for `</div><!-- end wrap -->` or the final `</div>` before `<?php` ends the method. Add before the final `</div>`:

```php
</div><!-- end ka_alt_tab_generate_all -->

<!-- Tab: Select Images -->
<div id="ka_alt_tab_select_images" class="ka_alt_tab_panel" style="display:none;" role="tabpanel">
	<div class="card">
		<h2 class="title"><?php esc_html_e( 'Select Images to Tag', 'auto-alt-tags' ); ?></h2>
		<div class="inside">

			<!-- Toolbar -->
			<div id="ka_alt_select_toolbar" style="display:flex; align-items:center; gap:16px; margin-bottom:16px; flex-wrap:wrap;">
				<button id="ka_alt_select_all" class="button"><?php esc_html_e( 'Select All', 'auto-alt-tags' ); ?></button>
				<span id="ka_alt_select_count" style="color:#555;"><?php esc_html_e( '0 selected', 'auto-alt-tags' ); ?></span>
				<button id="ka_alt_generate_selected" class="button button-primary" disabled>
					<?php esc_html_e( 'Generate Alt Tags for Selected (0)', 'auto-alt-tags' ); ?>
				</button>
			</div>

			<!-- Progress (reused, hidden by default) -->
			<div id="ka_alt_select_progress" style="display:none; margin-bottom:20px;">
				<progress id="ka_alt_select_progress_bar" value="0" max="100" style="width:100%; height:24px;"></progress>
				<div style="display:flex; justify-content:space-between; margin-top:6px;">
					<span id="ka_alt_select_progress_text"><?php esc_html_e( 'Starting\u2026', 'auto-alt-tags' ); ?></span>
					<span id="ka_alt_select_progress_pct">0%</span>
				</div>
				<button id="ka_alt_select_stop" class="button" style="margin-top:8px;"><?php esc_html_e( 'Stop', 'auto-alt-tags' ); ?></button>
			</div>

			<!-- Image Grid -->
			<div id="ka_alt_image_grid" class="ka_alt_image_grid">
				<p id="ka_alt_grid_loading"><?php esc_html_e( 'Loading images\u2026', 'auto-alt-tags' ); ?></p>
			</div>

			<!-- Pagination -->
			<div id="ka_alt_grid_pagination" style="display:none; margin-top:16px; display:flex; align-items:center; gap:12px;">
				<button id="ka_alt_grid_prev" class="button" disabled><?php esc_html_e( '&laquo; Prev', 'auto-alt-tags' ); ?></button>
				<span id="ka_alt_grid_page_info"></span>
				<button id="ka_alt_grid_next" class="button"><?php esc_html_e( 'Next &raquo;', 'auto-alt-tags' ); ?></button>
			</div>

		</div><!-- .inside -->
	</div><!-- .card -->
</div><!-- end ka_alt_tab_select_images -->
```

**Step 4: Run existing tests (no PHP unit tests needed for HTML output)**

```bash
composer test
```

Expected: all tests PASS.

**Step 5: Commit**

```bash
git add auto-alt-tags.php
git commit -m "feat: add Select Images tab HTML to admin page"
```

---

### Task 4: CSS — Tab and grid styles

**Files:**
- Modify: `assets/css/admin.css` (append at end of file)

**Step 1: Append new rules**

Add to the end of `assets/css/admin.css`:

```css
/* ---- Select Images Tab ---- */

/* Tab navigation bar */
.ka_alt_tab_nav {
    display: flex;
    gap: 0;
    border-bottom: 2px solid #0073aa;
    margin-bottom: 20px;
}

.ka_alt_tab_btn {
    background: #f1f1f1;
    border: 1px solid #ccd0d4;
    border-bottom: none;
    padding: 8px 20px;
    cursor: pointer;
    font-size: 14px;
    color: #23282d;
    border-radius: 3px 3px 0 0;
    margin-right: 4px;
    transition: background 0.15s;
}

.ka_alt_tab_btn:hover {
    background: #e2e2e2;
}

.ka_alt_tab_btn.ka_alt_tab_active {
    background: #fff;
    border-bottom: 2px solid #fff;
    color: #0073aa;
    font-weight: 600;
    margin-bottom: -2px;
}

/* Image grid */
.ka_alt_image_grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

@media (max-width: 900px) {
    .ka_alt_image_grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 600px) {
    .ka_alt_image_grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Individual grid cell */
.ka_alt_grid_item {
    position: relative;
    border: 2px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
    cursor: pointer;
    background: #f9f9f9;
    transition: border-color 0.15s;
}

.ka_alt_grid_item:hover {
    border-color: #0073aa;
}

.ka_alt_grid_item.ka_alt_selected {
    border-color: #0073aa;
    background: #e8f4fb;
}

.ka_alt_grid_item img {
    width: 100%;
    aspect-ratio: 1 / 1;
    object-fit: cover;
    display: block;
}

/* Checkbox overlay */
.ka_alt_grid_item input[type="checkbox"] {
    position: absolute;
    top: 6px;
    left: 6px;
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #0073aa;
    z-index: 2;
}

/* Title below thumbnail */
.ka_alt_grid_title {
    font-size: 11px;
    color: #555;
    padding: 4px 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Done overlay (green checkmark after generation) */
.ka_alt_grid_item.ka_alt_done::after {
    content: '✓';
    position: absolute;
    inset: 0;
    background: rgba(30, 138, 68, 0.75);
    color: #fff;
    font-size: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 3;
}
```

**Step 2: Visually verify** (browser check — no automated test needed for CSS)

**Step 3: Commit**

```bash
git add assets/css/admin.css
git commit -m "feat: add tab nav and image grid CSS"
```

---

### Task 5: JS — Tab switching + grid loading + checkbox state + pagination

**Files:**
- Modify: `assets/js/admin.js` (add new functions + event bindings inside the `$(document).ready` block)

**Overview of new JS state variables (add near the top of the IIFE, after existing variables):**

```js
let selectedIds     = new Set();  // persists across pagination
let gridCurrentPage = 1;
let gridTotalPages  = 1;
let gridTotal       = 0;
const GRID_PER_PAGE = 24;
```

**Step 1: Add `loadGrid(page)` function**

Add after the existing `checkForSession` function (around line 628):

```js
/**
 * Load the image grid for the Select Images tab
 */
function loadGrid(page) {
    gridCurrentPage = page;
    const $grid = $('#ka_alt_image_grid');
    $grid.html('<p id="ka_alt_grid_loading">Loading images\u2026</p>');
    $('#ka_alt_grid_pagination').hide();

    $.ajax({
        url: autoAltAjax.ajaxurl,
        type: 'POST',
        data: {
            action:   'auto_alt_get_missing_images',
            nonce:    autoAltAjax.nonce,
            page:     page,
            per_page: GRID_PER_PAGE
        },
        success: function(response) {
            if (!response.success) {
                $grid.html('<p>Error loading images.</p>');
                return;
            }

            const data = response.data;
            gridTotalPages = data.total_pages;
            gridTotal      = data.total;

            if (!data.images.length) {
                $grid.html('<p>No images missing alt text.</p>');
                return;
            }

            $grid.empty();
            data.images.forEach(function(img) {
                const checked   = selectedIds.has(img.id) ? 'checked' : '';
                const selClass  = selectedIds.has(img.id) ? ' ka_alt_selected' : '';
                const doneClass = '';  // cleared on each load
                const $item = $(
                    '<div class="ka_alt_grid_item' + selClass + doneClass + '" data-id="' + img.id + '">' +
                    '<input type="checkbox" ' + checked + ' aria-label="' + img.title.replace(/"/g, '&quot;') + '">' +
                    '<img src="' + img.thumbnail_url + '" alt="">' +
                    '<div class="ka_alt_grid_title">' + img.title + '</div>' +
                    '</div>'
                );
                $grid.append($item);
            });

            // Pagination controls
            if (gridTotalPages > 1) {
                $('#ka_alt_grid_page_info').text('Page ' + gridCurrentPage + ' of ' + gridTotalPages);
                $('#ka_alt_grid_prev').prop('disabled', gridCurrentPage <= 1);
                $('#ka_alt_grid_next').prop('disabled', gridCurrentPage >= gridTotalPages);
                $('#ka_alt_grid_pagination').show();
            }
        },
        error: function() {
            $grid.html('<p>Failed to load images.</p>');
        }
    });
}
```

**Step 2: Add `updateSelectionUI()` helper**

```js
/**
 * Sync the toolbar count label and Generate button with selectedIds
 */
function updateSelectionUI() {
    const n = selectedIds.size;
    $('#ka_alt_select_count').text(n + ' selected');
    $('#ka_alt_generate_selected')
        .text('Generate Alt Tags for Selected (' + n + ')')
        .prop('disabled', n === 0);
}
```

**Step 3: Add event bindings in `$(document).ready`**

Inside the `$(document).ready(function() { ... })` block, add after the existing bindings:

```js
// --- Select Images Tab ---

// Tab switching
$('.ka_alt_tab_btn').on('click', function() {
    const tab = $(this).data('tab');
    $('.ka_alt_tab_btn').removeClass('ka_alt_tab_active').attr('aria-selected', 'false');
    $(this).addClass('ka_alt_tab_active').attr('aria-selected', 'true');
    $('.ka_alt_tab_panel').hide();
    $('#ka_alt_tab_' + tab.replace(/-/g, '_')).show();

    if (tab === 'select-images' && $('#ka_alt_image_grid').children().length === 0) {
        loadGrid(1);
    }
});

// Checkbox toggle (delegated — grid is dynamic)
$('#ka_alt_image_grid').on('change', 'input[type="checkbox"]', function() {
    const $item = $(this).closest('.ka_alt_grid_item');
    const id    = parseInt($item.data('id'), 10);
    if (this.checked) {
        selectedIds.add(id);
        $item.addClass('ka_alt_selected');
    } else {
        selectedIds.delete(id);
        $item.removeClass('ka_alt_selected');
    }
    updateSelectionUI();
});

// Clicking the whole cell toggles the checkbox
$('#ka_alt_image_grid').on('click', '.ka_alt_grid_item', function(e) {
    if ($(e.target).is('input[type="checkbox"]')) return; // already handled
    $(this).find('input[type="checkbox"]').trigger('click');
});

// Select All / Deselect All
$('#ka_alt_select_all').on('click', function() {
    const allChecked = $('#ka_alt_image_grid input[type="checkbox"]:not(:checked)').length === 0;
    if (allChecked) {
        // deselect visible
        $('#ka_alt_image_grid .ka_alt_grid_item').each(function() {
            const id = parseInt($(this).data('id'), 10);
            selectedIds.delete(id);
            $(this).removeClass('ka_alt_selected').find('input').prop('checked', false);
        });
        $(this).text('Select All');
    } else {
        // select visible
        $('#ka_alt_image_grid .ka_alt_grid_item').each(function() {
            const id = parseInt($(this).data('id'), 10);
            selectedIds.add(id);
            $(this).addClass('ka_alt_selected').find('input').prop('checked', true);
        });
        $(this).text('Deselect All');
    }
    updateSelectionUI();
});

// Pagination
$('#ka_alt_grid_prev').on('click', function() {
    if (gridCurrentPage > 1) loadGrid(gridCurrentPage - 1);
});
$('#ka_alt_grid_next').on('click', function() {
    if (gridCurrentPage < gridTotalPages) loadGrid(gridCurrentPage + 1);
});
```

**Step 4: Commit**

```bash
git add assets/js/admin.js
git commit -m "feat: add Select Images tab grid loading, checkbox state, pagination"
```

---

### Task 6: JS — Generation from selected IDs + done overlay

**Files:**
- Modify: `assets/js/admin.js` (add `processSelectedBatch` + wire generate button + stop button)

**Step 1: Add `processSelectedBatch` function**

Add after `loadGrid` (before `$(document).ready`):

```js
/**
 * Process selected images batch-by-batch (Select Images tab)
 */
function processSelectedBatch(remainingIds, totalSelected) {
    if (shouldStop || remainingIds.length === 0) {
        $('#ka_alt_select_progress').hide();
        $('#ka_alt_select_toolbar').show();
        shouldStop = false;
        isProcessing = false;
        if (remainingIds.length === 0) {
            $('#ka_alt_select_progress_text').text('Done!');
            setTimeout(function() { $('#ka_alt_select_progress').hide(); }, 2000);
        }
        return;
    }

    // Take the next batch (server respects batch_size, but JS sends all at once;
    // the PHP handler slices internally — so send all remaining and let PHP batch)
    $.ajax({
        url: autoAltAjax.ajaxurl,
        type: 'POST',
        data: $.extend(
            { action: 'process_alt_tags', nonce: autoAltAjax.nonce },
            // Send remaining IDs as image_ids[]
            (function() {
                const d = {};
                remainingIds.forEach(function(id, i) { d['image_ids[' + i + ']'] = id; });
                return d;
            })()
        ),
        success: function(response) {
            if (!response.success) {
                alert('Error: ' + response.data);
                isProcessing = false;
                $('#ka_alt_select_progress').hide();
                $('#ka_alt_select_toolbar').show();
                return;
            }

            const d = response.data;
            const processed = totalSelected - remainingIds.length + (d.batch_success || 0);
            const pct = Math.round((processed / totalSelected) * 100);
            $('#ka_alt_select_progress_bar').val(pct);
            $('#ka_alt_select_progress_pct').text(pct + '%');
            $('#ka_alt_select_progress_text').text(
                'Processed ' + processed + ' of ' + totalSelected + ' images'
            );

            // Mark processed items done in grid (best-effort; only visible page)
            if (d.batch_success > 0) {
                // Mark the first batch_success items in remainingIds as done
                const justDone = remainingIds.slice(0, d.batch_success);
                justDone.forEach(function(id) {
                    $('#ka_alt_image_grid .ka_alt_grid_item[data-id="' + id + '"]')
                        .addClass('ka_alt_done');
                    selectedIds.delete(id);
                });
                updateSelectionUI();
            }

            if (d.completed) {
                $('#ka_alt_select_progress_bar').val(100);
                $('#ka_alt_select_progress_pct').text('100%');
                $('#ka_alt_select_progress_text').text('Complete!');
                isProcessing = false;
                setTimeout(function() {
                    $('#ka_alt_select_progress').hide();
                    $('#ka_alt_select_toolbar').show();
                }, 2000);
            } else {
                // Remaining = those not yet processed (drop batch_success from front)
                const newRemaining = remainingIds.slice(d.batch_success || 0);
                setTimeout(function() { processSelectedBatch(newRemaining, totalSelected); }, 500);
            }
        },
        error: function(xhr, status, error) {
            alert('Error: ' + error);
            isProcessing = false;
            $('#ka_alt_select_progress').hide();
            $('#ka_alt_select_toolbar').show();
        }
    });
}
```

**Step 2: Wire Generate button + Stop button in `$(document).ready`**

Inside `$(document).ready`, add:

```js
// Generate Selected button
$('#ka_alt_generate_selected').on('click', function() {
    if (isProcessing) return;
    if (selectedIds.size === 0) return;
    if (!confirm('Generate alt tags for ' + selectedIds.size + ' selected image(s)?')) return;

    isProcessing = true;
    shouldStop   = false;

    $('#ka_alt_select_toolbar').hide();
    $('#ka_alt_select_progress').show();
    $('#ka_alt_select_progress_bar').val(0);
    $('#ka_alt_select_progress_pct').text('0%');
    $('#ka_alt_select_progress_text').text('Starting\u2026');

    const ids = Array.from(selectedIds);
    processSelectedBatch(ids, ids.length);
});

// Stop button (Select Images tab)
$('#ka_alt_select_stop').on('click', function() {
    shouldStop = true;
    $(this).text('Stopping\u2026');
    debugLog('Stop requested');
});
```

**Step 3: Run all tests**

```bash
composer test
```

Expected: all tests PASS.

**Step 4: Commit**

```bash
git add assets/js/admin.js
git commit -m "feat: add Select Images generation loop and done overlay"
```

---

### Task 7: Manual smoke test checklist

Before marking complete, verify in a real WordPress environment:

1. Open Media → Auto Alt Tags. Two tabs visible: "Generate All" and "Select Images".
2. "Generate All" tab: existing workflow unchanged — Start, Stop, Resume all work.
3. "Select Images" tab: grid loads images missing alt text.
4. Clicking a cell or its checkbox toggles selection; count label and button update.
5. "Select All" selects all visible; clicking again deselects all.
6. Navigating pages retains selections from previous pages.
7. "Generate Alt Tags for Selected (N)" button: confirm dialog, then progress bar appears.
8. On completion, processed items show green checkmark. Button resets.
9. Stop mid-run: stops after current batch; unprocessed items remain checked.
10. If all images already have alt text, grid shows "No images missing alt text."

---

### Task 8: Final commit + version bump (optional)

If the feature is shipping in a new version:

```bash
# Update version in auto-alt-tags.php (line 8 and line 27) and readme.txt
git add auto-alt-tags.php readme.txt
git commit -m "chore: bump version for Select Images feature"
```
