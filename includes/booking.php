<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Slot-Verfügbarkeit ──
function studiobook_get_available_slots( $date ) {
    global $wpdb;
    $all_slots = studiobook_get_setting( 'slots', ['10:00','14:00','18:00','22:00'] );

    $booked = $wpdb->get_col( $wpdb->prepare(
        "SELECT slot_time FROM {$wpdb->prefix}studiobook_bookings
         WHERE booking_date = %s AND booking_status NOT IN ('cancelled') AND payment_status != 'failed'",
        $date
    ));
    $blocked = $wpdb->get_col( $wpdb->prepare(
        "SELECT slot_time FROM {$wpdb->prefix}studiobook_blocked WHERE blocked_date = %s", $date
    ));

    $unavailable = array_merge( $booked, $blocked );
    $available   = [];
    foreach ( $all_slots as $slot ) {
        $available[] = [ 'time' => $slot, 'available' => ! in_array( $slot, $unavailable ) ];
    }
    return $available;
}

// ── Monatsverfügbarkeit ──
add_action( 'wp_ajax_studiobook_get_month', 'studiobook_ajax_get_month' );
add_action( 'wp_ajax_nopriv_studiobook_get_month', 'studiobook_ajax_get_month' );
function studiobook_ajax_get_month() {
    check_ajax_referer( 'studiobook_nonce', 'nonce' );
    $year  = intval( $_POST['year']  ?? date('Y') );
    $month = intval( $_POST['month'] ?? date('m') );
    $all_slots   = studiobook_get_setting( 'slots', ['10:00','14:00','18:00','22:00'] );
    $total_slots = count( $all_slots );
    $days        = cal_days_in_month( CAL_GREGORIAN, $month, $year );
    $today       = date('Y-m-d');
    $result      = [];

    for ( $d = 1; $d <= $days; $d++ ) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
        if ( $date < $today ) { $result[$date] = 'past'; continue; }
        $avail = studiobook_get_available_slots( $date );
        $n     = count( array_filter( $avail, fn($s) => $s['available'] ) );
        $result[$date] = $n === 0 ? 'full' : ( $n < $total_slots ? 'partial' : 'open' );
    }
    wp_send_json_success( ['days' => $result] );
}

// ── Slots für Tag ──
add_action( 'wp_ajax_studiobook_get_slots', 'studiobook_ajax_get_slots' );
add_action( 'wp_ajax_nopriv_studiobook_get_slots', 'studiobook_ajax_get_slots' );
function studiobook_ajax_get_slots() {
    check_ajax_referer( 'studiobook_nonce', 'nonce' );
    $date           = sanitize_text_field( $_POST['date'] ?? '' );
    $payment_method = sanitize_text_field( $_POST['payment_method'] ?? 'online' );

    if ( ! $date || ! strtotime( $date ) ) wp_send_json_error( ['message' => 'Ungültiges Datum'] );

    // Beide Zahlungsmethoden: 24h Vorlauf
    $min_hours = intval( studiobook_get_setting('min_advance_hours_online', 24) );

    $slots = studiobook_get_available_slots( $date );
    foreach ( $slots as &$slot ) {
        if ( ! $slot['available'] ) continue;
        $slot_dt = strtotime( $date . ' ' . $slot['time'] . ':00' );
        if ( $slot_dt - time() < $min_hours * 3600 ) {
            $slot['available'] = false;
            $slot['reason']    = 'too_soon';
        }
    }
    wp_send_json_success( ['slots' => $slots] );
}

