<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'studiobook_portal', 'studiobook_portal_shortcode' );
function studiobook_portal_shortcode() {
    $s       = studiobook_get_settings();
    $accent  = $s['color_accent']   ?? '#553eff';
    $bg      = $s['color_bg']       ?? '#000000';
    $surface = $s['color_surface']  ?? '#111111';
    $border  = $s['color_border']   ?? '#222222';
    $text    = $s['color_text']     ?? '#ffffff';
    $muted   = $s['color_muted']    ?? '#888888';

    ob_start();
    ?>
    <style>
    #sb-portal {
        --sb-accent:    <?= esc_attr($accent) ?>;
        --sb-accent-dim:<?= esc_attr($accent) ?>22;
        --sb-bg:        <?= esc_attr($bg) ?>;
        --sb-surface:   <?= esc_attr($surface) ?>;
        --sb-border:    <?= esc_attr($border) ?>;
        --sb-text:      <?= esc_attr($text) ?>;
        --sb-muted:     <?= esc_attr($muted) ?>;
        --btn-main-text:  #ffffff;
        --sb-sidebar-w: 260px;
    }
    </style>
    <div id="sb-portal">
    <?php if ( ! is_user_logged_in() ) : ?>
        <?= studiobook_portal_auth_screen() ?>
    <?php else : ?>
        <?= studiobook_portal_dashboard() ?>
    <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// ── Auth Screen ──
