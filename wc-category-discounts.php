<?php
/**
 * Plugin Name:     Adjective Digital - Woo Category Discounts
 * Plugin URI:      https://adjectivedigital.com.au
 * Description:     A plugin for easily applying discounts to all products in a category
 * Author:          Adjective Digital
 * Author URI:      https://adjectivedigital.com.au
 * Text Domain:     wc-category-discounts
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Wc_Category_Discounts
 */

 if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants.
define('WC_CATEGORY_DISCOUNTS_VERSION', '0.1.0');
define('WC_CATEGORY_DISCOUNTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_CATEGORY_DISCOUNTS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include the main class file.
require_once WC_CATEGORY_DISCOUNTS_PLUGIN_DIR . 'includes/class-wc-category-discounts.php';

// Initialize the plugin.
Wc_Category_Discounts::get_instance();


