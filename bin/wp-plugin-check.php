#!/usr/bin/env php
<?php
/**
 * WordPress Plugin Directory Requirements Checker
 *
 * Validates that the plugin meets WordPress.org plugin directory requirements.
 * Reference: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
 *
 * @package AutoAltTags
 */

// Configuration
$plugin_slug = 'auto-alt-tags';
$plugin_file = 'auto-alt-tags.php';
$text_domain = 'auto-alt-tags';

// Colors for terminal output
define( 'COLOR_RED', "\033[0;31m" );
define( 'COLOR_GREEN', "\033[0;32m" );
define( 'COLOR_YELLOW', "\033[1;33m" );
define( 'COLOR_BLUE', "\033[0;34m" );
define( 'COLOR_NC', "\033[0m" );

// Track errors and warnings
$errors   = array();
$warnings = array();

/**
 * Print a success message
 */
function print_success( $message ) {
	echo COLOR_GREEN . '  ✓ ' . COLOR_NC . $message . PHP_EOL;
}

/**
 * Print an error message
 */
function print_error( $message ) {
	global $errors;
	$errors[] = $message;
	echo COLOR_RED . '  ✗ ' . COLOR_NC . $message . PHP_EOL;
}

/**
 * Print a warning message
 */
function print_warning( $message ) {
	global $warnings;
	$warnings[] = $message;
	echo COLOR_YELLOW . '  ⚠ ' . COLOR_NC . $message . PHP_EOL;
}

/**
 * Print a section header
 */
function print_section( $title ) {
	echo PHP_EOL . COLOR_BLUE . '► ' . $title . COLOR_NC . PHP_EOL;
}

/**
 * Get all PHP files in the plugin
 */
function get_php_files( $dir ) {
	$files    = array();
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file ) {
		if ( $file->isFile() && $file->getExtension() === 'php' ) {
			// Skip test files and vendor
			$path = $file->getPathname();
			if ( strpos( $path, '/tests/' ) === false && strpos( $path, '/vendor/' ) === false ) {
				$files[] = $path;
			}
		}
	}

	return $files;
}

// Start validation
echo PHP_EOL;
echo COLOR_BLUE . '════════════════════════════════════════════════' . COLOR_NC . PHP_EOL;
echo COLOR_BLUE . '  WordPress Plugin Requirements Checker' . COLOR_NC . PHP_EOL;
echo COLOR_BLUE . '════════════════════════════════════════════════' . COLOR_NC . PHP_EOL;

$root_dir = dirname( __DIR__ );
chdir( $root_dir );

// ============================================================================
// 1. Plugin Header Check
// ============================================================================
print_section( 'Plugin Header' );

$main_file_content = file_get_contents( $plugin_file );

$required_headers = array(
	'Plugin Name' => '/\*\s*Plugin Name:\s*(.+)/i',
	'Version'     => '/\*\s*Version:\s*(.+)/i',
	'Author'      => '/\*\s*Author:\s*(.+)/i',
	'License'     => '/\*\s*License:\s*(.+)/i',
	'Text Domain' => '/\*\s*Text Domain:\s*(.+)/i',
);

foreach ( $required_headers as $header => $pattern ) {
	if ( preg_match( $pattern, $main_file_content, $matches ) ) {
		print_success( "$header: " . trim( $matches[1] ) );
	} else {
		print_error( "Missing required header: $header" );
	}
}

// Check Requires at least and Requires PHP
$optional_headers = array(
	'Requires at least' => '/\*\s*Requires at least:\s*(.+)/i',
	'Requires PHP'      => '/\*\s*Requires PHP:\s*(.+)/i',
);

foreach ( $optional_headers as $header => $pattern ) {
	if ( preg_match( $pattern, $main_file_content, $matches ) ) {
		print_success( "$header: " . trim( $matches[1] ) );
	} else {
		print_warning( "Missing recommended header: $header" );
	}
}

// ============================================================================
// 2. readme.txt Validation
// ============================================================================
print_section( 'readme.txt Validation' );

