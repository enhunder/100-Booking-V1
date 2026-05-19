<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ─────────────────────────────────────────────
//  STRIPE
// ─────────────────────────────────────────────

add_action( 'wp_ajax_studiobook_stripe_create_intent', 'studiobook_stripe_create_intent' );
add_action( 'wp_ajax_nopriv_studiobook_stripe_create_intent', 'studiobook_stripe_create_intent' );
function studiobook_stripe_create_intent() {
    check_ajax_referer( 'studiobook_nonce', 'nonce' );

    $mode       = studiobook_get_setting( 'stripe_mode', 'test' );
    $secret_key = $mode === 'live'
        ? studiobook_get_setting( 'stripe_sk_live' )
        : studiobook_get_setting( 'stripe_sk_test' );

    if ( ! $secret_key ) {
        wp_send_json_error( ['message' => 'Stripe nicht konfiguriert.'] );
    }

    $amount   = intval( floatval( $_POST['price'] ) * 100 ); // Cent
    $currency = strtolower( studiobook_get_setting( 'currency', 'EUR' ) );

    $response = wp_remote_post( 'https://api.stripe.com/v1/payment_intents', [
        'headers' => [
            'Authorization' => 'Bearer ' . $secret_key,
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ],
        'body' => http_build_query([
            'amount'   => $amount,
            'currency' => $currency,
            'metadata' => [
                'service' => sanitize_text_field( $_POST['service'] ?? '' ),
                'date'    => sanitize_text_field( $_POST['date'] ?? '' ),
                'slot'    => sanitize_text_field( $_POST['slot'] ?? '' ),
            ],
        ]),
    ]);

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( ['message' => 'Stripe-Fehler: ' . $response->get_error_message()] );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( isset( $body['error'] ) ) {
        wp_send_json_error( ['message' => $body['error']['message']] );
    }

    wp_send_json_success([
        'client_secret' => $body['client_secret'],
        'payment_id'    => $body['id'],
    ]);
}

/**
 * Stripe Zahlung bestätigen & Buchung speichern
 */
add_action( 'wp_ajax_studiobook_stripe_confirm', 'studiobook_stripe_confirm' );
add_action( 'wp_ajax_nopriv_studiobook_stripe_confirm', 'studiobook_stripe_confirm' );
function studiobook_stripe_confirm() {
    check_ajax_referer( 'studiobook_nonce', 'nonce' );

    $mode       = studiobook_get_setting( 'stripe_mode', 'test' );
    $secret_key = $mode === 'live'
        ? studiobook_get_setting( 'stripe_sk_live' )
        : studiobook_get_setting( 'stripe_sk_test' );

    $payment_intent_id = sanitize_text_field( $_POST['payment_intent_id'] ?? '' );

    // Payment Intent Status bei Stripe prüfen
    $response = wp_remote_get( 'https://api.stripe.com/v1/payment_intents/' . $payment_intent_id, [
        'headers' => [
            'Authorization' => 'Bearer ' . $secret_key,
        ],
    ]);

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( ! isset( $body['status'] ) || $body['status'] !== 'succeeded' ) {
        wp_send_json_error( ['message' => 'Zahlung nicht erfolgreich.'] );
    }

    // Buchung speichern
    $booking_id = studiobook_save_booking([
        'service'        => sanitize_text_field( $_POST['service'] ?? '' ),
        'date'           => sanitize_text_field( $_POST['date'] ?? '' ),
        'slot'           => sanitize_text_field( $_POST['slot'] ?? '' ),
        'name'           => sanitize_text_field( $_POST['name'] ?? '' ),
        'email'          => sanitize_email( $_POST['email'] ?? '' ),
        'phone'          => sanitize_text_field( $_POST['phone'] ?? '' ),
        'message'        => sanitize_textarea_field( $_POST['message'] ?? '' ),
        'price'          => floatval( $_POST['price'] ?? 0 ),
        'payment_method' => 'stripe',
        'payment_status' => 'paid',
        'payment_id'     => $payment_intent_id,
        'user_id'        => intval($_POST['user_id'] ?? 0) ?: (is_user_logged_in() ? get_current_user_id() : null),
    ]);

    if ( ! $booking_id ) {
        wp_send_json_error( ['message' => 'Buchung konnte nicht gespeichert werden.'] );
    }

    studiobook_send_confirmation_mails( $booking_id );

    wp_send_json_success( ['booking_id' => $booking_id] );
}

// ─────────────────────────────────────────────
//  PAYPAL
// ─────────────────────────────────────────────

