<?php
/**
 * Plugin Update Checker Implementation - VERSION 11.5
 * COMPLETE WORDPRESS OVERRIDE STRATEGY - CLEAN BUILD
 * File: includes/plugin-updater.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * GitLab-based Auto-Update System for Operaton DMN Evaluator - V11.5
 * NUCLEAR OPTION: Override ALL WordPress extraction methods
 */
class OperatonDMNAutoUpdater {
    
    private $plugin_file;
    private $plugin_slug;
    private $version;
    private $gitlab_project_id;
    private $gitlab_url;
    private $cache_key;
    private $cache_allowed;
    private $is_our_update;
    private $clean_extraction_path;
    
    public function __construct($plugin_file, $version) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->version = $version;
        $this->gitlab_project_id = '39';
        $this->gitlab_url = 'https://git.open-regels.nl';
        $this->cache_key = 'operaton_dmn_updater';
        $this->cache_allowed = true;
        $this->is_our_update = false;
        $this->clean_extraction_path = null;
        
        add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_transient'));
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
        add_filter('upgrader_pre_download', array($this, 'download_package'), 10, 3);
        
        // V11.5 DISCOVERY STRATEGY: Hook into the move operation after extraction
        add_filter('upgrader_unpack_package', array($this, 'force_our_extraction'), 1, 4);
        add_filter('unzip_file', array($this, 'intercept_unzip'), 1, 4);
        
        // V11.5: Hook into the move operation (this is where the real naming happens!)
        add_filter('upgrader_install_package_result', array($this, 'fix_install_package_result'), 1, 2);
        add_action('upgrader_process_complete', array($this, 'verify_and_fix_extraction'), 10, 2);
        
        // V11.5: Post-extraction directory fix (nuclear fallback)
        add_action('upgrader_process_complete', array($this, 'post_extraction_directory_fix'), 99, 2);
        
        // V11.5: Force our extraction by hijacking the working directory
        add_action('upgrader_start', array($this, 'prepare_forced_extraction'));
        
