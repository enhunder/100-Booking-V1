<?php
if (! defined('ABSPATH')) exit;

add_shortcode('studiobook', 'studiobook_render_form');
function studiobook_render_form($atts)
{
    $s = studiobook_get_settings();

    $stripe_pk = ($s['stripe_mode'] ?? 'test') === 'live' ? ($s['stripe_pk_live'] ?? '') : ($s['stripe_pk_test'] ?? '');
    $paypal_id = ($s['paypal_mode'] ?? 'sandbox') === 'live' ? ($s['paypal_client_id_live'] ?? '') : ($s['paypal_client_id_sandbox'] ?? '');

    $accent   = esc_attr($s['color_accent']   ?? '#553eff');
    $bg       = esc_attr($s['color_bg']       ?? '#000000');
    $surface  = esc_attr($s['color_surface']  ?? '#111111');
    $border   = esc_attr($s['color_border']   ?? '#222222');
    $text_col = esc_attr($s['color_text']     ?? '#ffffff');
    $muted    = esc_attr($s['color_muted']    ?? '#888888');
    $btn_text = esc_attr($s['color_btn_text'] ?? '#ffffff');

    $t  = function ($k, $d = '') use ($s) {
        return esc_html($s[$k] ?? $d);
    };
    $tw = function ($k, $d = '') use ($s) {
        return wp_kses_post($s[$k] ?? $d);
    };

    $tax_enabled   = !empty($s['tax_enabled']);
    $tax_rate      = floatval($s['tax_rate'] ?? 19);
    $turnaround    = esc_html($s['mm_turnaround_days'] ?? '3-5 Werktage');
    $cancel_policy = esc_html($s['text_cancel_policy'] ?? 'Kostenlose Stornierung bis 12h vor dem Termin. Bei Stornierung innerhalb von 12h werden 50% einbehalten.');
    $show_test     = !empty($s['show_test_badge']);

    $ph_session = esc_attr($s['text_message_placeholder_session'] ?? 'Was bringst du mit? Besondere Wünsche?');
    $ph_mix     = esc_attr($s['text_message_placeholder_mix']     ?? 'z.B. Genre, Referenz-Sound, besondere Wünsche…');
    $ph_name    = esc_attr($s['placeholder_name']    ?? 'Vor- und Nachname');
    $ph_email   = esc_attr($s['placeholder_email']   ?? 'deine@mail.de');
    $ph_phone   = esc_attr($s['placeholder_phone']   ?? '+49 ...');
    $ph_artist  = esc_attr($s['placeholder_artist']  ?? 'Dein Künstlername (optional)');
    $ph_address = esc_attr($s['placeholder_address'] ?? 'Straße & Hausnummer');
    $ph_plz     = esc_attr($s['placeholder_plz']     ?? 'PLZ');
    $ph_city    = esc_attr($s['placeholder_city']    ?? 'Stadt');
    $ph_coupon  = esc_attr($s['placeholder_coupon']  ?? 'Gutschein- oder Rabattcode');

    ob_start();
?>
    <style>
        #studiobook-wrap {
            --sb-accent: <?= $accent ?>;
            --sb-accent-dim: <?= $accent ?>22;
            --sb-bg: <?= $bg ?>;
            --sb-surface: <?= $surface ?>;
            --sb-border: <?= $border ?>;
            --sb-text: <?= $text_col ?>;
            --sb-muted: <?= $muted ?>;
            --btn-main-text: <?= $btn_text ?>;
        }
    </style>

    <div id="studiobook-wrap" class="studiobook-wrap">

        <!-- Step 1: Service -->
        <div class="studiobook-step active" data-step="1">
            <h3 class="studiobook-step-title"><?= $t('text_step1_title', 'Welchen Service buchst du?') ?></h3>
            <div class="studiobook-services">
                <?php foreach ($s['services'] ?? [] as $svc) :
                    $is_mm = $svc['id'] === 'mixing_mastering';
                ?>
                    <label class="studiobook-service-card">
                        <input type="radio" name="studiobook_service"
                            value="<?= esc_attr($svc['id']) ?>"
                            data-price="<?= esc_attr($svc['price']) ?>"
                            data-name="<?= esc_attr($svc['name']) ?>">
                        <span class="card-inner">
                            <span class="card-title"><?= esc_html($svc['name']) ?></span>
                            <span class="card-desc"><?= esc_html($svc['desc']) ?></span>
                            <span class="card-meta">
                                <?php if ($is_mm) : ?>
                                    &#9200; ca. <?= $turnaround ?>
                                <?php else : ?>
                                    &#9200; 3 Stunden Session
                                <?php endif; ?>
                            </span>
                            <span class="card-price"><?= floatval($svc['price']) > 0 ? esc_html($svc['price']) . ' &euro;' : 'Preis auf Anfrage' ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <button class="btn-main" id="studiobook-step1-next">Weiter &#8594;</button>
        </div>

        <!-- Step 2a: Kalender -->
        <div class="studiobook-step" data-step="2" data-variant="calendar" style="display:none;">
            <h3 class="studiobook-step-title"><?= $t('text_step2_cal_title', 'Datum & Uhrzeit wählen') ?></h3>
            <div class="sb-calendar">
                <div class="sb-cal-header">
                    <button class="sb-cal-nav" id="sb-cal-prev">&#8249;</button>
                    <span class="sb-cal-title" id="sb-cal-title"></span>
                    <button class="sb-cal-nav" id="sb-cal-next">&#8250;</button>
                </div>
                <div class="sb-cal-weekdays">
                    <span>Mo</span><span>Di</span><span>Mi</span><span>Do</span><span>Fr</span><span>Sa</span><span>So</span>
                </div>
                <div class="sb-cal-grid" id="sb-cal-grid">
                    <div class="sb-cal-loading">Lade&#8230;</div>
                </div>
                <div class="sb-cal-legend">
                    <span class="leg-item"><span class="leg-open"></span> Verfügbar</span>
                    <span class="leg-item"><span class="leg-partial"></span> Teilweise</span>
                    <span class="leg-item"><span class="leg-full"></span> Ausgebucht</span>
                </div>
            </div>
            <div id="studiobook-slots" class="studiobook-slots"></div>
            <div class="studiobook-step-nav">
                <button class="btn-main secondary" data-back="1">&#8592; Zurück</button>
                <button class="btn-main" id="studiobook-step2-next">Weiter &#8594;</button>
            </div>
        </div>

        <!-- Step 2b: Upload -->
        <div class="studiobook-step" data-step="2" data-variant="upload" style="display:none;">
            <h3 class="studiobook-step-title"><?= $t('text_step2_upload_title', 'Datei hochladen') ?></h3>
            <p class="sb-turnaround">&#9200; Bearbeitungszeit: ca. <strong><?= $turnaround ?></strong></p>
            <div class="sb-upload-area" id="sb-upload-area">
                <input type="file" id="sb-file-input" accept=".mp3,.wav,.flac,.aiff,.aif,.ogg,.m4a,.zip,.rar" style="display:none;">
                <div class="upload-inner" id="sb-upload-inner">
                    <div class="upload-icon">&#127925;</div>
                    <p class="upload-title"><?= $t('text_upload_drag', 'Datei hier reinziehen') ?></p>
                    <p class="upload-sub">oder</p>
                    <button class="btn-main secondary" id="sb-upload-btn" type="button">Datei auswählen</button>
                    <p class="upload-formats"><?= $t('text_upload_formats', 'MP3 · WAV · FLAC · AIFF · OGG · M4A · ZIP · RAR — max. 200MB') ?></p>
                </div>
                <div class="upload-progress" id="sb-upload-progress" style="display:none;">
                    <div class="progress-bar">
                        <div class="progress-fill" id="sb-progress-fill"></div>
                    </div>
                    <p class="progress-label" id="sb-progress-label">0%</p>
                </div>
                <div class="upload-done" id="sb-upload-done" style="display:none;">
                    <div class="done-icon">&#10003;</div>
                    <p class="done-filename" id="sb-done-filename"></p>
                    <button class="btn-main secondary small" id="sb-upload-remove" type="button">Andere Datei wählen</button>
                </div>
            </div>
            <div class="studiobook-field full" style="margin-top:2rem;">
                <label>Anmerkungen (optional)</label>
                <textarea id="sb-mix-notes" placeholder="<?= $ph_mix ?>" style="height:100px;"></textarea>
            </div>
            <div class="studiobook-step-nav">
                <button class="btn-main secondary" data-back="1">&#8592; Zurück</button>
                <button class="btn-main" id="studiobook-step2b-next">Weiter &#8594;</button>
            </div>
        </div>

        <!-- Step 3: Kontakt -->
        <div class="studiobook-step" data-step="3">
            <h3 class="studiobook-step-title"><?= $t('text_step3_title', 'Deine Daten') ?></h3>
            <div class="studiobook-form-grid">
                <div class="studiobook-field">
                    <label>Name *</label>
                    <input type="text" id="sb-name" placeholder="<?= $ph_name ?>" required>
                </div>
                <div class="studiobook-field">
                    <label>E-Mail *</label>
                    <input type="email" id="sb-email" placeholder="<?= $ph_email ?>" required>
                </div>
                <div class="studiobook-field">
                    <label>Telefon *</label>
                    <input type="tel" id="sb-phone" placeholder="<?= $ph_phone ?>" required>
                </div>
                <div class="studiobook-field">
                    <label>Künstlername <span class="sb-internal-hint">(intern)</span></label>
                    <input type="text" id="sb-artist-name" placeholder="<?= $ph_artist ?>">
                </div>
                <div class="studiobook-field">
                    <label>Adresse *</label>
                    <input type="text" id="sb-address" placeholder="<?= $ph_address ?>" required>
                </div>
                <div class="studiobook-field">
                    <label>PLZ *</label>
                    <input type="text" id="sb-plz" placeholder="<?= $ph_plz ?>" required>
                </div>
                <div class="studiobook-field">
                    <label>Ort *</label>
                    <input type="text" id="sb-city" placeholder="<?= $ph_city ?>" required>
                </div>
                <div class="studiobook-field full">
                    <label>Nachricht (optional)</label>
                    <textarea id="sb-message" placeholder="<?= $ph_session ?>" style="height:100px;"></textarea>
                </div>
            </div>
            <div class="studiobook-step-nav">
                <button class="btn-main secondary" data-back="2">&#8592; Zurück</button>
                <button class="btn-main" id="studiobook-step3-next">Zur Zahlung &#8594;</button>
            </div>
        </div>

        <!-- Step 4: Auth Gate (wenn nicht eingeloggt) -->
        <div class="studiobook-step" data-step="4" data-variant="auth-gate" style="display:none;">
            <h3 class="studiobook-step-title">Fast geschafft! Bitte anmelden</h3>
            <p style="color:var(--sb-muted);margin-bottom:1.5rem;">Erstelle ein Konto oder logge dich ein um deine Buchung abzuschließen und sie in deinem Dashboard zu verwalten.</p>

            <div class="sb-inline-auth">
                <div class="sb-post-auth-tabs">
                    <button class="sb-post-auth-tab active" data-tab="register-panel" type="button">Konto erstellen</button>
                    <button class="sb-post-auth-tab" data-tab="login-panel" type="button">Einloggen</button>
                </div>

                <div id="sb-post-register-panel" class="sb-post-auth-panel">
                    <div class="sb-field">
                        <label for="sb-reg-username">Benutzername *</label>
                        <input type="text" id="sb-reg-username" placeholder="deinname" autocomplete="username">
                    </div>
                    <div class="sb-field">
                        <label for="sb-reg-pass">Passwort * <small style="color:var(--sb-muted)">(min. 8 Zeichen)</small></label>
                        <input type="password" id="sb-reg-pass" placeholder="••••••••" autocomplete="new-password">
                    </div>
                    <div class="sb-field">
                        <label for="sb-reg-pass2">Passwort bestätigen *</label>
                        <input type="password" id="sb-reg-pass2" placeholder="••••••••" autocomplete="new-password">
                    </div>
                    <div id="sb-reg-error" class="sb-auth-error"></div>
                    <button class="btn-main" id="sb-reg-btn" type="button">Konto erstellen & weiter zur Zahlung</button>
                </div>

                <div id="sb-post-login-panel" class="sb-post-auth-panel" style="display:none;">
                    <div class="sb-field">
                        <label for="sb-login-user">Benutzername oder E-Mail</label>
                        <input type="text" id="sb-login-user" placeholder="dein@email.de" autocomplete="username">
                    </div>
                    <div class="sb-field">
                        <label for="sb-login-pass">Passwort</label>
                        <input type="password" id="sb-login-pass" placeholder="••••••••" autocomplete="current-password">
                    </div>
                    <div id="sb-login-error" class="sb-auth-error"></div>
                    <button class="btn-main" id="sb-login-btn" type="button">Einloggen & weiter zur Zahlung</button>
                </div>
            </div>

            <div class="studiobook-step-nav" style="margin-top:1.5rem;">
                <button class="btn-main secondary" data-back="3">&#8592; Zurück</button>
            </div>
        </div>

        <!-- Step 4: Zahlung -->
        <div class="studiobook-step" data-step="4">
            <h3 class="studiobook-step-title"><?= $t('text_step4_title', 'Zahlung & Bestätigung') ?></h3>

            <!-- Zusammenfassung: Service/Datum -->
            <div class="sb-review-blocks">
                <div class="sb-review-block">
                    <div class="sb-review-block-header">
                        <span class="sb-review-block-title">Buchung</span>
                        <button class="sb-review-change" data-back-step="2" type="button">Ändern</button>
                    </div>
                    <div id="sb-review-booking"></div>
                </div>
                <div class="sb-review-block">
                    <div class="sb-review-block-header">
                        <span class="sb-review-block-title">Deine Daten</span>
                        <button class="sb-review-change" data-back-step="3" type="button">Ändern</button>
                    </div>
                    <div id="sb-review-contact"></div>
                </div>
            </div>

            <!-- Gutschein -->
            <div class="sb-coupon-wrap">
                <div class="sb-coupon-row">
                    <input type="text" id="sb-coupon-input" placeholder="<?= $ph_coupon ?>" style="text-transform:uppercase;">
                    <button type="button" class="btn-main secondary" id="sb-coupon-btn">Einlösen</button>
                </div>
                <div id="sb-coupon-msg" class="sb-coupon-msg"></div>
            </div>

            <!-- Preisübersicht -->
            <div class="studiobook-summary" id="studiobook-summary"></div>

            <!-- Zahlungsmethoden -->
            <div class="studiobook-payment-methods" id="sb-payment-methods">
                <label class="payment-option">
                    <input type="radio" name="payment_method" value="stripe">
                    <span>&#128179; Kreditkarte</span>
                </label>
                <label class="payment-option">
                    <input type="radio" name="payment_method" value="paypal">
                    <span>&#128371; PayPal</span>
                </label>
                <label class="payment-option sb-cash-option">
                    <input type="radio" name="payment_method" value="cash">
                    <span>&#128176; Barzahlung</span>
                </label>
            </div>

            <div id="stripe-section" class="payment-section" style="display:none;">
                <div id="stripe-card-element"></div>
            </div>
            <div id="paypal-section" class="payment-section" style="display:none;">
                <div id="paypal-button-container"></div>
            </div>
            <div id="cash-section" class="payment-section cash-info" style="display:none;">
                <p><?= $tw('text_cash_info', 'Du bezahlst bequem vor Ort in bar.') ?></p>
            </div>

            <div id="sb-cancel-policy-wrap" class="sb-cancel-policy" style="display:none;">
                <p>&#8505; <?= $cancel_policy ?></p>
            </div>

            <!-- Pflicht-Checkboxen -->
            <div class="sb-checks">
                <label class="sb-check-label">
                    <input type="checkbox" id="sb-privacy-check">
                    <span><span class="sb-required">*</span> <?= $tw('text_privacy_label', 'Ich habe die <a href="/datenschutz">Datenschutzerklärung</a> gelesen und stimme zu.') ?></span>
                </label>
                <label class="sb-check-label">
                    <input type="checkbox" id="sb-agb-check">
                    <span><span class="sb-required">*</span> <?= $tw('text_agb_label', 'Ich stimme den <a href="/agb">AGB</a> zu.') ?></span>
                </label>
                <label class="sb-check-label">
                    <input type="checkbox" id="sb-widerruf-check">
                    <span><span class="sb-required">*</span> <?= $tw('text_widerruf_label', 'Ich habe die <a href="/widerruf">Widerrufsbelehrung</a> zur Kenntnis genommen.') ?></span>
                </label>
                <p class="sb-required-note"><span class="sb-required">*</span> Pflichtfelder</p>
            </div>

            <div id="sb-pay-error" class="payment-error"></div>

            <!-- Submit + Zurück nebeneinander -->
            <div class="studiobook-step-nav">
                <button class="btn-main secondary" data-back="3">&#8592; Zurück</button>
                <button class="btn-main" id="sb-submit-btn" style="display:none;">Jetzt verbindlich buchen</button>
            </div>

            <?php if ($show_test) : ?>
                <div class="sb-test-mode-box">
                    <p>&#9888; <strong>Test-Modus aktiv</strong> – Simulierte Buchung ohne Zahlung</p>
                    <button class="btn-main sb-test-btn" id="sb-test-booking-btn" type="button">&#128295; Test-Buchung simulieren</button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Step 5 -->
        <div class="studiobook-step" data-step="5">
            <div class="studiobook-success">
                <div class="success-icon">&#127881;</div>
                <h3><?= $t('text_success_title', 'Buchung bestätigt!') ?></h3>
                <p>Bestätigungsmail geht an <strong id="sb-confirm-email"></strong>.</p>
                <p id="sb-success-subtitle"></p>
            </div>

            <?php if (!is_user_logged_in()) : ?>
                <!-- Post-Booking: Account erstellen oder einloggen -->
                <div class="sb-post-booking-auth">
                    <h4>&#127919; Buchungen verwalten</h4>
                    <p>Erstelle ein kostenloses Konto um deine Buchungen, Aufträge und Rechnungen jederzeit im Überblick zu behalten.</p>
                    <input type="hidden" id="sb-post-booking-id" value="">
                    <div class="sb-post-auth-tabs">
                        <button class="sb-post-auth-tab active" data-tab="register-panel">Konto erstellen</button>
                        <button class="sb-post-auth-tab" data-tab="login-panel">Einloggen</button>
                    </div>
                    <div id="sb-post-register-panel" class="sb-post-auth-panel">
                        <div class="sb-field"><label>Benutzername *</label><input type="text" id="sb-reg-username" placeholder="deinname"></div>
                        <div class="sb-field" style="margin-top:.75rem;"><label>Passwort * <small style="color:var(--sb-muted)">(min. 8 Zeichen)</small></label><input type="password" id="sb-reg-pass" placeholder="••••••••"></div>
                        <div class="sb-field" style="margin-top:.75rem;"><label>Passwort bestätigen *</label><input type="password" id="sb-reg-pass2" placeholder="••••••••"></div>
                        <div id="sb-reg-error" class="sb-auth-error" style="margin-top:.5rem;"></div>
                        <button class="btn-main" id="sb-reg-btn" style="margin-top:.75rem;">Konto erstellen & zum Dashboard</button>
                    </div>
                    <div id="sb-post-login-panel" class="sb-post-auth-panel" style="display:none;">
                        <div class="sb-field"><label>Benutzername oder E-Mail</label><input type="text" id="sb-login-user" placeholder="dein@email.de"></div>
                        <div class="sb-field" style="margin-top:.75rem;"><label>Passwort</label><input type="password" id="sb-login-pass" placeholder="••••••••"></div>
                        <div id="sb-login-error" class="sb-auth-error" style="margin-top:.5rem;"></div>
                        <button class="btn-main" id="sb-login-btn" style="margin-top:.75rem;">Einloggen & zum Dashboard</button>
                    </div>
                </div>
            <?php else : ?>
                <div style="text-align:center;margin-top:1.5rem;">
                    <a href="<?= esc_url(home_url('/dashboard')) ?>" class="btn-main" style="display:inline-block;text-decoration:none;">Zum Dashboard &#8594;</a>
                </div>
            <?php endif; ?>
        </div>
    </div><!-- /wrap -->

    <?php if ($stripe_pk) : ?>
        <script src="https://js.stripe.com/v3/"></script>
        <script>
            window.studiobookStripeKey = '<?= esc_js($stripe_pk) ?>';
        </script>
    <?php endif; ?>
    <?php if ($paypal_id) : ?>
        <script src="https://www.paypal.com/sdk/js?client-id=<?= esc_attr($paypal_id) ?>&currency=<?= esc_attr($s['currency'] ?? 'EUR') ?>"></script>
    <?php endif; ?>
    <script>
        window.studiobookIsLoggedIn = <?= is_user_logged_in() ? 'true' : 'false' ?>;
        window.studiobookCurrentUserId = <?= is_user_logged_in() ? get_current_user_id() : 0 ?>;
        window.studiobookMaxUploadMB = 200;
        window.studiobookTax = {
            enabled: <?= $tax_enabled ? 'true' : 'false' ?>,
            rate: <?= $tax_rate ?>,
            inclusive: <?= (!empty($s['tax_inclusive'] ?? true)) ? 'true' : 'false' ?>
        };
        window.studiobookTexts = {
            successSession: <?= json_encode($s['text_success_session'] ?? 'Wir freuen uns auf dich!') ?>,
            successMix: <?= json_encode($s['text_success_mix']     ?? 'Wir melden uns sobald dein Mix fertig ist!') ?>,
        };
    </script>
<?php
    return ob_get_clean();
}