function studiobook_portal_auth_screen() {
    ob_start();
    ?>
    <div class="sb-auth-wrap">
        <div class="sb-auth-box">
            <?php
            $s = studiobook_get_settings();
            $logo_url  = $s['sidebar_logo_url']  ?? '';
            $logo_link = $s['sidebar_logo_link'] ?? home_url('/');
            ?>
            <div class="sb-auth-logo">
                <a href="<?= esc_url($logo_link) ?>" style="text-decoration:none;">
                    <?php if ($logo_url) : ?>
                    <img src="<?= esc_url($logo_url) ?>" alt="Logo" style="max-height:60px;max-width:200px;object-fit:contain;">
                    <?php else : ?>
                    <span style="color:var(--sb-accent);font-weight:900;letter-spacing:-1px;">100Studios</span>
                    <?php endif; ?>
                </a>
            </div>
            <p class="sb-auth-sub">Dein persönliches Studio-Portal</p>

            <div class="sb-auth-tabs">
                <button class="sb-auth-tab active" data-tab="login">Einloggen</button>
                <button class="sb-auth-tab" data-tab="register">Registrieren</button>
            </div>

            <!-- Login -->
            <div class="sb-auth-panel active" id="sb-tab-login">
                <div class="sb-field">
                    <label for="sb-login-user">Benutzername oder E-Mail</label>
                    <input type="text" id="sb-login-user" placeholder="dein@email.de" autocomplete="username">
                </div>
                <div class="sb-field">
                    <label for="sb-login-pass">Passwort</label>
                    <input type="password" id="sb-login-pass" placeholder="••••••••" autocomplete="current-password">
                </div>
                <div id="sb-login-error" class="sb-auth-error"></div>
                <div class="sb-auth-btn-row">
                    <a href="<?= esc_url(home_url('/')) ?>" class="btn-main-back">&#8592; Zurück</a>
                    <button class="btn-main" id="sb-login-btn">Einloggen</button>
                </div>
                <button class="btn-main-link" id="sb-forgot-btn">Passwort vergessen?</button>
            </div>

            <!-- Register -->
            <div class="sb-auth-panel" id="sb-tab-register">
                <div class="sb-field">
                    <label for="sb-reg-username">Benutzername *</label>
                    <input type="text" id="sb-reg-username" placeholder="deinname" autocomplete="username">
                </div>
                <div class="sb-field">
                    <label for="sb-reg-email">E-Mail *</label>
                    <input type="email" id="sb-reg-email" placeholder="deine@mail.de" autocomplete="email">
                </div>
                <div class="sb-field">
                    <label for="sb-reg-artist">Künstlername (optional)</label>
                    <input type="text" id="sb-reg-artist" placeholder="EN100">
                </div>
                <div class="sb-field">
                    <label for="sb-reg-pass">Passwort *</label>
                    <input type="password" id="sb-reg-pass" placeholder="Min. 8 Zeichen" autocomplete="new-password">
                </div>
                <div class="sb-field">
                    <label for="sb-reg-pass2">Passwort bestätigen *</label>
                    <input type="password" id="sb-reg-pass2" placeholder="Wiederholen" autocomplete="new-password">
                </div>
                <div id="sb-reg-error" class="sb-auth-error"></div>
                <div class="sb-auth-btn-row">
                    <a href="<?= esc_url(home_url('/')) ?>" class="btn-main-back">&#8592; Zurück</a>
                    <button class="btn-main" id="sb-reg-btn">Konto erstellen</button>
                </div>
            </div>

            <!-- Forgot -->
            <div class="sb-auth-panel" id="sb-tab-forgot">
                <p style="color:var(--sb-muted);margin-bottom:1rem;">Gib deine E-Mail ein um einen Reset-Link zu erhalten.</p>
                <div class="sb-field">
                    <label for="sb-forgot-email">E-Mail</label>
                    <input type="email" id="sb-forgot-email" placeholder="deine@mail.de" autocomplete="email">
                </div>
                <div id="sb-forgot-msg" class="sb-auth-error"></div>
                <div class="sb-auth-btn-row">
                    <button class="btn-main-back" id="sb-back-to-login" type="button">&#8592; Zurück</button>
                    <button class="btn-main" id="sb-forgot-submit-btn">Reset-Link senden</button>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ── Dashboard ──
function studiobook_portal_dashboard() {
    $user        = wp_get_current_user();
    $artist_name = get_user_meta($user->ID, 'studiobook_artist_name', true)
                ?: get_user_meta($user->ID, 'artist_name', true);
    $avatar_url  = get_user_meta($user->ID, 'studiobook_avatar', true);
    $display     = $artist_name ?: $user->display_name;
    $section     = sanitize_text_field($_GET['section'] ?? 'bookings');

    $s          = studiobook_get_settings();
    $logo_url   = $s['sidebar_logo_url']  ?? '';
    $logo_link  = $s['sidebar_logo_link'] ?? home_url('/');

    ob_start();
    ?>
    <div class="sb-overlay" id="sb-overlay"></div>

    <div class="sb-dashboard">
        <!-- Sidebar -->
        <aside class="sb-sidebar" id="sb-sidebar">
            <div class="sb-sidebar-header">
                <a href="<?= esc_url($logo_link) ?>" class="sb-sidebar-logo-link">
                    <?php if ($logo_url) : ?>
                    <img src="<?= esc_url($logo_url) ?>" alt="Logo" class="sb-sidebar-logo-img">
                    <?php else : ?>
                    <span class="sb-sidebar-logo">100Studios</span>
                    <?php endif; ?>
                </a>
                <button class="sb-sidebar-close" id="sb-sidebar-close" aria-label="Menü schließen">&#10005;</button>
            </div>

            <div class="sb-sidebar-user">
                <?php if ($avatar_url) : ?>
                <img src="<?= esc_url($avatar_url) ?>" class="sb-sidebar-avatar" alt="Profilbild">
                <?php else : ?>
                <div class="sb-sidebar-avatar-placeholder" aria-hidden="true">&#128100;</div>
                <?php endif; ?>
                <div class="sb-sidebar-user-info">
                    <span class="sb-sidebar-username"><?= esc_html($display) ?></span>
                    <span class="sb-sidebar-email"><?= esc_html($user->user_email) ?></span>
                </div>
            </div>

            <nav class="sb-sidebar-nav" aria-label="Dashboard Navigation">
                <?php
                $nav_items = [
                    'bookings'      => ['icon' => '📋', 'label' => 'Meine Buchungen'],
                    'orders'        => ['icon' => '🎛️', 'label' => 'Aufträge'],
                    'invoices'      => ['icon' => '🧾', 'label' => 'Rechnungen'],
                    'gema'          => ['icon' => '🎵', 'label' => 'GEMA & Urheberrecht'],
                    'wissenswertes' => ['icon' => '💡', 'label' => 'Wissenswertes'],
                    'faq'           => ['icon' => '❓', 'label' => 'FAQ'],
                    'datenschutz'   => ['icon' => '🔒', 'label' => 'Datenschutz & AGB'],
                    'support'       => ['icon' => '💬', 'label' => 'Support & Kontakt'],
                    'settings'      => ['icon' => '⚙️', 'label' => 'Einstellungen'],
                ];
                foreach ($nav_items as $key => $item) :
                    $active = $section === $key ? 'active' : '';
                    $url    = add_query_arg('section', $key, get_permalink());
                ?>
                <a href="<?= esc_url($url) ?>" class="sb-nav-item <?= $active ?>">
                    <span class="sb-nav-icon" aria-hidden="true"><?= $item['icon'] ?></span>
                    <span><?= esc_html($item['label']) ?></span>
                </a>
                <?php endforeach; ?>

                <div class="sb-sidebar-divider"></div>

                <a href="<?= esc_url(home_url('/')) ?>" class="sb-nav-item sb-nav-home">
                    <span class="sb-nav-icon" aria-hidden="true">🏠</span>
                    <span>Zur Startseite</span>
                </a>

                <button class="sb-nav-item sb-logout-btn" id="sb-logout-btn">
                    <span class="sb-nav-icon" aria-hidden="true">🚪</span>
                    <span>Ausloggen</span>
                </button>
            </nav>
        </aside>

        <!-- Main -->
        <main class="sb-main">
            <div class="sb-mobile-header">
                <button class="sb-hamburger" id="sb-hamburger" aria-label="Menü öffnen">
                    <span></span><span></span><span></span>
                </button>
                <span class="sb-mobile-title">
                    <?php if ($logo_url) : ?>
                    <img src="<?= esc_url($logo_url) ?>" alt="Logo" style="max-height:28px;object-fit:contain;vertical-align:middle;">
                    <?php else : ?>
                    100Studios
                    <?php endif; ?>
                </span>
                <?php if ($avatar_url) : ?>
                <img src="<?= esc_url($avatar_url) ?>" class="sb-mobile-avatar" alt="Profil">
                <?php else : ?>
                <div class="sb-mobile-avatar-placeholder" aria-hidden="true">&#128100;</div>
                <?php endif; ?>
            </div>

            <div class="sb-content">
                <?= studiobook_portal_section($section, $user) ?>
            </div>
        </main>
    </div>
    <?php
    return ob_get_clean();
}

// ── Section Router ──
function studiobook_portal_section( $section, $user ) {
    switch ($section) {
        case 'bookings': return studiobook_portal_bookings($user);
        case 'orders':   return studiobook_portal_orders($user);
        case 'invoices': return studiobook_portal_invoices($user);
        case 'settings': return studiobook_portal_settings($user);
        default:         return studiobook_portal_info_page($section);
    }
}

// ── Buchungen ──
function studiobook_portal_bookings( $user ) {
    global $wpdb;
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}studiobook_bookings
         WHERE user_id = %d AND service = 'studio_session' AND booking_status != 'cancelled'
         ORDER BY booking_date DESC",
        $user->ID
    ));
    $status_map = [
        'confirmed' => ['label' => 'Bestätigt',     'color' => '#28a745'],
        'completed' => ['label' => 'Abgeschlossen', 'color' => '#553eff'],
        'pending'   => ['label' => 'Ausstehend',    'color' => '#ffc107'],
    ];
    ob_start();
    ?>
    <div class="sb-section-header">
        <h2>📋 Meine Buchungen</h2>
    </div>
    <?php if (empty($bookings)) : ?>
    <div class="sb-empty-state">
        <div class="sb-empty-icon">📅</div>
        <p>Noch keine Buchungen vorhanden.</p>
        <a href="<?= esc_url(home_url('/buchen')) ?>" class="btn-main">Session buchen</a>
    </div>
    <?php else : ?>
    <div class="sb-cards">
        <?php foreach ($bookings as $b) :
            $date_str = $b->booking_date ? date('d.m.Y', strtotime($b->booking_date)) : '—';
            $status   = $status_map[$b->booking_status] ?? ['label' => $b->booking_status, 'color' => '#999'];
        ?>
        <div class="sb-card">
            <div class="sb-card-header">
                <span class="sb-card-title">Studio Session</span>
                <span class="sb-status-badge" style="background:<?= $status['color'] ?>22;color:<?= $status['color'] ?>;border:1px solid <?= $status['color'] ?>44;"><?= $status['label'] ?></span>
            </div>
            <div class="sb-card-body">
                <div class="sb-card-row"><span>Datum</span><strong><?= esc_html($date_str) ?></strong></div>
                <div class="sb-card-row"><span>Uhrzeit</span><strong><?= esc_html($b->slot_time) ?> Uhr</strong></div>
                <div class="sb-card-row"><span>Betrag</span><strong><?= number_format(floatval($b->price_gross ?: $b->price), 2, ',', '.') ?> €</strong></div>
                <div class="sb-card-row"><span>Zahlung</span><strong><?= ucfirst(esc_html($b->payment_method)) ?></strong></div>
            </div>
            <?php if ($b->booking_status === 'confirmed' && $b->cancel_token) :
                $cancel_url = home_url('/?studiobook_cancel=' . $b->cancel_token);
            ?>
            <div class="sb-card-footer">
                <a href="<?= esc_url($cancel_url) ?>" class="btn-main-danger-sm" onclick="return confirm('Buchung wirklich stornieren?')">Stornieren</a>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