if ( file_exists( 'readme.txt' ) ) {
	$readme_content = file_get_contents( 'readme.txt' );

	// Check for required sections
	$readme_sections = array(
		'=== '              => 'Plugin name header',
		'== Description ==' => 'Description section',
		'== Installation =='=> 'Installation section',
		'== Changelog =='   => 'Changelog section',
		'Stable tag:'       => 'Stable tag',
		'License:'          => 'License',
		'Tested up to:'     => 'Tested up to',
		'Requires at least:'=> 'Requires at least',
	);

	foreach ( $readme_sections as $pattern => $name ) {
		if ( stripos( $readme_content, $pattern ) !== false ) {
			print_success( "$name found" );
		} else {
			print_error( "Missing in readme.txt: $name" );
		}
	}
} else {
	print_error( 'readme.txt file not found (required for WordPress.org)' );
}

// ============================================================================
// 3. Security Checks
// ============================================================================
print_section( 'Security Checks' );

$php_files = get_php_files( $root_dir );

// Check for direct file access prevention
$files_with_abspath_check = 0;
$files_without_abspath    = array();

foreach ( $php_files as $file ) {
	$content  = file_get_contents( $file );
	$relative = str_replace( $root_dir . '/', '', $file );

	// Skip index.php files (they just have "Silence is golden")
	if ( basename( $file ) === 'index.php' && strpos( $content, 'Silence is golden' ) !== false ) {
		continue;
	}

	// Check for ABSPATH or similar protection
	if (
		preg_match( '/defined\s*\(\s*[\'"]ABSPATH[\'"]\s*\)/i', $content ) ||
		preg_match( '/defined\s*\(\s*[\'"]WPINC[\'"]\s*\)/i', $content ) ||
		preg_match( '/if\s*\(\s*!\s*defined/i', $content )
	) {
		$files_with_abspath_check++;
	} else {
		$files_without_abspath[] = $relative;
	}
}

if ( empty( $files_without_abspath ) ) {
	print_success( 'All PHP files have direct access prevention' );
} else {
	foreach ( $files_without_abspath as $file ) {
		print_warning( "Missing ABSPATH check: $file" );
	}
}

// Check for proper nonce usage in AJAX handlers
$ajax_nonce_issues = array();
foreach ( $php_files as $file ) {
	$content  = file_get_contents( $file );
	$relative = str_replace( $root_dir . '/', '', $file );

	// Find AJAX handlers
	if ( preg_match_all( '/wp_ajax_(\w+)/', $content, $matches ) ) {
		// Check for nonce verification
		if ( ! preg_match( '/check_ajax_referer|wp_verify_nonce/', $content ) ) {
			$ajax_nonce_issues[] = $relative;
		}
	}
}

if ( empty( $ajax_nonce_issues ) ) {
	print_success( 'AJAX handlers have nonce verification' );
} else {
	foreach ( $ajax_nonce_issues as $file ) {
		print_error( "AJAX handler without nonce check: $file" );
	}
}

// Check for capability checks
$capability_check = false;
foreach ( $php_files as $file ) {
	$content = file_get_contents( $file );
	if ( preg_match( '/current_user_can\s*\(/', $content ) ) {
		$capability_check = true;
		break;
	}
}

if ( $capability_check ) {
	print_success( 'User capability checks found' );
} else {
	print_warning( 'No user capability checks found (current_user_can)' );
}

// ============================================================================
// 4. Data Sanitization and Escaping
// ============================================================================
print_section( 'Data Sanitization & Escaping' );

$sanitize_functions = array(
	'sanitize_text_field',
	'sanitize_textarea_field',
	'sanitize_email',
	'sanitize_file_name',
	'sanitize_key',
	'absint',
	'intval',
	'wp_kses',
);

$escape_functions = array(
	'esc_html',
	'esc_attr',
	'esc_url',
	'esc_js',
	'esc_textarea',
	'wp_kses',
	'wp_kses_post',
);

$found_sanitize = array();
$found_escape   = array();

foreach ( $php_files as $file ) {
	$content = file_get_contents( $file );

	foreach ( $sanitize_functions as $func ) {
		if ( strpos( $content, $func ) !== false ) {
			$found_sanitize[ $func ] = true;
		}
	}

	foreach ( $escape_functions as $func ) {
		if ( strpos( $content, $func ) !== false ) {
			$found_escape[ $func ] = true;
		}
	}
}

if ( ! empty( $found_sanitize ) ) {
	print_success( 'Input sanitization found: ' . implode( ', ', array_keys( $found_sanitize ) ) );
} else {
	print_error( 'No input sanitization functions found' );
}

