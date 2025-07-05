<?php
/**
 * Plugin Update Checker Implementation
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
    $this->gitlab_project_id = '39'; // Use numeric ID instead of path
    $this->gitlab_url = 'https://git.open-regels.nl';
    $this->cache_key = 'operaton_dmn_updater';
    $this->cache_allowed = true;
    
    add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_transient'));
    add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
    add_filter('upgrader_pre_download', array($this, 'download_package'), 10, 3);
}    
/**
 * Get information regarding our plugin from GitLab
 */
public function request() {
    $remote_get = get_transient($this->cache_key);
    
    if ($this->cache_allowed && $remote_get !== false) {
        return $remote_get;
    }
    
    // Use the releases endpoint instead of latest (which seems to have issues)
    $request = wp_remote_get(
        $this->gitlab_url . '/api/v4/projects/' . $this->gitlab_project_id . '/releases',
        array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json'
            )
        )
    );
    
    if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
        $releases = json_decode(wp_remote_retrieve_body($request), true);
        
        // Get the most recent release (first in the array)
        if (!empty($releases) && is_array($releases)) {
            $remote_get = $releases[0]; // Most recent release
        } else {
            $remote_get = false;
        }
    } else {
        $remote_get = false;
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
                // Look for zip file
                if (strpos($link['name'], '.zip') !== false || strpos($link['url'], '.zip') !== false) {
                    return $link['url'];
                }
            }
        }
        
        // Fallback to source archive
        $tag = $remote_version['tag_name'];
        return $this->gitlab_url . '/' . $this->gitlab_project_id . '/-/archive/' . $tag . '/' . basename($this->gitlab_project_id) . '-' . $tag . '.zip';
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
 * Download package and handle authentication if needed
 */
public function download_package($result, $package, $upgrader) {
    if (strpos($package, $this->gitlab_url) !== false) {
        // This is our plugin package - we need to handle the GitLab source archive structure
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Custom download handler - Original package: ' . $package);
        }
        
        // Download the GitLab source archive
        $temp_file = download_url($package, 300);
        
        if (is_wp_error($temp_file)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN: Download failed: ' . $temp_file->get_error_message());
            }
            return $temp_file;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Downloaded to temp file: ' . $temp_file);
        }
        
        // Create a properly structured ZIP for WordPress
        $proper_zip = $this->restructure_gitlab_zip($temp_file);
        
        // Clean up original
        @unlink($temp_file);
        
        if (is_wp_error($proper_zip)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN: Restructure failed: ' . $proper_zip->get_error_message());
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN: Restructured ZIP created: ' . $proper_zip);
            }
        }
        
        return $proper_zip;
    }
    
    return $result;
}

/**
 * Restructure GitLab source archive to WordPress plugin format
 */
private function restructure_gitlab_zip($source_zip) {
    require_once(ABSPATH . 'wp-admin/includes/class-pclzip.php');
    
    $temp_dir = sys_get_temp_dir() . '/operaton-dmn-' . uniqid();
    $output_zip = sys_get_temp_dir() . '/operaton-dmn-final-' . uniqid() . '.zip';
    
    try {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Starting ZIP restructure - temp dir: ' . $temp_dir);
        }
        
        // Create temp directory
        if (!wp_mkdir_p($temp_dir)) {
            return new WP_Error('mkdir_failed', 'Failed to create temp directory');
        }
        
        // Extract source ZIP
        $zip = new PclZip($source_zip);
        $result = $zip->extract(PCLZIP_OPT_PATH, $temp_dir);
        
        if (!$result) {
            return new WP_Error('extract_failed', 'Failed to extract source archive: ' . $zip->errorInfo(true));
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: ZIP extracted, looking for folders in: ' . $temp_dir);
        }
        
        // Find the extracted folder (GitLab creates: project-name-tag/)
        $extracted_folders = glob($temp_dir . '/*', GLOB_ONLYDIR);
        if (empty($extracted_folders)) {
            return new WP_Error('no_folder', 'No extracted folder found');
        }
        
        $source_folder = $extracted_folders[0];
        $plugin_folder = $temp_dir . '/operaton-dmn-evaluator';
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Found source folder: ' . $source_folder);
            error_log('Operaton DMN: Renaming to: ' . $plugin_folder);
        }
        
        // Rename to proper WordPress plugin folder name
        if (!rename($source_folder, $plugin_folder)) {
            return new WP_Error('rename_failed', 'Failed to rename extracted folder');
        }
        
        // Create new ZIP with proper structure
        $new_zip = new PclZip($output_zip);
        $result = $new_zip->create($plugin_folder, PCLZIP_OPT_REMOVE_PATH, dirname($plugin_folder));
        
        if (!$result) {
            return new WP_Error('zip_failed', 'Failed to create proper ZIP structure: ' . $new_zip->errorInfo(true));
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: New ZIP created successfully: ' . $output_zip);
        }
        
        // Clean up temp directory
        $this->delete_directory($temp_dir);
        
        return $output_zip;
        
    } catch (Exception $e) {
        // Clean up on error
        if (is_dir($temp_dir)) {
            $this->delete_directory($temp_dir);
        }
        @unlink($output_zip);
        return new WP_Error('restructure_failed', $e->getMessage());
    }
}

/**
 * Recursively delete directory
 */
private function delete_directory($dir) {
    if (!is_dir($dir)) return;
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? $this->delete_directory($path) : unlink($path);
    }
    rmdir($dir);
}    
    /**
     * Get GitLab access token (optional, for private repos)
     */
    private function get_gitlab_token() {
        // Return empty string for public repos
        // For private repos, you could store the token in wp-config.php:
        // define('OPERATON_DMN_GITLAB_TOKEN', 'your-token-here');
        return defined('OPERATON_DMN_GITLAB_TOKEN') ? OPERATON_DMN_GITLAB_TOKEN : '';
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
            $api_url = $this->repository_url . '/api/v4/projects/showcases%2Foperaton-dmn-evaluator/releases/latest';
            
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
                    $remote_version = ltrim($data['tag_name'], 'v');
                    
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