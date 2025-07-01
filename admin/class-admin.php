<?php
/**
 * Add these methods to your Admin class
 */

class Operaton_DMN_Admin {
    
    public function __construct() {
        // ... existing constructor code ...
        
        // Add these hooks
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'operaton-dmn') === false) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'operaton-dmn-admin',
            OPERATON_DMN_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            OPERATON_DMN_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'operaton-dmn-admin',
            OPERATON_DMN_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            OPERATON_DMN_VERSION,
            true
        );
        
        // Localize script for translations
        wp_localize_script('operaton-dmn-admin', 'operatonDmnAdmin', array(
            'strings' => array(
                'dmnVariable' => __('DMN Variable:', 'operaton-dmn'),
                'formFieldId' => __('Form Field ID:', 'operaton-dmn'),
                'dataType' => __('Data Type:', 'operaton-dmn'),
                'remove' => __('Remove', 'operaton-dmn'),
                'confirmRemove' => __('Are you sure you want to remove this mapping?', 'operaton-dmn'),
                'noMappingsWarning' => __('No field mappings configured. The DMN evaluation may not work properly. Continue anyway?', 'operaton-dmn'),
                'incompleteMapping' => __('Please fill in both DMN Variable and Form Field ID for all mappings.', 'operaton-dmn'),
            ),
            'nonce' => wp_create_nonce('operaton_dmn_admin_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ));
    }
    
    // ... rest of your existing methods ...
}