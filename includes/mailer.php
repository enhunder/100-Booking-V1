<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function studiobook_send_confirmation_mails( $booking_id, $send_invoice = true ) {
    global $wpdb;
    $booking = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}studiobook_bookings WHERE id = %d", $booking_id
    ));
    if ( ! $booking ) return;

    $service_labels = ['studio_session' => 'Studio Session', 'mixing_mastering' => 'Mixing & Mastering'];
    $service_label  = $service_labels[$booking->service] ?? $booking->service;
    $date_str       = $booking->booking_date ? date('d.m.Y', strtotime($booking->booking_date)) : '';

    $cancel_url    = home_url('/?studiobook_cancel=' . $booking->cancel_token);
    $cancel_policy = studiobook_get_setting('text_cancel_policy', 'Kostenlose Stornierung bis 12h vor dem Termin.');
    $turnaround    = studiobook_get_setting('mm_turnaround_days', '3-5 Werktage');

    $s   = studiobook_get_settings();
    $tax = ! empty($s['tax_enabled']);

    $price_gross = floatval($booking->price_gross ?: $booking->price);
    $price_line  = number_format($price_gross, 2, ',', '.') . ' EUR';
    if ($tax) $price_line .= ' (inkl. ' . number_format(floatval($booking->tax_rate), 0) . '% MwSt.)';

    $attachments = [];
    if ($send_invoice && $booking->payment_method !== 'test') {
        $pdf = studiobook_generate_invoice_pdf($booking);
        if ($pdf && file_exists($pdf)) $attachments[] = $pdf;
    }

    // ── Mail an KUNDEN ──
    $customer_subject = '✅ Buchungsbestätigung – ' . $service_label . ($date_str ? ' am ' . $date_str : '') . ' | 100Studios';
    $customer_body    = "Hallo {$booking->customer_name},\n\n";
    $customer_body   .= "deine Buchung bei 100Studios ist bestätigt!\n\n";
    $customer_body   .= "━━━━━━━━━━━━━━━━━━━━━━\n";
    $customer_body   .= "Service:   {$service_label}\n";
    if ($date_str)               $customer_body .= "Datum:     {$date_str}\n";
    if ($booking->slot_time)     $customer_body .= "Uhrzeit:   {$booking->slot_time} Uhr\n";
    if ($booking->service === 'mixing_mastering') {
        $customer_body .= "Bearbeitung: ca. {$turnaround}\n";
        if ($booking->file_name) $customer_body .= "Deine Datei: {$booking->file_name}\n";
    }
    $customer_body .= "Betrag:    {$price_line}\n";
    $customer_body .= "Zahlung:   " . ucfirst($booking->payment_method) . "\n";
    $customer_body .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";

    if ($booking->service === 'studio_session') {
        $customer_body .= "Adresse: Ulmenstraße 16, 47226 Duisburg\n\n";
        $customer_body .= "Stornobedingungen:\n{$cancel_policy}\n";
        $customer_body .= "Jetzt stornieren: {$cancel_url}\n\n";
    }

    if ($send_invoice && $booking->payment_method !== 'test') {
        $customer_body .= "Deine Rechnung findest du im Anhang.\n\n";
    }

    $customer_body .= "Stay creative 🎤\nEN100 / 100Studios\nhttps://100studios.de";

    wp_mail(
        $booking->customer_email,
        $customer_subject,
        $customer_body,
        ['From: 100Studios <noreply@100studios.de>'],
        $attachments
    );

    // ── Mail an ADMIN (intern) ──
    $is_test       = $booking->payment_method === 'test';
    $admin_subject = ($is_test ? '🧪 TEST ' : '🎙️ ') . 'Neue Buchung #' . $booking->id . ' – ' . $service_label . ($date_str ? ' am ' . $date_str : '');
    $artist_line   = $booking->artist_name ? "\nKünstlername: {$booking->artist_name}" : '';

    $admin_body  = ($is_test ? "[TEST-BUCHUNG]\n\n" : "");
    $admin_body .= "Neue Buchung eingegangen!\n\n";
    $admin_body .= "━━━━━━━━━━━━━━━━━━━━━━\n";
    $admin_body .= "Buchungs-ID: #{$booking->id}\n";
    $admin_body .= "Service:     {$service_label}\n";
    if ($date_str)           $admin_body .= "Datum:       {$date_str}\n";
    if ($booking->slot_time) $admin_body .= "Uhrzeit:     {$booking->slot_time} Uhr\n";
    if ($booking->file_name) $admin_body .= "Datei:       {$booking->file_name}\n";
    $admin_body .= "━━━━━━━━━━━━━━━━━━━━━━\n";
    $admin_body .= "Name:        {$booking->customer_name}{$artist_line}\n";
    $admin_body .= "E-Mail:      {$booking->customer_email}\n";
    $admin_body .= "Telefon:     {$booking->customer_phone}\n";
    $admin_body .= "Adresse:     {$booking->customer_address}, {$booking->customer_plz} {$booking->customer_city}\n";
    $admin_body .= "━━━━━━━━━━━━━━━━━━━━━━\n";
    $admin_body .= "Preis:       {$price_line}\n";
    $admin_body .= "Zahlung:     " . ucfirst($booking->payment_method) . "\n";
    if ($booking->payment_id) $admin_body .= "Payment-ID:  {$booking->payment_id}\n";
    $admin_body .= "━━━━━━━━━━━━━━━━━━━━━━\n";
    if ($booking->message) $admin_body .= "\nNachricht:\n{$booking->message}\n";

    wp_mail(
        studiobook_get_setting('notification_email', get_option('admin_email')),
        $admin_subject,
        $admin_body
    );
}

