<?php
/**
 * Plugin Name: 100Studios Booking System
 * Plugin URI: https://100studios.de
 * Description: Custom Booking System für 100Studios – Sessions buchen & direkt bezahlen.
 * Version: 1.0.0
 * Author: EN100 / Enrico Kausler
 * Text Domain: 100studios-booking
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'STUDIOBOOK_VERSION', '1.0.0' );
define( 'STUDIOBOOK_PATH', plugin_dir_path( __FILE__ ) );
define( 'STUDIOBOOK_URL', plugin_dir_url( __FILE__ ) );

// Includes
require_once STUDIOBOOK_PATH . 'includes/database.php';
require_once STUDIOBOOK_PATH . 'includes/settings.php';
require_once STUDIOBOOK_PATH . 'includes/booking.php';
require_once STUDIOBOOK_PATH . 'includes/payment.php';
require_once STUDIOBOOK_PATH . 'includes/mailer.php';
require_once STUDIOBOOK_PATH . 'includes/shortcode.php';
require_once STUDIOBOOK_PATH . 'includes/auth.php';
require_once STUDIOBOOK_PATH . 'includes/portal.php';
require_once STUDIOBOOK_PATH . 'includes/invoice.php';
require_once STUDIOBOOK_PATH . 'admin/admin.php';


// ── Dashboard Page Template ──
add_filter( 'theme_page_templates', 'studiobook_register_template' );
function studiobook_register_template( $templates ) {
    $templates['studiobook-dashboard'] = '100Studios Dashboard';
    return $templates;
}

add_filter( 'template_include', 'studiobook_load_template' );
function studiobook_load_template( $template ) {
    if ( is_page() ) {
        $page_template = get_post_meta( get_the_ID(), '_wp_page_template', true );
        if ( $page_template === 'studiobook-dashboard' ) {
            $plugin_template = STUDIOBOOK_PATH . 'templates/dashboard-template.php';
            if ( file_exists( $plugin_template ) ) return $plugin_template;
        }
    }
    return $template;
}

// ── Kunden vom WP-Admin weghalten ──
add_action( 'admin_init', 'studiobook_redirect_customers' );
function studiobook_redirect_customers() {
    if ( is_user_logged_in() && ! current_user_can('manage_options') && ! wp_doing_ajax() ) {
        $dashboard_url = home_url('/dashboard');
        wp_redirect( $dashboard_url );
        exit;
    }
}

// ── Admin-Bar für eingeloggte Kunden verstecken ──
add_action( 'after_setup_theme', 'studiobook_hide_admin_bar' );
function studiobook_hide_admin_bar() {
    if ( is_user_logged_in() && ! current_user_can('manage_options') ) {
        show_admin_bar( false );
    }
}

// ── Nach Login zum Dashboard weiterleiten ──
add_filter( 'login_redirect', 'studiobook_login_redirect', 10, 3 );
function studiobook_login_redirect( $redirect_to, $request, $user ) {
    if ( isset( $user->roles ) && is_array( $user->roles ) ) {
        if ( ! in_array('administrator', $user->roles) && ! in_array('editor', $user->roles) ) {
            return home_url('/dashboard');
        }
    }
    return $redirect_to;
}

// Activation Hook – Datenbank erstellen
register_activation_hook( __FILE__, 'studiobook_activate' );
function studiobook_activate() {
    studiobook_create_tables();
    studiobook_set_default_settings();
}

// Enqueue Scripts & Styles
add_action( 'wp_enqueue_scripts', 'studiobook_enqueue_assets' );
function studiobook_enqueue_assets() {
    wp_enqueue_style( 'studiobook-style', STUDIOBOOK_URL . 'assets/css/booking.css', [], STUDIOBOOK_VERSION );
    wp_enqueue_style( 'studiobook-portal-style', STUDIOBOOK_URL . 'assets/css/portal.css', [], STUDIOBOOK_VERSION );
    wp_enqueue_script( 'studiobook-script', STUDIOBOOK_URL . 'assets/js/booking.js', ['jquery'], STUDIOBOOK_VERSION, true );
    wp_enqueue_script( 'studiobook-portal-script', STUDIOBOOK_URL . 'assets/js/portal.js', ['jquery'], STUDIOBOOK_VERSION, true );

    wp_localize_script( 'studiobook-script', 'studiobookAjax', [
        'ajaxurl'   => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'studiobook_nonce' ),
    ]);
}

add_action( 'admin_enqueue_scripts', 'studiobook_admin_assets' );
function studiobook_admin_assets( $hook ) {
    if ( strpos( $hook, '100studios' ) === false ) return;
    wp_enqueue_style( 'studiobook-admin-style', STUDIOBOOK_URL . 'assets/css/admin.css', [], STUDIOBOOK_VERSION );
    wp_enqueue_script( 'studiobook-admin-script', STUDIOBOOK_URL . 'assets/js/admin.js', ['jquery'], STUDIOBOOK_VERSION, true );
}
