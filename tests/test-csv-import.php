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

	public function test_parse_multiple_rows_independently(): void {
		$rows = array(
			array( 'ID' => '1', 'Filename' => 'a.jpg', 'Alt Text' => 'Valid', 'URL' => '' ),
			array( 'ID' => 'bad', 'Filename' => 'b.jpg', 'Alt Text' => 'X', 'URL' => '' ),
			array( 'ID' => '3', 'Filename' => 'c.jpg', 'Alt Text' => '', 'URL' => '' ),
		);
		$result = $this->plugin->parse_csv_rows( $rows );
		$this->assertArrayHasKey( 1, $result['valid'] );
		$this->assertCount( 2, $result['errors'] );
	}

	public function test_parse_csv_trims_whitespace_from_alt_text(): void {
		$rows = array(
			array( 'ID' => '7', 'Filename' => 'x.jpg', 'Alt Text' => '  A cat  ', 'URL' => '' ),
		);
		$result = $this->plugin->parse_csv_rows( $rows );
		$this->assertSame( 'A cat', $result['valid'][7] );
	}
}