// ── Buchung speichern ──
function studiobook_save_booking( $data ) {
    global $wpdb;

    $s             = studiobook_get_settings();
    $tax_enabled   = ! empty( $s['tax_enabled'] );
    $tax_inclusive = ! empty( $s['tax_inclusive'] ?? true );
    $tax_rate      = $tax_enabled ? floatval( $s['tax_rate'] ?? 19 ) : 0;
    $price_gross   = floatval( $data['price'] ); // Preis ist immer Brutto

    if ($tax_enabled && $tax_inclusive) {
        // MwSt. ist bereits enthalten: Netto = Brutto / (1 + Rate/100)
        $price_net  = round( $price_gross / (1 + $tax_rate / 100), 2 );
        $tax_amount = round( $price_gross - $price_net, 2 );
    } elseif ($tax_enabled && !$tax_inclusive) {
        // MwSt. wird draufgerechnet
        $price_net   = $price_gross;
        $tax_amount  = round( $price_net * $tax_rate / 100, 2 );
        $price_gross = $price_net + $tax_amount;
    } else {
        $price_net  = $price_gross;
        $tax_amount = 0;
    }

    $cancel_token = bin2hex( random_bytes(32) );

    $insert = $wpdb->insert(
        $wpdb->prefix . 'studiobook_bookings',
        [
            'service'          => sanitize_text_field( $data['service'] ),
            'booking_date'     => !empty($data['date']) ? $data['date'] : null,
            'slot_time'        => sanitize_text_field( $data['slot'] ?? '' ),
            'customer_name'    => sanitize_text_field( $data['name'] ),
            'customer_email'   => sanitize_email( $data['email'] ),
            'customer_phone'   => sanitize_text_field( $data['phone'] ?? '' ),
            'artist_name'      => sanitize_text_field( $data['artist_name'] ?? '' ),
            'customer_address' => sanitize_text_field( $data['address'] ?? '' ),
            'customer_plz'     => sanitize_text_field( $data['plz'] ?? '' ),
            'customer_city'    => sanitize_text_field( $data['city'] ?? '' ),
            'message'          => sanitize_textarea_field( $data['message'] ?? '' ),
            'file_path'        => sanitize_text_field( $data['file_path'] ?? '' ),
            'file_name'        => sanitize_text_field( $data['file_name'] ?? '' ),
            'price'            => $price_net,
            'tax_rate'         => $tax_rate,
            'tax_amount'       => $tax_amount,
            'price_gross'      => $price_gross,
            'payment_method'   => sanitize_text_field( $data['payment_method'] ),
            'payment_status'   => sanitize_text_field( $data['payment_status'] ?? 'pending' ),
            'payment_id'       => sanitize_text_field( $data['payment_id'] ?? '' ),
            'coupon_code'      => sanitize_text_field( $data['coupon_code'] ?? '' ),
            'discount_amount'  => floatval( $data['discount_amount'] ?? 0 ),
            'booking_status'   => 'confirmed',
            'cancel_token'     => $cancel_token,
            'user_id'          => !empty($data['user_id']) ? intval($data['user_id']) : null,
        ],
        ['%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%f','%f','%f','%f','%s','%s','%s','%s','%s','%s','%f','%d']
    );


    // Fallback: E-Mail-Abgleich wenn keine user_id übergeben
    if ($booking_id && empty($data['user_id'])) {
        $email = sanitize_email($data['email'] ?? '');
        if ($email) {
            $existing_user = get_user_by('email', $email);
            if ($existing_user) {
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'studiobook_bookings',
                    ['user_id' => $existing_user->ID],
                    ['id' => $booking_id],
                    ['%d'], ['%d']
                );
            }
        }
    }
    // Gutschein verwenden
    if ($booking_id && !empty($data['coupon_code'])) {
        studiobook_use_coupon($data['coupon_code']);
    }

    return $insert ? $wpdb->insert_id : false;
}


