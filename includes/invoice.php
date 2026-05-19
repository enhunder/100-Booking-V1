<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function studiobook_generate_invoice_html( $booking, $invoice_number ) {
    $s = studiobook_get_settings();

    $accent      = $s['color_accent']   ?? '#553eff';
    $tax_enabled = ! empty($s['tax_enabled']);

    // Rechnungssteller – aus Settings
    $company  = esc_html( $s['invoice_company'] ?? '' );
    $inv_name = esc_html( $s['invoice_name']    ?? '' );
    $address  = esc_html( $s['invoice_address'] ?? '' );
    $plz      = esc_html( $s['invoice_plz']     ?? '' );
    $city     = esc_html( $s['invoice_city']    ?? '' );
    $tax_id   = esc_html( $s['invoice_tax_id']  ?? '' );
    $iban     = esc_html( $s['invoice_iban']    ?? '' );
    $bic      = esc_html( $s['invoice_bic']     ?? '' );
    $bank     = esc_html( $s['invoice_bank']    ?? '' );

    // Warnung wenn Rechnungsdaten fehlen
    $sender_missing = empty($company) && empty($inv_name);

    $service_labels = ['studio_session' => 'Studio Session', 'mixing_mastering' => 'Mixing & Mastering'];
    $service_label  = $service_labels[$booking->service] ?? $booking->service;
    $date_str       = $booking->booking_date ? date('d.m.Y', strtotime($booking->booking_date)) : date('d.m.Y');
    $invoice_date   = date('d.m.Y');

    $price_net   = floatval($booking->price);
    $tax_rate    = floatval($booking->tax_rate);
    $tax_amount  = floatval($booking->tax_amount);
    $price_gross = floatval($booking->price_gross ?: $booking->price);

    $description = $service_label;
    if ($booking->booking_date) $description .= ' am ' . $date_str;
    if ($booking->slot_time)    $description .= ' um ' . $booking->slot_time . ' Uhr';

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial, Helvetica, sans-serif; color:#111; background:#fff; }
.page { padding: 40px 48px; }
.header { display:table; width:100%; margin-bottom:40px; }
.header-left { display:table-cell; vertical-align:top; }
.header-right { display:table-cell; vertical-align:top; text-align:right; }
.logo { font-size:26px; font-weight:900; color:<?= $accent ?>; letter-spacing:-1px; }
.logo-sub { color:#666; margin-top:4px; font-size:13px; }
.inv-number { font-size:18px; font-weight:700; color:#111; }
.inv-date { color:#666; margin-top:4px; font-size:13px; }
.accent-bar { height:4px; background:<?= $accent ?>; margin-bottom:32px; border-radius:2px; }
.addresses { display:table; width:100%; margin-bottom:32px; }
.addr-cell { display:table-cell; width:50%; vertical-align:top; padding-right:20px; }
.addr-label { font-size:10px; text-transform:uppercase; letter-spacing:1px; color:#999; margin-bottom:8px; }
.addr-text { font-size:13px; line-height:1.7; color:#333; }
.warning { background:#fff3cd; border:1px solid #ffc107; padding:10px 14px; border-radius:4px; margin-bottom:24px; font-size:12px; color:#856404; }
.inv-title { font-size:20px; font-weight:700; margin-bottom:20px; color:#111; }
table.items { width:100%; border-collapse:collapse; margin-bottom:24px; }
table.items th { background:<?= $accent ?>; color:#fff; padding:10px 14px; text-align:left; font-size:12px; }
table.items td { padding:10px 14px; border-bottom:1px solid #eee; font-size:13px; color:#333; }
.text-right { text-align:right; }
.totals-wrap { margin-left:55%; }
.total-row { display:table; width:100%; padding:5px 0; font-size:13px; }
.total-row span { display:table-cell; }
.total-row .total-val { text-align:right; }
.total-final { border-top:2px solid <?= $accent ?>; padding-top:10px; margin-top:4px; font-weight:700; font-size:15px; color:<?= $accent ?>; }
.footer { margin-top:40px; border-top:1px solid #eee; padding-top:20px; display:table; width:100%; }
.footer-cell { display:table-cell; width:33%; vertical-align:top; padding-right:16px; font-size:12px; }
.footer-label { font-size:10px; text-transform:uppercase; letter-spacing:1px; color:#999; margin-bottom:5px; }
.footer-text { color:#555; line-height:1.6; }
.notice-small { font-size:11px; color:#999; margin-top:8px; font-style:italic; }
</style>
</head>
<body>
<div class="page">

<div class="header">
  <div class="header-left">
    <div class="logo">100Studios</div>
    <div class="logo-sub">
      <?= $company ? $company . '<br>' : '' ?>
      <?= $address ?><br>
      <?= $plz ?> <?= $city ?>
    </div>
  </div>
  <div class="header-right">
    <div class="inv-number">Rechnung <?= esc_html($invoice_number) ?></div>
    <div class="inv-date">Datum: <?= $invoice_date ?></div>
  </div>
</div>

<div class="accent-bar"></div>

<?php if ($sender_missing) : ?>
<div class="warning">⚠️ Rechnungssteller unvollständig – bitte unter 100Studios → Rechnung im Admin ausfüllen.</div>
<?php endif; ?>

<div class="addresses">
  <div class="addr-cell">
    <div class="addr-label">Rechnungsempfänger</div>
    <div class="addr-text">
      <?= esc_html($booking->customer_name) ?><br>
      <?= esc_html($booking->customer_address) ?><br>
      <?= esc_html($booking->customer_plz) ?> <?= esc_html($booking->customer_city) ?><br>
      <?= esc_html($booking->customer_email) ?>
    </div>
  </div>
  <div class="addr-cell">
    <div class="addr-label">Rechnungssteller</div>
    <div class="addr-text">
      <?= $company ?: '<em>Firma nicht hinterlegt</em>' ?><br>
      <?= $inv_name ?><br>
      <?= $address ?><br>
      <?= $plz ?> <?= $city ?>
      <?php if ($tax_id) : ?><br>St.-Nr.: <?= $tax_id ?><?php endif; ?>
    </div>
  </div>
</div>

<div class="inv-title">Rechnung <?= esc_html($invoice_number) ?></div>

<table class="items">
  <thead>
    <tr>
      <th>Beschreibung</th>
      <th class="text-right">Menge</th>
      <th class="text-right">Nettobetrag</th>
      <?php if ($tax_enabled) : ?><th class="text-right">MwSt.</th><?php endif; ?>
      <th class="text-right">Gesamt</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><?= esc_html($description) ?></td>
      <td class="text-right">1</td>
      <td class="text-right"><?= number_format($price_net, 2, ',', '.') ?> &euro;</td>
      <?php if ($tax_enabled) : ?>
      <td class="text-right"><?= number_format($tax_rate, 0) ?>%</td>
      <?php endif; ?>
      <td class="text-right"><?= number_format($price_gross, 2, ',', '.') ?> &euro;</td>
    </tr>
  </tbody>
</table>

<div class="totals-wrap">
  <div class="total-row"><span>Nettobetrag:</span><span class="total-val"><?= number_format($price_net, 2, ',', '.') ?> &euro;</span></div>
  <?php if ($tax_enabled) : ?>
  <div class="total-row"><span>MwSt. <?= number_format($tax_rate, 0) ?>%:</span><span class="total-val"><?= number_format($tax_amount, 2, ',', '.') ?> &euro;</span></div>
  <?php else : ?>
  <div class="total-row notice-small"><span colspan="2">Gemäß §19 UStG wird keine Umsatzsteuer berechnet.</span></div>
  <?php endif; ?>
  <div class="total-row total-final"><span>Gesamtbetrag:</span><span class="total-val"><?= number_format($price_gross, 2, ',', '.') ?> &euro;</span></div>
</div>

<div class="footer">
  <?php if ($iban) : ?>
  <div class="footer-cell">
    <div class="footer-label">Bankverbindung</div>
    <div class="footer-text">
      <?= $bank ?><br>
      IBAN: <?= $iban ?><br>
      BIC: <?= $bic ?>
    </div>
  </div>
  <?php endif; ?>
  <div class="footer-cell">
    <div class="footer-label">Zahlung</div>
    <div class="footer-text">
      <?= ucfirst(esc_html($booking->payment_method)) ?><br>
      <?= $booking->payment_method !== 'cash' ? 'Bereits bezahlt' : 'Barzahlung vor Ort' ?>
    </div>
  </div>
  <div class="footer-cell">
    <div class="footer-label">Buchungs-ID</div>
    <div class="footer-text">#<?= $booking->id ?></div>
  </div>
</div>

</div>
</body>
</html>
    <?php
    return ob_get_clean();
}

function studiobook_generate_invoice_pdf( $booking ) {
    global $wpdb;

    // Rechnungsnummer sicherstellen
    if ( empty($booking->invoice_number) ) {
        $invoice_number = studiobook_next_invoice_number();
        $wpdb->update(
            $wpdb->prefix . 'studiobook_bookings',
            ['invoice_number' => $invoice_number],
            ['id' => $booking->id],
            ['%s'], ['%d']
        );
        $booking->invoice_number = $invoice_number;
    } else {
        $invoice_number = $booking->invoice_number;
    }

    $html    = studiobook_generate_invoice_html($booking, $invoice_number);
    $upload  = wp_upload_dir();
    $dir     = $upload['basedir'] . '/studiobook-invoices/';

    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
        file_put_contents($dir . '.htaccess', "Options -Indexes\nDeny from all\n");
    }

    $safe_num = sanitize_file_name($invoice_number);

    // Versuch 1: mPDF (composer require mpdf/mpdf im Plugin-Verzeichnis)
    $mpdf_autoload = STUDIOBOOK_PATH . 'vendor/autoload.php';
    if (file_exists($mpdf_autoload)) {
        require_once $mpdf_autoload;
        if (class_exists('\Mpdf\Mpdf')) {
            try {
                $mpdf = new \Mpdf\Mpdf([
                    'margin_top' => 0, 'margin_bottom' => 0,
                    'margin_left' => 0, 'margin_right' => 0,
                    'tempDir' => sys_get_temp_dir(),
                ]);
                $mpdf->WriteHTML($html);
                $pdf_path = $dir . 'rechnung-' . $safe_num . '.pdf';
                $mpdf->Output($pdf_path, 'F');
                return $pdf_path;
            } catch (Exception $e) {
                // Fallback
            }
        }
    }

    // Versuch 2: wkhtmltopdf (falls auf Server vorhanden)
    $wk = trim(shell_exec('which wkhtmltopdf 2>/dev/null'));
    if ($wk) {
        $html_tmp  = sys_get_temp_dir() . '/sb_inv_' . $booking->id . '.html';
        $pdf_path  = $dir . 'rechnung-' . $safe_num . '.pdf';
        file_put_contents($html_tmp, $html);
        shell_exec(escapeshellcmd($wk) . ' --quiet --page-size A4 ' . escapeshellarg($html_tmp) . ' ' . escapeshellarg($pdf_path) . ' 2>/dev/null');
        @unlink($html_tmp);
        if (file_exists($pdf_path)) return $pdf_path;
    }

    // Fallback: HTML-Datei (öffnet im Browser als Rechnung)
    $html_path = $dir . 'rechnung-' . $safe_num . '.html';
    file_put_contents($html_path, $html);
    return $html_path;
}
