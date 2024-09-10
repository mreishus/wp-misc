<?php
/**
 * Plugin Name: WP Cache Stress Test - Split Requests
 * Description: Tests wp_cache_get, wp_cache_set, and wp_cache_get_multiple across separate requests with flexible size-iteration pairs
 * Version: 1.0a
 * Author: mreishus
 */

// Configuration variables
$TEST_CASES = [
    [ 'size' => 1, 'iterations' => 400 ],   // 1KB
    [ 'size' => 10, 'iterations' => 150 ],  // 10KB
    [ 'size' => 50, 'iterations' => 30 ],
    [ 'size' => 100, 'iterations' => 20 ], // 100KB
    [ 'size' => 150, 'iterations' => 10 ],
    [ 'size' => 1000, 'iterations' => 3 ],  // 1MB
];

$KEY_PREFIX = "stress_test_key_";
//$HASH_FILE = WP_CONTENT_DIR . '/cache_test_hashes.txt';
$HASH_FILE = '/tmp/cache_test_hashes.txt';

function get_cache_implementation() {
    if (defined('WP_CACHE_IMPLEMENTATION')) {
        return WP_CACHE_IMPLEMENTATION;
    }
    return 'undefined';
}

function run_cache_set_test() {
    global $TEST_CASES, $KEY_PREFIX, $HASH_FILE;

    echo "<h2>Cache Set Test Results</h2>";

    $hashes = [];
    $cache_implementation = get_cache_implementation();
    $csv_data = "Test Type,Cache Implementation,Size (KB),Iterations,Total Time (ms),Average Time (ms)\n";
    $csv_data .= "SET,{$cache_implementation},,,,\n";

    foreach ( $TEST_CASES as $case ) {
        $size = $case['size'];
        $iterations = $case['iterations'];
        $key_prefix = $KEY_PREFIX . $size . "_";

        // Prepare data outside of timing
        $data_array = [];
        for ( $i = 0; $i < $iterations; $i++ ) {
            $data_type = $i % 3; // 0: string, 1: array, 2: object
            switch ( $data_type ) {
                case 0:
                    $data = generate_random_data( $size );
                    break;
                case 1:
                    $data = generate_random_array( $size );
                    break;
                case 2:
                    $data = generate_random_object( $size );
                    break;
            }
            $data_array[$i] = $data;
            $key = $key_prefix . $i;
            $hashes[$key] = md5( serialize( $data ) );
        }

        // Perform timed cache set operations
        $start_time = microtime( true );
        for ( $i = 0; $i < $iterations; $i++ ) {
            $key = $key_prefix . $i;
            wp_cache_set( $key, $data_array[$i], '', 600 );
        }
        $set_time = ( microtime( true ) - $start_time ) * 1000;

        echo "<h3>Test with {$size}KB data:</h3>";
        echo "<p>Time to set {$iterations} items: " . number_format( $set_time, 2 ) . " ms</p>";
        echo "<p>Average time per set: " . number_format( $set_time / $iterations, 2 ) . " ms</p>";

        // Add to CSV data
        $csv_data .= "SET,{$cache_implementation},{$size},{$iterations}," . number_format($set_time, 2) . "," . number_format($set_time / $iterations, 2) . "\n";
    }

    file_put_contents( $HASH_FILE, serialize( $hashes ) );
    echo "<p>Cache set complete. Please run the get test now.</p>";

    output_csv_data($csv_data);
}