if ( ! empty( $found_escape ) ) {
	print_success( 'Output escaping found: ' . implode( ', ', array_keys( $found_escape ) ) );
} else {
	print_error( 'No output escaping functions found' );
}

// Check for $wpdb->prepare usage
$uses_wpdb_prepare = false;
$uses_raw_queries  = false;

foreach ( $php_files as $file ) {
	$content = file_get_contents( $file );

	if ( preg_match( '/\$wpdb->prepare\s*\(/', $content ) ) {
		$uses_wpdb_prepare = true;
	}

	// Check for potentially unsafe queries
	if ( preg_match( '/\$wpdb->(query|get_results|get_var|get_row)\s*\(\s*["\']/', $content ) ) {
		if ( ! preg_match( '/\$wpdb->(query|get_results|get_var|get_row)\s*\(\s*\$wpdb->prepare/', $content ) ) {
			$uses_raw_queries = true;
		}
	}
}

if ( $uses_wpdb_prepare ) {
	print_success( 'Using $wpdb->prepare() for database queries' );
}

if ( $uses_raw_queries ) {
	print_warning( 'Potential raw SQL queries detected - ensure proper escaping' );
}

// ============================================================================
// 5. Text Domain & Internationalization
// ============================================================================
print_section( 'Internationalization (i18n)' );

