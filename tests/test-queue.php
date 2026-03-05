<?php
/**
 * Tests for queue helper methods
 *
 * @package AutoAltTags\Tests
 */

require_once __DIR__ . '/bootstrap.php';

if ( ! class_exists( 'AutoAltTagGenerator' ) ) {
	require_once dirname( __DIR__ ) . '/auto-alt-tags.php';
}

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

	public function test_queue_get_returns_empty_on_corrupt_data(): void {
		global $mock_options;
		$mock_options['auto_alt_queue'] = 'not-valid-json';
		$queue = $this->plugin->queue_get();
		$this->assertSame( [], $queue );
	}

	public function test_queue_pop_with_zero_count_returns_empty(): void {
		$this->plugin->queue_push( 5 );
		$batch = $this->plugin->queue_pop( 0 );
		$this->assertSame( [], $batch );
		$this->assertContains( 5, $this->plugin->queue_get() );
	}

	public function test_on_attachment_upload_queues_image(): void {
		global $mock_options;
		$mock_options['auto_alt_auto_generate'] = true;
		$mock_options['auto_alt_queue']          = json_encode( [] );
		// Re-instantiate so __construct picks up the new option value.
		$plugin = new AutoAltTagGenerator();
		$plugin->on_attachment_upload( 99 );
		$this->assertContains( 99, $plugin->queue_get() );
	}

	public function test_on_attachment_upload_skips_when_disabled(): void {
		global $mock_options;
		$mock_options['auto_alt_auto_generate'] = false;
		$mock_options['auto_alt_queue']          = json_encode( [] );
		$plugin = new AutoAltTagGenerator();
		$plugin->on_attachment_upload( 99 );
		$this->assertNotContains( 99, $plugin->queue_get() );
	}
}
