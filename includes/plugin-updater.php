<?php
/**
 * Plugin Update Checker Implementation - SAFE VERSION
 * File: includes/plugin-updater.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * GitLab-based Auto-Update System for Operaton DMN Evaluator
 */
class OperatonDMNAutoUpdater {
    
    private $plugin_file;
    private $plugin_slug;
    private $version;
    private $gitlab_project_id;
    private $gitlab_url;
    private $cache_key;
    private $cache_allowed;
    
    public function __construct($plugin_file, $version) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->version = $version;
        $this->gitlab_project_id = '39';
        $this->gitlab_url = 'https://git.open-regels.nl';
        $this->cache_key = 'operaton_dmn_updater';
        $this->cache_allowed = true;
        
        add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_transient'));
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
        add_filter('upgrader_pre_download', array($this, 'download_package'), 10, 3);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Auto-Updater initialized for: ' . $this->plugin_slug);
        }
    }
    
    /**
     * Get information regarding our plugin from GitLab
     */
    public function request() {
        $remote_get = get_transient($this->cache_key);
        
        if ($this->cache_allowed && $remote_get !== false) {
            return $remote_get;
        }
        
        $request = wp_remote_get(
            $this->gitlab_url . '/api/v4/projects/' . $this->gitlab_project_id . '/releases',
            array(
                'timeout' => 15,
                'headers' => array(
                    'Accept' => 'application/json',
                    'User-Agent' => 'WordPress-Plugin-Updater/1.0'
                )
            )
        );
        
        if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
            $releases = json_decode(wp_remote_retrieve_body($request), true);
            
            if (!empty($releases) && is_array($releases)) {
                $remote_get = $releases[0];
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Operaton DMN: Latest release found: ' . $remote_get['tag_name']);
                }
            } else {
                $remote_get = false;
            }
        } else {
            $remote_get = false;
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $error = is_wp_error($request) ? $request->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($request);
                error_log('Operaton DMN: Failed to get releases: ' . $error);
            }
        }
        
        if ($this->cache_allowed) {
            set_transient($this->cache_key, $remote_get, 6 * HOUR_IN_SECONDS);
        }
        
        return $remote_get;
    }
    
    /**
     * Modify the plugin update transient
     */
    public function modify_transient($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $remote_version = $this->request();
        
        if ($remote_version && isset($remote_version['tag_name'])) {
            $new_version = ltrim($remote_version['tag_name'], 'v');
            
            if (version_compare($this->version, $new_version, '<')) {
                $transient->response[$this->plugin_slug] = (object) array(
                    'slug' => dirname($this->plugin_slug),
                    'plugin' => $this->plugin_slug,
                    'new_version' => $new_version,
                    'tested' => get_bloginfo('version'),
                    'package' => $this->get_download_url($remote_version),
                    'url' => $this->gitlab_url . '/' . $this->gitlab_project_id,
                );
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Operaton DMN: Update available - ' . $this->version . ' -> ' . $new_version);
                }
            }
        }
        
        return $transient;
    }
    
    /**
     * Add our plugin to the plugin information popup
     */
    public function plugin_popup($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        
        if (!empty($args->slug)) {
            if ($args->slug == dirname($this->plugin_slug)) {
                $remote_version = $this->request();
                
                if ($remote_version) {
                    $result = (object) array(
                        'name' => 'Operaton DMN Evaluator',
                        'slug' => dirname($this->plugin_slug),
                        'version' => ltrim($remote_version['tag_name'], 'v'),
                        'tested' => get_bloginfo('version'),
                        'requires' => '5.0',
                        'author' => 'Steven Gort',
                        'author_profile' => $this->gitlab_url . '/' . $this->gitlab_project_id,
                        'donate_link' => '',
                        'homepage' => $this->gitlab_url . '/' . $this->gitlab_project_id,
                        'download_link' => $this->get_download_url($remote_version),
                        'trunk' => $this->get_download_url($remote_version),
                        'requires_php' => '7.4',
                        'last_updated' => $remote_version['released_at'],
                        'sections' => array(
                            'description' => 'WordPress plugin to integrate Gravity Forms with Operaton DMN decision tables for dynamic form evaluations.',
                            'installation' => 'Upload the plugin files to `/wp-content/plugins/operaton-dmn-evaluator/` directory, or install through WordPress admin.',
                            'changelog' => $this->get_changelog($remote_version),
                        ),
                        'banners' => array(),
                        'icons' => array(),
                    );
                }
                
                return $result;
            }
        }
        
        return $result;
    }
    
    /**
     * Return the download URL for the latest release
     */
    private function get_download_url($remote_version) {
        // Check if there are any assets (uploaded files)
        if (isset($remote_version['assets']['links']) && !empty($remote_version['assets']['links'])) {
            foreach ($remote_version['assets']['links'] as $link) {
                if (strpos($link['name'], '.zip') !== false || strpos($link['url'], '.zip') !== false) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Operaton DMN: Using asset download URL: ' . $link['url']);
                    }
                    return $link['url'];
                }
            }
        }
        
        // Fallback to source archive
        $tag = $remote_version['tag_name'];
        $download_url = $this->gitlab_url . '/api/v4/projects/' . $this->gitlab_project_id . '/repository/archive.zip?sha=' . $tag;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Using API archive URL: ' . $download_url);
        }
        
        return $download_url;
    }
    
    /**
     * Get changelog from release description
     */
    private function get_changelog($remote_version) {
        if (isset($remote_version['description']) && !empty($remote_version['description'])) {
            return '<h4>Version ' . ltrim($remote_version['tag_name'], 'v') . '</h4>' . 
                   '<p>' . nl2br(esc_html($remote_version['description'])) . '</p>';
        }
        
        return '<h4>Version ' . ltrim($remote_version['tag_name'], 'v') . '</h4>' . 
               '<p>See the <a href="' . $this->gitlab_url . '/' . $this->gitlab_project_id . '/-/releases" target="_blank">release page</a> for details.</p>';
    }
    
    /**
     * Download package - SIMPLIFIED VERSION
     */
    public function download_package($result, $package, $upgrader) {
        // Only handle our GitLab packages
        if (strpos($package, $this->gitlab_url) === false) {
            return $result;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Using simplified download for: ' . $package);
        }
        
        // Let WordPress handle the download normally
        // The GitLab API provides a properly formatted ZIP that WordPress can handle
        return $result;
    }
    
    /**
     * Clear update cache
     */
    public function clear_cache() {
        delete_transient($this->cache_key);
    }
}

