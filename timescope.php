<?php
/**
 * Plugin Name: Timescope
 * Plugin URI:  https://example.com
 * Description: Standalone plugin providing dtm(), ac_start(), ac_stop() for debugging and performance analysis.
 * Version:     1.0
 * Author:      mreishus
 * License:     MIT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

function ll( $item ) {
	$file = '/tmp/1.log';
	$pr   = print_r( $item, true );

	$max_size = 1024 * 1024 * 100; // 100MB in Bytes
	$max_size = 1024 * 1024 * 2; // 2MB in Bytes
	if ( filesize( $file ) > $max_size ) {
		// Truncate File
		file_put_contents( $file, "\n" );
	}
	file_put_contents( $file, $pr . "\n", FILE_APPEND );
}

function ll_clear() {
	$file = '/tmp/1.log';
	file_put_contents( $file, "\n" );
}

function ll_memory( $prefix = '' ) {
	ll( $prefix . ' ' . round( memory_get_usage() / 1048576, 2 ) . ' megabytes used' );
}

/**
 * Gets the request prefix details (identifier, hash, full context).
 * Uses static variables to ensure it's generated only once per request.
 *
 * @return array ['prefix' => string, 'full_context' => string, 'is_first' => bool]
 */
function _llp_get_request_prefix_details() {
	static $prefix_details = null;
	static $is_first_call = true;

	if ( $prefix_details === null ) {
		// 1. Generate unique request hash (short)
		$request_hash = substr( bin2hex( random_bytes( 3 ) ), 0, 5 );

		// 2. Determine context and identifier
		$identifier   = 'unknown';
		$full_context = 'unknown context';
		$identifier_length = 12; // Desired length for the padded identifier part

		if ( php_sapi_name() === 'cli' ) {
			// CLI Request
			global $argv;
			$script_path = $argv[0] ?? 'cli';
			$full_context = implode( ' ', $argv ?? ['cli'] ); // Show command with args
			$identifier = basename( $script_path, '.php' ); // Use script name without .php
		} elseif ( isset( $_SERVER['REQUEST_URI'] ) ) {
			// Web Request
			$full_context = $_SERVER['REQUEST_METHOD'] .' '. $_SERVER['REQUEST_URI'];
			$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
			if ( $path && $path !== '/' ) {
				$segments = explode( '/', trim( $path, '/' ) );
				// Find the last non-empty segment
				$last_segment = '';
				while( ( $last_segment = array_pop( $segments ) ) === '' && ! empty( $segments ) );

				// Use the last segment, or 'root' if path was just '/' or segments were empty
				$identifier = ! empty( $last_segment ) ? $last_segment : 'root';
			} else {
				$identifier = 'root'; // Handle root URL or empty path
			}
		}

		// 3. Clean and Normalize Identifier
		$identifier = preg_replace( '/[^a-zA-Z0-9_-]/', '', $identifier ); // Remove special chars
		$identifier = substr( $identifier, 0, $identifier_length ); // Truncate if too long
		$identifier = str_pad( $identifier, $identifier_length ); // Pad with spaces if too short

		// 4. Format Prefix
		$prefix = sprintf( '[%-'. $identifier_length .'s %s]', $identifier, $request_hash );

		$prefix_details = [
			'prefix'       => $prefix,
			'full_context' => $full_context,
		];
	}

	$details = $prefix_details;
	$details['is_first'] = $is_first_call;

	// Important: Set first call flag to false *after* fetching its value
	if ($is_first_call) {
		$is_first_call = false;
	}

	return $details;
}

/**
 * LLP: Log with per-request prefix
 *
 * Similar to ll(), but each request has:
 *   1) A short label from the last segment of REQUEST_URI
 *   2) A short random "request ID"
 *   3) The very first llp() call in a request logs the full URL
 *
 * Usage:
 *   llp("hello world");
 *
 * Output example (in /tmp/1.log):
 *   [domains      c8b72] https://public-api.wordpress.com/rest/v1.2/sites/241709932/domains?http_envelope=1
 *   [domains      c8b72] hello world
 *
 */