// ── Aufträge ──
function studiobook_portal_orders( $user ) {
    global $wpdb;
    $orders = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}studiobook_bookings
         WHERE user_id = %d AND service = 'mixing_mastering'
         ORDER BY created_at DESC",
        $user->ID
    ));
    $status_map = [
        'confirmed' => ['label' => 'In Bearbeitung', 'color' => '#ffc107'],
        'completed' => ['label' => 'Fertig ✓',       'color' => '#28a745'],
        'cancelled' => ['label' => 'Storniert',       'color' => '#dc3545'],
        'pending'   => ['label' => 'Ausstehend',      'color' => '#999'],
    ];
    ob_start();
    ?>
    <div class="sb-section-header">
        <h2>🎛️ Aufträge</h2>
    </div>
    <?php if (empty($orders)) : ?>
    <div class="sb-empty-state">
        <div class="sb-empty-icon">🎛️</div>
        <p>Noch keine Mixing & Mastering Aufträge.</p>
        <a href="<?= esc_url(home_url('/buchen')) ?>" class="btn-main">Auftrag erstellen</a>
    </div>
    <?php else : ?>
    <div class="sb-cards">
        <?php foreach ($orders as $o) :
            $status = $status_map[$o->booking_status] ?? ['label' => $o->booking_status, 'color' => '#999'];
        ?>
        <div class="sb-card">
            <div class="sb-card-header">
                <span class="sb-card-title">Mixing & Mastering</span>
                <span class="sb-status-badge" style="background:<?= $status['color'] ?>22;color:<?= $status['color'] ?>;border:1px solid <?= $status['color'] ?>44;"><?= $status['label'] ?></span>
            </div>
            <div class="sb-card-body">
                <?php if ($o->file_name) : ?>
                <div class="sb-card-row"><span>Datei</span><strong>📎 <?= esc_html($o->file_name) ?></strong></div>
                <?php endif; ?>
                <div class="sb-card-row"><span>Beauftragt</span><strong><?= date('d.m.Y', strtotime($o->created_at)) ?></strong></div>
                <div class="sb-card-row"><span>Betrag</span><strong><?= number_format(floatval($o->price_gross ?: $o->price), 2, ',', '.') ?> €</strong></div>
                <?php if ($o->booking_status === 'completed' && $o->delivery_link) : ?>
                <div class="sb-card-row"><span>Deine Datei</span><strong><a href="<?= esc_url($o->delivery_link) ?>" target="_blank" class="sb-link">📥 Herunterladen</a></strong></div>
                <?php endif; ?>
                <?php if ($o->invoice_number) :
                    $inv_dl = wp_nonce_url(home_url('/?studiobook_invoice=' . $o->id), 'sb_invoice_' . $o->id);
                ?>
                <div class="sb-card-row"><span>Rechnung</span><strong><a href="<?= esc_url($inv_dl) ?>" class="sb-link">🧾 <?= esc_html($o->invoice_number) ?></a></strong></div>
                <?php endif; ?>
                <?php if ($o->delivery_note) : ?>
                <div class="sb-card-row sb-card-row-full"><span>Anmerkungen</span><p><?= esc_html($o->delivery_note) ?></p></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