function run_cache_get_test() {
    global $TEST_CASES, $KEY_PREFIX, $HASH_FILE;

    echo "<h2>Cache Get Test Results</h2>";

    $hashes = unserialize( file_get_contents( $HASH_FILE ) );
    $total_errors = 0;
    $cache_implementation = get_cache_implementation();
    $csv_data = "Test Type,Cache Implementation,Size (KB),Iterations,Total Time (ms),Average Time (ms),Memory Used (MB),Peak Memory (MB),Errors\n";
    $csv_data .= "GET,{$cache_implementation},,,,,,\n";

    foreach ( $TEST_CASES as $case ) {
        $size = $case['size'];
        $iterations = $case['iterations'];
        $key_prefix = $KEY_PREFIX . $size . "_";

        // Record initial memory usage
        $initial_memory = memory_get_usage(true);

        // Perform timed cache get operations
        $start_time = microtime( true );
        $retrieved_data = [];
        for ( $i = 0; $i < $iterations; $i++ ) {
            $key = $key_prefix . $i;
            $retrieved_data[$key] = wp_cache_get( $key, '' );
        }
        $get_time = ( microtime( true ) - $start_time ) * 1000;

        // Record peak memory usage
        $peak_memory = memory_get_peak_usage(true);

        // Verify data integrity outside of timing
        $errors = 0;
        foreach ( $retrieved_data as $key => $data ) {
            if ( $data === false ) {
                echo "<p>Warning: Data not found for key {$key}</p>";
                $errors++;
            } elseif ( md5( serialize( $data ) ) !== $hashes[$key] ) {
                echo "<p>Error: Data mismatch for key {$key}</p>";
                $errors++;
            }
        }

        // Calculate memory usage
        $memory_used = $peak_memory - $initial_memory;

        echo "<h3>Test with {$size}KB data:</h3>";
        echo "<p>Time to get {$iterations} items: " . number_format( $get_time, 2 ) . " ms</p>";
        echo "<p>Average time per get: " . number_format( $get_time / $iterations, 2 ) . " ms</p>";
        echo "<p>Memory used / Peak: " 
            . number_format( $memory_used / 1024 / 1024, 2 ) . " MB / "
            . number_format( $peak_memory / 1024 / 1024, 2 ) . " MB "
            . "</p>";
        echo "<p>Errors: {$errors}</p>";

        $total_errors += $errors;

        // Add to CSV data
        $csv_data .= "GET,{$cache_implementation},{$size},{$iterations}," . number_format($get_time, 2) . "," . number_format($get_time / $iterations, 2) . ","
            . number_format($memory_used / 1024 / 1024, 2) . "," . number_format($peak_memory / 1024 / 1024, 2) . ",{$errors}\n";
    }

    echo "<p>Total errors: {$total_errors}</p>";
    echo "<p>Cache get complete. Please run the multi-get test now.</p>";

    output_csv_data($csv_data);
}

function run_cache_multi_get_test() {
    global $TEST_CASES, $KEY_PREFIX, $HASH_FILE;

    echo "<h2>Cache Multi-Get Test Results</h2>";

    $hashes = unserialize( file_get_contents( $HASH_FILE ) );
    $total_errors = 0;
    $cache_implementation = get_cache_implementation();
    $csv_data = "Test Type,Cache Implementation,Size (KB),Iterations,Total Time (ms),Average Time (ms),Memory Used (MB),Peak Memory (MB),Errors\n";
    $csv_data .= "MULTI-GET,{$cache_implementation},,,,,,\n";

    foreach ( $TEST_CASES as $case ) {
        $size = $case['size'];
        $iterations = $case['iterations'];
        $key_prefix = $KEY_PREFIX . $size . "_";

        // Record initial memory usage
        $initial_memory = memory_get_usage(true);

        // Prepare keys for multi-get
        $keys = [];
        for ( $i = 0; $i < $iterations; $i++ ) {
            $keys[] = $key_prefix . $i;
        }

        // Perform timed cache multi-get operation
        $start_time = microtime( true );
        $retrieved_data = wp_cache_get_multiple( $keys, '' );
        $get_time = ( microtime( true ) - $start_time ) * 1000;

        // Record peak memory usage
        $peak_memory = memory_get_peak_usage(true);

        // Verify data integrity outside of timing
        $errors = 0;
        foreach ( $retrieved_data as $key => $data ) {
            if ( $data === false ) {
                echo "<p>Warning: Data not found for key {$key}</p>";
                $errors++;
            } elseif ( md5( serialize( $data ) ) !== $hashes[$key] ) {
                echo "<p>Error: Data mismatch for key {$key}</p>";
                $errors++;
            }
        }

        // Calculate memory usage
        $memory_used = $peak_memory - $initial_memory;

        echo "<h3>Test with {$size}KB data:</h3>";
        echo "<p>Time to multi-get {$iterations} items: " . number_format( $get_time, 2 ) . " ms</p>";
        echo "<p>Average time per item: " . number_format( $get_time / $iterations, 2 ) . " ms</p>";
        echo "<p>Memory used / Peak: " 
            . number_format( $memory_used / 1024 / 1024, 2 ) . " MB / "
            . number_format( $peak_memory / 1024 / 1024, 2 ) . " MB "
            . "</p>";
        echo "<p>Errors: {$errors}</p>";

        $total_errors += $errors;

        // Add to CSV data
        $csv_data .= "MULTI-GET,{$cache_implementation},{$size},{$iterations}," . number_format($get_time, 2) . "," . number_format($get_time / $iterations, 2) . ","
            . number_format($memory_used / 1024 / 1024, 2) . "," . number_format($peak_memory / 1024 / 1024, 2) . ",{$errors}\n";

        // Clean up
        foreach ( $keys as $key ) {
            wp_cache_delete( $key, '' );
        }
    }

    echo "<p>Total errors: {$total_errors}</p>";
    unlink( $HASH_FILE );

    output_csv_data($csv_data);
}

