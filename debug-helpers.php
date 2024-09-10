<?php

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

function ll( $item ) {
	$file = '/tmp/1.log';
	$pr   = print_r( $item, true );

	//$max_size = 1024 * 1024 * 100; // 100MB in Bytes
	//$max_size = 1024 * 1024 * 20; // 20MB in Bytes
	$max_size = 1024 * 1024 * 10; // 10MB in Bytes
	if ( filesize( $file ) > $max_size ) {
		// Truncate File
		file_put_contents( $file, "\n" );
	}

	$ending = $GLOBALS['sandbox_req_link_ending'] ?? '';
	$id     = $GLOBALS['sandbox_req_id'] ?? '';
	$ending = str_pad( $ending, 12 );
	if ( strlen( $ending ) > 12 ) {
		$ending = substr( $ending, 0, 12 );
	}
	/* $prefix = '[' . $ending . ' ' . $id . ']'; */
	/* $pr = $prefix . ' ' . $pr; */

	file_put_contents( $file, $pr . "\n", FILE_APPEND );
	$GLOBALS['sandbox_logs'][] = $pr;
}
function ll_clear() {
	$file = '/tmp/1.log';
	file_put_contents( $file, "\n" );
}
function ll_link() {
	$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	llp( $actual_link );
}
function llp( $item ) {
	if ( empty( $GLOBALS['sandbox_llp_report_link'] ) ) {
		$GLOBALS['sandbox_llp_report_link'] = true;
		ll_link();
	}

	$ending = $GLOBALS['sandbox_req_link_ending'] ?? '';
	$id     = $GLOBALS['sandbox_req_id'] ?? '';

	$ending = str_pad( $ending, 12 );
	if ( strlen( $ending ) > 12 ) {
		$ending = substr( $ending, 0, 12 );
	}

	$prefix = '[' . $ending . ' ' . $id . ']';

	if ( gettype( $item ) !== 'string' ) {
		$item = json_encode( $item );
	}
	ll( "$prefix $item" );
	if ( function_exists('rr') ) {
		rr( "$prefix $item" );
	}
}

$GLOBALS['ac_timings'] = [];

/**
 * Start timing a code block
 *
 * @param string $label Unique identifier for this code block
 */
function ac_start($label) {
	$GLOBALS['ac_timings'][$label]['count'] = ($GLOBALS['ac_timings'][$label]['count'] ?? 0) + 1;
	$GLOBALS['ac_timings'][$label]['start'] = microtime(true);
}

/**
 * Stop timing a code block
 *
 * @param string $label Unique identifier for this code block
 */
function ac_stop($label) {
	$end = microtime(true);
	$start = $GLOBALS['ac_timings'][$label]['start'] ?? $end;
	$GLOBALS['ac_timings'][$label]['total'] = ($GLOBALS['ac_timings'][$label]['total'] ?? 0) + ($end - $start);
}

/**
 * Register a shutdown function to log all timings
 */
register_shutdown_function(function() {
	foreach ($GLOBALS['ac_timings'] as $label => $data) {
		$count = $data['count'];
		if (true || $count > 500) {
			$total_time = round($data['total'] * 1000, 2);
			$avg_time = round($total_time / $count, 2);
			llp("AC Debug: '$label' ran $count times, total time: $total_time ms, avg time: $avg_time ms");
		}
	}
});

$GLOBALS['sandbox_start_time']     ??= microtime( true );
$GLOBALS['sandbox_last_selt_time'] ??= microtime( true );
function selt( $message, $do_log = true ) {
	if ( empty( $message ) ) {
		$message = 'Mark ' . $GLOBALS['sandbox_selt_num'] . ':';
		$GLOBALS['sandbox_selt_num'] += 1;
	}
	$now  = microtime( true );

	$elap       = round( ( $now - $GLOBALS['sandbox_last_selt_time'] ) * 1000, 2 );
	$elap_begin = round( ( $now - $GLOBALS['sandbox_start_time'] ) * 1000, 2 );

	if ( $do_log ) {
		$spaces = str_repeat(' ', $GLOBALS['level'] ?? 0);
		if ( function_exists('ll') ) {
			ll($spaces . "$message | $elap | $elap_begin");
		}
		if ( function_exists('rr') ) {
			rr($spaces . "$message | $elap | $elap_begin");
		}
	}

	$GLOBALS['sandbox_last_selt_time'] = microtime( true );
}
