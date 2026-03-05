<?php
/**
 * Tests for WP Cron integration methods
 *
 * @package AutoAltTags\Tests
 */

require_once __DIR__ . '/bootstrap.php';

if ( ! class_exists( 'AutoAltTagGenerator' ) ) {
	require_once dirname( __DIR__ ) . '/auto-alt-tags.php';
}

class CronTest extends PHPUnit\Framework\TestCase {

	private AutoAltTagGenerator $plugin;

	public function setUp(): void {
		global $mock_options;
		$mock_options['auto_alt_queue']    = json_encode( [] );
		$mock_options['auto_alt_provider'] = 'gemini';
		$this->plugin = new AutoAltTagGenerator();
	}

	public function test_add_cron_schedules_adds_interval(): void {
		$schedules = $this->plugin->add_cron_schedules( [] );
		$this->assertArrayHasKey( 'every_five_minutes', $schedules );
		$this->assertSame( 300, $schedules['every_five_minutes']['interval'] );
	}

	public function test_add_cron_schedules_does_not_overwrite_existing(): void {
		$existing = array(
			'every_five_minutes' => array( 'interval' => 999, 'display' => 'Custom' ),
		);
		$schedules = $this->plugin->add_cron_schedules( $existing );
		$this->assertSame( 999, $schedules['every_five_minutes']['interval'] );
	}

	public function test_process_queue_returns_early_when_queue_empty(): void {
		global $mock_options;
		$mock_options['auto_alt_queue'] = json_encode( [] );
		// Should complete without error — no API key needed when queue is empty.
		$this->plugin->process_queue_via_cron();
		$this->assertSame( [], $this->plugin->queue_get() );
	}

	public function test_process_queue_returns_early_when_no_api_key(): void {
		global $mock_options;
		$mock_options['auto_alt_queue']         = json_encode( [ 1, 2 ] );
		$mock_options['auto_alt_gemini_api_key'] = '';
		$plugin = new AutoAltTagGenerator();
		$plugin->process_queue_via_cron();
		// Queue should be unchanged since we returned early (no API key).
		// queue_pop was not called, so both IDs remain.
		$this->assertCount( 2, $plugin->queue_get() );
	}
}