function generate_random_data( $size_kb ) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $data = '';
    for ( $i = 0; $i < $size_kb * 1024; $i++ ) {
        $data .= $characters[rand( 0, strlen( $characters ) - 1 )];
    }
    return $data;
}

function generate_random_array( $size_kb ) {
    $array = [];
    $total_size = 0;
    $target_size = $size_kb * 1024;
    while ( $total_size < $target_size ) {
        $key = generate_random_data( 1 );
        $remaining_size = $target_size - $total_size - strlen( $key );
        $value_size = min( $remaining_size, 10 * 1024 ); // Cap at 10KB or remaining size
        $value = generate_random_data( $value_size / 1024 );
        $array[$key] = $value;
        $total_size += strlen( $key ) + strlen( $value );
    }
    return $array;
}

function generate_random_object( $size_kb ) {
    $obj = new stdClass();
    $total_size = 0;
    $target_size = $size_kb * 1024;
    while ( $total_size < $target_size ) {
        $key = generate_random_data( 1 );
        $remaining_size = $target_size - $total_size - strlen( $key );
        $value_size = min( $remaining_size, 10 * 1024 ); // Cap at 10KB or remaining size
        $value = generate_random_data( $value_size / 1024 );
        $obj->$key = $value;
        $total_size += strlen( $key ) + strlen( $value );
    }
    return $obj;
}

function output_csv_data($csv_data) {
    echo "<h3>CSV Data:</h3>";
    echo "<textarea id='csv-data' rows='10' cols='50'>" . htmlspecialchars($csv_data) . "</textarea><br>";
    echo "<button onclick='copyToClipboard()'>Copy to Clipboard</button>";

    echo "
    <script>
    function copyToClipboard() {
        var copyText = document.getElementById('csv-data');
        copyText.select();
        document.execCommand('copy');
        alert('Copied to clipboard!');
    }
    </script>
    ";
}

function add_cache_test_menu() {
    add_menu_page( 'Cache Set Test', 'Cache Set Test', 'manage_options', 'cache-set-test', 'run_cache_set_test' );
    add_menu_page( 'Cache Get Test', 'Cache Get Test', 'manage_options', 'cache-get-test', 'run_cache_get_test' );
    add_menu_page( 'Cache Multi-Get Test', 'Cache Multi-Get Test', 'manage_options', 'cache-multi-get-test', 'run_cache_multi_get_test' );
    add_menu_page( 'Theme Cache Test', 'Theme Cache Test', 'manage_options', 'theme-cache-test', 'simplified_theme_cache_test' );
    add_menu_page('Transient Set Test', 'Transient Set Test', 'manage_options', 'transient-set-test', 'run_transient_set_test');
    add_menu_page('Transient Get Test', 'Transient Get Test', 'manage_options', 'transient-get-test', 'run_transient_get_test');
    add_menu_page('JSON Decode Test', 'JSON Decode Test', 'manage_options', 'json-decode-test', 'run_json_decode_test');
    add_menu_page('Block Name Test', 'Block Name Test', 'manage_options', 'block-name-test', 'run_block_name_performance_test');
}
add_action( 'admin_menu', 'add_cache_test_menu' );