// ── DB-Upgrade: fehlende Spalten automatisch hinzufügen ──
add_action( 'plugins_loaded', 'studiobook_maybe_upgrade_db' );
function studiobook_maybe_upgrade_db() {
    global $wpdb;
    $table = $wpdb->prefix . 'studiobook_bookings';

    // Prüfe ob artist_name Spalte existiert
    $cols = $wpdb->get_col("DESCRIBE {$table}", 0);

    // user_id Spalte hinzufügen
    if ( ! in_array('user_id', $cols) ) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN user_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER cancel_token");
    }

    // Coupons-Tabelle anlegen falls nicht vorhanden
    $coupons_table = $wpdb->prefix . 'studiobook_coupons';
    if ($wpdb->get_var("SHOW TABLES LIKE '$coupons_table'") != $coupons_table) {
        studiobook_create_tables();
    }

    // coupon_code Spalte in bookings
    if (!in_array('coupon_code', $cols)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN coupon_code VARCHAR(100) DEFAULT '' AFTER payment_id");
    }
    if (!in_array('discount_amount', $cols)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0.00 AFTER coupon_code");
    }

    // Portal-Content Tabelle anlegen falls nicht vorhanden
    $portal_table = $wpdb->prefix . 'studiobook_portal_content';
    if ($wpdb->get_var("SHOW TABLES LIKE '$portal_table'") != $portal_table) {
        studiobook_create_tables();
    }

    // Stelle sicher dass booking_date NULL erlaubt (fix für ältere Versionen)
    $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN booking_date DATE DEFAULT NULL");
    $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN slot_time VARCHAR(10) DEFAULT NULL");
    if ( ! in_array('artist_name', $cols) ) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN artist_name VARCHAR(150) DEFAULT '' AFTER customer_phone");
    }
    // Weitere neue Spalten falls nötig
    if ( ! in_array('price_gross', $cols) ) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN price_gross DECIMAL(10,2) DEFAULT 0.00 AFTER tax_amount");
    }
    if ( ! in_array('cancel_token', $cols) ) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN cancel_token VARCHAR(64) DEFAULT ''");
    }
    if ( ! in_array('invoice_number', $cols) ) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN invoice_number VARCHAR(50) DEFAULT ''");
    }
    if ( ! in_array('delivery_link', $cols) ) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN delivery_link VARCHAR(500) DEFAULT ''");
    }
    if ( ! in_array('delivery_note', $cols) ) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN delivery_note TEXT");
    }
    if ( ! in_array('completed_at', $cols) ) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN completed_at DATETIME DEFAULT NULL");
    }
    if ( ! in_array('cancelled_at', $cols) ) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN cancelled_at DATETIME DEFAULT NULL");
    }
    if ( ! in_array('invoice_sent', $cols) ) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN invoice_sent TINYINT(1) DEFAULT 0");
    }
}

// ── Stornierung via Link ──
add_action( 'init', 'studiobook_handle_cancel' );
function studiobook_handle_cancel() {
    if ( ! isset( $_GET['studiobook_cancel'] ) ) return;
    $token = sanitize_text_field( $_GET['studiobook_cancel'] );
    global $wpdb;

    $booking = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}studiobook_bookings WHERE cancel_token = %s", $token
    ));

    if ( ! $booking ) wp_die( 'Ungültiger Storno-Link.' );
    if ( $booking->booking_status === 'cancelled' )  wp_die( 'Diese Buchung wurde bereits storniert.' );
    if ( $booking->booking_status === 'completed' )  wp_die( 'Dieser Auftrag wurde bereits erledigt und kann nicht storniert werden.' );

    $free_hours    = intval( studiobook_get_setting('cancel_free_hours', 12) );
    $partial_pct   = intval( studiobook_get_setting('cancel_partial_percent', 50) );

    if ( $booking->booking_date && $booking->slot_time ) {
        $session_time = strtotime( $booking->booking_date . ' ' . $booking->slot_time . ':00' );
        $hours_until  = ( $session_time - time() ) / 3600;

        if ( $hours_until < 0 ) wp_die( 'Der Termin liegt in der Vergangenheit.' );

        if ( $hours_until >= $free_hours ) {
            $msg = 'Deine Buchung wurde kostenfrei storniert.';
        } else {
            $refund_amt = round( floatval($booking->price_gross) * $partial_pct / 100, 2 );
            $msg = "Buchung storniert. Es werden {$partial_pct}% ({$refund_amt} €) einbehalten.";
        }
    } else {
        $msg = 'Dein Auftrag wurde storniert.';
    }

    $wpdb->update(
        $wpdb->prefix . 'studiobook_bookings',
        ['booking_status' => 'cancelled', 'cancelled_at' => current_time('mysql')],
        ['id' => $booking->id], ['%s','%s'], ['%d']
    );

    studiobook_send_cancel_mail( $booking, $msg );
    wp_die( esc_html($msg) . ' Eine Bestätigung geht an ' . esc_html($booking->customer_email) . '.', 'Stornierung bestätigt' );
}