// ── Rechnungen ──
function studiobook_portal_invoices( $user ) {
    global $wpdb;
    $invoices = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}studiobook_bookings
         WHERE user_id = %d AND invoice_number != ''
         ORDER BY created_at DESC",
        $user->ID
    ));
    ob_start();
    ?>
    <div class="sb-section-header">
        <h2>🧾 Rechnungen</h2>
    </div>
    <?php if (empty($invoices)) : ?>
    <div class="sb-empty-state">
        <div class="sb-empty-icon">🧾</div>
        <p>Noch keine Rechnungen vorhanden.</p>
    </div>
    <?php else : ?>
    <div class="sb-cards">
        <?php foreach ($invoices as $inv) :
            $service_label = $inv->service === 'studio_session' ? 'Studio Session' : 'Mixing & Mastering';
            $date_str      = $inv->completed_at
                ? date('d.m.Y', strtotime($inv->completed_at))
                : date('d.m.Y', strtotime($inv->created_at));
            $dl_url = wp_nonce_url(home_url('/?studiobook_invoice=' . $inv->id), 'sb_invoice_' . $inv->id);
        ?>
        <div class="sb-card">
            <div class="sb-card-header">
                <span class="sb-card-title"><?= esc_html($inv->invoice_number) ?></span>
                <span class="sb-status-badge" style="background:#553eff22;color:#553eff;border:1px solid #553eff44;">Bezahlt</span>
            </div>
            <div class="sb-card-body">
                <div class="sb-card-row"><span>Service</span><strong><?= esc_html($service_label) ?></strong></div>
                <div class="sb-card-row"><span>Datum</span><strong><?= $date_str ?></strong></div>
                <div class="sb-card-row"><span>Betrag</span><strong><?= number_format(floatval($inv->price_gross ?: $inv->price), 2, ',', '.') ?> €</strong></div>
            </div>
            <div class="sb-card-footer">
                <a href="<?= esc_url($dl_url) ?>" class="btn-main-download">
                    &#11123; Rechnung herunterladen
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

