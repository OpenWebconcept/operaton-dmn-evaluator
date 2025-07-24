<?php
/**
 * FIXED: Quirks Mode Fix with AJAX/REST API Protection
 * 
 * This version excludes AJAX calls and REST API requests from DOCTYPE injection
 * to prevent breaking JSON responses.
 * 
 * Replace your existing class-operaton-dmn-quirks-fix.php with this version
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Operaton_DMN_Quirks_Fix {
    
    /**
     * Initialize quirks mode fixes
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Frontend fixes - enhanced priority and order
        add_action('template_redirect', array($this, 'ensure_html5_doctype'), 1);
        add_action('wp_head', array($this, 'add_meta_tags'), 1);
        add_action('wp_head', array($this, 'add_quirks_mode_javascript_fix'), 2);
        add_action('wp_head', array($this, 'add_jquery_compatibility_fix'), 3);
        add_action('wp_head', array($this, 'add_quirks_mode_css_fix'), 10);
        
        // Admin notices
        add_action('admin_notices', array($this, 'quirks_mode_admin_notice'));
        add_action('admin_footer', array($this, 'check_doctype_status'));
        
        // FIXED: Conditional output buffer fix that excludes AJAX/REST
        add_action('init', array($this, 'conditional_start_output_buffer'), 1);
        
        // AJAX handler for notice dismissal
        add_action('wp_ajax_operaton_dismiss_quirks_notice', array($this, 'dismiss_quirks_notice'));
    }
    
    /**
     * Ensure proper DOCTYPE is output
     */
    public function ensure_html5_doctype() {
        if (!is_admin() && !headers_sent()) {
            $this->prepare_doctype_fix();
        }
    }
    
    /**
     * Prepare DOCTYPE fix
     */
    private function prepare_doctype_fix() {
        if (!defined('OPERATON_DOCTYPE_CHECK')) {
            define('OPERATON_DOCTYPE_CHECK', true);
        }
    }
    
    /**
     * FIXED: Conditional output buffer that excludes AJAX and REST API calls
     */
    public function conditional_start_output_buffer() {
        // Skip output buffering for admin pages
        if (is_admin()) {
            return;
        }
        
        // CRITICAL FIX: Skip output buffering for AJAX and REST API calls
        if ($this->is_ajax_or_rest_request()) {
            return;
        }
        
        // Skip if headers already sent
        if (headers_sent()) {
            return;
        }
        
        // Only apply to regular frontend page loads
        ob_start(array($this, 'fix_doctype_in_output'));
    }
    
    /**
     * NEW: Detect AJAX and REST API requests
     */
    private function is_ajax_or_rest_request() {
        // Check for WordPress AJAX
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return true;
        }
        
        // Check for REST API requests
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }
        
        // Check for wp-json in the URL
        if (strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false) {
            return true;
        }
        
        // Check for admin-ajax.php
        if (strpos($_SERVER['REQUEST_URI'], '/wp-admin/admin-ajax.php') !== false) {
            return true;
        }
        
        // Check for AJAX headers
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        
        // Check for JSON content type in request
        $content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        if (strpos($content_type, 'application/json') !== false) {
            return true;
        }
        
        // Check for Accept header requesting JSON
        $accept_header = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
        if (strpos($accept_header, 'application/json') !== false && 
            strpos($accept_header, 'text/html') === false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * ENHANCED: Fix DOCTYPE in output buffer with better detection
     */
    public function fix_doctype_in_output($content) {
        // Only process non-empty content
        if (empty($content)) {
            return $content;
        }
        
        // Double-check that this isn't JSON or other non-HTML content
        $trimmed_content = ltrim($content);
        
        // Don't modify JSON responses
        if (substr($trimmed_content, 0, 1) === '{' || substr($trimmed_content, 0, 1) === '[') {
            return $content;
        }
        
        // Don't modify XML responses
        if (substr($trimmed_content, 0, 5) === '<?xml') {
            return $content;
        }
        
        // Only process HTML content
        if (!preg_match('/^\s*<!DOCTYPE\s+html/i', $trimmed_content) && 
            (strpos($trimmed_content, '<html') !== false || strpos($trimmed_content, '<body') !== false)) {
            
            // Add proper DOCTYPE at the beginning
            $content = "<!DOCTYPE html>\n" . $content;
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN: Added missing DOCTYPE to page output');
            }
        }
        
        return $content;
    }
    
    /**
     * ENHANCED: Add meta tags to ensure proper rendering mode
     */
    public function add_meta_tags() {
        if (!is_admin() && !$this->is_ajax_or_rest_request()) {
            echo '<meta http-equiv="X-UA-Compatible" content="IE=edge">' . "\n";
            echo '<meta charset="' . get_bloginfo('charset') . '">' . "\n";
            echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
            echo '<meta name="operaton-dmn-quirks-fix" content="active">' . "\n";
        }
    }
    
    /**
     * NEW: Add jQuery compatibility fix specifically
     */
    public function add_jquery_compatibility_fix() {
        if (is_admin() || $this->is_ajax_or_rest_request()) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        /* Operaton DMN: jQuery Compatibility Fix */
        (function() {
            'use strict';
            
            // Enhanced jQuery detection and compatibility
            var jqueryCompatibility = {
                available: false,
                version: null,
                quirksMode: document.compatMode === "BackCompat",
                doctype: document.doctype ? document.doctype.name : null,
                issues: []
            };
            
            // Function to check and fix jQuery compatibility
            function checkjQueryCompatibility() {
                if (typeof jQuery !== 'undefined') {
                    jqueryCompatibility.available = true;
                    jqueryCompatibility.version = jQuery.fn.jquery;
                    
                    // Suppress jQuery Migrate warnings in Quirks Mode
                    if (jqueryCompatibility.quirksMode && jQuery.migrateWarnings) {
                        // Store original console.warn
                        var originalWarn = console.warn;
                        
                        // Override console.warn to filter out jQuery Migrate warnings
                        console.warn = function() {
                            var message = arguments[0];
                            if (typeof message === 'string' && 
                                (message.includes('JQMIGRATE') || 
                                 message.includes('Quirks Mode') || 
                                 message.includes('not compatible'))) {
                                // Suppress these warnings
                                return;
                            }
                            // Call original warn for other messages
                            originalWarn.apply(console, arguments);
                        };
                        
                        console.log('✅ Operaton DMN: jQuery Migrate warnings suppressed for Quirks Mode');
                    }
                    
                    // Store compatibility info globally
                    window.operatonJQueryCompatibility = jqueryCompatibility;
                    
                    if (<?php echo defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false'; ?>) {
                        console.log('Operaton DMN jQuery Compatibility:', jqueryCompatibility);
                    }
                }
            }
            
            // Check immediately if jQuery is available
            if (typeof jQuery !== 'undefined') {
                checkjQueryCompatibility();
            } else {
                // Set up polling for jQuery
                var jqueryCheckAttempts = 0;
                var maxjQueryAttempts = 50;
                
                function waitForjQuery() {
                    jqueryCheckAttempts++;
                    
                    if (typeof jQuery !== 'undefined') {
                        checkjQueryCompatibility();
                    } else if (jqueryCheckAttempts < maxjQueryAttempts) {
                        setTimeout(waitForjQuery, 100);
                    } else {
                        console.error('❌ Operaton DMN: jQuery not found after 5 seconds');
                        jqueryCompatibility.issues.push('jquery_not_loaded');
                    }
                }
                
                waitForjQuery();
            }
        })();
        </script>
        <?php
    }
    
    /**
     * ENHANCED: Add JavaScript fix for Quirks Mode compatibility
     */
    public function add_quirks_mode_javascript_fix() {
        if (is_admin() || $this->is_ajax_or_rest_request()) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        /* Operaton DMN: Enhanced Quirks Mode Compatibility Fix */
        (function() {
            'use strict';
            
            // Check for Quirks Mode
            var isQuirksMode = document.compatMode === "BackCompat";
            var hasDoctype = document.doctype !== null;
            
            if (isQuirksMode || !hasDoctype) {
                console.warn('⚠️ Operaton DMN: Document compatibility issues detected');
                console.warn('- Quirks Mode:', isQuirksMode);
                console.warn('- DOCTYPE present:', hasDoctype);
                
                if (!hasDoctype) {
                    console.warn('💡 Solution: Add <!DOCTYPE html> to your theme header.php file');
                }
                
                // Store quirks mode info globally
                window.operatonQuirksModeDetected = true;
                window.operatonCompatibilityInfo = {
                    quirksMode: isQuirksMode,
                    hasDoctype: hasDoctype,
                    doctype: document.doctype ? document.doctype.name : 'missing',
                    jqueryWarning: true,
                    fixApplied: true
                };
                
                // Add CSS class to body for styling fixes
                function addQuirksClass() {
                    if (document.body) {
                        document.body.className += ' operaton-quirks-mode-fix';
                        document.body.setAttribute('data-operaton-quirks', 'true');
                    }
                }
                
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', addQuirksClass);
                } else {
                    addQuirksClass();
                }
                
            } else {
                window.operatonQuirksModeDetected = false;
                window.operatonCompatibilityInfo = {
                    quirksMode: false,
                    hasDoctype: hasDoctype,
                    doctype: document.doctype ? document.doctype.name : 'html',
                    jqueryWarning: false,
                    fixApplied: false
                };
                
                if (<?php echo defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false'; ?>) {
                    console.log('✅ Operaton DMN: Document in Standards Mode');
                }
            }
        })();
        </script>
        <?php
    }
    
    /**
     * ENHANCED: Add comprehensive CSS fixes for Quirks Mode
     */
    public function add_quirks_mode_css_fix() {
        if (is_admin() || $this->is_ajax_or_rest_request()) {
            return;
        }
        
        ?>
        <style type="text/css">
        /* Operaton DMN: Comprehensive Quirks Mode CSS Fixes */
        
        /* Global box model fix */
        .operaton-quirks-mode-fix,
        .operaton-quirks-mode-fix *,
        .operaton-quirks-mode-fix *:before,
        .operaton-quirks-mode-fix *:after {
            -webkit-box-sizing: border-box !important;
            -moz-box-sizing: border-box !important;
            box-sizing: border-box !important;
        }
        
        /* Gravity Forms specific fixes */
        .operaton-quirks-mode-fix .gform_wrapper {
            width: 100% !important;
            max-width: 100% !important;
        }
        
        .operaton-quirks-mode-fix .gform_wrapper input,
        .operaton-quirks-mode-fix .gform_wrapper select,
        .operaton-quirks-mode-fix .gform_wrapper textarea {
            width: 100% !important;
            max-width: 100% !important;
        }
        
        /* Operaton DMN specific button fixes */
        .operaton-quirks-mode-fix .operaton-evaluate-btn {
            display: inline-block !important;
            width: auto !important;
            max-width: none !important;
            margin: 10px 5px !important;
            padding: 8px 16px !important;
            line-height: 1.4 !important;
            vertical-align: top !important;
            border: 1px solid #ccc !important;
            background: #f7f7f7 !important;
            cursor: pointer !important;
        }
        
        .operaton-quirks-mode-fix .operaton-evaluate-btn:hover {
            background: #e7e7e7 !important;
        }
        
        /* Radio button table fixes */
        .operaton-quirks-mode-fix .gf-table-row {
            display: block !important;
            width: 100% !important;
            margin-bottom: 10px !important;
            padding: 10px !important;
            border-bottom: 1px solid #eee !important;
        }
        
        .operaton-quirks-mode-fix .gf-table-row input[type="radio"] {
            width: auto !important;
            max-width: none !important;
            margin-right: 8px !important;
            vertical-align: middle !important;
        }
        
        .operaton-quirks-mode-fix .gf-table-row label {
            display: inline !important;
            width: auto !important;
            vertical-align: middle !important;
            cursor: pointer !important;
        }
        
        /* Form button fixes */
        .operaton-quirks-mode-fix .gform_footer {
            width: 100% !important;
        }
        
        /* Layout grid fixes */
        .operaton-quirks-mode-fix .gf_column_2 {
            display: block !important;
        }
        
        /* Input field fixes */
        .operaton-quirks-mode-fix input[type="text"],
        .operaton-quirks-mode-fix input[type="email"],
        .operaton-quirks-mode-fix input[type="number"],
        .operaton-quirks-mode-fix input[type="date"] {
            padding: 6px 8px !important;
            border: 1px solid #ddd !important;
        }
        
        /* Decision flow table fixes */
        .operaton-quirks-mode-fix .decision-table.excel-style {
            table-layout: fixed !important;
            width: 100% !important;
            border-collapse: collapse !important;
        }
        
        /* Notification fixes */
        .operaton-quirks-mode-fix .operaton-notification {
            position: fixed !important;
            z-index: 999999 !important;
        }
        
        /* Debug indicator for quirks mode */
        <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
        .operaton-quirks-mode-fix::before {
            content: "⚠️ Quirks Mode Detected - Operaton DMN compatibility fixes active";
            display: block;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 8px 12px;
            margin: 0 0 15px 0;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
        }
        <?php else: ?>
        .operaton-quirks-mode-fix::before {
            display: none !important;
        }
        <?php endif; ?>
        </style>
        <?php
    }
    
    /**
     * ENHANCED: Admin notice for Quirks Mode detection with dismissal
     */
    public function quirks_mode_admin_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check if notice has been dismissed
        if (get_option('operaton_dmn_quirks_notice_dismissed')) {
            return;
        }
        
        // Only show on plugin pages or pages with forms
        $screen = get_current_screen();
        if (!$screen || (strpos($screen->id, 'operaton-dmn') === false && !class_exists('GFForms'))) {
            return;
        }
        
        ?>
        <div class="notice notice-info is-dismissible" id="operaton-quirks-notice">
            <h3><?php _e('Operaton DMN: DOCTYPE Protection Active', 'operaton-dmn'); ?></h3>
            <p><?php _e('The plugin is automatically protecting your AJAX/REST API calls from DOCTYPE interference.', 'operaton-dmn'); ?></p>
            <p><strong><?php _e('Status:', 'operaton-dmn'); ?></strong> ✅ <?php _e('API calls are protected from DOCTYPE injection', 'operaton-dmn'); ?></p>
            
            <div style="background: #f9f9f9; padding: 15px; margin: 10px 0; border-left: 4px solid #0073aa;">
                <h4><?php _e('For best results, still add DOCTYPE to your theme:', 'operaton-dmn'); ?></h4>
                <ol>
                    <li><?php _e('This ensures full compatibility across all browsers', 'operaton-dmn'); ?></li>
                    <li><?php _e('Prevents reliance on automatic fixes', 'operaton-dmn'); ?></li>
                    <li><?php _e('Improves overall site performance', 'operaton-dmn'); ?></li>
                </ol>
            </div>
            
            <p>
                <button type="button" class="button button-primary" onclick="operatonDismissQuirksNotice(false)">
                    <?php _e('Got it, thanks!', 'operaton-dmn'); ?>
                </button>
            </p>
            
            <script>
            function operatonDismissQuirksNotice(temporary) {
                jQuery.post(ajaxurl, {
                    action: 'operaton_dismiss_quirks_notice',
                    temporary: temporary,
                    nonce: '<?php echo wp_create_nonce('operaton_quirks_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        jQuery('#operaton-quirks-notice').slideUp();
                    }
                });
            }
            </script>
        </div>
        <?php
    }
    
    /**
     * Handle notice dismissal
     */
    public function dismiss_quirks_notice() {
        if (!wp_verify_nonce($_POST['nonce'], 'operaton_quirks_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $temporary = $_POST['temporary'] === 'true';
        
        if ($temporary) {
            // Dismiss for 24 hours
            set_transient('operaton_dmn_quirks_notice_dismissed', true, DAY_IN_SECONDS);
        } else {
            // Dismiss permanently
            update_option('operaton_dmn_quirks_notice_dismissed', true);
        }
        
        wp_send_json_success();
    }
    
    /**
     * ENHANCED: Debug function to check DOCTYPE status (admin only)
     */
    public function check_doctype_status() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Only output on plugin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'operaton-dmn') === false) {
            return;
        }
        
        ?>
        <script>
        console.group('Operaton DMN DOCTYPE & API Protection Check');
        console.log('Document compatibility mode:', document.compatMode);
        console.log('DOCTYPE exists:', document.doctype !== null);
        console.log('DOCTYPE name:', document.doctype ? document.doctype.name : 'none');
        console.log('Quirks mode detected:', document.compatMode === "BackCompat");
        console.log('API protection active: ✅ AJAX/REST calls are protected from DOCTYPE injection');
        
        if (document.compatMode === "BackCompat") {
            console.warn('🚨 QUIRKS MODE DETECTED!');
            console.warn('Operaton DMN compatibility fixes are active.');
            console.log('✅ API calls are protected from DOCTYPE interference');
        } else {
            console.log('✅ Standards mode active - optimal compatibility');
        }
        
        // Check if jQuery is available
        if (typeof jQuery !== 'undefined') {
            console.log('✅ jQuery available, version:', jQuery.fn.jquery);
        } else {
            console.warn('⚠️ jQuery not yet available');
        }
        
        // Check compatibility info
        if (typeof window.operatonCompatibilityInfo !== 'undefined') {
            console.log('Compatibility info:', window.operatonCompatibilityInfo);
        }
        
        console.groupEnd();
        </script>
        <?php
    }
    
    /**
     * Get compatibility status (for debugging and API)
     */
    public function get_compatibility_status() {
        return array(
            'quirks_fix_active' => true,
            'api_protection_active' => true,
            'version' => OPERATON_DMN_VERSION,
            'ajax_detection_methods' => array(
                'DOING_AJAX',
                'REST_REQUEST', 
                'wp-json URL pattern',
                'admin-ajax.php URL pattern',
                'X-Requested-With header',
                'Content-Type: application/json',
                'Accept: application/json'
            ),
            'hooks_registered' => array(
                'template_redirect' => has_action('template_redirect', array($this, 'ensure_html5_doctype')),
                'wp_head_meta' => has_action('wp_head', array($this, 'add_meta_tags')),
                'wp_head_js' => has_action('wp_head', array($this, 'add_quirks_mode_javascript_fix')),
                'wp_head_jquery' => has_action('wp_head', array($this, 'add_jquery_compatibility_fix')),
                'wp_head_css' => has_action('wp_head', array($this, 'add_quirks_mode_css_fix'))
            ),
            'output_buffer' => has_action('init', array($this, 'conditional_start_output_buffer')),
            'admin_notices' => has_action('admin_notices', array($this, 'quirks_mode_admin_notice'))
        );
    }
    
    /**
     * Get current page compatibility info
     */
    public function get_page_compatibility_info() {
        $info = array(
            'is_ajax_request' => $this->is_ajax_or_rest_request(),
            'output_buffer_active' => !$this->is_ajax_or_rest_request() && !is_admin(),
            'recommendations' => array()
        );
        
        if ($info['is_ajax_request']) {
            $info['recommendations'][] = 'AJAX/REST request detected - DOCTYPE injection disabled';
        } else {
            $info['recommendations'][] = 'Regular page load - DOCTYPE injection enabled if needed';
        }
        
        return $info;
    }
}