        error_log('Operaton DMN Auto-Updater V11.5 initialized (NUCLEAR OVERRIDE MODE)');
    }
    
    /**
     * V11.5: Prepare for forced extraction by monitoring WordPress
     */
    public function prepare_forced_extraction($hook_extra) {
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_slug) {
            $this->is_our_update = true;
            error_log('=== OPERATON DMN V11.5 NUCLEAR MODE ACTIVATED ===');
            
            // Store the fact that we're updating
            set_transient('operaton_dmn_v11_nuclear_mode', array(
                'plugin_slug' => $this->plugin_slug,
                'timestamp' => time()
            ), 600);
        }
    }
    
    /**
     * V11.5: Intercept WordPress's unzip_file function
     */
    public function intercept_unzip($result, $file, $to, $needed_dirs) {
        // Only intercept our plugin updates
        $nuclear_mode = get_transient('operaton_dmn_v11_nuclear_mode');
        if (!$nuclear_mode || !$this->is_our_update) {
            return $result; // Let WordPress handle other plugins normally
        }
        
        error_log('=== OPERATON DMN V11.5 INTERCEPT UNZIP ===');
        error_log('Operaton DMN V11.5: Intercepting unzip_file for: ' . $file);
        error_log('Operaton DMN V11.5: Target directory: ' . $to);
        
        // Use our custom extraction instead
        $custom_result = $this->force_clean_extraction($file, $to);
        
        if (is_wp_error($custom_result)) {
            error_log('Operaton DMN V11.5: Custom extraction failed: ' . $custom_result->get_error_message());
            return $result; // Fall back to WordPress
        }
        
        error_log('Operaton DMN V11.5: Custom extraction succeeded!');
        return true; // Tell WordPress extraction was successful
    }
    
    /**
     * V11.5: Force our extraction to override WordPress completely
     */
    public function force_our_extraction($result, $package, $delete_package_after, $hook_extra) {
        // Only handle our plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_slug) {
            return $result;
        }
        
        error_log('=== OPERATON DMN V11.5 FORCE EXTRACTION ===');
        error_log('Operaton DMN V11.5: Taking complete control of extraction process');
        error_log('Operaton DMN V11.5: WordPress wants to extract to: ' . (is_string($result) ? $result : 'unknown'));
        
        // V11.5: COMPLETELY override WordPress extraction
        $clean_extraction_result = $this->v11_5_complete_override($package);
        
        if (is_wp_error($clean_extraction_result)) {
            error_log('Operaton DMN V11.5: Complete override failed: ' . $clean_extraction_result->get_error_message());
            return $result; // Fall back to WordPress
        }
        
        error_log('Operaton DMN V11.5: Complete override succeeded: ' . $clean_extraction_result);
        return $clean_extraction_result; // Return our clean extraction path
    }
    
    /**
     * V11.5: Complete extraction override that forces correct folder naming
     */
    private function v11_5_complete_override($package) {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('no_zip', 'ZipArchive not available for V11.5 override');
        }
        
        // V11.5: Force the correct plugin directory location
        $correct_plugin_dir = WP_PLUGIN_DIR . '/operaton-dmn-evaluator';
        
        error_log('Operaton DMN V11.5: Forcing extraction to correct location: ' . $correct_plugin_dir);
        
        try {
            // Clean up any existing directory (wrong or right name)
            if (is_dir($correct_plugin_dir)) {
                error_log('Operaton DMN V11.5: Removing existing plugin directory');
                $this->delete_directory($correct_plugin_dir);
            }
            
            // Create the correct directory
            if (!wp_mkdir_p($correct_plugin_dir)) {
                return new WP_Error('mkdir_failed', 'V11.5: Failed to create correct plugin directory');
            }
            
            $zip = new ZipArchive();
            if ($zip->open($package) !== TRUE) {
                $this->delete_directory($correct_plugin_dir);
                return new WP_Error('zip_open_failed', 'V11.5: Failed to open package');
            }
            
            error_log('Operaton DMN V11.5: Extracting ' . $zip->numFiles . ' files with complete override');
            
            $files_extracted = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) continue;
                
                $filename = $this->fix_filename_encoding($stat['name']);
                
                // V11.5: Enhanced GitLab folder prefix removal
                if (strpos($filename, 'operaton-dmn-evaluator-v') !== false) {
                    $clean_filename = preg_replace('/^operaton-dmn-evaluator-v[^\/]+\//', '', $filename);
                } elseif (strpos($filename, 'operaton-dmn-evaluator') !== false && strpos($filename, '/') !== false) {
                    $clean_filename = preg_replace('/^operaton-dmn-evaluator[^\/]*\//', '', $filename);
                } else {
                    $clean_filename = $filename;
                }
                
                // V11.5: Skip unwanted files and folders
                if ($this->should_skip_file($clean_filename)) {
                    error_log('Operaton DMN V11.5: Skipping unwanted file: ' . $clean_filename);
                    continue;
                }
                
                // Skip empty filenames
                if (empty($clean_filename) || $clean_filename === '/') {
                    continue;
                }
                
                $extract_path = $correct_plugin_dir . '/' . $clean_filename;
                
                // Create directory structure
                $dir = dirname($extract_path);
                if (!file_exists($dir)) {
                    wp_mkdir_p($dir);
                }
                
                // Extract file content
                if ($stat['size'] > 0) {
                    $content = $zip->getFromIndex($i);
                    if ($content !== false) {
                        file_put_contents($extract_path, $content);
                        $files_extracted++;
                    }
                } else if (substr($clean_filename, -1) === '/') {
                    wp_mkdir_p($extract_path);
                }
            }
            
            $zip->close();
            
            error_log('Operaton DMN V11.5: Successfully extracted ' . $files_extracted . ' files to correct location');
            
            // Verify extraction
            if (!file_exists($correct_plugin_dir . '/operaton-dmn-plugin.php')) {
                return new WP_Error('plugin_file_missing', 'V11.5: Main plugin file not found after extraction');
            }
            
            // Store clean extraction path for post-processing
            $this->clean_extraction_path = $correct_plugin_dir;
            set_transient('operaton_dmn_v11_clean_path', $correct_plugin_dir, 600);
            
            error_log('Operaton DMN V11.5: Complete override extraction successful!');
            return $correct_plugin_dir;
            
        } catch (Exception $e) {
            if (is_dir($correct_plugin_dir)) {
                $this->delete_directory($correct_plugin_dir);
            }
            return new WP_Error('v11_5_override_failed', 'V11.5 complete override failed: ' . $e->getMessage());
        }
    }
    
    /**
     * V11.5: Intercept the install package result to fix directory naming
     * This is where WordPress moves from /upgrade/ to /plugins/
     */
    public function fix_install_package_result($result, $hook_extra) {
        // Only handle our plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_slug) {
            return $result;
        }
        
        error_log('=== OPERATON DMN V11.5 INSTALL PACKAGE RESULT INTERCEPT ===');
        error_log('V11.5: WordPress install result: ' . print_r($result, true));
        
        if (isset($result['destination']) && is_string($result['destination'])) {
            $destination = $result['destination'];
            error_log('V11.5: WordPress wants to install to: ' . $destination);
            
            // Check if destination has wrong GitLab naming
            if (strpos($destination, 'operaton-dmn-evaluator-v') !== false) {
                $correct_destination = WP_PLUGIN_DIR . '/operaton-dmn-evaluator';
                error_log('V11.5: INTERCEPTING! Changing destination from: ' . basename($destination) . ' to: operaton-dmn-evaluator');
                
                // If correct destination exists, remove it first
                if (is_dir($correct_destination)) {
                    $this->delete_directory($correct_destination);
                    error_log('V11.5: Removed existing correct directory');
                }
                
                // Move the wrongly named directory to correct location
                if (is_dir($destination)) {
                    if (rename($destination, $correct_destination)) {
                        error_log('V11.5: ✓ SUCCESSFUL RENAME: ' . basename($destination) . ' → operaton-dmn-evaluator');
                        
                        // Clean up unwanted files
                        $this->cleanup_files_in_directory($correct_destination);
                        error_log('V11.5: Cleaned up unwanted files');
                        
                        // Update the result to reflect correct destination
                        $result['destination'] = $correct_destination;
                        $result['destination_name'] = 'operaton-dmn-evaluator';
                        
                    } else {
                        error_log('V11.5: ✗ RENAME FAILED - will try copy method');
                        
                        // Fallback: copy method
                        wp_mkdir_p($correct_destination);
                        $this->copy_directory_contents($destination, $correct_destination);
                        $this->cleanup_files_in_directory($correct_destination);
                        $this->delete_directory($destination);
                        
                        $result['destination'] = $correct_destination;
                        $result['destination_name'] = 'operaton-dmn-evaluator';
                        error_log('V11.5: ✓ COPY METHOD SUCCESSFUL');
                    }
                } else {
                    error_log('V11.5: ✗ Source directory does not exist: ' . $destination);
                }
            } else {
                error_log('V11.5: Destination already has correct naming: ' . basename($destination));
            }
        } else {
            error_log('V11.5: No destination found in result or result is not array');
        }
        
        error_log('V11.5: Final result destination: ' . (isset($result['destination']) ? $result['destination'] : 'unknown'));
        return $result;
    }
    
    /**
     * V11.5: Custom clean extraction that bypasses all WordPress methods and fixes folder naming
     */
    private function force_clean_extraction($zip_file, $target_dir) {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('no_zip', 'ZipArchive not available');
        }
        
        try {
            // V11.5: Check if target directory has wrong GitLab name and fix it
            $correct_plugin_dir = WP_PLUGIN_DIR . '/operaton-dmn-evaluator';
            $current_dir = $target_dir;
            
            // If WordPress created a directory with GitLab naming, we need to work around it
            if (strpos($target_dir, 'operaton-dmn-evaluator-v') !== false && $target_dir !== $correct_plugin_dir) {
                error_log('V11.5: Detected WordPress created wrong folder: ' . $target_dir);
                error_log('V11.5: Will extract to correct location: ' . $correct_plugin_dir);
                
                // Remove the wrong directory if it exists
                if (is_dir($correct_plugin_dir)) {
                    $this->clean_directory_contents($correct_plugin_dir);
                } else {
                    wp_mkdir_p($correct_plugin_dir);
                }
                
                // Set target to correct directory
                $target_dir = $correct_plugin_dir;
            } else {
                // Remove any existing files in target directory first
                if (is_dir($target_dir)) {
                    $this->clean_directory_contents($target_dir);
                } else {
                    wp_mkdir_p($target_dir);
                }
            }
            
            $zip = new ZipArchive();
            if ($zip->open($zip_file) !== TRUE) {
                return new WP_Error('zip_open_failed', 'Failed to open ZIP for clean extraction');
            }
            
            error_log('Operaton DMN V11.5: Clean extracting ' . $zip->numFiles . ' files to: ' . $target_dir);
            
            $files_extracted = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) continue;
                
                $filename = $this->fix_filename_encoding($stat['name']);
                
                // V11.5: Enhanced GitLab folder prefix removal
                if (strpos($filename, 'operaton-dmn-evaluator-v') !== false) {
                    $clean_filename = preg_replace('/^operaton-dmn-evaluator-v[^\/]+\//', '', $filename);
                } elseif (strpos($filename, 'operaton-dmn-evaluator') !== false && strpos($filename, '/') !== false) {
                    $clean_filename = preg_replace('/^operaton-dmn-evaluator[^\/]*\//', '', $filename);
                } else {
                    $clean_filename = $filename;
                }
                
                // V11.5: Skip unwanted files and folders
                if ($this->should_skip_file($clean_filename)) {
                    continue;
                }
                
                // Skip empty filenames
                if (empty($clean_filename) || $clean_filename === '/') {
                    continue;
                }
                
                $extract_path = $target_dir . '/' . $clean_filename;
                
                // Create directory structure
                $dir = dirname($extract_path);
                if (!file_exists($dir)) {
                    wp_mkdir_p($dir);
                }
                
                // Extract file content
                if ($stat['size'] > 0) {
                    $content = $zip->getFromIndex($i);
                    if ($content !== false) {
                        file_put_contents($extract_path, $content);
                        $files_extracted++;
                    }
                } else if (substr($clean_filename, -1) === '/') {
                    wp_mkdir_p($extract_path);
                }
            }
            
            $zip->close();
            
            error_log('Operaton DMN V11.5: Successfully extracted ' . $files_extracted . ' files');
            
            // V11.5: If we had to fix the directory location, clean up the wrong one
            if ($current_dir !== $target_dir && is_dir($current_dir)) {
                error_log('V11.5: Cleaning up wrong directory: ' . $current_dir);
                $this->delete_directory($current_dir);
            }
            
            // Verify extraction
            if (!file_exists($target_dir . '/operaton-dmn-plugin.php')) {
                return new WP_Error('plugin_file_missing', 'Main plugin file not found after clean extraction');
            }
            
            error_log('V11.5: Extraction completed successfully to: ' . $target_dir);
            return true;
            
        } catch (Exception $e) {
            return new WP_Error('clean_extraction_failed', 'Clean extraction failed: ' . $e->getMessage());
        }
    }
    
    /**
     * V11.5: Verify extraction and fix any corruption + cleanup wrong directories
     */
    public function verify_and_fix_extraction($upgrader, $hook_extra) {
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_slug) {
            return;
        }
        
        $nuclear_mode = get_transient('operaton_dmn_v11_nuclear_mode');
        if (!$nuclear_mode) {
            return;
        }
        
        error_log('=== OPERATON DMN V11.5 POST-EXTRACTION VERIFICATION ===');
        
        $correct_plugin_path = WP_PLUGIN_DIR . '/operaton-dmn-evaluator';
        $clean_path = get_transient('operaton_dmn_v11_clean_path');
        
        // V11.5: Look for and remove any wrongly named directories
        $this->cleanup_wrong_directories();
        
        if (is_dir($correct_plugin_path)) {
            $corruption_detected = $this->detect_corruption_in_path($correct_plugin_path);
            
            if ($corruption_detected && $clean_path && is_dir($clean_path) && $clean_path !== $correct_plugin_path) {
                error_log('Operaton DMN V11.5: CORRUPTION DETECTED! Attempting emergency fix...');
                
                // Emergency fix: Replace corrupted files with our clean extraction
                $this->emergency_fix_corruption($correct_plugin_path, $clean_path);
                
                // Verify fix worked
                $still_corrupted = $this->detect_corruption_in_path($correct_plugin_path);
                if (!$still_corrupted) {
                    error_log('Operaton DMN V11.5: ✓ EMERGENCY FIX SUCCESSFUL!');
                } else {
                    error_log('Operaton DMN V11.5: ✗ Emergency fix failed - manual intervention required');
                }
            } else if (!$corruption_detected) {
                error_log('Operaton DMN V11.5: ✓ SUCCESS! No corruption detected - V11.5 worked!');
            } else {
                error_log('Operaton DMN V11.5: ✗ Corruption detected but no clean backup available');
            }
        } else {
            error_log('Operaton DMN V11.5: ✗ Correct plugin directory not found!');
        }
        
        // Cleanup
        if ($clean_path && is_dir($clean_path) && $clean_path !== $correct_plugin_path) {
            $this->delete_directory($clean_path);
        }
        delete_transient('operaton_dmn_v11_nuclear_mode');
        delete_transient('operaton_dmn_v11_clean_path');
        
        error_log('=== OPERATON DMN V11.5 VERIFICATION COMPLETE ===');
    }
    
    /**
     * V11.5: Post-extraction directory fix - NUCLEAR FALLBACK
     * This runs AFTER WordPress has done everything, as a final cleanup
     */
    public function post_extraction_directory_fix($upgrader, $hook_extra) {
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_slug) {
            return;
        }
        
        error_log('=== OPERATON DMN V11.5 POST-EXTRACTION NUCLEAR FALLBACK ===');
        
        $correct_plugin_path = WP_PLUGIN_DIR . '/operaton-dmn-evaluator';
        
        // V11.5: Look for any wrongly named directories that WordPress created
        $wrong_dirs = glob(WP_PLUGIN_DIR . '/operaton-dmn-evaluator-v*');
        
        if (!empty($wrong_dirs)) {
            foreach ($wrong_dirs as $wrong_dir) {
                if (is_dir($wrong_dir) && basename($wrong_dir) !== 'operaton-dmn-evaluator') {
                    error_log('Operaton DMN V11.5: NUCLEAR FALLBACK - Found wrong directory: ' . basename($wrong_dir));
                    
                    // Check if it contains our plugin files
                    if (file_exists($wrong_dir . '/operaton-dmn-plugin.php')) {
                        error_log('Operaton DMN V11.5: Wrong directory contains plugin files - executing nuclear move');
                        
                        // Remove correct directory if it exists
                        if (is_dir($correct_plugin_path)) {
                            error_log('Operaton DMN V11.5: Removing existing correct directory');
                            $this->delete_directory($correct_plugin_path);
                        }
                        
                        // Move wrong directory to correct location
                        if (rename($wrong_dir, $correct_plugin_path)) {
                            error_log('Operaton DMN V11.5: ✓ NUCLEAR MOVE SUCCESSFUL: ' . basename($wrong_dir) . ' → operaton-dmn-evaluator');
                            
                            // Clean up files inside the moved directory
                            $this->cleanup_files_in_directory($correct_plugin_path);
                            
                        } else {
                            error_log('Operaton DMN V11.5: ✗ NUCLEAR MOVE FAILED - attempting copy method');
                            
                            // Fallback: copy instead of move
                            wp_mkdir_p($correct_plugin_path);
                            $this->copy_directory_contents($wrong_dir, $correct_plugin_path);
                            $this->cleanup_files_in_directory($correct_plugin_path);
                            $this->delete_directory($wrong_dir);
                            
                            error_log('Operaton DMN V11.5: ✓ NUCLEAR COPY COMPLETED');
                        }
                    }
                }
            }
        } else {
            error_log('Operaton DMN V11.5: No wrong directories found - V11.5 extraction might have worked');
        }
        
        // Final verification
        if (is_dir($correct_plugin_path) && file_exists($correct_plugin_path . '/operaton-dmn-plugin.php')) {
            error_log('Operaton DMN V11.5: ✓ FINAL SUCCESS - Plugin is in correct location');
        } else {
            error_log('Operaton DMN V11.5: ✗ FINAL FAILURE - Plugin not found in correct location');
        }
        
        error_log('=== OPERATON DMN V11.5 NUCLEAR FALLBACK COMPLETE ===');
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
                    'url' => 'https://git.open-regels.nl/showcases/operaton-dmn-evaluator',
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
                        'author_profile' => 'https://git.open-regels.nl/showcases/operaton-dmn-evaluator',
                        'donate_link' => '',
                        'homepage' => 'https://git.open-regels.nl/showcases/operaton-dmn-evaluator',
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
        if (isset($remote_version['assets']['links']) && !empty($remote_version['assets']['links'])) {
            foreach ($remote_version['assets']['links'] as $link) {
                if (strpos($link['name'], '.zip') !== false || strpos($link['url'], '.zip') !== false) {
                    return $link['url'];
                }
            }
        }
        
        $tag = $remote_version['tag_name'];
        return $this->gitlab_url . '/api/v4/projects/' . $this->gitlab_project_id . '/repository/archive.zip?sha=' . $tag;
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
     * V11.5: Download package - simplified since we're doing extraction differently
     */
    public function download_package($result, $package, $upgrader) {
        // Only handle our GitLab packages
        if (strpos($package, $this->gitlab_url) === false) {
            return $result;
        }
        
        error_log('=== OPERATON DMN V11.5 DOWNLOAD START ===');
        error_log('Operaton DMN V11.5: Handling GitLab package: ' . $package);
        
        $this->is_our_update = true;
        
        // Download the file normally - let WordPress handle the download
        // Our extraction override will handle the rest
        $temp_file = download_url($package, 300);
        
        if (is_wp_error($temp_file)) {
            error_log('Operaton DMN V11.5: Download failed: ' . $temp_file->get_error_message());
            return $temp_file;
        }
        
        error_log('Operaton DMN V11.5: Downloaded: ' . $temp_file . ' (' . filesize($temp_file) . ' bytes)');
        error_log('=== OPERATON DMN V11.5 DOWNLOAD END ===');
        
        return $temp_file;
    }
    
    /**
     * V11.5: Check if file should be skipped during extraction
     */
    private function should_skip_file($filename) {
        $skip_patterns = array(
            '.github/',           // GitHub workflows
            '.github',            // GitHub folder
            '.gitignore',         // Git ignore file
            'vendor/',            // Composer vendor (if you want to exclude)
            'vendor',             // Vendor folder
        );
        
        foreach ($skip_patterns as $pattern) {
            if (strpos($filename, $pattern) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * V11.5: Detect corruption in extracted path
     */
    private function detect_corruption_in_path($path) {
        $corruption_indicators = array('hub', 'lates', 'or', 'pts', 'ts', 'udes', 'aton-dmn-plugin.php');
        
        $items = glob($path . '/*');
        foreach ($items as $item) {
            $basename = basename($item);
            if (in_array($basename, $corruption_indicators)) {
                return true;
            }
        }
        
        // Check if main plugin file exists with correct name
        if (!file_exists($path . '/operaton-dmn-plugin.php')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * V11.5: Fix filename encoding
     */
    private function fix_filename_encoding($filename) {
        $clean = preg_replace('/[\x00-\x1F\x7F]/', '', $filename);
        
        if (mb_check_encoding($clean, 'UTF-8')) {
            return $clean;
        }
        
        $encodings_to_try = array('CP437', 'CP850', 'ISO-8859-1', 'Windows-1252');
        
        foreach ($encodings_to_try as $encoding) {
            $converted = @mb_convert_encoding($clean, 'UTF-8', $encoding);
            if ($converted && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }
        
        return preg_replace('/[^\x20-\x7E]/', '', $clean) ?: 'unknown_file';
    }
    
    /**
     * V11.5: Clean directory contents without removing the directory itself
     */
    private function clean_directory_contents($dir) {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                @unlink($path);
            }
        }
    }
    
    /**
     * V11.5: Emergency corruption fix
     */
    private function emergency_fix_corruption($corrupted_path, $clean_path) {
        error_log('Operaton DMN V11.5: Starting emergency corruption fix...');
        
        try {
            // Remove all corrupted files
            $this->clean_directory_contents($corrupted_path);
            
            // Copy all clean files
            $this->copy_directory_contents($clean_path, $corrupted_path);
            
            error_log('Operaton DMN V11.5: Emergency fix completed');
            
        } catch (Exception $e) {
            error_log('Operaton DMN V11.5: Emergency fix failed: ' . $e->getMessage());
        }
    }
    
    /**
     * V11.5: Copy directory contents recursively
     */
    private function copy_directory_contents($source, $target) {
        if (!is_dir($source) || !is_dir($target)) {
            return false;
        }
        
        $files = array_diff(scandir($source), array('.', '..'));
        
        foreach ($files as $file) {
            $source_path = $source . '/' . $file;
            $target_path = $target . '/' . $file;
            
            if (is_dir($source_path)) {
                wp_mkdir_p($target_path);
                $this->copy_directory_contents($source_path, $target_path);
            } else {
                copy($source_path, $target_path);
            }
        }
        
        return true;
    }
    
    /**
     * V11.5: Clean up unwanted files in the final directory
     */
    private function cleanup_files_in_directory($directory) {
        error_log('Operaton DMN V11.5: Cleaning up unwanted files in: ' . $directory);
        
        $unwanted_items = array(
            $directory . '/.github',
            $directory . '/.gitignore',
            $directory . '/vendor'
        );
        
        foreach ($unwanted_items as $item) {
            if (file_exists($item)) {
                if (is_dir($item)) {
                    error_log('Operaton DMN V11.5: Removing unwanted directory: ' . basename($item));
                    $this->delete_directory($item);
                } else {
                    error_log('Operaton DMN V11.5: Removing unwanted file: ' . basename($item));
                    @unlink($item);
                }
            }
        }
    }
    
    /**
     * V11.5: Clean up any wrongly named plugin directories
     */
    private function cleanup_wrong_directories() {
        $plugins_dir = WP_PLUGIN_DIR;
        $correct_name = 'operaton-dmn-evaluator';
        
        // Look for directories that match the wrong GitLab pattern
        $wrong_dirs = glob($plugins_dir . '/operaton-dmn-evaluator-v*');
        
        foreach ($wrong_dirs as $wrong_dir) {
            if (basename($wrong_dir) !== $correct_name && is_dir($wrong_dir)) {
                error_log('Operaton DMN V11.5: Found wrong directory: ' . basename($wrong_dir));
                error_log('Operaton DMN V11.5: Removing wrong directory: ' . $wrong_dir);
                $this->delete_directory($wrong_dir);
            }
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
    private $gitlab_project_id;
    
    public function __construct() {
        $this->plugin_slug = 'operaton-dmn-evaluator';
        $this->version = OPERATON_DMN_VERSION;
        $this->repository_url = 'https://git.open-regels.nl/showcases/operaton-dmn-evaluator';
        $this->gitlab_project_id = '39';
        
        add_action('admin_notices', array($this, 'show_fallback_notice'));
    }
    
    public function show_fallback_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $update_plugins = get_site_transient('update_plugins');
        
        if (isset($update_plugins->response) && !empty($update_plugins->response)) {
            foreach ($update_plugins->response as $plugin => $data) {
                if (strpos($plugin, 'operaton-dmn') !== false) {
                    return;
                }
            }
        }
        
        $transient_key = 'operaton_dmn_fallback_check';
        $last_check = get_transient($transient_key);
        
        if ($last_check === false) {
            $api_url = 'https://git.open-regels.nl/api/v4/projects/' . $this->gitlab_project_id . '/releases';
            
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
                    $latest_release = $releases[0];
                    $remote_version = ltrim($latest_release['tag_name'], 'v');
                    $tag_name = $latest_release['tag_name'];
                    
                    if (version_compare($this->version, $remote_version, '<')) {
                        $download_url = $this->repository_url . '/-/releases/' . $tag_name;
                        
                        echo '<div class="notice notice-warning is-dismissible">';
                        echo '<p><strong>Operaton DMN Evaluator:</strong> ';
                        echo sprintf(
                            __('Version %s is available. <a href="%s" target="_blank">Download manually</a> or check the plugins page for automatic updates.', 'operaton-dmn'),
                            $remote_version,
                            $download_url
                        );
                        echo '</p></div>';
                    }
                }
            }
            
            set_transient($transient_key, time(), 12 * HOUR_IN_SECONDS);
        }
    }
}

// Initialize fallback notifier
new OperatonDMNUpdateNotifier();