function llp( $message ) {
	static $request_prefix = '';
	static $first          = true;

	// Decide which file to log to and set up a max size
	$file     = '/tmp/1.log';
	$max_size = 2 * 1024 * 1024; // 2MB

	// Possibly truncate if the file size grows beyond $max_size
	if ( file_exists( $file ) && filesize( $file ) > $max_size ) {
		file_put_contents( $file, "\n" );
	}

	// Generate our request_prefix only once per request
	if ( '' === $request_prefix ) {
		// Attempt to parse something from the requested URL, or "CLI" if not present
		$request_uri = $_SERVER['REQUEST_URI'] ?? 'CLI';
		$path        = parse_url( $request_uri, PHP_URL_PATH ) ?: '';
		$parts       = explode( '/', trim( $path, '/' ) );
		$label       = end( $parts ) ?: 'root';

		// Create a short random ID
		$rand = substr( md5( uniqid( '', true ) ), 0, 5 );

		// Example bracket format: [domains      c8b72]
		// Make the label left-aligned in, say, 12 spaces, then the ID right after
		$request_prefix = sprintf( '[%-12s %5s]', $label, $rand );
	}

	// If this is our first llp() call in the request, log the full URL
	if ( $first ) {
		$first = false;

		// Try to construct a full URL (HTTP/HTTPS plus host plus path)
		$scheme = 'http';
		if ( ! empty( $_SERVER['HTTPS'] ) && strtolower( $_SERVER['HTTPS'] ) !== 'off' ) {
			$scheme = 'https';
		}
		$host      = $_SERVER['HTTP_HOST'] ?? 'cli';
		$request   = $_SERVER['REQUEST_URI'] ?? '';
		$full_url  = $scheme . '://' . $host . $request;

		// Log the full URL line
		file_put_contents( $file, $request_prefix . ' ' . $full_url . "\n", FILE_APPEND );
	}

	// Log the user message
	file_put_contents( $file, $request_prefix . ' ' . $message . "\n", FILE_APPEND );
}


/**
 * Log Like Print (LLP) - Logs variables with request context.
 *
 * Logs data to /tmp/1.log, prefixed with a request identifier and hash.
 * The first call per request includes the full request URI/command.
 * Handles log file rotation based on size.
 *
 * @param mixed $item The variable or message to log.
 */
function llp_2( $item ) {
	$details = _llp_get_request_prefix_details();
	$prefix = $details['prefix'];
	$log_data = print_r( $item, true );

	$log_message = '';
	// Add full context only on the very first call for this request
	if ( $details['is_first'] ) {
		$log_message .= $prefix . ' ' . $details['full_context'] . "\n";
	}

	// Add the actual log item, prefixed
	// Handle multi-line print_r output - prefix each line
	$lines = explode("\n", rtrim($log_data)); // rtrim to avoid trailing empty line issue
	foreach ($lines as $line) {
		$log_message .= $prefix . ' ' . $line . "\n";
	}

	// Write to file with rotation
	$file = '/tmp/1.log';
	$max_size = 1024 * 1024 * 2; // 2MB

	// Check size and truncate if needed - check existence first
	if ( file_exists( $file ) && filesize( $file ) > $max_size ) {
		file_put_contents( $file, '' ); // Clear file content
	}

	// Append the message(s)
	file_put_contents( $file, $log_message, FILE_APPEND );
}

/*
 * Globals for dtm() and AC block timing
 */
$GLOBALS['dtm_start']  = $GLOBALS['dtm_start'] ?? hrtime( true );
$GLOBALS['ac_timings'] = [];

/**
 * Debug Timer Function (dtm)
 *
 * A lightweight, high-precision timing function for debugging and performance analysis.
 * It measures elapsed time between function calls and from the start of the script.
 *
 * @param string $message Optional. Message to log with the timing information.
 * @param bool   $do_log  Optional. Whether to log the timing information. Default true.
 * @param bool   $flip    Optional. Whether to flip the message to the end. Default false.
 *
 * @return string Formatted log message with timing info.
 */
