<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Buchung mit User verknüpfen ──
function studiobook_link_booking_to_user( $booking_id, $user_id ) {
    global $wpdb;
    $wpdb->update(
        $wpdb->prefix . 'studiobook_bookings',
        ['user_id' => $user_id],
        ['id' => $booking_id],
        ['%d'], ['%d']
    );
}

// ── AJAX: Registrierung ──
add_action( 'wp_ajax_nopriv_studiobook_register', 'studiobook_ajax_register' );
function studiobook_ajax_register() {
    check_ajax_referer( 'studiobook_nonce', 'nonce' );

    $email       = sanitize_email( $_POST['email'] ?? '' );
    $username    = sanitize_user( $_POST['username'] ?? '' );
    $password    = $_POST['password'] ?? '';
    $booking_id  = intval( $_POST['booking_id'] ?? 0 );
    $artist_name = sanitize_text_field( $_POST['artist_name'] ?? '' );

    if ( ! $email || ! $username || ! $password )
        wp_send_json_error( ['message' => 'Bitte alle Pflichtfelder ausfüllen.'] );
    if ( strlen($password) < 8 )
        wp_send_json_error( ['message' => 'Passwort muss mindestens 8 Zeichen lang sein.'] );
    if ( email_exists($email) )
        wp_send_json_error( ['message' => 'Diese E-Mail ist bereits registriert. Bitte einloggen.'] );
    if ( username_exists($username) )
        wp_send_json_error( ['message' => 'Dieser Benutzername ist bereits vergeben.'] );

    $user_id = wp_create_user( $username, $password, $email );
    if ( is_wp_error($user_id) )
        wp_send_json_error( ['message' => $user_id->get_error_message()] );

    $user = new WP_User($user_id);
    $user->set_role('subscriber');

    if ($artist_name) {
        update_user_meta($user_id, 'studiobook_artist_name', $artist_name);
        update_user_meta($user_id, 'artist_name', $artist_name);
    }

    // Alle Buchungen mit dieser E-Mail verknüpfen
    global $wpdb;
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}studiobook_bookings SET user_id = %d WHERE customer_email = %s AND (user_id IS NULL OR user_id = 0)",
        $user_id, $email
    ));

    if ($booking_id) studiobook_link_booking_to_user($booking_id, $user_id);

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    wp_mail(
        $email,
        '🎤 Willkommen bei 100Studios!',
        "Hallo {$username},\n\ndein 100Studios Konto wurde erstellt!\n\nBenutzername: {$username}\n\nDashboard: " . home_url('/dashboard') . "\n\nStay creative\nEN100 / 100Studios",
        ['From: 100Studios <noreply@100studios.de>']
    );

    $in_booking = !empty($_POST['in_booking']);
    if ($in_booking) {
        wp_send_json_success([
            'message'    => 'Konto erstellt!',
            'in_booking' => true,
            'user_id'    => $user_id,
        ]);
        return;
    }
    wp_send_json_success(['message' => 'Konto erstellt!', 'redirect' => home_url('/dashboard'), 'user_id' => $user_id]);
}

// ── AJAX: Login ──
add_action( 'wp_ajax_nopriv_studiobook_login', 'studiobook_ajax_login' );
function studiobook_ajax_login() {
    check_ajax_referer( 'studiobook_nonce', 'nonce' );

    $login      = sanitize_text_field( $_POST['login'] ?? '' );
    $password   = $_POST['password'] ?? '';
    $booking_id = intval( $_POST['booking_id'] ?? 0 );
    $redirect   = sanitize_text_field( $_POST['redirect'] ?? '' );

    $user = wp_authenticate( $login, $password );
    if ( is_wp_error($user) )
        wp_send_json_error( ['message' => 'Benutzername oder Passwort falsch.'] );

    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);

    // Alle Buchungen mit dieser E-Mail verknüpfen
    global $wpdb;
    $wpdb->update(
        $wpdb->prefix . 'studiobook_bookings',
        ['user_id' => $user->ID],
        ['customer_email' => $user->user_email, 'user_id' => null],
        ['%d'], ['%s', '%s']
    );
    // Auch user_id = 0 abdecken
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}studiobook_bookings SET user_id = %d WHERE customer_email = %s AND (user_id IS NULL OR user_id = 0)",
        $user->ID, $user->user_email
    ));

    if ($booking_id) studiobook_link_booking_to_user($booking_id, $user->ID);

    $in_booking = !empty($_POST['in_booking']);
    if ($in_booking) {
        wp_send_json_success([
            'message'    => 'Eingeloggt!',
            'in_booking' => true,
            'user_id'    => $user->ID,
        ]);
        return;
    }

    $dest = $redirect ?: home_url('/dashboard');
    wp_send_json_success(['message' => 'Eingeloggt!', 'redirect' => $dest, 'user_id' => $user->ID]);
}

