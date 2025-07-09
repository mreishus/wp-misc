<?php
/**
 * Plugin Name: Alloptions Ping-Pong Test
 * Description: Test plugin to demonstrate and measure the alloptions ping-pong effect when repeatedly updating options
 * Version: 1.0.0
 * Author: Test Author
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Alloptions_PingPong_Test {
    
    private $test_results = [];
    private $start_memory;
    private $start_time;
    
    public function __construct() {
        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Add test scenarios that run on init (like the problematic plugins)
        add_action('admin_init', [$this, 'maybe_run_init_tests']);
        
        // Add admin notice
        add_action('admin_notices', [$this, 'show_test_notice']);
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Alloptions Ping-Pong Test',
            'Alloptions Test',
            'manage_options',
            'alloptions-pingpong-test',
            [$this, 'render_admin_page'],
            'dashicons-update',
            99
        );
    }
    
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>Alloptions Ping-Pong Test</h1>
            
            <div class="notice notice-info">
                <p><strong>Current alloptions size:</strong> <?php echo $this->get_alloptions_size(); ?></p>
            </div>
            
            <h2>Test Scenarios</h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('alloptions_test', 'alloptions_test_nonce'); ?>
                
                <h3>1. Role/Capability Tests (Simulating Plugin Behavior)</h3>
                <p>These tests simulate what Solid Affiliate, SEOPress, and PixelYourSite do on every page load.</p>
                
                <p>
                    <input type="submit" name="test_add_cap_same" class="button button-primary" 
                           value="Test: Add Same Capability Repeatedly (10x)" />
                    <br><small>Adds the same capability to administrator role 10 times</small>
                </p>
                
                <p>
                    <input type="submit" name="test_remove_cap_nonexistent" class="button button-primary" 
                           value="Test: Remove Non-existent Capability (10x)" />
                    <br><small>Attempts to remove a capability that doesn't exist</small>
                </p>
                
                <p>
                    <input type="submit" name="test_add_existing_role" class="button button-primary" 
                           value="Test: Add Existing Role (10x)" />
                    <br><small>Attempts to add a role that already exists</small>
                </p>
                
                <h3>2. Option Update Tests</h3>
                <p>These tests demonstrate the behavior of update_option with unchanged values.</p>
                
                <p>
                    <input type="submit" name="test_update_same_option" class="button button-primary" 
                           value="Test: Update Option with Same Value (10x)" />
                    <br><small>Updates an autoloaded option with the exact same value</small>
                </p>
                
                <p>
                    <input type="submit" name="test_update_different_option" class="button button-primary" 
                           value="Test: Update Option with Different Value (10x)" />
                    <br><small>Updates an autoloaded option with different values</small>
                </p>
                
                <h3>3. Enable/Disable Init Hook Test</h3>
                <p>
                    <input type="checkbox" name="enable_init_test" value="1" 
                           <?php checked(get_option('alloptions_test_init_enabled'), '1'); ?> />
                    <label for="enable_init_test">
                        Enable init/admin_init hook test (adds capability on every admin page load)
                    </label>
                    <input type="submit" name="save_init_setting" class="button" value="Save Setting" />
                </p>
                
                <h3>4. Cache Analysis</h3>
                <p>
                    <input type="submit" name="analyze_cache_calls" class="button button-secondary" 
                           value="Analyze Cache Calls for This Page Load" />
                </p>
            </form>
            
            <?php if (!empty($this->test_results)): ?>
                <h2>Test Results</h2>
                <div style="background: #f1f1f1; padding: 20px; margin-top: 20px;">
                    <pre><?php echo esc_html(print_r($this->test_results, true)); ?></pre>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_POST['analyze_cache_calls'])): ?>
                <h2>Cache Call Analysis</h2>
                <div style="background: #f1f1f1; padding: 20px; margin-top: 20px;">
                    <?php $this->analyze_cache_calls(); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function maybe_run_init_tests() {
        // Only run if enabled and we're in admin
        if (!is_admin() || get_option('alloptions_test_init_enabled') !== '1') {
            return;
        }
        
        // Simulate what problematic plugins do
        $this->track_operation_start('init_hook_capability_add');
        
        $role = get_role('administrator');
        if ($role) {
            // This should trigger alloptions ping-pong even if capability already exists
            $role->add_cap('alloptions_test_cap');
        }
        
        $this->track_operation_end('init_hook_capability_add');
    }
    
    public function show_test_notice() {
        if (get_option('alloptions_test_init_enabled') === '1') {
            ?>
            <div class="notice notice-warning">
                <p><strong>Alloptions Test:</strong> Init hook test is enabled. 
                   Check your Query Monitor or debug logs to see the ping-pong effect on every admin page load.</p>
            </div>
            <?php
        }
    }
    
    public function process_tests() {
        if (!isset($_POST['alloptions_test_nonce']) || 
            !wp_verify_nonce($_POST['alloptions_test_nonce'], 'alloptions_test')) {
            return;
        }
        
        // Save init setting
        if (isset($_POST['save_init_setting'])) {
            $enabled = isset($_POST['enable_init_test']) ? '1' : '0';
            update_option('alloptions_test_init_enabled', $enabled);
            $this->test_results['init_setting'] = $enabled === '1' ? 'Enabled' : 'Disabled';
            return;
        }
        
        // Run various tests
        if (isset($_POST['test_add_cap_same'])) {
            $this->test_add_same_capability();
        } elseif (isset($_POST['test_remove_cap_nonexistent'])) {
            $this->test_remove_nonexistent_capability();
        } elseif (isset($_POST['test_add_existing_role'])) {
            $this->test_add_existing_role();
        } elseif (isset($_POST['test_update_same_option'])) {
            $this->test_update_same_option();
        } elseif (isset($_POST['test_update_different_option'])) {
            $this->test_update_different_option();
        }
    }
    
    private function test_add_same_capability() {
        $this->test_results = ['test' => 'Add Same Capability 10x'];
        $role = get_role('administrator');
        
        for ($i = 1; $i <= 10; $i++) {
            $this->track_operation_start("add_cap_iteration_$i");
            
            // This should trigger ping-pong even though capability already exists
            $role->add_cap('alloptions_test_capability');
            
            $this->track_operation_end("add_cap_iteration_$i");
        }
    }
    
    private function test_remove_nonexistent_capability() {
        $this->test_results = ['test' => 'Remove Non-existent Capability 10x'];
        $role = get_role('administrator');
        
        for ($i = 1; $i <= 10; $i++) {
            $this->track_operation_start("remove_cap_iteration_$i");
            
            // This should trigger ping-pong even though capability doesn't exist
            $role->remove_cap('alloptions_nonexistent_cap_xyz');
            
            $this->track_operation_end("remove_cap_iteration_$i");
        }
    }
    
    private function test_add_existing_role() {
        $this->test_results = ['test' => 'Add Existing Role 10x'];
        
        // First ensure our test role exists
        add_role('alloptions_test_role', 'Test Role', ['read' => true]);
        
        for ($i = 1; $i <= 10; $i++) {
            $this->track_operation_start("add_role_iteration_$i");
            
            // This should trigger ping-pong even though role already exists
            add_role('alloptions_test_role', 'Test Role', ['read' => true]);
            
            $this->track_operation_end("add_role_iteration_$i");
        }
    }
    
    private function test_update_same_option() {
        $this->test_results = ['test' => 'Update Option with Same Value 10x'];
        
        // Set initial value, and turn autoload on
        update_option('alloptions_test_option', 'test_value_unchanged', true);
        
        for ($i = 1; $i <= 10; $i++) {
            $this->track_operation_start("update_same_iteration_$i");
            
            // Update with exact same value
            update_option('alloptions_test_option', 'test_value_unchanged');
            
            $this->track_operation_end("update_same_iteration_$i");
        }
    }
    
    private function test_update_different_option() {
        $this->test_results = ['test' => 'Update Option with Different Value 10x'];
	
        // Set initial value, and turn autoload on
        update_option('alloptions_test_option', 'test_value_unchanged', true);
        
        for ($i = 1; $i <= 10; $i++) {
            $this->track_operation_start("update_different_iteration_$i");
            
            // Update with different value each time
            update_option('alloptions_test_option', 'test_value_' . $i);
            
            $this->track_operation_end("update_different_iteration_$i");
        }
    }
    
    private function track_operation_start($operation) {
        $this->start_memory = memory_get_usage();
        $this->start_time = microtime(true);
    }
    
    private function track_operation_end($operation) {
        $end_time = microtime(true);
        $end_memory = memory_get_usage();
        
        $this->test_results['operations'][$operation] = [
            'time_ms' => round(($end_time - $this->start_time) * 1000, 2),
            'memory_change' => number_format($end_memory - $this->start_memory),
            'cache_size' => $this->get_cache_size_estimate()
        ];
    }
    
    private function get_alloptions_size() {
        global $wpdb;
        $alloptions = wp_load_alloptions();
        $serialized = serialize($alloptions);
        return $this->format_bytes(strlen($serialized));
    }
    
    private function get_cache_size_estimate() {
        // This is a rough estimate - actual implementation depends on your cache backend
        if (function_exists('wp_cache_get')) {
            $alloptions = wp_cache_get('alloptions', 'options');
            if ($alloptions !== false) {
                return $this->format_bytes(strlen(serialize($alloptions)));
            }
        }
        return 'N/A';
    }
    
    private function analyze_cache_calls() {
        global $wp_object_cache;
        
        echo "<h3>Cache Statistics</h3>";
        
        if (method_exists($wp_object_cache, 'stats')) {
            echo "<pre>";
            $wp_object_cache->stats();
            echo "</pre>";
        } else {
            echo "<p>Cache statistics not available. Consider using Query Monitor plugin for detailed analysis.</p>";
        }
        
        // Show current cache groups
        if (property_exists($wp_object_cache, 'cache')) {
            echo "<h3>Cache Groups</h3>";
            echo "<ul>";
            foreach (array_keys($wp_object_cache->cache) as $group) {
                echo "<li>$group</li>";
            }
            echo "</ul>";
        }
    }
    
    private function format_bytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    new Alloptions_PingPong_Test();
});

// Handle form submissions early
add_action('admin_init', function() {
    if (isset($_POST['alloptions_test_nonce'])) {
        $test = new Alloptions_PingPong_Test();
        $test->process_tests();
    }
}, 5); // Run before other admin_init hooks