if ( ! function_exists( 'dtm' ) ) {
	function dtm( $message = '', $do_log = true, $flip = false ) {
		static $last_dtm_time = null;
		$now = hrtime( true );

		// If you'd like to measure from another known constant, e.g. WP_START_TIMESTAMP
		// you can define that in your environment. Otherwise, fall back to dtm_start.
		if ( defined( 'WP_START_TIMESTAMP' ) ) {
			$total = round( ( microtime( true ) - WP_START_TIMESTAMP ) * 1000, 3 );
		} else {
			$total = round( ( $now - $GLOBALS['dtm_start'] ) / 1e6, 3 );
		}

		if ( $last_dtm_time === null ) {
			$elapsed = $total;
		} else {
			$elapsed = round( ( $now - $last_dtm_time ) / 1e6, 3 );
		}

		if ( empty( $message ) ) {
			static $dtm_count = null;
			if ( $dtm_count === null ) {
				$dtm_count = 0;
			}
			$dtm_count++;
			$message = "Mark $dtm_count";
		}

		if ( ! is_string( $message ) ) {
			$message = json_encode( $message );
		}

		$message_pad_length = 20;
		if ( strlen( $message ) < $message_pad_length ) {
			$message = str_pad( $message, $message_pad_length );
		}

		$elapsed = sprintf( '%7.2f', $elapsed );
		$total   = sprintf( '%8.2f', $total );

		if ( $flip ) {
			$log_message = "$elapsed | $total | $message";
		} else {
			$log_message = "$message | $elapsed | $total";
		}

		if ( $do_log ) {
			/**
			 * Fires right after dtm() constructs the log message.
			 * If you want to route dtm logs to a custom handler, add your callback:
			 *
			 *     add_action( 'dtm_logged', 'my_dtm_logger' );
			 *     function my_dtm_logger( $msg ) {
			 *         error_log( "My custom dtm log => $msg" );
			 *     }
			 */
			$has_dtm_logged_hooks = has_action( 'dtm_logged' );
			if ( $has_dtm_logged_hooks ) {
				do_action( 'dtm_logged', $log_message );
			} else {
				error_log( $log_message );
				// If your environment provides an rr() function (e.g. Request Radar), use it:
				if ( function_exists( 'rr' ) ) {
					rr( $log_message );
				}
			}
		}

		$last_dtm_time = $now;
		return $log_message;
	}
}

/**
 * Start accumulative timing for a code block
 *
 * @param string $label A short, unique identifier for this code block
 */
if ( ! function_exists( 'ac_start' ) ) {
	function ac_start( $label ) {
		if ( ! isset( $GLOBALS['ac_timings'][ $label ] ) ) {
			$GLOBALS['ac_timings'][ $label ] = [
				'count'   => 0,
				'total'   => 0,
				'min'     => PHP_INT_MAX,
				'max'     => 0,
				'samples' => [], // For median calculation
			];
		}
		$GLOBALS['ac_timings'][ $label ]['start'] = hrtime( true );
	}
}

/**
 * Stop accumulative timing for a code block
 *
 * @param string $label The same identifier used in the corresponding ac_start() call
 */
if ( ! function_exists( 'ac_stop' ) ) {
	function ac_stop( $label ) {
		$end = hrtime( true );
		if ( ! isset( $GLOBALS['ac_timings'][ $label ]['start'] ) ) {
			error_log( "AC Debug Error: ac_stop('$label') called without a matching ac_start()" );
			return;
		}
		$duration = ( $end - $GLOBALS['ac_timings'][ $label ]['start'] ) / 1e6; // ms

		$GLOBALS['ac_timings'][ $label ]['count']++;
		$GLOBALS['ac_timings'][ $label ]['total'] += $duration;
		$GLOBALS['ac_timings'][ $label ]['min']    = min( $GLOBALS['ac_timings'][ $label ]['min'], $duration );
		$GLOBALS['ac_timings'][ $label ]['max']    = max( $GLOBALS['ac_timings'][ $label ]['max'], $duration );

		// Smart sampling for median calculation
		// Keep up to 1000 samples. If we exceed that, randomly replace an old sample.
		if ( count( $GLOBALS['ac_timings'][ $label ]['samples'] ) < 1000 ) {
			$GLOBALS['ac_timings'][ $label ]['samples'][] = $duration;
		} else if ( mt_rand( 0, $GLOBALS['ac_timings'][ $label ]['count'] ) < 1000 ) {
			$GLOBALS['ac_timings'][ $label ]['samples'][ mt_rand( 0, 999 ) ] = $duration;
		}

		unset( $GLOBALS['ac_timings'][ $label ]['start'] ); // Clear the start time
	}
}

/**
 * Calculate median from samples
 *
 * @param array $samples Array of timing samples in milliseconds
 * @return float Median value
 */
if ( ! function_exists( 'ac_calculate_median' ) ) {
	function ac_calculate_median( $samples ) {
		$count = count( $samples );
		if ( $count === 0 ) {
			return 0;
		}
		sort( $samples );
		$middle = floor( ( $count - 1 ) / 2 );
		if ( $count % 2 ) {
			return $samples[ $middle ];
		}
		return ( $samples[ $middle ] + $samples[ $middle + 1 ] ) / 2;
	}
}

/**
 * On shutdown, log accumulated AC data (ac_start/ac_stop).
 */