// ── Info-Seiten ──
function studiobook_portal_info_page( $section ) {
    global $wpdb;
    $allowed = ['gema','faq','wissenswertes','datenschutz','support'];
    if (!in_array($section, $allowed)) return '<p>Seite nicht gefunden.</p>';

    $content = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}studiobook_portal_content WHERE content_key = %s", $section
    ));
    ob_start();
    ?>
    <div class="sb-section-header">
        <h2><?= esc_html($content->content_title ?? $section) ?></h2>
    </div>
    <div class="sb-info-content">
        <?= wp_kses_post($content->content_body ?? '<p>Inhalt folgt.</p>') ?>
    </div>
    <?php
    return ob_get_clean();
}

// ── Einstellungen ──
function studiobook_portal_settings( $user ) {
    $artist_name = get_user_meta($user->ID, 'studiobook_artist_name', true)
                ?: get_user_meta($user->ID, 'artist_name', true);
    $avatar_url  = get_user_meta($user->ID, 'studiobook_avatar', true);
    ob_start();
    ?>
    <div class="sb-section-header">
        <h2>⚙️ Einstellungen</h2>
    </div>

    <div class="sb-settings-card">
        <h3>Profil</h3>
        <div class="sb-avatar-upload">
            <?php if ($avatar_url) : ?>
            <img src="<?= esc_url($avatar_url) ?>" class="sb-settings-avatar" id="sb-avatar-preview" alt="Avatar">
            <?php else : ?>
            <div class="sb-settings-avatar-placeholder" id="sb-avatar-preview" aria-hidden="true">&#128100;</div>
            <?php endif; ?>
            <div>
                <label class="btn-main secondary" style="cursor:pointer;display:inline-block;">
                    Bild ändern
                    <input type="file" id="sb-avatar-file" accept="image/*" style="display:none;" aria-label="Profilbild auswählen">
                </label>
                <p style="color:var(--sb-muted);margin-top:6px;">JPG, PNG oder WebP – max. 2MB</p>
            </div>
        </div>
        <div class="sb-settings-grid">
            <div class="sb-field">
                <label for="sb-set-username">Benutzername</label>
                <input type="text" id="sb-set-username" value="<?= esc_attr($user->user_login) ?>" disabled aria-disabled="true" style="opacity:0.5;cursor:not-allowed;">
            </div>
            <div class="sb-field">
                <label for="sb-set-artist">Künstlername</label>
                <input type="text" id="sb-set-artist" value="<?= esc_attr($artist_name) ?>" placeholder="EN100">
            </div>
            <div class="sb-field">
                <label for="sb-set-firstname">Vorname</label>
                <input type="text" id="sb-set-firstname" value="<?= esc_attr($user->first_name) ?>" placeholder="Enrico">
            </div>
            <div class="sb-field">
                <label for="sb-set-lastname">Nachname</label>
                <input type="text" id="sb-set-lastname" value="<?= esc_attr($user->last_name) ?>" placeholder="Kausler">
            </div>
            <div class="sb-field">
                <label for="sb-set-email">E-Mail</label>
                <input type="email" id="sb-set-email" value="<?= esc_attr($user->user_email) ?>">
            </div>
        </div>
        <div id="sb-profile-msg" class="sb-settings-msg" role="status" aria-live="polite"></div>
        <button class="btn-main" id="sb-save-profile-btn">Profil speichern</button>
    </div>

    <div class="sb-settings-card">
        <h3>Passwort ändern</h3>
        <div class="sb-settings-grid">
            <div class="sb-field">
                <label for="sb-pw-current">Aktuelles Passwort</label>
                <input type="password" id="sb-pw-current" placeholder="••••••••" autocomplete="current-password">
            </div>
            <div class="sb-field">
                <label for="sb-pw-new">Neues Passwort</label>
                <input type="password" id="sb-pw-new" placeholder="Min. 8 Zeichen" autocomplete="new-password">
            </div>
            <div class="sb-field">
                <label for="sb-pw-confirm">Passwort bestätigen</label>
                <input type="password" id="sb-pw-confirm" placeholder="Wiederholen" autocomplete="new-password">
            </div>
        </div>
        <div id="sb-pw-msg" class="sb-settings-msg" role="status" aria-live="polite"></div>
        <button class="btn-main" id="sb-save-pw-btn">Passwort ändern</button>
    </div>
    <?php
    return ob_get_clean();
}