function studiobook_send_cancel_mail( $booking, $msg ) {
    // Mail an Kunden
    wp_mail(
        $booking->customer_email,
        '🚫 Stornierungsbestätigung – Buchung #' . $booking->id,
        "Hallo {$booking->customer_name},\n\n{$msg}\n\nBuchungs-ID: #{$booking->id}\n\nBei Fragen melde dich jederzeit.\n\nEN100 / 100Studios\nhttps://100studios.de",
        ['From: 100Studios <noreply@100studios.de>']
    );
    // Mail an Admin
    wp_mail(
        studiobook_get_setting('notification_email', get_option('admin_email')),
        '🚫 Stornierung Buchung #' . $booking->id . ' – ' . $booking->customer_name,
        "Buchung #{$booking->id} wurde storniert.\n\nKunde: {$booking->customer_name}\nE-Mail: {$booking->customer_email}\nService: {$booking->service}\n\nHinweis: {$msg}"
    );
}

// ── Datei-Upload ──
add_action( 'wp_ajax_studiobook_upload_file', 'studiobook_ajax_upload_file' );
add_action( 'wp_ajax_nopriv_studiobook_upload_file', 'studiobook_ajax_upload_file' );
function studiobook_ajax_upload_file() {
    check_ajax_referer( 'studiobook_nonce', 'nonce' );
    if ( ! isset( $_FILES['file'] ) ) wp_send_json_error( ['message' => 'Keine Datei.'] );
    $file    = $_FILES['file'];
    if ( $file['size'] > 200 * 1024 * 1024 ) wp_send_json_error( ['message' => 'Datei zu groß (max. 200MB).'] );
    $allowed = ['mp3','wav','flac','aiff','aif','ogg','m4a','zip','rar'];
    $ext     = strtolower( pathinfo($file['name'], PATHINFO_EXTENSION) );
    if ( ! in_array($ext, $allowed) ) wp_send_json_error( ['message' => 'Format nicht erlaubt.'] );

    $upload_dir = wp_upload_dir();
    $target_dir = $upload_dir['basedir'] . '/studiobook-uploads/';
    if ( ! file_exists($target_dir) ) {
        wp_mkdir_p($target_dir);
        file_put_contents($target_dir . '.htaccess', "Options -Indexes\n");
    }
    $safe_name = uniqid('sb_') . '_' . sanitize_file_name($file['name']);
    if ( ! move_uploaded_file($file['tmp_name'], $target_dir . $safe_name) ) {
        wp_send_json_error( ['message' => 'Upload fehlgeschlagen.'] );
    }
    wp_send_json_success([
        'file_path' => $target_dir . $safe_name,
        'file_name' => $file['name'],
        'file_url'  => $upload_dir['baseurl'] . '/studiobook-uploads/' . $safe_name,
    ]);
}