add_action(
	'shutdown',
	function () {
		if ( empty( $GLOBALS['ac_timings'] ) ) {
			return;
		}

		$request_url = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : 'CLI';
		$log_messages = [ "AC Debug Summary for: $request_url" ];
		$format = '%-20s | %8s | %10s | %10s | %10s | %10s | %10s';
		$log_messages[] = sprintf( $format, 'Label', 'Count', 'Total (ms)', 'Avg (ms)', 'Median (ms)', 'Min (ms)', 'Max (ms)' );
		$log_messages[] = str_repeat( '-', 100 );

		foreach ( $GLOBALS['ac_timings'] as $label => $data ) {
			if ( $data['count'] > 0 ) {
				$avg    = $data['total'] / $data['count'];
				$median = ac_calculate_median( $data['samples'] );

				$log_messages[] = sprintf(
					$format,
					substr( $label, 0, 20 ),            // Truncate labels longer than 20 chars
					$data['count'],
					number_format( $data['total'], 2 ), // Total
					number_format( $avg, 2 ),           // Average
					number_format( $median, 2 ),        // Median
					number_format( $data['min'], 2 ),   // Min
					number_format( $data['max'], 2 )    // Max
				);
			}
		}

		$log_messages[] = str_repeat( '-', 100 );
		$log_message = implode( "\n", $log_messages );

		/**
		 * Fires when accumulative timing data is logged at shutdown.
		 * Use this to customize how or where your AC logs are emitted.
		 *
		 *     add_action( 'ac_debug_logged', 'my_ac_logger' );
		 *     function my_ac_logger( $log_message ) {
		 *         error_log( "My custom AC log => \n$log_message" );
		 *     }
		 */
		$has_ac_debug_logged_hooks = has_action( 'ac_debug_logged' );
		if ( $has_ac_debug_logged_hooks ) {
			do_action( 'ac_debug_logged', $log_message );
		} else {
			error_log( $log_message );
		}
	},
	9999
);

function test_dtm_logged($log_message) {
	llp($log_message);
}

add_action('ac_debug_logged', function($log_message) {
	error_log($log_message);
	if ( function_exists('llp') ) {
		llp($log_message);
	}
});

function sandbox_dots($in, $l) {
	return strlen($in) > $l ? substr($in,0,$l)."..." : $in;
}
function stack_trace() {
	$stack = debug_backtrace();
	$output = 'Stack trace:' . PHP_EOL;

	$stackLen = count($stack);
	for ($i = 1; $i < $stackLen; $i++) {
		$entry = $stack[$i];

		$func = $entry['function'] . '(';
		$argsLen = is_countable( $entry['args'] ?? null ) ? count($entry['args']) : 0;
		for ($j = 0; $j < $argsLen; $j++) {
			try {
				//$func .= "Unknown";
				$func .= sandbox_dots( json_encode( $entry['args'][$j] ), 50 );
			} catch (Throwable $e) {
				$func .= "Unknown";
			}
			if ($j < $argsLen - 1) $func .= ', ';
		}
		$func .= ')';

		$output .= '#' . ($i - 1) . ' ' . $entry['file'] . ':' . $entry['line'] . ' - ' . $func . PHP_EOL;
	}

	return $output;
}

add_action('admin_menu', function () {
	add_management_page(
		'Timescope Debug Tools',
		'Timescope Debug',
		'manage_options',
		'timescope-debug-tools',
		'timescope_debug_tools_page'
	);
});

function timescope_debug_tools_page() {
	$logfile = '/tmp/1.log';

	// Handle clear log action.
	if (isset($_POST['ll_clear']) && check_admin_referer('timescope_clear_log')) {
		file_put_contents($logfile, '');
		echo '<div class="updated"><p>Log cleared.</p></div>';
	}
	echo '<div class="wrap"><h1>Timescope Debug Tools</h1>';
	echo '<h2>Available Functions</h2>';
	echo '<ul>
		<li><code>ll($item)</code>: Log an item to /tmp/1.log (print_r style)</li>
		<li><code>llp($item)</code>: Log with per-request prefix/context</li>
		<li><code>dtm($msg)</code>: Print debug timing mark</li>
		<li><code>ac_start($label)</code> / <code>ac_stop($label)</code>: Accumulate and report block timing</li>
		<li><code>stack_trace()</code>: Stack trace as string</li>
	</ul>';

	echo '<form method="post">';
	wp_nonce_field('timescope_clear_log');
	echo '<button type="submit" name="ll_clear" class="button">Clear /tmp/1.log</button>';
	echo '</form>';

	if (file_exists($logfile)) {
		echo '<h2>Last 100 lines from /tmp/1.log</h2><pre style="max-height:400px;overflow:auto;">';
		// Show tail of log file
		$lines = @file($logfile);
		if ($lines !== false) {
				$tail = array_slice($lines, -100);
				echo esc_html(implode('', $tail));
		} else {
				echo "Could not read log file.";
		}
		echo '</pre>';
	}
	echo '</div>';
}
