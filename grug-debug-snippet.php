<?php

add_action('wp_footer', function() {
	global $grug_debug;
	if (!empty($grug_debug)) {
		echo "\n<!-- GRUG DEBUG:\n";
		foreach ($grug_debug as $msg) {
			echo "	" . $msg . "\n";
		}
		echo "-->\n";
	}
}, 9999);

add_action('admin_footer', function() {
	global $grug_debug;
	if (!empty($grug_debug)) {
		echo "\n<!-- GRUG DEBUG:\n";
		foreach ($grug_debug as $msg) {
			echo "	" . $msg . "\n";
		}
		echo "-->\n";
	}
}, 9999);


add_action('ac_debug_logged', function($log_message) {
	echo "\n<!-- AC TIMINGS\n";
	echo $log_message;
	echo "-->\n";
});

global $grug_debug;
$grug_debug = array();

function grug($message, $label = '') {
	global $grug_debug;

	if (is_array($message) || is_object($message)) {
		$message = print_r($message, true);
	}

	$grug_debug[] = ($label ? "[$label] " : "") . $message;
}

