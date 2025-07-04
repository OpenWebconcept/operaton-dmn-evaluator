<?php
/**
 * Plugin Update Checker Implementation
 * File: includes/plugin-updater.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Only initialize updater in admin or during cron jobs
if (is_admin() || wp_doing_cron()) {
    
    // Check if the update checker library exists
    $update_checker_path = OPERATON_DMN_PLUGIN_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';
    
    if (file_exists($update_checker_path)) {
        
        // Include the update checker library
        require_once $update_checker_path;
        
        // Check if the class exists before using it
        if (class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
            
            try {
                // For GitLab, we need to be more specific about the update source
                // Since you're using releases, let's disable the auto-updater for now
                // and just implement update notifications
                
                // Initialize with a simpler approach - just notifications
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Operaton DMN: Plugin Update Checker library loaded successfully');
                }
                
                // Add admin notice for manual updates instead of automatic checking
                add_action('admin_notices', function() {
                    if (current_user_can('manage_options')) {
                        $current_version = OPERATON_DMN_VERSION;
                        
                        // Simple version check against GitLab API
                        $transient_key = 'operaton_dmn_version_check';
                        $cached_check = get_transient($transient_key);
                        
                        if ($cached_check === false) {
                            $api_url = 'https://git.open-regels.nl/api/v4/projects/showcases%2Foperaton-dmn-evaluator/releases/latest';
                            
                            $response = wp_remote_get($api_url, array(
                                'timeout' => 10,
                                'headers' => array(
                                    'User-Agent' => 'Operaton-DMN-Plugin/' . $current_version
                                )
                            ));
                            
                            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                                $body = wp_remote_retrieve_body($response);
                                $data = json_decode($body, true);
                                
                                if (isset($data['tag_name'])) {
                                    $remote_version = ltrim($data['tag_name'], 'v');
                                    
                                    if (version_compare($current_version, $remote_version, '<')) {
                                        echo '<div class="notice notice-info is-dismissible">';
                                        echo '<p><strong>Operaton DMN Evaluator:</strong> ';
                                        echo sprintf(
                                            __('Version %s is available. <a href="%s" target="_blank">Download from GitLab</a>', 'operaton-dmn'),
                                            $remote_version,
                                            'https://git.open-regels.nl/showcases/operaton-dmn-evaluator/-/releases'
                                        );
                                        echo '</p></div>';
                                    }
                                    
                                    // Cache the check for 6 hours
                                    set_transient($transient_key, $remote_version, 6 * HOUR_IN_SECONDS);
                                }
                            } else {
                                // Cache failed check for 1 hour
                                set_transient($transient_key, 'failed', HOUR_IN_SECONDS);
                            }
                        }
                    }
                });
                
            } catch (Exception $e) {
                // Log error if update checker fails to initialize
                error_log('Operaton DMN: Update checker failed to initialize: ' . $e->getMessage());
            }
            
        } else {
            // Log error if class doesn't exist
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN: PucFactory class not found after including update checker library');
            }
        }
        
    } else {
        // Log warning if update checker library is missing
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Update checker library not found at: ' . $update_checker_path);
        }
        
        // Fallback: Show admin notice about missing update library
        add_action('admin_notices', function() {
            if (current_user_can('manage_options')) {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>Operaton DMN Evaluator:</strong> Auto-update system is not available. Please update manually from the repository.</p>';
                echo '</div>';
            }
        });
    }
}

/**
 * Simple update notifier that works with GitLab
 */
class OperatonDMNUpdateNotifier {
    
    private $plugin_slug;
    private $plugin_file;
    private $version;
    private $repository_url;
    
    public function __construct() {
        $this->plugin_slug = 'operaton-dmn-evaluator';
        $this->plugin_file = OPERATON_DMN_PLUGIN_PATH . 'operaton-dmn-plugin.php';
        $this->version = OPERATON_DMN_VERSION;
        $this->repository_url = 'https://git.open-regels.nl/showcases/operaton-dmn-evaluator';
        
        // Only run in admin
        if (is_admin()) {
            add_action('admin_init', array($this, 'check_for_update_notification'));
        }
    }
    
    /**
     * Check for updates and show notification
     */
    public function check_for_update_notification() {
        // Check once per day
        $last_check = get_transient('operaton_dmn_update_check');
        if ($last_check !== false) {
            return;
        }
        
        $remote_version = $this->get_remote_version();
        
        if ($remote_version && version_compare($this->version, $remote_version, '<')) {
            set_transient('operaton_dmn_update_available', $remote_version, DAY_IN_SECONDS);
            add_action('admin_notices', array($this, 'show_update_notice'));
        }
        
        // Cache the check for 1 day
        set_transient('operaton_dmn_update_check', time(), DAY_IN_SECONDS);
    }
    
    /**
     * Get remote version from repository
     */
    private function get_remote_version() {
        // Try GitLab API
        $api_url = 'https://git.open-regels.nl/api/v4/projects/showcases%2Foperaton-dmn-evaluator/releases/latest';
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'Operaton-DMN-Plugin/' . $this->version
            )
        ));
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['tag_name'])) {
                return ltrim($data['tag_name'], 'v');
            }
        }
        
        return false;
    }
    
    /**
     * Show update notice
     */
    public function show_update_notice() {
        $new_version = get_transient('operaton_dmn_update_available');
        
        if (!$new_version) {
            return;
        }
        
        $message = sprintf(
            __('A new version (%s) of Operaton DMN Evaluator is available. <a href="%s" target="_blank">Download from repository</a>.', 'operaton-dmn'),
            $new_version,
            $this->repository_url . '/-/releases'
        );
        
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>Operaton DMN Evaluator:</strong> ' . $message . '</p>';
        echo '</div>';
    }
}

// For now, let's just use the simple notifier instead of the complex auto-updater
new OperatonDMNUpdateNotifier();