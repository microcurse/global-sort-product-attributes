<?php
/**
 * Plugin Name: Global Sort Product Attributes
 * Plugin URI: https://forbesindustries.com
 * Description: A WooCommerce plugin that allows users to reorder global product attributes for variable products across all products or within selected categories.
 * Version: 1.0.0
 * Author: Marc Maninang
 * Author URI: https://forbesindustries.com
 * Text Domain: global-sort-product-attributes
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('GSPA_VERSION', '1.0.0');
define('GSPA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GSPA_PLUGIN_URL', plugin_dir_url(__FILE__));

// Ensure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// Include required files
require_once GSPA_PLUGIN_DIR . 'includes/class-gspa-core.php';
require_once GSPA_PLUGIN_DIR . 'includes/class-gspa-admin.php';

// Initialize the plugin
function gspa_init() {
    // Initialize core functionality
    $gspa_core = new GSPA_Core();
    $gspa_core->init();

    // Initialize admin interface if in admin area
    if (is_admin()) {
        $gspa_admin = new GSPA_Admin();
        $gspa_admin->init();
    }
}
add_action('plugins_loaded', 'gspa_init');

// Activation hook
register_activation_hook(__FILE__, 'gspa_activate');
function gspa_activate() {
    // Create necessary database tables and options
    update_option('gspa_version', GSPA_VERSION);
    
    // Create log table for tracking changes
    global $wpdb;
    $table_name = $wpdb->prefix . 'gspa_logs';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        product_id bigint(20) NOT NULL,
        category_id bigint(20),
        old_order text NOT NULL,
        new_order text NOT NULL,
        user_id bigint(20) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'gspa_deactivate');
function gspa_deactivate() {
    // Clean up if necessary
} 