<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'studiobook_admin_menu' );
function studiobook_admin_menu() {
    add_menu_page('100Studios', '100Studios', 'manage_options', '100studios-bookings', 'studiobook_admin_bookings_page', 'dashicons-calendar-alt', 30);
    add_submenu_page('100studios-bookings', '📋 Buchungen',      'Buchungen',      'manage_options', '100studios-bookings',  'studiobook_admin_bookings_page');
    add_submenu_page('100studios-bookings', '🚫 Blockierungen',  'Blockierungen',  'manage_options', '100studios-blocked',   'studiobook_admin_blocked_page');
    add_submenu_page('100studios-bookings', '🎟️ Gutscheine',     'Gutscheine',     'manage_options', '100studios-coupons',   'studiobook_admin_coupons_page');
    add_submenu_page('100studios-bookings', '⚙️ Einstellungen',  'Einstellungen',  'manage_options', '100studios-settings',  'studiobook_admin_settings_page');
    add_submenu_page('100studios-bookings', '📄 Rechnung',       'Rechnung',       'manage_options', '100studios-invoice',   'studiobook_admin_invoice_page');
    add_submenu_page('100studios-bookings', '✏️ Texte',          'Texte',          'manage_options', '100studios-texts',     'studiobook_admin_texts_page');
    add_submenu_page('100studios-bookings', '🎨 Farben',         'Farben',         'manage_options', '100studios-colors',    'studiobook_admin_colors_page');
    add_submenu_page('100studios-bookings', '📚 Portal Inhalte', 'Portal Inhalte', 'manage_options', '100studios-portal',    'studiobook_admin_portal_page');
}