// ── AJAX: Logout ──
add_action( 'wp_ajax_studiobook_logout', 'studiobook_ajax_logout' );
function studiobook_ajax_logout() {
    check_ajax_referer( 'studiobook_nonce', 'nonce' );
    wp_logout();
    wp_send_json_success( ['redirect' => home_url()] );
}

// ── AJAX: Profil aktualisieren ──
add_action( 'wp_ajax_studiobook_update_profile', 'studiobook_ajax_update_profile' );
function studiobook_ajax_update_profile() {
    check_ajax_referer( 'studiobook_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( ['message' => 'Nicht eingeloggt.'] );

    $user_id     = get_current_user_id();
    $artist_name = sanitize_text_field( $_POST['artist_name'] ?? '' );
    $first_name  = sanitize_text_field( $_POST['first_name'] ?? '' );
    $last_name   = sanitize_text_field( $_POST['last_name'] ?? '' );
    $email       = sanitize_email( $_POST['email'] ?? '' );

    update_user_meta($user_id, 'studiobook_artist_name', $artist_name);
    update_user_meta($user_id, 'artist_name', $artist_name);
    wp_update_user(['ID' => $user_id, 'first_name' => $first_name, 'last_name' => $last_name]);

    if ($email && $email !== wp_get_current_user()->user_email) {
        if (email_exists($email)) wp_send_json_error(['message' => 'E-Mail bereits vergeben.']);
        wp_update_user(['ID' => $user_id, 'user_email' => $email]);
    }

    if ( isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK ) {
        $file    = $_FILES['avatar'];
        $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
        if (!in_array($file['type'], $allowed)) wp_send_json_error(['message' => 'Nur JPG, PNG, WebP erlaubt.']);
        if ($file['size'] > 2 * 1024 * 1024) wp_send_json_error(['message' => 'Bild max. 2MB.']);

        $upload = wp_upload_dir();
        $dir    = $upload['basedir'] . '/studiobook-avatars/';
        if (!file_exists($dir)) wp_mkdir_p($dir);
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar-' . $user_id . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $dir . $filename);
        update_user_meta($user_id, 'studiobook_avatar', $upload['baseurl'] . '/studiobook-avatars/' . $filename . '?v=' . time());
    }

    wp_send_json_success( ['message' => 'Profil gespeichert!'] );
}

// ── AJAX: Passwort ändern ──
add_action( 'wp_ajax_studiobook_change_password', 'studiobook_ajax_change_password' );
function studiobook_ajax_change_password() {
    check_ajax_referer( 'studiobook_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( ['message' => 'Nicht eingeloggt.'] );

    $user_id = get_current_user_id();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($new) < 8) wp_send_json_error(['message' => 'Neues Passwort muss min. 8 Zeichen lang sein.']);
    if ($new !== $confirm) wp_send_json_error(['message' => 'Passwörter stimmen nicht überein.']);

    $user = get_user_by('id', $user_id);
    if (!wp_check_password($current, $user->user_pass, $user_id))
        wp_send_json_error(['message' => 'Aktuelles Passwort falsch.']);

    wp_set_password($new, $user_id);
    wp_set_auth_cookie($user_id, true);
    wp_send_json_success(['message' => 'Passwort erfolgreich geändert!']);
}

// ── AJAX: Passwort-Reset ──
add_action( 'wp_ajax_nopriv_studiobook_forgot_password', 'studiobook_ajax_forgot_password' );
function studiobook_ajax_forgot_password() {
    check_ajax_referer( 'studiobook_nonce', 'nonce' );
    $email = sanitize_email( $_POST['email'] ?? '' );
    $user  = get_user_by('email', $email);
    if (!$user) {
        wp_send_json_success(['message' => 'Falls diese E-Mail registriert ist, bekommst du einen Reset-Link.']);
    }
    $key = get_password_reset_key($user);
    if (is_wp_error($key)) wp_send_json_error(['message' => 'Fehler beim Erstellen des Links.']);
    $reset_url = network_site_url("wp-login.php?action=rp&key={$key}&login=" . rawurlencode($user->user_login), 'login');
    wp_mail(
        $email,
        '🔑 Passwort zurücksetzen – 100Studios',
        "Hallo {$user->user_login},\n\nKlicke hier um dein Passwort zurückzusetzen:\n{$reset_url}\n\nDer Link ist 24 Stunden gültig.\n\nEN100 / 100Studios",
        ['From: 100Studios <noreply@100studios.de>']
    );
    wp_send_json_success(['message' => 'Falls diese E-Mail registriert ist, bekommst du einen Reset-Link.']);
}

// ── Rechnungs-Download ──
add_action( 'init', 'studiobook_handle_invoice_download' );
function studiobook_handle_invoice_download() {
    if (!isset($_GET['studiobook_invoice'])) return;
    if (!is_user_logged_in()) wp_die('Bitte einloggen.');

    $booking_id = intval($_GET['studiobook_invoice']);
    $nonce      = sanitize_text_field($_GET['_wpnonce'] ?? '');
    if (!wp_verify_nonce($nonce, 'sb_invoice_' . $booking_id)) wp_die('Ungültiger Link.');

    global $wpdb;
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}studiobook_bookings WHERE id = %d AND user_id = %d",
        $booking_id, get_current_user_id()
    ));
    if (!$booking) wp_die('Buchung nicht gefunden.');

    $pdf = studiobook_generate_invoice_pdf($booking);
    if (!$pdf || !file_exists($pdf)) wp_die('Rechnung konnte nicht erstellt werden.');

    $ext      = pathinfo($pdf, PATHINFO_EXTENSION);
    $filename = 'Rechnung-' . ($booking->invoice_number ?: $booking_id) . '.' . $ext;

    header('Content-Description: File Transfer');
    header('Content-Type: ' . ($ext === 'pdf' ? 'application/pdf' : 'text/html'));
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($pdf));
    header('Pragma: public');
    flush();
    readfile($pdf);
    exit;
}

