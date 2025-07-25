<?php
/**
 * Performance Monitor for Operaton DMN Plugin
 * 
 * File: includes/class-operaton-dmn-performance.php
 * 
 * @package OperatonDMN
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Operaton_DMN_Performance_Monitor {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Start time of monitoring
     */
    private $start_time;
    
    /**
     * Performance milestones
     */
    private $milestones = array();
    
    /**
     * Memory usage tracking
     */
    private $memory_usage = array();
    
    /**
     * Request tracking
     */
    private $request_data = array();
    
    /**
     * Debug mode flag
     */
    private $debug_enabled = false;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize performance monitoring
     */
    private function __construct() {
        $this->start_time = microtime(true);
        $this->debug_enabled = defined('WP_DEBUG') && WP_DEBUG;
        
        // Record initial state
        $this->mark('monitoring_start', 'Performance monitoring initialized');
        
        // Track request data
        $this->request_data = array(
            'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'is_admin' => is_admin(),
            'is_ajax' => wp_doing_ajax(),
            'timestamp' => current_time('mysql')
        );
        
        // Add shutdown hook for final summary
        add_action('shutdown', array($this, 'log_final_summary'), 999);
    }
    
    /**
     * Mark a performance milestone
     * 
     * @param string $name Milestone name
     * @param string $details Optional details
     * @param array $context Optional context data
     */
    public function mark($name, $details = '', $context = array()) {
        $current_time = microtime(true);
        $elapsed = ($current_time - $this->start_time) * 1000; // Convert to milliseconds
        $memory = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        
        $milestone = array(
            'name' => $name,
            'time_ms' => round($elapsed, 2),
            'memory_current' => $memory,
            'memory_peak' => $memory_peak,
            'memory_current_formatted' => $this->format_bytes($memory),
            'memory_peak_formatted' => $this->format_bytes($memory_peak),
            'details' => $details,
            'context' => $context,
            'timestamp' => $current_time
        );
        
        $this->milestones[$name] = $milestone;
        
        // Log immediately if debug enabled
        if ($this->debug_enabled) {
            $this->log_milestone($milestone);
        }
        
        return $milestone;
    }
    
    /**
     * Log a single milestone
     */
    private function log_milestone($milestone) {
        $context_str = '';
        if (!empty($milestone['context'])) {
            $context_str = ' | Context: ' . wp_json_encode($milestone['context']);
        }
        
        error_log(sprintf(
            '⏱️ Operaton Performance: %s = %sms (Memory: %s, Peak: %s)%s%s',
            $milestone['name'],
            $milestone['time_ms'],
            $milestone['memory_current_formatted'],
            $milestone['memory_peak_formatted'],
            $milestone['details'] ? ' - ' . $milestone['details'] : '',
            $context_str
        ));
    }
    
    /**
     * Start timing a specific operation
     * 
     * @param string $operation_name Name of the operation
     * @return string Timer ID for stopping
     */
    public function start_timer($operation_name) {
        $timer_id = $operation_name . '_' . uniqid();
        $this->mark($timer_id . '_start', 'Started: ' . $operation_name);
        return $timer_id;
    }
    
    /**
     * Stop timing a specific operation
     * 
     * @param string $timer_id Timer ID from start_timer
     * @param string $details Optional details
     */
    public function stop_timer($timer_id, $details = '') {
        $start_key = $timer_id . '_start';
        $end_key = $timer_id . '_end';
        
        if (!isset($this->milestones[$start_key])) {
            if ($this->debug_enabled) {
                error_log('⚠️ Operaton Performance: Timer not found: ' . $timer_id);
            }
            return;
        }
        
        $this->mark($end_key, 'Finished: ' . str_replace('_' . explode('_', $timer_id)[1], '', $timer_id));
        
        // Calculate duration
        $start_time = $this->milestones[$start_key]['time_ms'];
        $end_time = $this->milestones[$end_key]['time_ms'];
        $duration = $end_time - $start_time;
        
        // Log duration
        if ($this->debug_enabled) {
            error_log(sprintf(
                '⏳ Operaton Performance: %s took %sms%s',
                str_replace('_' . explode('_', $timer_id)[1], '', $timer_id),
                round($duration, 2),
                $details ? ' - ' . $details : ''
            ));
        }
        
        return $duration;
    }
    
    /**
     * Get performance summary
     * 
     * @return array Complete performance data
     */
    public function get_summary() {
        $total_time = (microtime(true) - $this->start_time) * 1000;
        $peak_memory = memory_get_peak_usage(true);
        
        return array(
            'total_time_ms' => round($total_time, 2),
            'peak_memory' => $peak_memory,
            'peak_memory_formatted' => $this->format_bytes($peak_memory),
            'milestones' => $this->milestones,
            'milestone_count' => count($this->milestones),
            'request_data' => $this->request_data,
            'slowest_operations' => $this->get_slowest_operations(),
            'memory_intensive_operations' => $this->get_memory_intensive_operations()
        );
    }
    
    /**
     * Get slowest operations
     */
    private function get_slowest_operations($limit = 5) {
        $operations = array();
        
        foreach ($this->milestones as $name => $milestone) {
            if (strpos($name, '_start') !== false) {
                $base_name = str_replace('_start', '', $name);
                $end_name = $base_name . '_end';
                
                if (isset($this->milestones[$end_name])) {
                    $duration = $this->milestones[$end_name]['time_ms'] - $milestone['time_ms'];
                    $operations[] = array(
                        'name' => $base_name,
                        'duration_ms' => round($duration, 2)
                    );
                }
            }
        }
        
        // Sort by duration descending
        usort($operations, function($a, $b) {
            return $b['duration_ms'] <=> $a['duration_ms'];
        });
        
        return array_slice($operations, 0, $limit);
    }
    
    /**
     * Get memory intensive operations
     */
    private function get_memory_intensive_operations($limit = 5) {
        $operations = $this->milestones;
        
        // Sort by peak memory descending
        uasort($operations, function($a, $b) {
            return $b['memory_peak'] <=> $a['memory_peak'];
        });
        
        return array_slice($operations, 0, $limit, true);
    }
    
    /**
     * Log final summary on shutdown
     */
    public function log_final_summary() {
        if (!$this->debug_enabled) {
            return;
        }
        
        $summary = $this->get_summary();
        
        error_log('=== OPERATON DMN PERFORMANCE SUMMARY ===');
        error_log('Request: ' . $this->request_data['method'] . ' ' . $this->request_data['url']);
        error_log('Total Time: ' . $summary['total_time_ms'] . 'ms');
        error_log('Peak Memory: ' . $summary['peak_memory_formatted']);
        error_log('Milestones: ' . $summary['milestone_count']);
        
        if (!empty($summary['slowest_operations'])) {
            error_log('Slowest Operations:');
            foreach ($summary['slowest_operations'] as $op) {
                error_log('  ' . $op['name'] . ': ' . $op['duration_ms'] . 'ms');
            }
        }
        
        error_log('Key Milestones:');
        foreach ($this->milestones as $name => $milestone) {
            if (!strpos($name, '_start') && !strpos($name, '_end')) {
                error_log('  ' . $name . ': ' . $milestone['time_ms'] . 'ms');
            }
        }
        
        error_log('========================================');
    }
    
    /**
     * Get performance data for admin dashboard
     */
    public function get_dashboard_data() {
        $summary = $this->get_summary();
        
        return array(
            'current_request' => array(
                'total_time' => $summary['total_time_ms'],
                'peak_memory' => $summary['peak_memory_formatted'],
                'milestone_count' => $summary['milestone_count']
            ),
            'recent_performance' => $this->get_recent_performance_stats(),
            'recommendations' => $this->get_performance_recommendations($summary)
        );
    }
    
    /**
     * Get recent performance statistics
     */
    private function get_recent_performance_stats() {
        $stats = get_transient('operaton_performance_stats');
        
        if (!$stats) {
            $stats = array(
                'requests_today' => 0,
                'avg_response_time' => 0,
                'peak_memory_today' => 0,
                'last_updated' => current_time('mysql')
            );
        }
        
        return $stats;
    }
    
    /**
     * Store performance statistics
     */
    public function store_performance_stats() {
        $summary = $this->get_summary();
        $stats = $this->get_recent_performance_stats();
        
        // Update statistics
        $stats['requests_today']++;
        $stats['avg_response_time'] = (($stats['avg_response_time'] * ($stats['requests_today'] - 1)) + $summary['total_time_ms']) / $stats['requests_today'];
        $stats['peak_memory_today'] = max($stats['peak_memory_today'], $summary['peak_memory']);
        $stats['last_updated'] = current_time('mysql');
        
        // Store for 24 hours
        set_transient('operaton_performance_stats', $stats, DAY_IN_SECONDS);
    }
    
    /**
     * Get performance recommendations
     */
    private function get_performance_recommendations($summary) {
        $recommendations = array();
        
        if ($summary['total_time_ms'] > 2000) {
            $recommendations[] = 'Request took longer than 2 seconds. Consider optimization.';
        }
        
        if ($summary['peak_memory'] > 64 * 1024 * 1024) { // 64MB
            $recommendations[] = 'High memory usage detected. Review memory-intensive operations.';
        }
        
        if ($summary['milestone_count'] > 50) {
            $recommendations[] = 'Many performance milestones. Consider reducing monitoring in production.';
        }
        
        return $recommendations;
    }
    
    /**
     * Format bytes for human reading
     */
    private function format_bytes($size, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Enable/disable debug mode
     */
    public function set_debug_mode($enabled) {
        $this->debug_enabled = $enabled;
    }
    
    /**
     * Reset all performance data
     */
    public function reset() {
        $this->milestones = array();
        $this->start_time = microtime(true);
        $this->mark('monitoring_reset', 'Performance monitoring reset');
    }
}