$i18n_functions      = array( '__', '_e', '_n', '_x', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e' );
$i18n_found          = false;
$wrong_text_domains  = array();
$missing_text_domain = array();

foreach ( $php_files as $file ) {
	$content  = file_get_contents( $file );
	$relative = str_replace( $root_dir . '/', '', $file );

	foreach ( $i18n_functions as $func ) {
		// Find function calls with text domain
		$pattern = '/' . preg_quote( $func, '/' ) . '\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/';
		if ( preg_match_all( $pattern, $content, $matches ) ) {
			$i18n_found = true;
			foreach ( $matches[1] as $domain ) {
				if ( $domain !== $text_domain ) {
					$wrong_text_domains[ $relative ][] = $domain;
				}
			}
		}

		// Check for missing text domain (function call without second parameter for simple functions)
		if ( in_array( $func, array( '__', '_e' ), true ) ) {
			$pattern_missing = '/' . preg_quote( $func, '/' ) . '\s*\(\s*[\'"][^\'"]+[\'"]\s*\)/';
			if ( preg_match( $pattern_missing, $content ) ) {
				$missing_text_domain[] = $relative;
			}
		}
	}
}

if ( $i18n_found ) {
	print_success( 'Internationalization functions found' );
} else {
	print_warning( 'No internationalization functions found' );
}

if ( empty( $wrong_text_domains ) ) {
	print_success( "Text domain '$text_domain' used consistently" );
} else {
	foreach ( $wrong_text_domains as $file => $domains ) {
		print_error( "Wrong text domain in $file: " . implode( ', ', array_unique( $domains ) ) );
	}
}

$missing_text_domain = array_unique( $missing_text_domain );
if ( ! empty( $missing_text_domain ) ) {
	foreach ( $missing_text_domain as $file ) {
		print_warning( "Possible missing text domain in: $file" );
	}
}

// ============================================================================
// 6. Deprecated Functions Check
// ============================================================================
print_section( 'Deprecated Functions Check' );

$deprecated_functions = array(
	'ereg'                    => 'preg_match',
	'eregi'                   => 'preg_match with i flag',
	'split'                   => 'explode or preg_split',
	'mysql_query'             => '$wpdb methods',
	'mysql_connect'           => '$wpdb',
	'mysql_real_escape_string'=> '$wpdb->prepare()',
	'create_function'         => 'anonymous functions',
	'each'                    => 'foreach',
);

$found_deprecated = array();

foreach ( $php_files as $file ) {
	$content  = file_get_contents( $file );
	$relative = str_replace( $root_dir . '/', '', $file );

	foreach ( $deprecated_functions as $func => $replacement ) {
		if ( preg_match( '/\b' . preg_quote( $func, '/' ) . '\s*\(/', $content ) ) {
			$found_deprecated[ $func ] = array(
				'file'        => $relative,
				'replacement' => $replacement,
			);
		}
	}
}

if ( empty( $found_deprecated ) ) {
	print_success( 'No deprecated PHP functions found' );
} else {
	foreach ( $found_deprecated as $func => $info ) {
		print_error( "Deprecated function '$func' in {$info['file']} - use {$info['replacement']}" );
	}
}

// ============================================================================
// 7. File Structure Check
// ============================================================================
print_section( 'File Structure' );

// Check for required files
$required_files = array(
	$plugin_file => 'Main plugin file',
	'readme.txt' => 'WordPress.org readme',
);

foreach ( $required_files as $file => $desc ) {
	if ( file_exists( $file ) ) {
		print_success( "$desc exists ($file)" );
	} else {
		print_error( "$desc missing ($file)" );
	}
}

// Check for recommended files
$recommended_files = array(
	'LICENSE'      => 'License file',
	'CHANGELOG.md' => 'Changelog',
);

foreach ( $recommended_files as $file => $desc ) {
	if ( file_exists( $file ) ) {
		print_success( "$desc exists ($file)" );
	} else {
		print_warning( "$desc recommended ($file)" );
	}
}

// Check that sensitive files are not included
$sensitive_files = array(
	'.git'                       => 'Git directory',
	'.env'                       => 'Environment file',
	'phpunit.xml'                => 'PHPUnit config (use phpunit.xml.dist)',
	'composer.lock'              => 'Composer lock file',
	'SECURITY_AUDIT_REPORT.md'   => 'Security audit report',
	'WORDPRESS_SUBMISSION_REPORT.md' => 'Submission report',
);

foreach ( $sensitive_files as $file => $desc ) {
	if ( file_exists( $file ) && $file !== 'phpunit.xml.dist' ) {
		if ( $file === '.git' ) {
			print_success( "$desc exists (will be excluded from dist)" );
		} else {
			print_warning( "$desc should be excluded from distribution" );
		}
	}
}

// ============================================================================
// 8. Code Quality Checks
// ============================================================================
print_section( 'Code Quality' );

// Check for PHP short tags
$short_tags_found = false;
foreach ( $php_files as $file ) {
	$content = file_get_contents( $file );
	if ( preg_match( '/<\?(?!php|xml)/', $content ) ) {
		$short_tags_found = true;
		$relative         = str_replace( $root_dir . '/', '', $file );
		print_error( "PHP short tags found in: $relative" );
	}
}

if ( ! $short_tags_found ) {
	print_success( 'No PHP short tags found' );
}

// Check for proper PHP closing tags (should not have closing tag at end of file)
$closing_tag_issues = array();
foreach ( $php_files as $file ) {
	$content  = file_get_contents( $file );
	$relative = str_replace( $root_dir . '/', '', $file );

	// Skip index.php files
	if ( basename( $file ) === 'index.php' ) {
		continue;
	}

	// Check if file ends with closing PHP tag
	$content = rtrim( $content );
	if ( preg_match( '/\?>\s*$/', $content ) ) {
		$closing_tag_issues[] = $relative;
	}
}

if ( empty( $closing_tag_issues ) ) {
	print_success( 'PHP files do not have trailing closing tags (good practice)' );
} else {
	foreach ( $closing_tag_issues as $file ) {
		print_warning( "Trailing ?> found in: $file (WordPress recommends omitting)" );
	}
}

// ============================================================================
// Summary
// ============================================================================
echo PHP_EOL;
echo COLOR_BLUE . '════════════════════════════════════════════════' . COLOR_NC . PHP_EOL;
echo COLOR_BLUE . '  Summary' . COLOR_NC . PHP_EOL;
echo COLOR_BLUE . '════════════════════════════════════════════════' . COLOR_NC . PHP_EOL;
echo PHP_EOL;

$error_count   = count( $errors );
$warning_count = count( $warnings );

if ( $error_count === 0 && $warning_count === 0 ) {
	echo COLOR_GREEN . '  ✓ All checks passed! Plugin is ready for submission.' . COLOR_NC . PHP_EOL;
} else {
	if ( $error_count > 0 ) {
		echo COLOR_RED . "  ✗ $error_count error(s) found - must be fixed before submission" . COLOR_NC . PHP_EOL;
	}
	if ( $warning_count > 0 ) {
		echo COLOR_YELLOW . "  ⚠ $warning_count warning(s) - should be reviewed" . COLOR_NC . PHP_EOL;
	}
}

echo PHP_EOL;

// Exit with error code if there are errors
exit( $error_count > 0 ? 1 : 0 );