// ── Login-Icon Shortcode ──
add_shortcode( 'studiobook_login_icon', 'studiobook_login_icon_shortcode' );
function studiobook_login_icon_shortcode() {
    ob_start();
    if ( is_user_logged_in() ) :
        $user       = wp_get_current_user();
        $avatar_url = get_user_meta($user->ID, 'studiobook_avatar', true);
        $dash_url   = home_url('/dashboard');
        $settings_url = add_query_arg('section', 'settings', $dash_url);
        $logout_nonce = wp_create_nonce('studiobook_nonce');
        ?>
        <div class="sb-nav-dropdown-wrap">
            <button class="sb-nav-icon-btn" aria-haspopup="true" aria-expanded="false" aria-label="Profil-Menü">
                <?php if ($avatar_url) : ?>
                <img src="<?= esc_url($avatar_url) ?>" class="sb-nav-avatar" alt="Profil">
                <?php else : ?>
                <span class="sb-nav-icon-person" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 512 512" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M256,0C181.561,0,121,60.561,121,135s60.561,135,135,135s135-60.561,135-135S330.439,0,256,0z"/>
                        <path d="M423.966,358.195C387.006,320.667,338.009,300,286,300h-60c-52.008,0-101.006,20.667-137.966,58.195C51.255,395.539,31,444.833,31,497c0,8.284,6.716,15,15,15h420c8.284,0,15-6.716,15-15C481,444.833,460.745,395.539,423.966,358.195z"/>
                    </svg>
                </span>
                <?php endif; ?>
            </button>
            <div class="sb-nav-dropdown" role="menu">
                <a href="<?= esc_url($dash_url) ?>" class="sb-nav-dropdown-item" role="menuitem">Dashboard</a>
                <a href="<?= esc_url(add_query_arg('section','bookings',$dash_url)) ?>" class="sb-nav-dropdown-item" role="menuitem">Buchungen</a>
                <a href="<?= esc_url(add_query_arg('section','orders',$dash_url)) ?>" class="sb-nav-dropdown-item" role="menuitem">Aufträge</a>
                <a href="<?= esc_url($settings_url) ?>" class="sb-nav-dropdown-item" role="menuitem">Einstellungen</a>
                <button class="sb-nav-dropdown-item sb-nav-dropdown-logout" role="menuitem"
                    data-nonce="<?= esc_attr($logout_nonce) ?>">Ausloggen</button>
            </div>
        </div>
        <?php
    else :
        $login_url = home_url('/dashboard');
        ?>
        <a href="<?= esc_url($login_url) ?>" class="sb-nav-icon-btn sb-nav-login" aria-label="Anmelden">
            <span class="sb-nav-icon-person" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 512 512" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M256,0C181.561,0,121,60.561,121,135s60.561,135,135,135s135-60.561,135-135S330.439,0,256,0z"/>
                    <path d="M423.966,358.195C387.006,320.667,338.009,300,286,300h-60c-52.008,0-101.006,20.667-137.966,58.195C51.255,395.539,31,444.833,31,497c0,8.284,6.716,15,15,15h420c8.284,0,15-6.716,15-15C481,444.833,460.745,395.539,423.966,358.195z"/>
                </svg>
            </span>
        </a>
        <?php
    endif;
    return ob_get_clean();
}