function studiobook_send_completion_mail( $booking_id, $delivery_link = '', $delivery_note = '' ) {
    global $wpdb;
    $booking = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}studiobook_bookings WHERE id = %d", $booking_id
    ));
    if ( ! $booking ) return false;

    $service_labels = ['studio_session' => 'Studio Session', 'mixing_mastering' => 'Mixing & Mastering'];
    $service_label  = $service_labels[$booking->service] ?? $booking->service;

    $pdf = studiobook_generate_invoice_pdf($booking);
    $attachments = [];
    if ($pdf && file_exists($pdf)) $attachments[] = $pdf;

    // ── Mail an KUNDEN ──
    if ($booking->service === 'mixing_mastering') {
        $subject = '🎛️ Dein Mix ist fertig! – Buchung #' . $booking->id . ' | 100Studios';
        $body    = "Hallo {$booking->customer_name},\n\n";
        $body   .= "dein Mixing & Mastering Auftrag ist abgeschlossen!\n\n";
        if ($delivery_link) $body .= "📥 Download-Link: {$delivery_link}\n\n";
        if ($delivery_note) $body .= "Anmerkungen:\n{$delivery_note}\n\n";
        $body   .= "Deine Rechnung findest du im Anhang.\n\n";
        $body   .= "Stay creative 🎤\nEN100 / 100Studios\nhttps://100studios.de";
    } else {
        $subject = '🧾 Deine Rechnung – Buchung #' . $booking->id . ' | 100Studios';
        $body    = "Hallo {$booking->customer_name},\n\n";
        $body   .= "vielen Dank für deine Session bei 100Studios!\n";
        $body   .= "Deine Rechnung findest du im Anhang.\n\n";
        if ($delivery_note) $body .= "{$delivery_note}\n\n";
        $body   .= "Stay creative 🎤\nEN100 / 100Studios\nhttps://100studios.de";
    }

    $result = wp_mail(
        $booking->customer_email,
        $subject,
        $body,
        ['From: 100Studios <noreply@100studios.de>'],
        $attachments
    );

    if ($result) {
        $wpdb->update(
            $wpdb->prefix . 'studiobook_bookings',
            [
                'booking_status' => 'completed',
                'invoice_sent'   => 1,
                'delivery_link'  => sanitize_text_field($delivery_link),
                'delivery_note'  => sanitize_textarea_field($delivery_note),
                'completed_at'   => current_time('mysql'),
            ],
            ['id' => $booking->id],
            ['%s','%d','%s','%s','%s'], ['%d']
        );

        // Admin-Info
        wp_mail(
            studiobook_get_setting('notification_email', get_option('admin_email')),
            '✅ Auftrag erledigt #' . $booking->id . ' – ' . $booking->customer_name,
            "Auftrag #{$booking->id} ({$service_label}) wurde als erledigt markiert.\nMail mit Rechnung wurde an {$booking->customer_email} gesendet."
        );
    }

    return $result;
}