add_action('admin_menu', function() {
    add_menu_page('PHP Info', 'PHP Info', 'manage_options', 'custom-phpinfo', function() {
        if (!current_user_can('manage_options')) return;
        echo '<div class="wrap"><h1>PHP Info</h1><div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';
        ob_start();
        phpinfo();
        $phpinfo = ob_get_clean();
        $phpinfo = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $phpinfo);
        echo $phpinfo;
        echo '</div></div>';
    }, 'dashicons-admin-generic', 99);
});

function simplified_theme_cache_test() {
    echo "<pre>";
    $key = 'theme-a90f2dffc37ebc65946bf6af114c9bea';
    $group = 'themes';
    $expiration = 1800; // 30 minutes

    $data = [
        "block_theme" => true,
        "block_template_folders" => [
            "wp_template" => "templates",
            "wp_template_part" => "parts"
        ],
        "headers" => [
            "Name" => "Twenty Twenty-Four",
            // ... other headers ...
        ],
        "stylesheet" => "twentytwentyfour",
        "template" => "twentytwentyfour"
    ];

    echo "Simplified Theme Cache Test\n\n";

    // Step 1: Attempt to add the data to cache
    $add_result = wp_cache_add($key, $data, $group, $expiration);
    echo "1. Cache add result: " . ($add_result ? 'true' : 'false') . "\n";

    // Step 2: Attempt to get the data immediately after adding
    $get_result = wp_cache_get($key, $group);
    echo "2. Immediate get result: " . ($get_result ? 'data retrieved' : 'data not found') . "\n";

    // Step 3: Attempt to get the data again
    $second_get_result = wp_cache_get($key, $group);
    echo "3. Second get result: " . ($second_get_result ? 'data retrieved' : 'data not found') . "\n";

    // Additional checks
    echo "\nAdditional Information:\n";
    global $wp_object_cache;
    echo "Impl: " . get_cache_implementation() . "\n";
    echo "'themes' in non-persistent groups: " . (in_array('themes', $wp_object_cache->no_mc_groups) ? 'yes' : 'no') . "\n";

    // Check internal cache state - not working
    //$internal_cache = isset($wp_object_cache->cache[$group][$key]) ? 'present' : 'absent';
    //echo "Internal cache state: " . $internal_cache . "\n";

    // Attempt a force get
    $force_get_result = wp_cache_get($key, $group, true);
    echo "Force get result: " . ($force_get_result ? 'data retrieved' : 'data not found') . "\n";
    echo "</pre>";
}

function run_transient_set_test() {
    global $TEST_CASES, $KEY_PREFIX;

    echo "<h2>Transient Set Test Results</h2>";

    $csv_data = "Test Type,Cache Implementation,Size (KB),Iterations,Total Time (ms),Average Time (ms)\n";
    $csv_data .= "TRANSIENT_SET," . get_cache_implementation() . ",,,,\n";

    foreach ($TEST_CASES as $case) {
        $size = $case['size'];
        $iterations = $case['iterations'];
        $key_prefix = $KEY_PREFIX . "transient_" . $size . "_";

        // Prepare data outside of timing
        $data_array = [];
        for ($i = 0; $i < $iterations; $i++) {
            $data_type = $i % 3; // 0: string, 1: array, 2: object
            switch ($data_type) {
                case 0:
                    $data = generate_random_data($size);
                    break;
                case 1:
                    $data = generate_random_array($size);
                    break;
                case 2:
                    $data = generate_random_object($size);
                    break;
            }
            $data_array[$i] = $data;
        }

        // Perform timed transient set operations
        $start_time = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $key = $key_prefix . $i;
            set_transient($key, $data_array[$i], 600);
        }
        $set_time = (microtime(true) - $start_time) * 1000;

        echo "<h3>Test with {$size}KB data:</h3>";
        echo "<p>Time to set {$iterations} transients: " . number_format($set_time, 2) . " ms</p>";
        echo "<p>Average time per set: " . number_format($set_time / $iterations, 2) . " ms</p>";

        // Add to CSV data
        $csv_data .= "TRANSIENT_SET," . get_cache_implementation() . ",$size,$iterations," . number_format($set_time, 2) . "," . number_format($set_time / $iterations, 2) . "\n";
    }

    echo "<p>Transient set complete. Please run the transient get test now.</p>";

    output_csv_data($csv_data);
}