add_action( 'admin_head', 'studiobook_admin_inline_css' );
function studiobook_admin_inline_css() {
    if (strpos($_GET['page'] ?? '', '100studios') === false) return;
    ?>
    <style>
    .sb-admin-wrap { max-width:1200px; }
    .sb-card { background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:20px; margin-bottom:20px; }
    .sb-card h2 { margin:0 0 16px; padding-bottom:12px; border-bottom:2px solid #553eff; color:#333; }
    .sb-bookings-grid { display:grid; gap:12px; }
    .sb-booking-card { background:#fff; border:1px solid #ddd; border-radius:8px; padding:16px; }
    .sb-booking-card-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px; }
    .sb-booking-card-title { font-weight:700; }
    .sb-booking-card-body { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:12px; }
    @media(max-width:600px){ .sb-booking-card-body { grid-template-columns:1fr; } }
    .sb-booking-card-field { font-size:13px; }
    .sb-booking-card-field span { color:#999; display:block; font-size:11px; }
    .sb-booking-card-actions { border-top:1px solid #eee; padding-top:10px; display:flex; gap:8px; flex-wrap:wrap; }
    .sb-status { display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600; }
    .sb-status-confirmed { background:#d4edda; color:#155724; }
    .sb-status-completed { background:#cce5ff; color:#004085; }
    .sb-status-cancelled { background:#f8d7da; color:#721c24; }
    .sb-status-pending   { background:#fff3cd; color:#856404; }
    .sb-status-test      { background:#e2d9f3; color:#5a189a; }
    .sb-complete-form { background:#f9f9f9; border:1px solid #ddd; border-radius:6px; padding:14px; margin-top:10px; }
    .sb-complete-form input[type=text], .sb-complete-form textarea { width:100%; box-sizing:border-box; margin-top:4px; padding:8px; border:1px solid #ccc; border-radius:4px; }
    .sb-complete-form textarea { height:80px; resize:vertical; }
    .sb-filter-bar { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
    .sb-logo-preview { max-height:60px; max-width:200px; object-fit:contain; display:block; margin-bottom:8px; border:1px solid #ddd; border-radius:6px; padding:4px; }
    </style>
    <?php
}

// ─────────────────────────────────────────────
//  BUCHUNGEN
// ─────────────────────────────────────────────
function studiobook_admin_bookings_page() {
    global $wpdb;

    // Download
    if (isset($_GET['sb_download']) && current_user_can('manage_options')) {
        $bid   = intval($_GET['sb_download']);
        $nonce = sanitize_text_field($_GET['_wpnonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'sb_download_' . $bid)) wp_die('Ungültig.');
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}studiobook_bookings WHERE id = %d", $bid));
        if ($booking && $booking->file_path && file_exists($booking->file_path)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . ($booking->file_name ?: basename($booking->file_path)) . '"');
            header('Content-Length: ' . filesize($booking->file_path));
            flush(); readfile($booking->file_path); exit;
        }
        wp_die('Datei nicht gefunden.');
    }

    // Erledigen
    if (isset($_POST['complete_booking']) && check_admin_referer('studiobook_complete_' . intval($_POST['booking_id']))) {
        $bid  = intval($_POST['booking_id']);
        $link = sanitize_text_field($_POST['delivery_link'] ?? '');
        $note = sanitize_textarea_field($_POST['delivery_note'] ?? '');
        $ok   = studiobook_send_completion_mail($bid, $link, $note);
        echo $ok ? '<div class="notice notice-success"><p>✅ Auftrag erledigt & Rechnung gesendet.</p></div>'
                 : '<div class="notice notice-error"><p>⚠️ Mail konnte nicht gesendet werden.</p></div>';
    }

    // Stornieren
    if (isset($_POST['cancel_booking']) && check_admin_referer('studiobook_cancel_' . intval($_POST['booking_id']))) {
        $bid     = intval($_POST['booking_id']);
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}studiobook_bookings WHERE id = %d", $bid));
        if ($booking && $booking->booking_status !== 'completed') {
            $wpdb->update($wpdb->prefix . 'studiobook_bookings',
                ['booking_status' => 'cancelled', 'cancelled_at' => current_time('mysql')],
                ['id' => $bid], ['%s','%s'], ['%d']
            );
            studiobook_send_cancel_mail($booking, 'Deine Buchung wurde vom Studio storniert.');
            echo '<div class="notice notice-success"><p>🚫 Buchung storniert & Mail gesendet.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Abgeschlossene Aufträge können nicht storniert werden.</p></div>';
        }
    }

    // Löschen
    if (isset($_POST['delete_booking']) && check_admin_referer('studiobook_delete_' . intval($_POST['booking_id']))) {
        $bid = intval($_POST['booking_id']);
        $wpdb->delete($wpdb->prefix . 'studiobook_bookings', ['id' => $bid], ['%d']);
        echo '<div class="notice notice-success"><p>🗑️ Buchung #' . $bid . ' gelöscht.</p></div>';
    }

    $filter   = sanitize_text_field($_GET['status'] ?? '');
    $where    = $filter ? $wpdb->prepare("WHERE booking_status = %s", $filter) : '';
    $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}studiobook_bookings {$where} ORDER BY created_at DESC");

    $service_labels = ['studio_session' => 'Studio Session', 'mixing_mastering' => 'M&M'];
    $statuses       = ['' => 'Alle', 'confirmed' => 'Bestätigt', 'completed' => 'Erledigt', 'cancelled' => 'Storniert', 'pending' => 'Ausstehend'];
    $status_colors  = ['confirmed' => '#d4edda', 'completed' => '#cce5ff', 'cancelled' => '#f8d7da', 'pending' => '#fff3cd'];
    ?>
    <div class="wrap sb-admin-wrap">
        <h1>🎙️ Buchungen</h1>
        <div class="sb-filter-bar">
            <?php foreach ($statuses as $st => $lbl) : ?>
            <a href="?page=100studios-bookings<?= $st ? '&status='.$st : '' ?>"
               class="button <?= $filter === $st ? 'button-primary' : '' ?>">
                <?= $lbl ?> (<?= intval($wpdb->get_var($st
                    ? $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}studiobook_bookings WHERE booking_status=%s", $st)
                    : "SELECT COUNT(*) FROM {$wpdb->prefix}studiobook_bookings")) ?>)
            </a>
            <?php endforeach; ?>
        </div>

        <div class="sb-bookings-grid">
        <?php foreach ($bookings as $b) :
            $label     = $service_labels[$b->service] ?? $b->service;
            $date_str  = $b->booking_date ? date('d.m.Y', strtotime($b->booking_date)) . ($b->slot_time ? ' ' . $b->slot_time : '') : '—';
            $bg        = $status_colors[$b->booking_status] ?? '#fff';
            $status_cls = 'sb-status sb-status-' . ($b->payment_method === 'test' ? 'test' : $b->booking_status);
            $is_done   = $b->booking_status === 'completed';
            $is_canc   = $b->booking_status === 'cancelled';
        ?>
        <div class="sb-booking-card" style="border-left:4px solid <?= $bg ?>;">
            <div class="sb-booking-card-header">
                <div class="sb-booking-card-title">
                    #<?= $b->id ?> – <?= esc_html($b->customer_name) ?>
                    <?php if ($b->artist_name) : ?><small style="color:#999;font-weight:400;"> (<?= esc_html($b->artist_name) ?>)</small><?php endif; ?>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <span class="<?= $status_cls ?>"><?= esc_html($b->booking_status) ?></span>
                    <span style="background:#eee;color:#555;padding:3px 8px;border-radius:4px;font-size:12px;"><?= esc_html($label) ?></span>
                </div>
            </div>
            <div class="sb-booking-card-body">
                <div class="sb-booking-card-field"><span>Datum/Zeit</span><?= esc_html($date_str) ?></div>
                <div class="sb-booking-card-field"><span>E-Mail</span><a href="mailto:<?= esc_attr($b->customer_email) ?>"><?= esc_html($b->customer_email) ?></a></div>
                <div class="sb-booking-card-field"><span>Telefon</span><?= esc_html($b->customer_phone) ?></div>
                <div class="sb-booking-card-field"><span>Adresse</span><?= esc_html($b->customer_address . ', ' . $b->customer_plz . ' ' . $b->customer_city) ?></div>
                <div class="sb-booking-card-field"><span>Preis</span>
                    <?= number_format(floatval($b->price_gross ?: $b->price), 2, ',', '.') ?> €
                    <?php if ($b->discount_amount > 0) : ?><small style="color:#28a745;"> (<?= number_format($b->discount_amount,2,',','.') ?> € Rabatt, Code: <?= esc_html($b->coupon_code) ?>)</small><?php endif; ?>
                </div>
                <div class="sb-booking-card-field"><span>Zahlung</span><?= ucfirst(esc_html($b->payment_method)) ?></div>
                <div class="sb-booking-card-field"><span>Account</span>
                    <?php if ($b->user_id) :
                        $u = get_userdata($b->user_id);
                        if ($u) : ?>
                        <a href="<?= esc_url(admin_url('user-edit.php?user_id=' . $b->user_id)) ?>" style="color:#553eff;">
                            👤 <?= esc_html($u->display_name) ?> (ID: <?= $b->user_id ?>)
                        </a>
                        <?php else : ?>
                        ID: <?= $b->user_id ?>
                        <?php endif; ?>
                    <?php else : ?>
                    <span style="color:#999;">Kein Account</span>
                    <?php endif; ?>
                </div>
                <?php if ($b->invoice_number) : ?><div class="sb-booking-card-field"><span>Rechnung</span><?= esc_html($b->invoice_number) ?></div><?php endif; ?>
                <?php if ($b->file_name) : ?>
                <div class="sb-booking-card-field"><span>Datei</span>
                    📎 <?= esc_html($b->file_name) ?>
                    <?php if ($b->file_path && file_exists($b->file_path)) :
                        $dl = wp_nonce_url(admin_url('admin.php?page=100studios-bookings&sb_download=' . $b->id), 'sb_download_' . $b->id); ?>
                    <a href="<?= esc_url($dl) ?>" class="button button-small" style="margin-left:6px;">⬇️</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="sb-booking-card-actions">
                <?php if (!$is_done && !$is_canc) : ?>
                <details style="width:100%;">
                    <summary class="button button-primary" style="cursor:pointer;">✓ Erledigen & Rechnung senden</summary>
                    <div class="sb-complete-form">
                        <form method="post">
                            <?php wp_nonce_field('studiobook_complete_' . $b->id); ?>
                            <input type="hidden" name="booking_id" value="<?= $b->id ?>">
                            <?php if ($b->service === 'mixing_mastering') : ?>
                            <p><label><strong>Download-Link</strong><br>
                            <input type="text" name="delivery_link" placeholder="https://..."></label></p>
                            <?php endif; ?>
                            <p><label><strong>Nachricht (optional)</strong><br>
                            <textarea name="delivery_note" placeholder="Anmerkungen für den Kunden…"></textarea></label></p>
                            <input type="submit" name="complete_booking" class="button button-primary" value="✓ Erledigt & Mail senden">
                        </form>
                    </div>
                </details>
                <form method="post" onsubmit="return confirm('Buchung #<?= $b->id ?> stornieren?\nKunde wird per Mail informiert.')">
                    <?php wp_nonce_field('studiobook_cancel_' . $b->id); ?>
                    <input type="hidden" name="booking_id" value="<?= $b->id ?>">
                    <input type="submit" name="cancel_booking" class="button" value="🚫 Stornieren">
                </form>
                <?php elseif ($is_done) : ?>
                    <span style="color:#004085;">✅ Erledigt <?= $b->completed_at ? 'am ' . date('d.m.Y', strtotime($b->completed_at)) : '' ?></span>
                    <?php if ($b->delivery_link) : ?><a href="<?= esc_url($b->delivery_link) ?>" target="_blank" class="button button-small">📥 Link</a><?php endif; ?>
                <?php else : ?>
                    <span style="color:#721c24;">🚫 Storniert <?= $b->cancelled_at ? 'am ' . date('d.m.Y', strtotime($b->cancelled_at)) : '' ?></span>
                <?php endif; ?>
                <form method="post" onsubmit="return confirm('Buchung #<?= $b->id ?> löschen?')" style="margin-left:auto;">
                    <?php wp_nonce_field('studiobook_delete_' . $b->id); ?>
                    <input type="hidden" name="booking_id" value="<?= $b->id ?>">
                    <input type="submit" name="delete_booking" class="button" value="🗑️" title="Löschen" style="color:#cc0000;">
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($bookings)) : ?><p style="color:#999;">Keine Buchungen.</p><?php endif; ?>
        </div>
    </div>
    <?php
}

// ─────────────────────────────────────────────
//  BLOCKIERUNGEN
// ─────────────────────────────────────────────
function studiobook_admin_blocked_page() {
    global $wpdb;
    if (isset($_POST['block_slot']) && check_admin_referer('studiobook_block')) {
        $wpdb->replace($wpdb->prefix . 'studiobook_blocked', [
            'blocked_date' => sanitize_text_field($_POST['block_date']),
            'slot_time'    => sanitize_text_field($_POST['block_slot_time']),
            'reason'       => sanitize_text_field($_POST['block_reason']),
        ], ['%s','%s','%s']);
        echo '<div class="notice notice-success"><p>Slot blockiert.</p></div>';
    }
    if (isset($_GET['unblock'])) {
        $wpdb->delete($wpdb->prefix . 'studiobook_blocked', ['id' => intval($_GET['unblock'])], ['%d']);
        echo '<div class="notice notice-success"><p>Freigegeben.</p></div>';
    }
    $blocked = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}studiobook_blocked ORDER BY blocked_date, slot_time");
    $slots   = studiobook_get_setting('slots', ['10:00','14:00','18:00','22:00']);
    ?>
    <div class="wrap sb-admin-wrap">
        <h1>🚫 Slots blockieren</h1>
        <div class="sb-card">
            <h2>Slot blockieren</h2>
            <form method="post">
                <?php wp_nonce_field('studiobook_block'); ?>
                <table class="form-table">
                    <tr><th>Datum</th><td><input type="date" name="block_date" required></td></tr>
                    <tr><th>Slot</th><td><select name="block_slot_time"><?php foreach($slots as $sl): ?><option><?= esc_html($sl) ?></option><?php endforeach; ?></select></td></tr>
                    <tr><th>Grund</th><td><input type="text" name="block_reason" placeholder="z.B. Eigene Session" class="regular-text"></td></tr>
                </table>
                <input type="submit" name="block_slot" class="button button-primary" value="Slot blockieren">
            </form>
        </div>
        <?php if (!empty($blocked)) : ?>
        <div class="sb-card">
            <h2>Aktuelle Blockierungen</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Datum</th><th>Slot</th><th>Grund</th><th width="80">Aktion</th></tr></thead>
                <tbody>
                <?php foreach($blocked as $b): ?>
                <tr>
                    <td><?= date('d.m.Y', strtotime($b->blocked_date)) ?></td>
                    <td><?= esc_html($b->slot_time) ?> Uhr</td>
                    <td><?= esc_html($b->reason) ?></td>
                    <td><a href="?page=100studios-blocked&unblock=<?= $b->id ?>" class="button button-small">Freigeben</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

// ─────────────────────────────────────────────
//  GUTSCHEINE
// ─────────────────────────────────────────────
function studiobook_admin_coupons_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'studiobook_coupons';

    if (isset($_POST['save_coupon']) && check_admin_referer('studiobook_coupon')) {
        $data = [
            'code'        => strtoupper(sanitize_text_field($_POST['code'])),
            'type'        => sanitize_text_field($_POST['type']),
            'value'       => floatval($_POST['value']),
            'min_amount'  => floatval($_POST['min_amount'] ?? 0),
            'usage_limit' => !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null,
            'valid_from'  => !empty($_POST['valid_from'])  ? sanitize_text_field($_POST['valid_from'])  : null,
            'valid_until' => !empty($_POST['valid_until']) ? sanitize_text_field($_POST['valid_until']) : null,
            'active'      => !empty($_POST['active']) ? 1 : 0,
        ];
        if (isset($_POST['coupon_id'])) {
            $wpdb->update($table, $data, ['id' => intval($_POST['coupon_id'])]);
            echo '<div class="notice notice-success"><p>✅ Gutschein aktualisiert.</p></div>';
        } else {
            $wpdb->insert($table, $data);
            echo '<div class="notice notice-success"><p>✅ Gutschein erstellt.</p></div>';
        }
    }

    if (isset($_GET['delete_coupon'])) {
        $wpdb->delete($table, ['id' => intval($_GET['delete_coupon'])], ['%d']);
        echo '<div class="notice notice-success"><p>🗑️ Gutschein gelöscht.</p></div>';
    }

    $edit_coupon = null;
    if (isset($_GET['edit_coupon'])) {
        $edit_coupon = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($_GET['edit_coupon'])));
    }

    $coupons = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    ?>
    <div class="wrap sb-admin-wrap">
        <h1>🎟️ Gutscheine & Rabattcodes</h1>

        <div class="sb-card">
            <h2><?= $edit_coupon ? 'Gutschein bearbeiten' : 'Neuen Gutschein erstellen' ?></h2>
            <form method="post">
                <?php wp_nonce_field('studiobook_coupon'); ?>
                <?php if ($edit_coupon) : ?><input type="hidden" name="coupon_id" value="<?= $edit_coupon->id ?>"><?php endif; ?>
                <table class="form-table">
                    <tr><th>Code *</th><td><input type="text" name="code" value="<?= esc_attr($edit_coupon->code ?? '') ?>" class="regular-text" placeholder="z.B. SUMMER20" style="text-transform:uppercase;" required></td></tr>
                    <tr><th>Typ</th><td>
                        <select name="type">
                            <option value="percent" <?= selected($edit_coupon->type ?? 'percent','percent',false) ?>>Prozent (%)</option>
                            <option value="fixed"   <?= selected($edit_coupon->type ?? '','fixed',false) ?>>Festbetrag (€)</option>
                        </select>
                    </td></tr>
                    <tr><th>Wert *</th><td><input type="number" name="value" value="<?= esc_attr($edit_coupon->value ?? '') ?>" step="0.01" min="0" class="small-text" placeholder="z.B. 20"> (% oder €)</td></tr>
                    <tr><th>Mindestbestellwert</th><td><input type="number" name="min_amount" value="<?= esc_attr($edit_coupon->min_amount ?? 0) ?>" step="0.01" min="0" class="small-text"> €</td></tr>
                    <tr><th>Nutzungslimit</th><td><input type="number" name="usage_limit" value="<?= esc_attr($edit_coupon->usage_limit ?? '') ?>" min="1" class="small-text" placeholder="Leer = unbegrenzt"></td></tr>
                    <tr><th>Gültig von</th><td><input type="date" name="valid_from" value="<?= esc_attr($edit_coupon->valid_from ?? '') ?>"></td></tr>
                    <tr><th>Gültig bis</th><td><input type="date" name="valid_until" value="<?= esc_attr($edit_coupon->valid_until ?? '') ?>"></td></tr>
                    <tr><th>Aktiv</th><td><label><input type="checkbox" name="active" value="1" <?= checked($edit_coupon->active ?? 1, 1, false) ?>> Gutschein ist aktiv</label></td></tr>
                </table>
                <input type="submit" name="save_coupon" class="button button-primary" value="<?= $edit_coupon ? '✅ Aktualisieren' : '✅ Gutschein erstellen' ?>">
                <?php if ($edit_coupon) : ?><a href="?page=100studios-coupons" class="button" style="margin-left:8px;">Abbrechen</a><?php endif; ?>
            </form>
        </div>

        <div class="sb-card">
            <h2>Alle Gutscheine</h2>
            <?php if (empty($coupons)) : ?><p style="color:#999;">Noch keine Gutscheine.</p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Code</th><th>Typ</th><th>Wert</th><th>Verwendet</th><th>Gültig bis</th><th>Status</th><th>Aktionen</th></tr></thead>
                <tbody>
                <?php foreach($coupons as $c) : ?>
                <tr>
                    <td><strong><?= esc_html($c->code) ?></strong></td>
                    <td><?= $c->type === 'percent' ? 'Prozent' : 'Festbetrag' ?></td>
                    <td><?= $c->type === 'percent' ? number_format($c->value,0) . '%' : number_format($c->value,2,',','.') . ' €' ?></td>
                    <td><?= $c->usage_count ?><?= $c->usage_limit ? ' / ' . $c->usage_limit : ' / ∞' ?></td>
                    <td><?= $c->valid_until ? date('d.m.Y', strtotime($c->valid_until)) : '–' ?></td>
                    <td><span style="color:<?= $c->active ? '#28a745' : '#dc3545' ?>;font-weight:600;"><?= $c->active ? '● Aktiv' : '● Inaktiv' ?></span></td>
                    <td>
                        <a href="?page=100studios-coupons&edit_coupon=<?= $c->id ?>" class="button button-small">Bearbeiten</a>
                        <a href="?page=100studios-coupons&delete_coupon=<?= $c->id ?>" class="button button-small" style="color:#cc0000;" onclick="return confirm('Gutschein löschen?')">Löschen</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// ─────────────────────────────────────────────
//  EINSTELLUNGEN
// ─────────────────────────────────────────────
function studiobook_admin_settings_page() {
    if (isset($_POST['save_settings']) && check_admin_referer('studiobook_settings')) {
        $old = studiobook_get_settings();
        foreach ($old['services'] as &$svc) {
            $svc['price'] = sanitize_text_field($_POST['price_' . $svc['id']] ?? $svc['price']);
            $svc['desc']  = sanitize_text_field($_POST['desc_'  . $svc['id']] ?? $svc['desc']);
        }

        // Logo Upload
        $logo_url = $old['sidebar_logo_url'] ?? '';
        if (isset($_FILES['sidebar_logo']) && $_FILES['sidebar_logo']['error'] === UPLOAD_ERR_OK) {
            $file    = $_FILES['sidebar_logo'];
            $allowed = ['image/jpeg','image/png','image/webp','image/gif','image/svg+xml'];
            if (in_array($file['type'], $allowed) && $file['size'] < 2 * 1024 * 1024) {
                $upload = wp_upload_dir();
                $dir    = $upload['basedir'] . '/studiobook-logos/';
                if (!file_exists($dir)) wp_mkdir_p($dir);
                $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'sb-logo.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
                    $logo_url = $upload['baseurl'] . '/studiobook-logos/' . $filename . '?v=' . time();
                }
            }
        } elseif (!empty($_POST['sidebar_logo_remove'])) {
            $logo_url = '';
        }

        // Reset booking counter
        $booking_counter = $old['booking_counter'] ?? 0;
        if (!empty($_POST['reset_booking_counter'])) {
            $booking_counter = max(0, intval($_POST['booking_counter_start'] ?? 0));
        }

        update_option('studiobook_settings', array_merge($old, [
            'services'                 => $old['services'],
            'mm_turnaround_days'       => sanitize_text_field($_POST['mm_turnaround_days'] ?? '3-5 Werktage'),
            'tax_enabled'              => !empty($_POST['tax_enabled']),
            'tax_rate'                 => floatval($_POST['tax_rate'] ?? 19),
            'tax_inclusive'            => !empty($_POST['tax_inclusive']),
            'min_advance_hours_online' => intval($_POST['min_advance_hours_online'] ?? 24),
            'min_advance_hours_cash'   => intval($_POST['min_advance_hours_cash'] ?? 24),
            'cancel_free_hours'        => intval($_POST['cancel_free_hours'] ?? 12),
            'cancel_partial_percent'   => intval($_POST['cancel_partial_percent'] ?? 50),
            'show_test_badge'          => !empty($_POST['show_test_badge']),
            'stripe_mode'              => sanitize_text_field($_POST['stripe_mode']),
            'stripe_pk_test'           => sanitize_text_field($_POST['stripe_pk_test']),
            'stripe_sk_test'           => sanitize_text_field($_POST['stripe_sk_test']),
            'stripe_pk_live'           => sanitize_text_field($_POST['stripe_pk_live']),
            'stripe_sk_live'           => sanitize_text_field($_POST['stripe_sk_live']),
            'paypal_mode'              => sanitize_text_field($_POST['paypal_mode']),
            'paypal_client_id_sandbox' => sanitize_text_field($_POST['paypal_client_id_sandbox']),
            'paypal_secret_sandbox'    => sanitize_text_field($_POST['paypal_secret_sandbox']),
            'paypal_client_id_live'    => sanitize_text_field($_POST['paypal_client_id_live']),
            'paypal_secret_live'       => sanitize_text_field($_POST['paypal_secret_live']),
            'notification_email'       => sanitize_email($_POST['notification_email']),
            'sidebar_logo_url'         => $logo_url,
            'sidebar_logo_link'        => sanitize_text_field($_POST['sidebar_logo_link'] ?? home_url('/')),
            'booking_counter'          => $booking_counter,
        ]));
        echo '<div class="notice notice-success"><p>✅ Gespeichert.</p></div>';
    }
    $s = studiobook_get_settings();
    ?>
    <div class="wrap sb-admin-wrap">
        <h1>⚙️ Einstellungen</h1>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('studiobook_settings'); ?>

            <div class="sb-card">
                <h2>Sidebar & Branding</h2>
                <table class="form-table">
                    <tr><th>Logo</th><td>
                        <?php if (!empty($s['sidebar_logo_url'])) : ?>
                        <img src="<?= esc_url($s['sidebar_logo_url']) ?>" class="sb-logo-preview" alt="Logo">
                        <label><input type="checkbox" name="sidebar_logo_remove" value="1"> Logo entfernen</label><br><br>
                        <?php endif; ?>
                        <input type="file" name="sidebar_logo" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml">
                        <br><small>JPG, PNG, WebP, GIF oder SVG – max. 2MB. Empfohlen: 200×60px.</small>
                    </td></tr>
                    <tr><th>Logo Link</th><td><input type="text" name="sidebar_logo_link" value="<?= esc_attr($s['sidebar_logo_link'] ?? home_url('/')) ?>" class="regular-text"></td></tr>
                </table>
            </div>

            <div class="sb-card">
                <h2>Services & Preise</h2>
                <table class="form-table">
                <?php foreach($s['services'] as $svc): ?>
                    <tr><th colspan="2" style="color:#553eff;padding-bottom:4px;"><?= esc_html($svc['name']) ?></th></tr>
                    <tr><th>Preis</th><td><input type="number" name="price_<?= $svc['id'] ?>" value="<?= esc_attr($svc['price']) ?>" step="0.01" min="0"> €</td></tr>
                    <tr><th>Beschreibung</th><td><input type="text" name="desc_<?= $svc['id'] ?>" value="<?= esc_attr($svc['desc']) ?>" class="large-text"></td></tr>
                <?php endforeach; ?>
                    <tr><th>M&M Bearbeitungszeit</th><td><input type="text" name="mm_turnaround_days" value="<?= esc_attr($s['mm_turnaround_days'] ?? '3-5 Werktage') ?>" class="regular-text"></td></tr>
                </table>
            </div>

            <div class="sb-card">
                <h2>Mehrwertsteuer</h2>
                <table class="form-table">
                    <tr><th>MwSt. aktivieren</th><td><label><input type="checkbox" name="tax_enabled" value="1" <?= checked(!empty($s['tax_enabled'])) ?>> Auf Rechnungen anzeigen</label></td></tr>
                    <tr><th>MwSt.-Satz</th><td><input type="number" name="tax_rate" value="<?= esc_attr($s['tax_rate'] ?? 19) ?>" min="0" max="100" step="0.01"> %</td></tr>
                    <tr><th>MwSt. inklusiv</th><td><label><input type="checkbox" name="tax_inclusive" value="1" <?= checked(!empty($s['tax_inclusive'] ?? true)) ?>> MwSt. ist im Preis enthalten (nicht drauf)</label><br><small>Aktiviert: 100€ Preis = 84,03€ netto + 15,97€ MwSt. (19%)</small></td></tr>
                </table>
            </div>

            <div class="sb-card">
                <h2>Buchungsregeln</h2>
                <table class="form-table">
                    <tr><th>Vorlauf (Online)</th><td><input type="number" name="min_advance_hours_online" value="<?= esc_attr($s['min_advance_hours_online'] ?? 24) ?>" min="0"> Stunden</td></tr>
                    <tr><th>Vorlauf (Bar)</th><td><input type="number" name="min_advance_hours_cash" value="<?= esc_attr($s['min_advance_hours_cash'] ?? 24) ?>" min="0"> Stunden</td></tr>
                    <tr><th>Kostenlose Stornierung bis</th><td><input type="number" name="cancel_free_hours" value="<?= esc_attr($s['cancel_free_hours'] ?? 12) ?>" min="0"> Stunden vor Termin</td></tr>
                    <tr><th>Storno-Einbehalt</th><td><input type="number" name="cancel_partial_percent" value="<?= esc_attr($s['cancel_partial_percent'] ?? 50) ?>" min="0" max="100"> %</td></tr>
                </table>
            </div>

            <div class="sb-card">
                <h2>Buchungsnummer</h2>
                <p>Aktueller Zähler: <strong><?= intval($s['booking_counter'] ?? 0) ?></strong></p>
                <table class="form-table">
                    <tr><th>Zähler zurücksetzen</th><td>
                        <label><input type="checkbox" name="reset_booking_counter" value="1"> Zähler auf
                        <input type="number" name="booking_counter_start" value="0" min="0" style="width:70px;"> setzen</label>
                    </td></tr>
                </table>
            </div>

            <div class="sb-card">
                <h2>Test-Modus</h2>
                <table class="form-table">
                    <tr><th>Test-Button</th><td><label><input type="checkbox" name="show_test_badge" value="1" <?= checked(!empty($s['show_test_badge'])) ?>> Test-Buchungs-Button im Frontend anzeigen</label></td></tr>
                </table>
            </div>

            <div class="sb-card">
                <h2>Benachrichtigungen</h2>
                <table class="form-table">
                    <tr><th>Admin E-Mail</th><td><input type="email" name="notification_email" value="<?= esc_attr($s['notification_email'] ?? '') ?>" class="regular-text"></td></tr>
                </table>
            </div>

            <div class="sb-card">
                <h2>Stripe</h2>
                <table class="form-table">
                    <tr><th>Modus</th><td><select name="stripe_mode"><option value="test" <?= selected($s['stripe_mode']??'test','test',false) ?>>Test</option><option value="live" <?= selected($s['stripe_mode']??'test','live',false) ?>>Live</option></select></td></tr>
                    <tr><th>Public Key (Test)</th><td><input type="text" name="stripe_pk_test" value="<?= esc_attr($s['stripe_pk_test']??'') ?>" class="regular-text"></td></tr>
                    <tr><th>Secret Key (Test)</th><td><input type="password" name="stripe_sk_test" value="<?= esc_attr($s['stripe_sk_test']??'') ?>" class="regular-text"></td></tr>
                    <tr><th>Public Key (Live)</th><td><input type="text" name="stripe_pk_live" value="<?= esc_attr($s['stripe_pk_live']??'') ?>" class="regular-text"></td></tr>
                    <tr><th>Secret Key (Live)</th><td><input type="password" name="stripe_sk_live" value="<?= esc_attr($s['stripe_sk_live']??'') ?>" class="regular-text"></td></tr>
                </table>
            </div>

            <div class="sb-card">
                <h2>PayPal</h2>
                <table class="form-table">
                    <tr><th>Modus</th><td><select name="paypal_mode"><option value="sandbox" <?= selected($s['paypal_mode']??'sandbox','sandbox',false) ?>>Sandbox</option><option value="live" <?= selected($s['paypal_mode']??'sandbox','live',false) ?>>Live</option></select></td></tr>
                    <tr><th>Client ID (Sandbox)</th><td><input type="text" name="paypal_client_id_sandbox" value="<?= esc_attr($s['paypal_client_id_sandbox']??'') ?>" class="regular-text"></td></tr>
                    <tr><th>Secret (Sandbox)</th><td><input type="password" name="paypal_secret_sandbox" value="<?= esc_attr($s['paypal_secret_sandbox']??'') ?>" class="regular-text"></td></tr>
                    <tr><th>Client ID (Live)</th><td><input type="text" name="paypal_client_id_live" value="<?= esc_attr($s['paypal_client_id_live']??'') ?>" class="regular-text"></td></tr>
                    <tr><th>Secret (Live)</th><td><input type="password" name="paypal_secret_live" value="<?= esc_attr($s['paypal_secret_live']??'') ?>" class="regular-text"></td></tr>
                </table>
            </div>

            <p><input type="submit" name="save_settings" class="button button-primary button-large" value="✅ Alles speichern"></p>
            <p><strong>Shortcode:</strong> <code>[studiobook]</code> &nbsp; <strong>Portal:</strong> <code>[studiobook_portal]</code> &nbsp; <strong>Nav-Icon:</strong> <code>[studiobook_login_icon]</code></p>
        </form>
    </div>
    <?php
}

// ─────────────────────────────────────────────
//  RECHNUNG
// ─────────────────────────────────────────────
function studiobook_admin_invoice_page() {
    if (isset($_POST['save_invoice']) && check_admin_referer('studiobook_invoice')) {
        $old = studiobook_get_settings();
        foreach (['invoice_company','invoice_name','invoice_address','invoice_plz','invoice_city','invoice_tax_id','invoice_iban','invoice_bic','invoice_bank','invoice_prefix'] as $f) {
            $old[$f] = sanitize_text_field($_POST[$f] ?? '');
        }
        if (!empty($_POST['reset_counter'])) $old['invoice_counter'] = max(0, intval($_POST['invoice_counter_start'] ?? 0));
        update_option('studiobook_settings', $old);
        echo '<div class="notice notice-success"><p>✅ Gespeichert.</p></div>';
    }
    $s    = studiobook_get_settings();
    $next = ($s['invoice_prefix'] ?? 'RE') . '-' . date('Y') . '-' . str_pad(intval($s['invoice_counter'] ?? 0) + 1, 3, '0', STR_PAD_LEFT);
    $mpdf_ok = file_exists(STUDIOBOOK_PATH . 'vendor/autoload.php');
    $wk_ok   = !empty(trim(@shell_exec('which wkhtmltopdf 2>/dev/null')));
    ?>
    <div class="wrap sb-admin-wrap">
        <h1>📄 Rechnungsdetails</h1>
        <div class="sb-card" style="border-left:4px solid <?= ($mpdf_ok || $wk_ok) ? '#28a745' : '#ffc107' ?>;">
            <h2>PDF-Status</h2>
            <?php if ($mpdf_ok) : ?><p>✅ <strong>mPDF</strong> installiert – echte PDFs aktiv.</p>
            <?php elseif ($wk_ok) : ?><p>✅ <strong>wkhtmltopdf</strong> verfügbar.</p>
            <?php else : ?><p>⚠️ Kein PDF-Generator. Rechnungen als HTML (im Browser öffnen). <br>Nutze den mPDF-Installer für echte PDFs.</p>
            <?php endif; ?>
        </div>
        <div class="sb-card">
            <h2>Nächste Rechnungsnummer: <strong><?= esc_html($next) ?></strong></h2>
        </div>
        <div class="sb-card">
            <h2>Rechnungssteller</h2>
            <form method="post">
                <?php wp_nonce_field('studiobook_invoice'); ?>
                <table class="form-table">
                    <tr><th>Firmenname</th><td><input type="text" name="invoice_company" value="<?= esc_attr($s['invoice_company']??'') ?>" class="regular-text" placeholder="Firmenname"></td></tr>
                    <tr><th>Inhaber</th><td><input type="text" name="invoice_name" value="<?= esc_attr($s['invoice_name']??'') ?>" class="regular-text" placeholder="Vor- und Nachname"></td></tr>
                    <tr><th>Straße</th><td><input type="text" name="invoice_address" value="<?= esc_attr($s['invoice_address']??'') ?>" class="regular-text" placeholder="Straße und Hausnummer"></td></tr>
                    <tr><th>PLZ</th><td><input type="text" name="invoice_plz" value="<?= esc_attr($s['invoice_plz']??'') ?>" class="regular-text" placeholder="PLZ"></td></tr>
                    <tr><th>Ort</th><td><input type="text" name="invoice_city" value="<?= esc_attr($s['invoice_city']??'') ?>" class="regular-text" placeholder="Stadt"></td></tr>
                    <tr><th>Steuernummer / USt-ID</th><td><input type="text" name="invoice_tax_id" value="<?= esc_attr($s['invoice_tax_id']??'') ?>" class="regular-text" placeholder="Steuernummer oder USt-ID"></td></tr>
                    <tr><th>IBAN</th><td><input type="text" name="invoice_iban" value="<?= esc_attr($s['invoice_iban']??'') ?>" class="regular-text" placeholder="IBAN"></td></tr>
                    <tr><th>BIC</th><td><input type="text" name="invoice_bic" value="<?= esc_attr($s['invoice_bic']??'') ?>" class="regular-text" placeholder="BIC"></td></tr>
                    <tr><th>Bank</th><td><input type="text" name="invoice_bank" value="<?= esc_attr($s['invoice_bank']??'') ?>" class="regular-text" placeholder="Bankname"></td></tr>
                    <tr><th>Präfix</th><td><input type="text" name="invoice_prefix" value="<?= esc_attr($s['invoice_prefix']??'RE') ?>" class="small-text" placeholder="RE"></td></tr>
                    <tr><th>Zähler zurücksetzen</th><td>
                        <label><input type="checkbox" name="reset_counter" value="1"> Zähler auf
                        <input type="number" name="invoice_counter_start" value="0" min="0" style="width:70px;"> setzen</label>
                    </td></tr>
                </table>
                <p><input type="submit" name="save_invoice" class="button button-primary" value="✅ Speichern"></p>
            </form>
        </div>
    </div>
    <?php
}

// ─────────────────────────────────────────────
//  TEXTE
// ─────────────────────────────────────────────
function studiobook_admin_texts_page() {
    if (isset($_POST['save_texts']) && check_admin_referer('studiobook_texts')) {
        $old    = studiobook_get_settings();
        $fields = ['text_step1_title','text_step2_cal_title','text_step2_upload_title','text_step3_title','text_step4_title','text_privacy_label','text_agb_label','text_widerruf_label','text_cancel_policy','text_success_title','text_success_session','text_success_mix','text_upload_drag','text_upload_formats','text_cash_info','text_message_placeholder_session','text_message_placeholder_mix','placeholder_name','placeholder_email','placeholder_phone','placeholder_artist','placeholder_address','placeholder_plz','placeholder_city','placeholder_coupon'];
        foreach ($fields as $f) $old[$f] = wp_kses_post($_POST[$f] ?? $old[$f] ?? '');
        update_option('studiobook_settings', $old);
        echo '<div class="notice notice-success"><p>✅ Texte gespeichert.</p></div>';
    }
    $s = studiobook_get_settings();
    $groups = [
        'Schritte' => ['text_step1_title'=>'Step 1: Service','text_step2_cal_title'=>'Step 2: Kalender','text_step2_upload_title'=>'Step 2: Upload','text_step3_title'=>'Step 3: Kontakt','text_step4_title'=>'Step 4: Zahlung'],
        'Rechtliches' => ['text_privacy_label'=>'Datenschutz','text_agb_label'=>'AGB','text_widerruf_label'=>'Widerruf','text_cancel_policy'=>'Stornobedingungen'],
        'Bestätigung' => ['text_success_title'=>'Buchung bestätigt: Titel','text_success_session'=>'Text Session','text_success_mix'=>'Text M&M'],
        'Upload & Sonstiges' => [
            'text_upload_drag'                 => 'Upload: Drag-Text',
            'text_upload_formats'              => 'Upload: Formate',
            'text_cash_info'                   => 'Barzahlung Info',
            'text_message_placeholder_session' => 'Placeholder Nachricht (Session)',
            'text_message_placeholder_mix'     => 'Placeholder Anmerkungen (M&M)',
        ],
        'Formular Placeholders' => [
            'placeholder_name'    => 'Placeholder Name',
            'placeholder_email'   => 'Placeholder E-Mail',
            'placeholder_phone'   => 'Placeholder Telefon',
            'placeholder_artist'  => 'Placeholder Künstlername',
            'placeholder_address' => 'Placeholder Adresse',
            'placeholder_plz'     => 'Placeholder PLZ',
            'placeholder_city'    => 'Placeholder Stadt',
            'placeholder_coupon'  => 'Placeholder Gutscheincode',
        ],
    ];
    ?>
    <div class="wrap sb-admin-wrap">
        <h1>✏️ Texte</h1>
        <form method="post">
            <?php wp_nonce_field('studiobook_texts'); ?>
            <?php foreach ($groups as $title => $fields) : ?>
            <div class="sb-card">
                <h2><?= esc_html($title) ?></h2>
                <table class="form-table">
                <?php foreach ($fields as $key => $label) : ?>
                    <tr><th><?= esc_html($label) ?></th>
                    <td><input type="text" name="<?= esc_attr($key) ?>" value="<?= esc_attr($s[$key]??'') ?>" class="large-text"></td></tr>
                <?php endforeach; ?>
                </table>
            </div>
            <?php endforeach; ?>
            <p><input type="submit" name="save_texts" class="button button-primary button-large" value="✅ Speichern"></p>
        </form>
    </div>
    <?php
}

// ─────────────────────────────────────────────
//  FARBEN
// ─────────────────────────────────────────────
function studiobook_admin_colors_page() {
    if (isset($_POST['save_colors']) && check_admin_referer('studiobook_colors')) {
        $old = studiobook_get_settings();
        foreach (['color_accent','color_bg','color_surface','color_border','color_text','color_muted','color_btn_text'] as $f) {
            $v = sanitize_text_field($_POST[$f] ?? '');
            if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $v)) $old[$f] = $v;
        }
        update_option('studiobook_settings', $old);
        echo '<div class="notice notice-success"><p>✅ Farben gespeichert.</p></div>';
    }
    $s      = studiobook_get_settings();
    $colors = ['color_accent'=>'Akzentfarbe','color_bg'=>'Hintergrund','color_surface'=>'Karten/Felder','color_border'=>'Rahmen','color_text'=>'Text','color_muted'=>'Gedämpfter Text','color_btn_text'=>'Button-Text'];
    ?>
    <div class="wrap sb-admin-wrap">
        <h1>🎨 Farben</h1>
        <div class="sb-card">
            <h2>Corporate Design</h2>
            <form method="post">
                <?php wp_nonce_field('studiobook_colors'); ?>
                <table class="form-table">
                <?php foreach ($colors as $key => $label) : ?>
                    <tr><th><?= esc_html($label) ?></th>
                    <td style="display:flex;align-items:center;gap:12px;">
                        <input type="color" id="c_<?= $key ?>" value="<?= esc_attr($s[$key]??'#000000') ?>" style="width:48px;height:36px;padding:2px;border-radius:6px;cursor:pointer;">
                        <input type="text" name="<?= esc_attr($key) ?>" id="h_<?= $key ?>" value="<?= esc_attr($s[$key]??'#000000') ?>" class="small-text" style="font-family:monospace;width:90px;">
                        <span style="width:32px;height:32px;border-radius:6px;background:<?= esc_attr($s[$key]??'#000') ?>;border:1px solid #ccc;display:inline-block;" id="p_<?= $key ?>"></span>
                    </td></tr>
                <?php endforeach; ?>
                </table>
                <p><input type="submit" name="save_colors" class="button button-primary button-large" value="✅ Speichern"></p>
            </form>
        </div>
    </div>
    <script>
    document.querySelectorAll('input[type="color"]').forEach(function(p){
        var k=p.id.replace('c_','');
        p.addEventListener('input',function(){
            document.getElementById('h_'+k).value=this.value;
            document.getElementById('p_'+k).style.background=this.value;
        });
        document.getElementById('h_'+k).addEventListener('input',function(){
            if(/^#[0-9a-fA-F]{3,8}$/.test(this.value)){
                document.getElementById('c_'+k).value=this.value;
                document.getElementById('p_'+k).style.background=this.value;
            }
        });
    });
    </script>
    <?php
}

// ─────────────────────────────────────────────
//  PORTAL INHALTE
// ─────────────────────────────────────────────
function studiobook_admin_portal_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'studiobook_portal_content';

    if (isset($_POST['save_portal_content']) && check_admin_referer('studiobook_portal_content')) {
        $key   = sanitize_key($_POST['content_key'] ?? '');
        $title = sanitize_text_field($_POST['content_title'] ?? '');
        $body  = wp_kses_post($_POST['content_body'] ?? '');
        $wpdb->replace($table, ['content_key'=>$key,'content_title'=>$title,'content_body'=>$body], ['%s','%s','%s']);
        echo '<div class="notice notice-success"><p>✅ Gespeichert.</p></div>';
    }

    $pages   = ['gema'=>'GEMA & Urheberrecht','wissenswertes'=>'Wissenswertes','faq'=>'FAQ','datenschutz'=>'Datenschutz & AGB','support'=>'Support & Kontakt'];
    $editing = sanitize_key($_GET['edit'] ?? array_key_first($pages));
    $content = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE content_key = %s", $editing));
    ?>
    <div class="wrap sb-admin-wrap">
        <h1>📚 Portal Inhalte</h1>
        <div style="display:flex;gap:1.5rem;align-items:flex-start;">
            <div style="min-width:180px;">
                <?php foreach ($pages as $key => $label) : ?>
                <a href="?page=100studios-portal&edit=<?= $key ?>"
                   style="display:block;padding:10px 14px;margin-bottom:4px;border-radius:6px;text-decoration:none;background:<?= $editing===$key?'#553eff':'#f0f0f0' ?>;color:<?= $editing===$key?'#fff':'#333' ?>;font-weight:<?= $editing===$key?'700':'400' ?>;">
                    <?= esc_html($label) ?>
                </a>
                <?php endforeach; ?>
            </div>
            <div style="flex:1;">
                <div class="sb-card">
                    <h2><?= esc_html($pages[$editing] ?? $editing) ?></h2>
                    <form method="post">
                        <?php wp_nonce_field('studiobook_portal_content'); ?>
                        <input type="hidden" name="content_key" value="<?= esc_attr($editing) ?>">
                        <table class="form-table">
                            <tr><th>Überschrift</th><td><input type="text" name="content_title" value="<?= esc_attr($content->content_title ?? '') ?>" class="large-text"></td></tr>
                            <tr><th>Inhalt</th><td><?php wp_editor($content->content_body ?? '', 'content_body', ['textarea_name'=>'content_body','media_buttons'=>false,'textarea_rows'=>15]); ?></td></tr>
                        </table>
                        <p><input type="submit" name="save_portal_content" class="button button-primary" value="✅ Speichern"></p>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
}
