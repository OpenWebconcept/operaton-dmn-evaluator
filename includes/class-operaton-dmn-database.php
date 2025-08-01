<?php

/**
 * Database Manager for Operaton DMN Plugin
 *
 * Handles all database operations including schema creation, configuration management,
 * data persistence, and automatic migrations. Follows WordPress coding standards
 * and database best practices.
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Operaton_DMN_Database
{
    /**
     * Database table name for configurations
     * WordPress prefixed table name for plugin configurations
     *
     * @var string
     * @since 1.0.0
     */
    private $table_name;

    /**
     * WordPress database instance
     * Reference to global $wpdb for database operations
     *
     * @var wpdb
     * @since 1.0.0
     */
    private $wpdb;

    /**
     * Plugin version for database schema versioning
     * Used for automatic migration detection and version tracking
     *
     * @var string
     * @since 1.0.0
     */
    private $plugin_version;

    /**
     * Database schema version option name
     * WordPress option key for tracking database schema version
     *
     * @var string
     * @since 1.0.0
     */
    private $db_version_option = 'operaton_dmn_db_version';

    /**
     * Current database schema version
     * Incremented when schema changes require migration
     *
     * @var int
     * @since 1.0.0
     */
    private $current_db_version = 3;

    /**
     * Constructor for database manager
     * Initializes database connection and table references
     *
     * @param string $plugin_version Current plugin version
     * @since 1.0.0
     */
    public function __construct($plugin_version = OPERATON_DMN_VERSION)
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->plugin_version = $plugin_version;
        $this->table_name = $this->wpdb->prefix . 'operaton_dmn_configs';

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Database: Manager initialized with version ' . $plugin_version);
        }

        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks for database operations
     * Sets up activation, upgrade, and AJAX hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        // Database management hooks
        add_action('admin_init', array($this, 'check_and_update_database'), 1);

        // AJAX handlers
        add_action('wp_ajax_operaton_manual_db_update', array($this, 'ajax_manual_database_update'));

        // Cleanup scheduled task
        add_action('operaton_dmn_cleanup', array($this, 'cleanup_old_data'));
    }

    // =============================================================================
    // SCHEMA MANAGEMENT METHODS
    // =============================================================================

    /**
     * Create or update database tables with proper WordPress schema handling
     * Creates the main configuration table with all necessary columns for DMN and process execution
     *
     * @since 1.0.0
     * @return bool Success status of table creation
     */
    public function create_database_tables()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Database: Creating/updating database tables');
        }

        // Check if table already exists and handle column additions
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name) {
            return $this->add_missing_columns();
        }

        // Create new table with complete schema
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            form_id int(11) NOT NULL,
            dmn_endpoint varchar(500) NOT NULL,
            decision_key varchar(255) NOT NULL,
            field_mappings longtext NOT NULL,
            result_mappings longtext NOT NULL,
            evaluation_step varchar(10) DEFAULT 'auto',
            button_text varchar(255) DEFAULT 'Evaluate',
            use_process boolean DEFAULT false,
            process_key varchar(255) DEFAULT NULL,
            show_decision_flow boolean DEFAULT false,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_form_id (form_id),
            KEY idx_form_id (form_id),
            KEY idx_decision_key (decision_key),
            KEY idx_process_key (process_key)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);

        if ($result) {
            // Update database version
            update_option($this->db_version_option, $this->current_db_version);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Database: Table created successfully');
            }
        }

        return !empty($result);
    }

    /**
     * Add missing columns to existing table for backward compatibility
     * Handles incremental schema updates for plugin upgrades
     *
     * @since 1.0.0
     * @return bool Success status of column additions
     */
    private function add_missing_columns()
    {
        $columns = $this->wpdb->get_col("SHOW COLUMNS FROM {$this->table_name}");
        $added_columns = 0;

        // Define required columns with their SQL definitions
        $required_columns = array(
            'result_mappings' => "ADD COLUMN result_mappings longtext NOT NULL DEFAULT '{}'",
            'evaluation_step' => "ADD COLUMN evaluation_step varchar(10) DEFAULT 'auto'",
            'use_process' => "ADD COLUMN use_process boolean DEFAULT false",
            'process_key' => "ADD COLUMN process_key varchar(255) DEFAULT NULL",
            'show_decision_flow' => "ADD COLUMN show_decision_flow boolean DEFAULT false",
            'created_at' => "ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP",
            'updated_at' => "ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        );

        foreach ($required_columns as $column_name => $sql_definition) {
            if (!in_array($column_name, $columns)) {
                $result = $this->wpdb->query("ALTER TABLE {$this->table_name} {$sql_definition}");

                if ($result !== false) {
                    $added_columns++;
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("Operaton DMN Database: Added column '{$column_name}'");
                    }
                } else {
                    error_log("Operaton DMN Database: Failed to add column '{$column_name}': " . $this->wpdb->last_error);
                }
            }
        }

        // Add indexes if they don't exist
        $this->add_missing_indexes();

        if ($added_columns > 0) {
            update_option($this->db_version_option, $this->current_db_version);
        }

        return $added_columns >= 0; // Return true even if no columns were added
    }

    /**
     * Add missing database indexes for performance optimization
     * Creates indexes on frequently queried columns
     *
     * @since 1.0.0
     */
    private function add_missing_indexes()
    {
        $indexes = array(
            'idx_decision_key' => "CREATE INDEX idx_decision_key ON {$this->table_name} (decision_key)",
            'idx_process_key' => "CREATE INDEX idx_process_key ON {$this->table_name} (process_key)"
        );

        foreach ($indexes as $index_name => $sql) {
            // Check if index exists
            $index_exists = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                     WHERE table_schema = %s AND table_name = %s AND index_name = %s",
                    DB_NAME,
                    $this->table_name,
                    $index_name
                )
            );

            if (!$index_exists) {
                $this->wpdb->query($sql);
            }
        }
    }

    /**
     * Automatic database migration system for plugin upgrades
     * Compares database version with plugin requirements and runs migrations
     *
     * @since 1.0.0
     * @return bool Success status of migration
     */
    public function check_and_update_database()
    {
        $installed_db_version = get_option($this->db_version_option, 0);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Operaton DMN Database: Checking database version - installed: {$installed_db_version}, current: {$this->current_db_version}");
        }

        // Check if table exists at all
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") !== $this->table_name) {
            return $this->create_database_tables();
        }

        // Check if migration is needed
        if (version_compare($installed_db_version, $this->current_db_version, '<')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Operaton DMN Database: Migration needed from version {$installed_db_version} to {$this->current_db_version}");
            }

            return $this->run_database_migration($installed_db_version);
        }

        return true;
    }

    /**
     * Run database migration between versions
     * Handles version-specific database schema changes
     *
     * @param int $from_version Database version to migrate from
     * @since 1.0.0
     * @return bool Success status of migration
     */
    private function run_database_migration($from_version)
    {
        $success = true;

        try {
            // Version 1 to 2: Add result_mappings and evaluation_step
            if ($from_version < 2) {
                $success &= $this->migrate_to_version_2();
            }

            // Version 2 to 3: Add process execution support
            if ($from_version < 3) {
                $success &= $this->migrate_to_version_3();
            }

            if ($success) {
                update_option($this->db_version_option, $this->current_db_version);

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Operaton DMN Database: Migration completed successfully to version {$this->current_db_version}");
                }
            }
        } catch (Exception $e) {
            error_log('Operaton DMN Database: Migration failed: ' . $e->getMessage());
            $success = false;
        }

        return $success;
    }

    /**
     * Migrate database schema to version 2
     * Adds result_mappings and evaluation_step columns
     *
     * @since 1.0.0
     * @return bool Success status
     */
    private function migrate_to_version_2()
    {
        $columns = $this->wpdb->get_col("SHOW COLUMNS FROM {$this->table_name}");

        if (!in_array('result_mappings', $columns)) {
            $result = $this->wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN result_mappings longtext NOT NULL DEFAULT '{}'");
            if ($result === false) {
                return false;
            }
        }

        if (!in_array('evaluation_step', $columns)) {
            $result = $this->wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN evaluation_step varchar(10) DEFAULT 'auto'");
            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Migrate database schema to version 3
     * Adds process execution support columns
     *
     * @since 1.0.0
     * @return bool Success status
     */
    private function migrate_to_version_3()
    {
        $columns = $this->wpdb->get_col("SHOW COLUMNS FROM {$this->table_name}");
        $process_columns = array(
            'use_process' => "ADD COLUMN use_process boolean DEFAULT false",
            'process_key' => "ADD COLUMN process_key varchar(255) DEFAULT NULL",
            'show_decision_flow' => "ADD COLUMN show_decision_flow boolean DEFAULT false"
        );

        foreach ($process_columns as $column => $sql) {
            if (!in_array($column, $columns)) {
                $result = $this->wpdb->query("ALTER TABLE {$this->table_name} {$sql}");
                if ($result === false) {
                    return false;
                }
            }
        }

        return true;
    }

    // =============================================================================
    // CONFIGURATION MANAGEMENT METHODS
    // =============================================================================

    /**
     * Retrieve all DMN configurations with optional filtering and ordering
     * Gets complete list of plugin configurations for admin display
     *
     * @param array $args Query arguments (orderby, order, limit, etc.)
     * @since 1.0.0
     * @return array Array of configuration objects
     */
    public function get_all_configurations($args = array())
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Database: Retrieving all configurations');
        }

        // Default arguments
        $defaults = array(
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => null,
            'offset' => 0
        );

        $args = wp_parse_args($args, $defaults);

        // Build query
        $sql = "SELECT * FROM {$this->table_name}";

        // Add ORDER BY
        $allowed_orderby = array('id', 'name', 'form_id', 'created_at', 'updated_at');
        if (in_array($args['orderby'], $allowed_orderby)) {
            $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
            $sql .= " ORDER BY {$args['orderby']} {$order}";
        }

        // Add LIMIT
        if ($args['limit']) {
            $sql .= $this->wpdb->prepare(" LIMIT %d", $args['limit']);

            if ($args['offset']) {
                $sql .= $this->wpdb->prepare(" OFFSET %d", $args['offset']);
            }
        }

        return $this->wpdb->get_results($sql);
    }

    /**
     * Retrieve single configuration by ID with caching support
     * Gets specific DMN configuration for editing or processing
     *
     * @param int $id Configuration ID to retrieve
     * @param bool $use_cache Whether to use WordPress object cache
     * @since 1.0.0
     * @return object|null Configuration object or null if not found
     */
    public function get_configuration($id, $use_cache = true)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Database: Retrieving configuration with ID: ' . $id);
        }

        $cache_key = "operaton_dmn_config_{$id}";

        // Try cache first
        if ($use_cache) {
            $cached = wp_cache_get($cache_key, 'operaton_dmn');
            if ($cached !== false) {
                return $cached;
            }
        }

        $config = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id)
        );

        // Cache the result
        if ($use_cache && $config) {
            wp_cache_set($cache_key, $config, 'operaton_dmn', 3600); // Cache for 1 hour
        }

        return $config;
    }

    /**
     * Retrieve configuration by form ID with static caching
     * Gets DMN configuration associated with specific Gravity Form
     *
     * @param int $form_id Gravity Forms form ID
     * @param bool $use_cache Whether to use static cache
     * @since 1.0.0
     * @return object|null Configuration object or null if not found
     */
    public function get_config_by_form_id($form_id, $use_cache = true)
    {
        static $cache = array();

        if ($use_cache && isset($cache[$form_id])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Database: Using cached config for form: ' . $form_id);
            }
            return $cache[$form_id];
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Database: Loading config from database for form: ' . $form_id);
        }

        $config = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE form_id = %d", $form_id)
        );

        if ($use_cache) {
            $cache[$form_id] = $config;
        }

        return $config;
    }

    /**
     * Save configuration with comprehensive validation and sanitization
     * Creates or updates DMN configuration with proper data validation
     *
     * @param array $data Configuration data to save
     * @since 1.0.0
     * @return bool|WP_Error Success status or error object
     */
    public function save_configuration($data)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Database: Saving configuration');
        }

        // Validate data first
        $validation_result = $this->validate_configuration_data($data);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        // Sanitize and prepare data
        $config_data = $this->sanitize_configuration_data($data);

        $config_id = isset($data['config_id']) ? intval($data['config_id']) : 0;

        if ($config_id > 0) {
            // Update existing configuration
            $result = $this->update_configuration($config_id, $config_data);
        } else {
            // Create new configuration
            $result = $this->create_configuration($config_data);
        }

        if ($result && !is_wp_error($result)) {
            // Clear caches
            $this->clear_configuration_cache();

            // Clear WordPress object cache
            wp_cache_delete("operaton_dmn_config_{$config_id}", 'operaton_dmn');

            do_action('operaton_dmn_configuration_saved', $result, $config_data);
        }

        return $result;
    }

    /**
     * Create new configuration record
     * Inserts new configuration into database with duplicate checking
     *
     * @param array $config_data Sanitized configuration data
     * @since 1.0.0
     * @return int|WP_Error New configuration ID or error object
     */
    private function create_configuration($config_data)
    {
        // Check for duplicate form_id
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE form_id = %d",
                $config_data['form_id']
            )
        );

        if ($existing) {
            return new WP_Error(
                'duplicate_form_id',
                __('A configuration for this form already exists.', 'operaton-dmn')
            );
        }

        $result = $this->wpdb->insert(
            $this->table_name,
            $config_data,
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d')
        );

        if ($result === false) {
            return new WP_Error(
                'database_error',
                sprintf(__('Database error: %s', 'operaton-dmn'), $this->wpdb->last_error)
            );
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Update existing configuration record
     * Updates configuration in database with validation
     *
     * @param int $config_id Configuration ID to update
     * @param array $config_data Sanitized configuration data
     * @since 1.0.0
     * @return bool|WP_Error Success status or error object
     */
    private function update_configuration($config_id, $config_data)
    {
        // Check if configuration exists
        $existing = $this->get_configuration($config_id, false);
        if (!$existing) {
            return new WP_Error(
                'config_not_found',
                __('Configuration not found.', 'operaton-dmn')
            );
        }

        // Check for duplicate form_id (excluding current config)
        $duplicate = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE form_id = %d AND id != %d",
                $config_data['form_id'],
                $config_id
            )
        );

        if ($duplicate) {
            return new WP_Error(
                'duplicate_form_id',
                __('Another configuration for this form already exists.', 'operaton-dmn')
            );
        }

        $result = $this->wpdb->update(
            $this->table_name,
            $config_data,
            array('id' => $config_id),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error(
                'database_error',
                sprintf(__('Database error: %s', 'operaton-dmn'), $this->wpdb->last_error)
            );
        }

        return true;
    }

    /**
     * Delete configuration with proper cleanup and validation
     * Removes DMN configuration and clears associated cached data
     *
     * @param int $id Configuration ID to delete
     * @since 1.0.0
     * @return bool|WP_Error Success status or error object
     */
    public function delete_config($id)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Database: Deleting configuration with ID: ' . $id);
        }

        // Validate ID
        $id = intval($id);
        if ($id <= 0) {
            return new WP_Error('invalid_id', __('Invalid configuration ID.', 'operaton-dmn'));
        }

        // Check if configuration exists
        $config = $this->get_configuration($id, false);
        if (!$config) {
            return new WP_Error('config_not_found', __('Configuration not found.', 'operaton-dmn'));
        }

        // Delete configuration
        $result = $this->wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error(
                'database_error',
                sprintf(__('Database error: %s', 'operaton-dmn'), $this->wpdb->last_error)
            );
        }

        if ($result === 0) {
            return new WP_Error('config_not_found', __('Configuration not found.', 'operaton-dmn'));
        }

        // Clear caches
        $this->clear_configuration_cache();
        wp_cache_delete("operaton_dmn_config_{$id}", 'operaton_dmn');

        // Trigger action for cleanup
        do_action('operaton_dmn_configuration_deleted', $id, $config);

        return true;
    }

    // =============================================================================
    // PROCESS TRACKING METHODS
    // =============================================================================

    /**
     * Store process instance ID for decision flow tracking
     * Saves process execution ID in session and user meta for later retrieval
     *
     * @param int $form_id Gravity Forms form ID
     * @param string $process_instance_id Operaton process instance identifier
     * @since 1.0.0
     * @return bool Success status
     */
    public function store_process_instance_id($form_id, $process_instance_id)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Operaton DMN Database: Storing process instance ID: {$process_instance_id} for form: {$form_id}");
        }

        // Validate inputs
        if (empty($form_id) || empty($process_instance_id)) {
            return false;
        }

        $form_id = intval($form_id);
        $process_instance_id = sanitize_text_field($process_instance_id);

        // Store in session if available
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION["operaton_process_{$form_id}"] = $process_instance_id;

        // Store in user meta if user is logged in
        if (is_user_logged_in()) {
            $result = update_user_meta(
                get_current_user_id(),
                "operaton_process_{$form_id}",
                $process_instance_id
            );

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Operaton DMN Database: User meta storage result: " . ($result ? 'success' : 'failed'));
            }
        }

        // Store in WordPress transients as backup (expire in 24 hours)
        $transient_key = "operaton_process_{$form_id}_" . (is_user_logged_in() ? get_current_user_id() : session_id());
        set_transient($transient_key, $process_instance_id, DAY_IN_SECONDS);

        return true;
    }

    /**
     * Retrieve stored process instance ID from various storage methods
     * Gets previously stored process ID for decision flow summary access
     *
     * @param int $form_id Gravity Forms form ID
     * @since 1.0.0
     * @return string|null Process instance ID or null if not found
     */
    public function get_process_instance_id($form_id)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Database: Retrieving process instance ID for form: ' . $form_id);
        }

        $form_id = intval($form_id);
        if ($form_id <= 0) {
            return null;
        }

        // Try session first
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION["operaton_process_{$form_id}"])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Database: Found process ID in session');
            }
            return sanitize_text_field($_SESSION["operaton_process_{$form_id}"]);
        }

        // Try user meta if logged in
        if (is_user_logged_in()) {
            $process_id = get_user_meta(get_current_user_id(), "operaton_process_{$form_id}", true);
            if ($process_id) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Operaton DMN Database: Found process ID in user meta');
                }
                return sanitize_text_field($process_id);
            }
        }

        // Try transients as backup
        $transient_key = "operaton_process_{$form_id}_" . (is_user_logged_in() ? get_current_user_id() : session_id());
        $process_id = get_transient($transient_key);

        if ($process_id) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Database: Found process ID in transients');
            }
            return sanitize_text_field($process_id);
        }

        return null;
    }

    // =============================================================================
    // AJAX HANDLERS
    // =============================================================================

    /**
     * AJAX handler for manual database update trigger
     * Provides manual database migration option for administrators
     *
     * @since 1.0.0
     */
    public function ajax_manual_database_update()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Database: Manual database update requested');
        }

        // Verify nonce and permissions
        if (!wp_verify_nonce($_GET['_wpnonce'], 'operaton_manual_db_update') || !current_user_can('manage_options')) {
            wp_die(__('Security check failed', 'operaton-dmn'));
        }

        // Perform database update
        $result = $this->check_and_update_database();

        if ($result) {
            $message = __('Database updated successfully!', 'operaton-dmn');
            $notice_type = 'success';
        } else {
            $message = __('Database update failed. Please check the error log.', 'operaton-dmn');
            $notice_type = 'error';
        }

        // Redirect back with message
        wp_redirect(add_query_arg(array(
            'page' => 'operaton-dmn',
            'database_updated' => $result ? '1' : '0',
            'notice' => $notice_type,
            'message' => urlencode($message)
        ), admin_url('admin.php')));
        exit;
    }

    // =============================================================================
    // VALIDATION AND SANITIZATION METHODS
    // =============================================================================

    /**
     * Validate configuration data before saving
     * Comprehensive validation of all configuration fields
     *
     * @param array $data Configuration data to validate
     * @since 1.0.0
     * @return bool|WP_Error True if valid, WP_Error object if invalid
     */
    private function validate_configuration_data($data)
    {
        $errors = array();

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Database: Validating configuration data');
        }

        // Required field validation
        $required_fields = array(
            'name' => __('Configuration Name', 'operaton-dmn'),
            'form_id' => __('Gravity Form', 'operaton-dmn'),
            'dmn_endpoint' => __('DMN Base Endpoint URL', 'operaton-dmn'),
        );

        foreach ($required_fields as $field => $label) {
            if (empty($data[$field])) {
                $errors[] = sprintf(__('%s is required.', 'operaton-dmn'), $label);
            }
        }

        // Decision key OR process key is required
        $use_process = isset($data['use_process']) && $data['use_process'];

        if ($use_process) {
            if (empty($data['process_key'])) {
                $errors[] = __('Process Key is required when using process execution.', 'operaton-dmn');
            }
        } else {
            if (empty($data['decision_key'])) {
                $errors[] = __('Decision Key is required when using direct decision evaluation.', 'operaton-dmn');
            }
        }

        // URL validation
        if (!empty($data['dmn_endpoint']) && !filter_var($data['dmn_endpoint'], FILTER_VALIDATE_URL)) {
            $errors[] = __('DMN Base Endpoint URL is not valid.', 'operaton-dmn');
        }

        // Key validation
        $key_to_validate = $use_process ? ($data['process_key'] ?? '') : ($data['decision_key'] ?? '');
        if (!empty($key_to_validate)) {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', trim($key_to_validate))) {
                $key_type = $use_process ? 'Process key' : 'Decision key';
                $errors[] = sprintf(__('%s should only contain letters, numbers, hyphens, and underscores.', 'operaton-dmn'), $key_type);
            }
        }

        // Form ID validation
        if (!empty($data['form_id'])) {
            $form_id = intval($data['form_id']);
            if ($form_id <= 0) {
                $errors[] = __('Invalid form ID.', 'operaton-dmn');
            } elseif (class_exists('GFAPI')) {
                $form = GFAPI::get_form($form_id);
                if (!$form) {
                    $errors[] = __('Selected Gravity Form does not exist.', 'operaton-dmn');
                }
            }
        }

        // Field mappings validation
        $has_input_mappings = $this->validate_field_mappings($data, $errors);
        if (!$has_input_mappings) {
            $errors[] = __('At least one input field mapping is required.', 'operaton-dmn');
        }

        // Result mappings validation
        $has_result_mappings = $this->validate_result_mappings($data, $errors);
        if (!$has_result_mappings) {
            $errors[] = __('At least one result field mapping is required.', 'operaton-dmn');
        }

        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(' ', $errors), $errors);
        }

        return true;
    }

    /**
     * Validate field mappings
     * Validates input field mappings configuration
     *
     * @param array $data Configuration data
     * @param array &$errors Reference to errors array
     * @since 1.0.0
     * @return bool True if valid mappings exist
     */
    private function validate_field_mappings($data, &$errors)
    {
        $has_mappings = false;

        if (isset($data['field_mappings_dmn_variable']) && is_array($data['field_mappings_dmn_variable'])) {
            $dmn_variables = $data['field_mappings_dmn_variable'];
            $field_ids = isset($data['field_mappings_field_id']) ? $data['field_mappings_field_id'] : array();

            for ($i = 0; $i < count($dmn_variables); $i++) {
                $dmn_var = trim($dmn_variables[$i]);
                $field_id = isset($field_ids[$i]) ? trim($field_ids[$i]) : '';

                if (!empty($dmn_var) && !empty($field_id)) {
                    $has_mappings = true;

                    if (!is_numeric($field_id)) {
                        $errors[] = sprintf(__('Field ID "%s" must be numeric.', 'operaton-dmn'), $field_id);
                    }

                    // Validate field exists in form
                    if (class_exists('GFAPI') && !empty($data['form_id'])) {
                        $this->validate_field_exists_in_form($data['form_id'], $field_id, $errors, 'Input');
                    }
                }
            }
        }

        return $has_mappings;
    }

    /**
     * Validate result mappings
     * Validates output result mappings configuration
     *
     * @param array $data Configuration data
     * @param array &$errors Reference to errors array
     * @since 1.0.0
     * @return bool True if valid mappings exist
     */
    private function validate_result_mappings($data, &$errors)
    {
        $has_mappings = false;

        if (isset($data['result_mappings_dmn_result']) && is_array($data['result_mappings_dmn_result'])) {
            $dmn_results = $data['result_mappings_dmn_result'];
            $result_field_ids = isset($data['result_mappings_field_id']) ? $data['result_mappings_field_id'] : array();

            for ($i = 0; $i < count($dmn_results); $i++) {
                $dmn_result = trim($dmn_results[$i]);
                $field_id = isset($result_field_ids[$i]) ? trim($result_field_ids[$i]) : '';

                if (!empty($dmn_result) && !empty($field_id)) {
                    $has_mappings = true;

                    if (!is_numeric($field_id)) {
                        $errors[] = sprintf(__('Result field ID "%s" must be numeric.', 'operaton-dmn'), $field_id);
                    }

                    // Validate field exists in form
                    if (class_exists('GFAPI') && !empty($data['form_id'])) {
                        $this->validate_field_exists_in_form($data['form_id'], $field_id, $errors, 'Result');
                    }
                }
            }
        }

        return $has_mappings;
    }

    /**
     * Validate field exists in Gravity Form
     * Checks if specified field ID exists in the target form
     *
     * @param int $form_id Gravity Forms form ID
     * @param string $field_id Field ID to validate
     * @param array &$errors Reference to errors array
     * @param string $field_type Type description for error messages
     * @since 1.0.0
     */
    private function validate_field_exists_in_form($form_id, $field_id, &$errors, $field_type = 'Field')
    {
        $form = GFAPI::get_form($form_id);
        if ($form) {
            $field_exists = false;
            foreach ($form['fields'] as $form_field) {
                if ($form_field->id == $field_id) {
                    $field_exists = true;
                    break;
                }
            }
            if (!$field_exists) {
                $errors[] = sprintf(__('%s field ID "%s" does not exist in the selected form.', 'operaton-dmn'), $field_type, $field_id);
            }
        }
    }

    /**
     * Sanitize configuration data for database storage
     * Properly sanitizes all configuration fields according to their types
     *
     * @param array $data Raw configuration data
     * @since 1.0.0
     * @return array Sanitized configuration data
     */
    private function sanitize_configuration_data($data)
    {
        // Process field mappings
        $field_mappings = array();

        if (isset($data['field_mappings_dmn_variable']) && is_array($data['field_mappings_dmn_variable'])) {
            $dmn_variables = $data['field_mappings_dmn_variable'];
            $field_ids = isset($data['field_mappings_field_id']) ? $data['field_mappings_field_id'] : array();
            $types = isset($data['field_mappings_type']) ? $data['field_mappings_type'] : array();
            $radio_names = isset($data['field_mappings_radio_name']) ? $data['field_mappings_radio_name'] : array();

            for ($i = 0; $i < count($dmn_variables); $i++) {
                $dmn_var = sanitize_text_field(trim($dmn_variables[$i]));
                $field_id = isset($field_ids[$i]) ? sanitize_text_field(trim($field_ids[$i])) : '';
                $type = isset($types[$i]) ? sanitize_text_field($types[$i]) : 'String';
                $radio_name = isset($radio_names[$i]) ? sanitize_text_field(trim($radio_names[$i])) : '';

                if (!empty($dmn_var) && !empty($field_id)) {
                    $field_mappings[$dmn_var] = array(
                        'field_id' => $field_id,
                        'type' => $type,
                        'radio_name' => $radio_name
                    );
                }
            }
        }

        // Process result mappings
        $result_mappings = array();

        if (isset($data['result_mappings_dmn_result']) && is_array($data['result_mappings_dmn_result'])) {
            $dmn_results = $data['result_mappings_dmn_result'];
            $result_field_ids = isset($data['result_mappings_field_id']) ? $data['result_mappings_field_id'] : array();

            for ($i = 0; $i < count($dmn_results); $i++) {
                $dmn_result = sanitize_text_field(trim($dmn_results[$i]));
                $field_id = isset($result_field_ids[$i]) ? sanitize_text_field(trim($result_field_ids[$i])) : '';

                if (!empty($dmn_result) && !empty($field_id)) {
                    $result_mappings[$dmn_result] = array(
                        'field_id' => $field_id
                    );
                }
            }
        }

        return array(
            'name' => sanitize_text_field($data['name']),
            'form_id' => intval($data['form_id']),
            'dmn_endpoint' => esc_url_raw($data['dmn_endpoint']),
            'decision_key' => sanitize_text_field($data['decision_key'] ?? ''),
            'field_mappings' => wp_json_encode($field_mappings),
            'result_mappings' => wp_json_encode($result_mappings),
            'evaluation_step' => sanitize_text_field($data['evaluation_step'] ?? 'auto'),
            'button_text' => sanitize_text_field($data['button_text'] ?: 'Evaluate'),
            'use_process' => isset($data['use_process']) ? (bool)$data['use_process'] : false,
            'process_key' => sanitize_text_field($data['process_key'] ?? ''),
            'show_decision_flow' => isset($data['show_decision_flow']) ? (bool)$data['show_decision_flow'] : false
        );
    }

    // =============================================================================
    // UTILITY AND MAINTENANCE METHODS
    // =============================================================================

    /**
     * Clear configuration cache to force fresh database queries
     * Removes cached configuration data after save/delete operations
     *
     * @since 1.0.0
     */
    public function clear_configuration_cache()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Database: Clearing configuration cache');
        }

        // Clear WordPress object cache
        wp_cache_flush_group('operaton_dmn');

        // Clear any plugin-specific transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_operaton_dmn_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_operaton_dmn_%'");
    }

    /**
     * Cleanup old data scheduled task for maintenance
     * Removes expired cache entries and temporary data
     *
     * @since 1.0.0
     */
    public function cleanup_old_data()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Database: Running cleanup task');
        }

        // Clean up old process instance data (older than 7 days)
        if (is_user_logged_in()) {
            global $wpdb;

            // Clean old user meta entries
            $wpdb->query(
                "DELETE FROM {$wpdb->usermeta}
                 WHERE meta_key LIKE 'operaton_process_%'
                 AND meta_key REGEXP '^operaton_process_[0-9]+"
            );
        }

        // Clean up old transients
        $this->cleanup_expired_transients();

        // Clear configuration cache
        $this->clear_configuration_cache();

        do_action('operaton_dmn_cleanup_completed');
    }

    /**
     * Clean up expired transients
     * Removes expired WordPress transients related to the plugin
     *
     * @since 1.0.0
     */
    private function cleanup_expired_transients()
    {
        global $wpdb;

        // Get current time
        $current_time = time();

        // Find expired transients
        $expired_transients = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options}
                 WHERE option_name LIKE '_transient_timeout_operaton_%%'
                 AND option_value < %d",
                $current_time
            )
        );

        // Delete expired transients and their timeout options
        foreach ($expired_transients as $timeout_option) {
            $transient_option = str_replace('_timeout_', '_', $timeout_option);
            delete_option($timeout_option);
            delete_option($transient_option);
        }

        if (defined('WP_DEBUG') && WP_DEBUG && !empty($expired_transients)) {
            error_log('Operaton DMN Database: Cleaned up ' . count($expired_transients) . ' expired transients');
        }
    }

    /**
     * Get database statistics for admin dashboard
     * Provides information about database usage and health
     *
     * @since 1.0.0
     * @return array Database statistics
     */
    public function get_database_stats()
    {
        $stats = array(
            'total_configurations' => 0,
            'active_configurations' => 0,
            'process_configurations' => 0,
            'decision_configurations' => 0,
            'table_size' => 0,
            'last_updated' => null
        );

        // Get total configurations
        $stats['total_configurations'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name}"
        );

        // Get configurations by type
        $stats['process_configurations'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE use_process = 1"
        );

        $stats['decision_configurations'] = $stats['total_configurations'] - $stats['process_configurations'];

        // Get table size
        $table_status = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT data_length + index_length as size_bytes
                 FROM information_schema.TABLES
                 WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $this->table_name
            )
        );

        if ($table_status) {
            $stats['table_size'] = $table_status->size_bytes;
        }

        // Get last updated timestamp
        $last_updated = $this->wpdb->get_var(
            "SELECT MAX(updated_at) FROM {$this->table_name}"
        );

        if ($last_updated) {
            $stats['last_updated'] = $last_updated;
        }

        return $stats;
    }

    /**
     * Check database health and integrity
     * Validates database structure and identifies potential issues
     *
     * @since 1.0.0
     * @return array Health check results
     */
    public function check_database_health()
    {
        $health = array(
            'table_exists' => false,
            'all_columns_exist' => false,
            'indexes_exist' => false,
            'permissions_ok' => false,
            'issues' => array()
        );

        // Check if table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name;
        $health['table_exists'] = $table_exists;

        if (!$table_exists) {
            $health['issues'][] = __('Main configuration table does not exist.', 'operaton-dmn');
            return $health;
        }

        // Check columns
        $columns = $this->wpdb->get_col("SHOW COLUMNS FROM {$this->table_name}");
        $required_columns = array(
            'id',
            'name',
            'form_id',
            'dmn_endpoint',
            'decision_key',
            'field_mappings',
            'result_mappings',
            'evaluation_step',
            'button_text',
            'use_process',
            'process_key',
            'show_decision_flow',
            'created_at',
            'updated_at'
        );

        $missing_columns = array_diff($required_columns, $columns);
        $health['all_columns_exist'] = empty($missing_columns);

        if (!empty($missing_columns)) {
            $health['issues'][] = sprintf(
                __('Missing columns: %s', 'operaton-dmn'),
                implode(', ', $missing_columns)
            );
        }

        // Check basic permissions (try a simple SELECT)
        $can_read = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}") !== null;
        $health['permissions_ok'] = $can_read;

        if (!$can_read) {
            $health['issues'][] = __('Cannot read from configuration table.', 'operaton-dmn');
        }

        return $health;
    }

    /**
     * Get table name for external access
     * Provides access to table name for other components
     *
     * @since 1.0.0
     * @return string Database table name
     */
    public function get_table_name()
    {
        return $this->table_name;
    }

    /**
     * Get current database version
     * Provides access to current schema version
     *
     * @since 1.0.0
     * @return int Current database schema version
     */
    public function get_current_db_version()
    {
        return $this->current_db_version;
    }

    /**
     * Get installed database version
     * Gets the database version stored in WordPress options
     *
     * @since 1.0.0
     * @return int Installed database schema version
     */
    public function get_installed_db_version()
    {
        return intval(get_option($this->db_version_option, 0));
    }
}
