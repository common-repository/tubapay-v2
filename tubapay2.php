<?php

/**
 * Plugin Name: Tubapay
 * Description: Płatność TubaPay
 * Plugin URI:  https://tubapay.pl/
 * Version:     3.0.3
 * Author:      cftb.pl
 * Author URI:  https://cftb.pl/
 * Text Domain: tubapay-v2
 * License:     GNUGPLv3
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// fix na podwójny plugin
if(class_exists('TubaPay2_REST_API')) {
    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    deactivate_plugins( 'tubapay-v2/tubapay2.php' );
}

require_once 'functions/functions.php';

register_activation_hook( __FILE__, 'tubapay2_plugin_activate' );

if(!function_exists('tubapay2_admin_init')) {
    function tubapay2_admin_init() {
        tubapay2_requirements_check();
        tubapay2_load_plugin();
        tubapay2_label_update();
    }
}
add_action( 'admin_init', 'tubapay2_admin_init' );

if(!function_exists('tubapay2_plugins_loaded')) {
    function tubapay2_plugins_loaded() {
        require_once 'functions/tubapay-wc-gateway-class.php';

    }
}
add_action('plugins_loaded', 'tubapay2_plugins_loaded');

if(!function_exists('tubapay2_init')) {
    function tubapay2_init() {
        tubapay2_wpex_wc_register_post_statuses();

    }
}
add_filter( 'init', 'tubapay2_init' );

add_filter( 'user_has_cap', 'tubapay2_user_has_cap', 9999, 3 );

if(!function_exists('tubapay2_safe_style_css')) {
    function tubapay2_safe_style_css($styles) {
        $styles[] = 'display';
        return $styles;
    }
}
add_filter( 'safe_style_css', 'tubapay2_safe_style_css' );

if(!function_exists('tubapay2_add_meta_boxes')) {
    function tubapay2_add_meta_boxes() {
        remove_meta_box('postcustom', 'shop_order', 'normal');
    }
}
add_action( 'add_meta_boxes', 'tubapay2_add_meta_boxes', 90 );

// Endpoint
add_action( 'parse_request', function( $wp ){
    if ( preg_match( '#^tubapay_endpoint/?#', $wp->request, $matches ) ) {
        include_once plugin_dir_path( __FILE__ ) . 'functions/notification-handler.php';
        exit;
    }
});

if(!function_exists('tubapay2_admin_enqueue_scripts')) {
    function tubapay2_admin_enqueue_scripts($hook) {
        wp_enqueue_script('tubapay2_admin_js', plugins_url('assets/js/admin.js', __FILE__), array('jquery'), false, false);
    }
}
add_action('admin_enqueue_scripts', 'tubapay2_admin_enqueue_scripts');

add_action( 'manage_shop_order_posts_custom_column' , 'tubapay2_manage_shop_order_posts_custom_column', 20, 2 );

add_action( 'before_woocommerce_pay', 'tubapay2_before_woocommerce_pay' );

if(!function_exists('tubapay2_before_woocommerce_init')) {
    function tubapay2_before_woocommerce_init() {
        tubapay2_declare_cart_checkout_blocks_compatibility();
    }
}
add_action('before_woocommerce_init', 'tubapay2_before_woocommerce_init');

if(!function_exists('tubapay2_woocommerce_blocks_loaded')) {
    function tubapay2_woocommerce_blocks_loaded() {
        tubapay2_register_order_approval_payment_method_type();
    }
}
add_action( 'woocommerce_blocks_loaded', 'tubapay2_woocommerce_blocks_loaded' );

if(!function_exists('tubapay2_woocommerce_payment_gateways')) {
    function tubapay2_woocommerce_payment_gateways($methods) {
        $methods[] = 'WC_Gateway_TubaPay2';
        return $methods;
    }
}
add_filter( 'woocommerce_payment_gateways', 'tubapay2_woocommerce_payment_gateways' );

if(!function_exists('tubapay2_woocommerce_admin_order_data_after_billing_address')) {
    function tubapay2_woocommerce_admin_order_data_after_billing_address($order) {
        tubapay2_checkout_field_display_admin_order_meta($order);
        tubapay2_display_option_near_admin_order_billing_address($order);
    }
}
add_action( 'woocommerce_admin_order_data_after_billing_address', 'tubapay2_woocommerce_admin_order_data_after_billing_address', 10, 1 );

if(!function_exists('tubapay2_woocommerce_available_payment_gateways')) {
    function tubapay2_woocommerce_available_payment_gateways($available_gateways) {
        $available_gateways = tubapay2_turn_off($available_gateways);
        $available_gateways = tubapay2_direct_checkout_payment($available_gateways);
        return $available_gateways;
    }
}
add_filter( 'woocommerce_available_payment_gateways', 'tubapay2_woocommerce_available_payment_gateways' );

add_filter( 'wc_order_statuses', 'tubapay2_wc_order_statuses' );

add_filter( 'woocommerce_order_is_paid_statuses', 'tubapay2_woocommerce_order_is_paid_statuses' );

add_filter( 'woocommerce_valid_order_statuses_for_payment', 'tubapay2_woocommerce_valid_order_statuses_for_payment', 10, 2);

add_filter( 'woocommerce_add_to_cart_validation', 'tubapay2_woocommerce_add_to_cart_validation', 20, 3 );

add_action( 'woocommerce_before_add_to_cart_form', 'tubapay2_woocommerce_before_add_to_cart_form', 10, 0 );

add_filter( 'woocommerce_gateway_description', 'tubapay2_woocommerce_gateway_description', 20, 2 );

add_action( 'woocommerce_checkout_process', 'tubapay2_woocommerce_checkout_process' );

add_action( 'woocommerce_checkout_create_order', 'tubapay2_woocommerce_checkout_create_order', 10, 2 );

add_action( 'woocommerce_get_order_item_totals', 'tubapay2_woocommerce_get_order_item_totals', 10, 3 );

add_action( 'woocommerce_admin_order_data_after_order_details', 'tubapay2_woocommerce_admin_order_data_after_order_details' );

add_action( 'woocommerce_order_status_changed', 'tubapay2_woocommerce_order_status_changed', 10, 3);

add_filter( 'manage_edit-shop_order_columns', 'tubapay2_add_admin_order_list_custom_column', 20 );

add_filter( 'manage_woocommerce_page_wc-orders_columns', 'tubapay2_add_wc_order_list_custom_column' );

add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'tubapay2_display_wc_order_list_custom_column_content', 10, 2);

//add_filter( 'woocommerce_get_price_html', 'tubapay_display_calc_box', 10, 2 );

//// Schedule Cron Job Event
//if (!wp_next_scheduled('tubapay2_labels_update')) {
//    wp_schedule_event( time(), 'daily', 'tubapay2_labels_update' );
//}
//add_action( 'tubapay2_labels_update', 'tubapay2_label_update' );
