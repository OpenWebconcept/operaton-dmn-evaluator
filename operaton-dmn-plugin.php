<?php
/**
 * Plugin Name: Operaton DMN Evaluator
 * Plugin URI: https://git.open-regels.nl/showcases/operaton-dmn-evaluator
 * Description: WordPress plugin to integrate Gravity Forms with Operaton DMN decision tables for dynamic form evaluations.
 * Version: 1.0.0-beta.1
 * Author: Steven Gort
 * License: GPL v2 or later
 * Text Domain: operaton-dmn
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OPERATON_DMN_VERSION', '1.0.0-beta.1');
define('OPERATON_DMN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OPERATON_DMN_PLUGIN_PATH', plugin_dir_path(__FILE__));

class OperatonDMNEvaluator {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('gform_enqueue_scripts', array($this, 'enqueue_gravity_scripts'), 10, 2);
        add_filter('gform_submit_button', array($this, 'add_evaluate_button'), 10, 2);

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public function enqueue_scripts() {
        if (!is_admin()) {
            wp_enqueue_script('jquery');
            wp_enqueue_script(
                'operaton-dmn-frontend',
                OPERATON_DMN_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                OPERATON_DMN_VERSION,
                true
            );

            wp_enqueue_style(
                'operaton-dmn-frontend',
                OPERATON_DMN_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                OPERATON_DMN_VERSION
            );

            wp_localize_script('operaton-dmn-frontend', 'operaton_ajax', array(
                'url' => rest_url('operaton-dmn/v1/evaluate'),
                'nonce' => wp_create_nonce('wp_rest')
            ));
        }

        if (is_admin()) {
            wp_enqueue_style(
                'operaton-dmn-admin',
                OPERATON_DMN_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                OPERATON_DMN_VERSION
            );
        }
    }

    public function enqueue_gravity_scripts($form, $is_ajax) {
        if (is_admin()) return;

        $config = $this->get_config_by_form_id($form['id']);
        if ($config) {
            wp_localize_script('operaton-dmn-frontend', 'operaton_config_' . $form['id'], array(
                'config_id' => $config->id,
                'button_text' => $config->button_text,
                'field_mappings' => json_decode($config->field_mappings, true)
            ));
        }
    }

    public function add_evaluate_button($button, $form) {
        if (is_admin()) return $button;

        $config = $this->get_config_by_form_id($form['id']);
        if ($config) {
            $evaluate_button = '<input type="button" id="operaton-evaluate-' . $form['id'] . '" value="' . esc_attr($config->button_text) . '" class="gform_button button operaton-evaluate-btn" data-form-id="' . $form['id'] . '" data-config-id="' . $config->id . '" style="margin-left: 10px;">';
            $button .= '<div class="gfield">' . $evaluate_button;
            $button .= '<div id="operaton-result-' . $form['id'] . '" class="ginput_container operaton-result gfield" style="display: none; margin-top: 20px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;"><h4>' . __('Result:', 'operaton-dmn') . '</h4><div class="result-content"></div></div></div>';
        }
        return $button;
    }

    // Keep the rest of the original plugin code below unchanged...
}

OperatonDMNEvaluator::get_instance();

register_activation_hook(__FILE__, 'operaton_dmn_create_files');
function operaton_dmn_create_files() {
    $upload_dir = wp_upload_dir();
    $plugin_dir = $upload_dir['basedir'] . '/operaton-dmn/';

    if (!file_exists($plugin_dir)) {
        wp_mkdir_p($plugin_dir);
    }
}