add_action( 'wp_ajax_studiobook_paypal_create_order', 'studiobook_paypal_create_order' );
add_action( 'wp_ajax_nopriv_studiobook_paypal_create_order', 'studiobook_paypal_create_order' );
function studiobook_paypal_create_order() {
    check_ajax_referer( 'studiobook_nonce', 'nonce' );

    $mode      = studiobook_get_setting( 'paypal_mode', 'sandbox' );
    $client_id = $mode === 'live'
        ? studiobook_get_setting( 'paypal_client_id_live' )
        : studiobook_get_setting( 'paypal_client_id_sandbox' );

    // PayPal Access Token holen
    $token_response = wp_remote_post(
        $mode === 'live'
            ? 'https://api-m.paypal.com/v1/oauth2/token'
            : 'https://api-m.sandbox.paypal.com/v1/oauth2/token',
        [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . studiobook_get_setting( 'paypal_secret_' . $mode ) ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body' => 'grant_type=client_credentials',
        ]
    );

    $token_body   = json_decode( wp_remote_retrieve_body( $token_response ), true );
    $access_token = $token_body['access_token'] ?? '';

    if ( ! $access_token ) {
        wp_send_json_error( ['message' => 'PayPal Auth fehlgeschlagen.'] );
    }

    $price    = number_format( floatval( $_POST['price'] ?? 0 ), 2, '.', '' );
    $currency = studiobook_get_setting( 'currency', 'EUR' );

    $order_response = wp_remote_post(
        $mode === 'live'
            ? 'https://api-m.paypal.com/v2/checkout/orders'
            : 'https://api-m.sandbox.paypal.com/v2/checkout/orders',
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode([
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'amount' => [
                        'currency_code' => $currency,
                        'value'         => $price,
                    ],
                    'description' => sanitize_text_field( $_POST['service_name'] ?? 'Studio Session' ),
                ]],
            ]),
        ]
    );

    $order_body = json_decode( wp_remote_retrieve_body( $order_response ), true );

    if ( ! isset( $order_body['id'] ) ) {
        wp_send_json_error( ['message' => 'PayPal Order konnte nicht erstellt werden.'] );
    }

    wp_send_json_success( ['order_id' => $order_body['id']] );
}

/**
 * PayPal Zahlung capturen & Buchung speichern
 */
add_action( 'wp_ajax_studiobook_paypal_capture', 'studiobook_paypal_capture' );
add_action( 'wp_ajax_nopriv_studiobook_paypal_capture', 'studiobook_paypal_capture' );
function studiobook_paypal_capture() {
    check_ajax_referer( 'studiobook_nonce', 'nonce' );

    $mode      = studiobook_get_setting( 'paypal_mode', 'sandbox' );
    $client_id = $mode === 'live'
        ? studiobook_get_setting( 'paypal_client_id_live' )
        : studiobook_get_setting( 'paypal_client_id_sandbox' );

    $order_id = sanitize_text_field( $_POST['order_id'] ?? '' );

    // Access Token
    $token_response = wp_remote_post(
        $mode === 'live'
            ? 'https://api-m.paypal.com/v1/oauth2/token'
            : 'https://api-m.sandbox.paypal.com/v1/oauth2/token',
        [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . studiobook_get_setting( 'paypal_secret_' . $mode ) ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body' => 'grant_type=client_credentials',
        ]
    );
    $token_body   = json_decode( wp_remote_retrieve_body( $token_response ), true );
    $access_token = $token_body['access_token'] ?? '';

    // Capture
    $capture_response = wp_remote_post(
        ( $mode === 'live'
            ? 'https://api-m.paypal.com/v2/checkout/orders/'
            : 'https://api-m.sandbox.paypal.com/v2/checkout/orders/' ) . $order_id . '/capture',
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'body' => '{}',
        ]
    );

    $capture_body = json_decode( wp_remote_retrieve_body( $capture_response ), true );

    if ( ( $capture_body['status'] ?? '' ) !== 'COMPLETED' ) {
        wp_send_json_error( ['message' => 'PayPal Zahlung fehlgeschlagen.'] );
    }

    $booking_id = studiobook_save_booking([
        'service'        => sanitize_text_field( $_POST['service'] ?? '' ),
        'date'           => sanitize_text_field( $_POST['date'] ?? '' ),
        'slot'           => sanitize_text_field( $_POST['slot'] ?? '' ),
        'name'           => sanitize_text_field( $_POST['name'] ?? '' ),
        'email'          => sanitize_email( $_POST['email'] ?? '' ),
        'phone'          => sanitize_text_field( $_POST['phone'] ?? '' ),
        'message'        => sanitize_textarea_field( $_POST['message'] ?? '' ),
        'price'          => floatval( $_POST['price'] ?? 0 ),
        'payment_method' => 'paypal',
        'payment_status' => 'paid',
        'payment_id'     => $order_id,
        'user_id'        => intval($_POST['user_id'] ?? 0) ?: (is_user_logged_in() ? get_current_user_id() : null),
    ]);

    if ( ! $booking_id ) {
        wp_send_json_error( ['message' => 'Buchung konnte nicht gespeichert werden.'] );
    }

    studiobook_send_confirmation_mails( $booking_id );

    wp_send_json_success( ['booking_id' => $booking_id] );
}