/**
 * Fallback update notification system
 */
class OperatonDMNUpdateNotifier {
    
    private $plugin_slug;
    private $version;
    private $repository_url;
    
    public function __construct() {
        $this->plugin_slug = 'operaton-dmn-evaluator';
        $this->version = OPERATON_DMN_VERSION;
        $this->repository_url = 'https://git.open-regels.nl/showcases/operaton-dmn-evaluator';
        
        // Show manual update notice if auto-update fails
        add_action('admin_notices', array($this, 'show_fallback_notice'));
    }
    
    /**
     * Show fallback update notice
     */
    public function show_fallback_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Only show if the main auto-updater hasn't shown an update
        $update_plugins = get_site_transient('update_plugins');
        
        if (isset($update_plugins->response) && !empty($update_plugins->response)) {
            foreach ($update_plugins->response as $plugin => $data) {
                if (strpos($plugin, 'operaton-dmn') !== false) {
                    // Auto-update system is working, don't show fallback
                    return;
                }
            }
        }
        
        // Check for updates manually as fallback
        $transient_key = 'operaton_dmn_fallback_check';
        $last_check = get_transient($transient_key);
        
        if ($last_check === false) {
            $api_url = 'https://git.open-regels.nl/api/v4/projects/39/releases';
            
            $response = wp_remote_get($api_url, array(
                'timeout' => 10,
                'headers' => array(
                    'User-Agent' => 'Operaton-DMN-Plugin/' . $this->version,
                    'Accept' => 'application/json'
                )
            ));
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                $releases = json_decode($body, true);
                
                if (!empty($releases) && isset($releases[0]['tag_name'])) {
                    $remote_version = ltrim($releases[0]['tag_name'], 'v');
                    
                    if (version_compare($this->version, $remote_version, '<')) {
                        echo '<div class="notice notice-warning is-dismissible">';
                        echo '<p><strong>Operaton DMN Evaluator:</strong> ';
                        echo sprintf(
                            __('Version %s is available. <a href="%s" target="_blank">Download manually</a> or check the plugins page for automatic updates.', 'operaton-dmn'),
                            $remote_version,
                            $this->repository_url . '/-/releases/latest'
                        );
                        echo '</p></div>';
                    }
                }
            }
            
            // Cache the check for 12 hours
            set_transient($transient_key, time(), 12 * HOUR_IN_SECONDS);
        }
    }
}

// Initialize fallback notifier
new OperatonDMNUpdateNotifier();