// ── Barzahlung ──
add_action( 'wp_ajax_studiobook_cash_booking', 'studiobook_ajax_cash_booking' );
add_action( 'wp_ajax_nopriv_studiobook_cash_booking', 'studiobook_ajax_cash_booking' );
function studiobook_ajax_cash_booking() {
    check_ajax_referer( 'studiobook_nonce', 'nonce' );

    $date = sanitize_text_field( $_POST['date'] ?? '' );
    $slot = sanitize_text_field( $_POST['slot'] ?? '' );

    if ( $date && $slot ) {
        $min_hours = intval( studiobook_get_setting('min_advance_hours_cash', 24) );
        $slot_time = strtotime( $date . ' ' . $slot . ':00' );
        if ( $slot_time - time() < $min_hours * 3600 ) {
            wp_send_json_error( ['message' => 'Barzahlung muss mindestens ' . $min_hours . 'h vorher gebucht werden.'] );
        }
    }

    $booking_id = studiobook_save_booking([
        'service'        => sanitize_text_field( $_POST['service'] ?? '' ),
        'date'           => $date,
        'slot'           => $slot,
        'name'           => sanitize_text_field( $_POST['name'] ?? '' ),
        'email'          => sanitize_email( $_POST['email'] ?? '' ),
        'phone'          => sanitize_text_field( $_POST['phone'] ?? '' ),
        'artist_name'    => sanitize_text_field( $_POST['artist_name'] ?? '' ),
        'address'        => sanitize_text_field( $_POST['address'] ?? '' ),
        'plz'            => sanitize_text_field( $_POST['plz'] ?? '' ),
        'city'           => sanitize_text_field( $_POST['city'] ?? '' ),
        'message'        => sanitize_textarea_field( $_POST['message'] ?? '' ),
        'file_path'      => '',
        'file_name'      => '',
        'price'          => floatval( $_POST['price'] ?? 0 ),
        'payment_method' => 'cash',
        'payment_status' => 'pending',
        'payment_id'     => '',
        'user_id'        => intval($_POST['user_id'] ?? 0) ?: null,
    ]);

    if ( ! $booking_id ) wp_send_json_error( ['message' => 'Buchung fehlgeschlagen.'] );
    studiobook_send_confirmation_mails( $booking_id, false );
    wp_send_json_success( ['booking_id' => $booking_id] );
}

// ── Test-Buchung ──
add_action( 'wp_ajax_studiobook_test_booking', 'studiobook_ajax_test_booking' );
add_action( 'wp_ajax_nopriv_studiobook_test_booking', 'studiobook_ajax_test_booking' );
function studiobook_ajax_test_booking() {
    check_ajax_referer( 'studiobook_nonce', 'nonce' );

    if ( empty( studiobook_get_setting('show_test_badge') ) ) {
        wp_send_json_error( ['message' => 'Test-Modus nicht aktiv.'] );
    }

    $service = sanitize_text_field( $_POST['service'] ?? 'studio_session' );
    $is_mm   = $service === 'mixing_mastering';

    $booking_id = studiobook_save_booking([
        'service'        => $service,
        'date'           => $is_mm ? '' : sanitize_text_field( $_POST['date'] ?? date('Y-m-d', strtotime('+2 days')) ),
        'slot'           => $is_mm ? '' : sanitize_text_field( $_POST['slot'] ?? '10:00' ),
        'name'           => sanitize_text_field( $_POST['name'] ?? 'Test Kunde' ),
        'email'          => sanitize_email( $_POST['email'] ?? get_option('admin_email') ),
        'phone'          => sanitize_text_field( $_POST['phone'] ?? '+49 000 0000000' ),
        'artist_name'    => sanitize_text_field( $_POST['artist_name'] ?? 'TestArtist' ),
        'address'        => sanitize_text_field( $_POST['address'] ?? 'Teststraße 1' ),
        'plz'            => sanitize_text_field( $_POST['plz'] ?? '00000' ),
        'city'           => sanitize_text_field( $_POST['city'] ?? 'Teststadt' ),
        'message'        => '[TEST-BUCHUNG]',
        'file_path'      => $is_mm ? '/tmp/test.mp3' : '',
        'file_name'      => $is_mm ? 'test-track.mp3' : '',
        'price'          => floatval( $_POST['price'] ?? 0 ),
        'payment_method' => 'test',
        'payment_status' => 'test',
        'payment_id'     => 'TEST-' . time(),
    ]);

    if ( ! $booking_id ) {
        global $wpdb;
        wp_send_json_error( ['message' => 'Test-Buchung fehlgeschlagen: ' . ($wpdb->last_error ?: 'Unbekannter DB-Fehler')] );
    }

    // Bestätigungsmail senden
    studiobook_send_confirmation_mails( $booking_id, true );

    wp_send_json_success( ['booking_id' => $booking_id, 'test' => true] );
}