function run_transient_get_test() {
    global $TEST_CASES, $KEY_PREFIX;

    echo "<h2>Transient Get Test Results</h2>";

    $csv_data = "Test Type,Cache Implementation,Size (KB),Iterations,Total Time (ms),Average Time (ms),Memory Used (MB),Peak Memory (MB)\n";
    $csv_data .= "TRANSIENT_GET," . get_cache_implementation() . ",,,,,,\n";

    foreach ($TEST_CASES as $case) {
        $size = $case['size'];
        $iterations = $case['iterations'];
        $key_prefix = $KEY_PREFIX . "transient_" . $size . "_";

        // Record initial memory usage
        $initial_memory = memory_get_usage(true);

        // Perform timed transient get operations
        $start_time = microtime(true);
        $retrieved_data = [];
        for ($i = 0; $i < $iterations; $i++) {
            $key = $key_prefix . $i;
            $retrieved_data[$key] = get_transient($key);
        }
        $get_time = (microtime(true) - $start_time) * 1000;

        // Record peak memory usage
        $peak_memory = memory_get_peak_usage(true);

        // Calculate memory usage
        $memory_used = $peak_memory - $initial_memory;

        echo "<h3>Test with {$size}KB data:</h3>";
        echo "<p>Time to get {$iterations} transients: " . number_format($get_time, 2) . " ms</p>";
        echo "<p>Average time per get: " . number_format($get_time / $iterations, 2) . " ms</p>";
        echo "<p>Memory used / Peak: " 
            . number_format($memory_used / 1024 / 1024, 2) . " MB / "
            . number_format($peak_memory / 1024 / 1024, 2) . " MB "
            . "</p>";

        // Add to CSV data
        $csv_data .= "TRANSIENT_GET," . get_cache_implementation() . ",$size,$iterations," . number_format($get_time, 2) . "," . number_format($get_time / $iterations, 2) . ","
            . number_format($memory_used / 1024 / 1024, 2) . "," . number_format($peak_memory / 1024 / 1024, 2) . "\n";

        // Clean up
        for ($i = 0; $i < $iterations; $i++) {
            $key = $key_prefix . $i;
            delete_transient($key);
        }
    }

    echo "<p>Transient get complete.</p>";

    output_csv_data($csv_data);
}

function run_json_decode_test() {
    echo "<h2>wp_json_file_decode() Test Results</h2>";

    $block_json_files = glob(ABSPATH . 'wp-includes/blocks/**/block.json');
    $num_files = count($block_json_files);
    $iterations = 5; // Number of times to loop through all files

    $csv_data = "Test Type,Number of Files,Iterations,Total Files Decoded,Total Time (ms),Time per 100 Files (ms),Average Time per File (ms),Total Size (KB),Memory Used (MB),Peak Memory (MB)\n";

    // Record initial memory usage
    $initial_memory = memory_get_usage(true);

    $start_time = microtime(true);
    $total_size = 0;
    $total_files_decoded = 0;

    for ($i = 0; $i < $iterations; $i++) {
        foreach ($block_json_files as $file) {
            $json_data = wp_json_file_decode($file, ['associative' => true]);
            $total_files_decoded++;
            if ($i == 0) { // Only count size once
                $total_size += filesize($file);
            }
        }
    }

    $total_time = (microtime(true) - $start_time) * 1000;
    $avg_time_per_file = $total_time / $total_files_decoded;
    $time_per_100_files = ($total_time / $total_files_decoded) * 100;

    // Record peak memory usage
    $peak_memory = memory_get_peak_usage(true);

    // Calculate memory usage
    $memory_used = $peak_memory - $initial_memory;

    echo "<p>Number of block.json files: {$num_files}</p>";
    echo "<p>Number of iterations: {$iterations}</p>";
    echo "<p>Total number of files decoded: {$total_files_decoded}</p>";
    echo "<p>Total time to decode all files {$iterations} times: " . number_format($total_time, 2) . " ms</p>";
    echo "<p>Time to decode 100 files: " . number_format($time_per_100_files, 2) . " ms</p>";
    echo "<p>Average time per file: " . number_format($avg_time_per_file, 2) . " ms</p>";
    echo "<p>Total size of all files: " . number_format($total_size / 1024, 2) . " KB</p>";
    echo "<p>Memory used / Peak: " 
        . number_format($memory_used / 1024 / 1024, 2) . " MB / "
        . number_format($peak_memory / 1024 / 1024, 2) . " MB "
        . "</p>";

    // Add to CSV data
    $csv_data .= "JSON_DECODE,$num_files,$iterations,$total_files_decoded," . number_format($total_time, 2) . "," 
        . number_format($time_per_100_files, 2) . "," . number_format($avg_time_per_file, 2) . ","
        . number_format($total_size / 1024, 2) . ","
        . number_format($memory_used / 1024 / 1024, 2) . "," . number_format($peak_memory / 1024 / 1024, 2) . "\n";

    output_csv_data($csv_data);
}

