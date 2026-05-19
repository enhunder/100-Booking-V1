<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function studiobook_set_default_settings() {
    $defaults = [
        'services' => [
            [
                'id'    => 'studio_session',
                'name'  => 'Studio Session',
                'desc'  => '3 Stunden Studiozeit inkl. Equipment',
                'price' => '0',
            ],
            [
                'id'    => 'mixing_mastering',
                'name'  => 'Mixing & Mastering',
                'desc'  => 'Professionelles Mixing & Mastering deines Tracks',
                'price' => '0',
            ],
        ],
        'slots'              => ['10:00', '14:00', '18:00', '22:00'],
        'session_hours'      => 3,
        'buffer_hours'       => 1,
        'currency'           => 'EUR',

        // Stripe
        'stripe_mode'        => 'test',
        'stripe_pk_test'     => '',
        'stripe_sk_test'     => '',
        'stripe_pk_live'     => '',
        'stripe_sk_live'     => '',

        // PayPal
        'paypal_mode'              => 'sandbox',
        'paypal_client_id_sandbox' => '',
        'paypal_secret_sandbox'    => '',
        'paypal_client_id_live'    => '',
        'paypal_secret_live'       => '',

        'notification_email' => get_option('admin_email'),
        'booking_days_ahead' => 60,

        // MwSt
        'tax_enabled'        => false,
        'tax_rate'           => 19,
        'tax_inclusive'      => true,
        'booking_counter'    => 0,

        // Buchungsregeln
        'min_advance_hours_online' => 24,
        'min_advance_hours_cash'   => 24,
        'cancel_free_hours'        => 12,
        'cancel_partial_hours'     => 24,   // danach nur 50%
        'cancel_partial_percent'   => 50,
        'mm_turnaround_days'       => '3-5 Werktage',

        // Rechnungsdetails
        'invoice_company'    => '',
        'invoice_name'       => '',
        'invoice_address'    => '',
        'invoice_plz'        => '',
        'invoice_city'       => '',
        'invoice_tax_id'     => '',
        'invoice_iban'       => '',
        'invoice_bic'        => '',
        'invoice_bank'       => '',
        'invoice_counter'    => 0,
        'invoice_prefix'     => 'RE',

        'show_test_badge'     => true,

        'sidebar_logo_url'   => '',
        'sidebar_logo_link'  => home_url('/'),

        // Texte
        'text_step1_title'                 => 'Welchen Service buchst du?',
        'text_step2_cal_title'             => 'Datum & Uhrzeit wählen',
        'text_step2_upload_title'          => 'Datei hochladen',
        'text_step3_title'                 => 'Deine Daten',
        'text_step4_title'                 => 'Zahlung & Bestätigung',
        'text_privacy_label'               => 'Ich habe die <a href="/datenschutz" target="_blank">Datenschutzerklärung</a> gelesen und stimme zu.',
        'text_agb_label'                   => 'Ich habe die <a href="/agb" target="_blank">AGB</a> gelesen und stimme zu.',
        'text_widerruf_label'              => 'Ich habe die <a href="/widerruf" target="_blank">Widerrufsbelehrung</a> zur Kenntnis genommen.',
        'text_cancel_policy'               => 'Kostenlose Stornierung bis 24h vor dem Termin. Danach werden 50% des Betrags einbehalten.',
        'text_success_title'               => 'Buchung bestätigt!',
        'text_success_session'             => 'Du bekommst eine Bestätigungsmail. Wir freuen uns auf dich!',
        'text_success_mix'                 => 'Wir melden uns sobald dein Mix fertig ist!',
        'text_upload_drag'                 => 'Datei hier reinziehen',
        'text_upload_formats'              => 'MP3 · WAV · FLAC · AIFF · OGG · M4A · ZIP · RAR — max. 200MB',
        'text_cash_info'                   => 'Du bezahlst bequem vor Ort in bar. Deine Buchung wird direkt reserviert.',
        'text_message_placeholder_session' => 'Was bringst du mit? Besondere Wünsche?',
        'text_message_placeholder_mix'     => 'z.B. Genre, Referenz-Sound, besondere Wünsche…',
        'placeholder_name'                 => 'Vor- und Nachname',
        'placeholder_email'                => 'deine@mail.de',
        'placeholder_phone'                => '+49 ...',
        'placeholder_artist'               => 'Dein Künstlername (optional)',
        'placeholder_address'              => 'Straße & Hausnummer',
        'placeholder_plz'                  => 'PLZ',
        'placeholder_city'                 => 'Stadt',
        'placeholder_coupon'               => 'Gutschein- oder Rabattcode',


        // Farben
        'color_accent'      => '#553eff',
        'color_bg'          => '#000000',
        'color_surface'     => '#111111',
        'color_border'      => '#222222',
        'color_text'        => '#ffffff',
        'color_muted'       => '#888888',
        'color_btn_text'    => '#ffffff',
    ];

    $existing = get_option( 'studiobook_settings', [] );
    // Merge: neue Keys aus $defaults einsetzen, bestehende behalten
    $merged = array_merge( $defaults, $existing );
    update_option( 'studiobook_settings', $merged );
}

function studiobook_get_settings() {
    return get_option( 'studiobook_settings', [] );
}

function studiobook_get_setting( $key, $default = null ) {
    $s = studiobook_get_settings();
    return isset( $s[$key] ) ? $s[$key] : $default;
}

// Nächste Rechnungsnummer generieren
function studiobook_next_invoice_number() {
    $s       = studiobook_get_settings();
    $counter = intval( $s['invoice_counter'] ?? 0 ) + 1;
    $prefix  = $s['invoice_prefix'] ?? 'RE';
    $year    = date('Y');

    $s['invoice_counter'] = $counter;
    update_option( 'studiobook_settings', $s );

    return $prefix . '-' . $year . '-' . str_pad( $counter, 3, '0', STR_PAD_LEFT );
}