function simplified_get_block_name_from_path_convention($path) {
    $path_parts = explode('/', $path);
    if (count($path_parts) <= 0) {
        return false;
    }
    $last_part = $path_parts[count($path_parts) - 1];
    $breaks_convention = array('premium-content');
    if (in_array($last_part, $breaks_convention, true)) {
        return false;
    }
    return 'jetpack/' . $last_part;
}

function generate_test_paths($count) {
    $paths = array();
    $base_paths = array(
        '/path/to/extensions/blocks/',
        '/var/www/html/wp-content/plugins/jetpack/extensions/blocks/',
        '/home/user/wordpress/wp-content/plugins/jetpack/extensions/blocks/',
    );
    $block_names = array(
        'apple', 'banana', 'cherry', 'date', 'elderberry', 'fig', 'grape', 'honeydew',
        'ice-cream', 'jackfruit', 'kiwi', 'lemon', 'mango', 'nectarine', 'orange', 'papaya',
        'quince', 'raspberry', 'strawberry', 'tangerine', 'ugli-fruit', 'vanilla', 'watermelon',
        'xigua', 'yuzu', 'zucchini'
    );
    for ($i = 0; $i < $count; $i++) {
        $base_path = $base_paths[array_rand($base_paths)];
        $block_name = $block_names[array_rand($block_names)];
        $paths[] = $base_path . $block_name;
    }
    return $paths;
}

function run_block_name_performance_test() {
    echo "<h2>Jetpack Block Name Performance Test</h2>";

    $num_paths = 1000;
    $test_paths = generate_test_paths($num_paths);

    $start_time = microtime(true);
    $results = array();

    foreach ($test_paths as $path) {
        $results[] = simplified_get_block_name_from_path_convention($path);
    }

    $total_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds
    $avg_time_per_path = $total_time / $num_paths;
    $time_per_100_paths = $avg_time_per_path * 100;

    echo "<p>Total paths processed: {$num_paths}</p>";
    echo "<p>Total time: " . number_format($total_time, 2) . " ms</p>";
    echo "<p>Average time per path: " . number_format($avg_time_per_path, 4) . " ms</p>";
    echo "<p>Time to process 100 paths: " . number_format($time_per_100_paths, 2) . " ms</p>";

    $csv_data = "Test Type,Total Paths,Total Time (ms),Avg Time per Path (ms),Time per 100 Paths (ms)\n";
    $csv_data .= "BLOCK_NAME_CONVERSION,{$num_paths}," . number_format($total_time, 2) . "," 
               . number_format($avg_time_per_path, 4) . "," . number_format($time_per_100_paths, 2) . "\n";

    output_csv_data($csv_data);

    // Display a few sample results
    echo "<h3>Sample Results:</h3>";
    echo "<pre>";
    for ($i = 0; $i < 5; $i++) {
        $index = array_rand($test_paths);
        echo "Path: " . $test_paths[$index] . "\n";
        echo "Result: " . $results[$index] . "\n\n";
    }
    echo "</pre>";
}
