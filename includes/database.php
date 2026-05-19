<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function studiobook_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $bookings_table = $wpdb->prefix . 'studiobook_bookings';
    $sql_bookings = "CREATE TABLE $bookings_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        service VARCHAR(100) NOT NULL,
        booking_date DATE DEFAULT NULL,
        slot_time VARCHAR(10) DEFAULT NULL,
        customer_name VARCHAR(150) NOT NULL,
        customer_email VARCHAR(150) NOT NULL,
        customer_phone VARCHAR(50) DEFAULT '',
        artist_name VARCHAR(150) DEFAULT '',
        customer_address VARCHAR(200) DEFAULT '',
        customer_plz VARCHAR(20) DEFAULT '',
        customer_city VARCHAR(100) DEFAULT '',
        message TEXT DEFAULT '',
        file_path VARCHAR(500) DEFAULT '',
        file_name VARCHAR(255) DEFAULT '',
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        tax_rate DECIMAL(5,2) DEFAULT 0.00,
        tax_amount DECIMAL(10,2) DEFAULT 0.00,
        price_gross DECIMAL(10,2) DEFAULT 0.00,
        payment_method VARCHAR(50) DEFAULT '',
        payment_status VARCHAR(50) DEFAULT 'pending',
        payment_id VARCHAR(200) DEFAULT '',
        booking_status VARCHAR(50) DEFAULT 'pending',
        user_id BIGINT(20) UNSIGNED DEFAULT NULL,
        cancel_token VARCHAR(64) DEFAULT '',
        cancelled_at DATETIME DEFAULT NULL,
        invoice_number VARCHAR(50) DEFAULT '',
        invoice_sent TINYINT(1) DEFAULT 0,
        delivery_link VARCHAR(500) DEFAULT '',
        delivery_note TEXT DEFAULT '',
        completed_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    $blocked_table = $wpdb->prefix . 'studiobook_blocked';
    $sql_blocked = "CREATE TABLE $blocked_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        blocked_date DATE NOT NULL,
        slot_time VARCHAR(10) NOT NULL,
        reason VARCHAR(200) DEFAULT '',
        PRIMARY KEY (id),
        UNIQUE KEY unique_slot (blocked_date, slot_time)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql_bookings );
    dbDelta( $sql_blocked );
    // Portal-Content Tabelle
    $portal_table = $wpdb->prefix . 'studiobook_portal_content';
    $sql_portal = "CREATE TABLE $portal_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        content_key VARCHAR(100) NOT NULL,
        content_title VARCHAR(200) DEFAULT '',
        content_body LONGTEXT DEFAULT '',
        PRIMARY KEY (id),
        UNIQUE KEY content_key (content_key)
    ) $charset_collate;";
    dbDelta( $sql_portal );

    // Standard-Inhalte einfügen
    studiobook_insert_default_portal_content();
    // Gutschein-Tabelle
    $coupons_table = $wpdb->prefix . 'studiobook_coupons';
    $sql_coupons = "CREATE TABLE $coupons_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        code VARCHAR(100) NOT NULL,
        type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
        value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        min_amount DECIMAL(10,2) DEFAULT 0.00,
        usage_limit INT DEFAULT NULL,
        usage_count INT DEFAULT 0,
        valid_from DATE DEFAULT NULL,
        valid_until DATE DEFAULT NULL,
        active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY code (code)
    ) $charset_collate;";
    dbDelta( $sql_coupons );


}

function studiobook_insert_default_portal_content() {
    global $wpdb;
    $table = $wpdb->prefix . 'studiobook_portal_content';

    $defaults = [
        'gema' => [
            'title' => 'GEMA & Urheberrecht',
            'body'  => '<h3>Was ist die GEMA?</h3><p>Die GEMA (Gesellschaft für musikalische Aufführungs- und mechanische Vervielfältigungsrechte) ist die Verwertungsgesellschaft für Musik in Deutschland. Als Musikproduzent und Künstler solltest du deine Werke bei der GEMA anmelden um Tantiemen zu erhalten.</p><h3>GEMA-Mitgliedschaft</h3><p>Sobald du Musik veröffentlichst oder aufführst, lohnt sich eine GEMA-Mitgliedschaft. Die Anmeldung erfolgt unter <a href="https://www.gema.de" target="_blank">gema.de</a>.</p><h3>Studio & GEMA</h3><p>Bei Aufnahmen im 100Studios werden keine GEMA-Gebühren auf Studiozeit berechnet. Für die Veröffentlichung deiner Tracks bist du selbst verantwortlich.</p>',
        ],
        'faq' => [
            'title' => 'Häufige Fragen',
            'body'  => '<h3>Wie buche ich eine Session?</h3><p>Wähle deinen gewünschten Service, Datum und Uhrzeit und schließe die Buchung mit deiner bevorzugten Zahlungsmethode ab.</p><h3>Was soll ich zur Session mitbringen?</h3><p>Bring deine Texte, Beats oder Ideen mit. Equipment wie Mikrofone und Monitore sind vorhanden. Informiere uns vorab über besondere Anforderungen.</p><h3>Wie lange dauert eine Session?</h3><p>Eine Studio Session läuft 3 Stunden. Danach gibt es 1 Stunde Puffer für den nächsten Künstler.</p><h3>Kann ich meine Buchung stornieren?</h3><p>Ja, kostenlose Stornierung ist bis 12 Stunden vor dem Termin möglich. Danach werden 50% des Betrags einbehalten.</p><h3>Wie läuft Mixing & Mastering ab?</h3><p>Du lädst deine Stems oder Mixdown-Datei hoch, bezahlst online und wir bearbeiten deinen Track innerhalb der angegebenen Bearbeitungszeit. Du bekommst eine E-Mail mit dem fertigen Download-Link.</p>',
        ],
        'wissenswertes' => [
            'title' => 'Wissenswertes',
            'body'  => '<h3>Über 100Studios</h3><p>100Studios ist ein professionelles Recording- und Produktionsstudio in Duisburg, betrieben von EN100. Wir spezialisieren uns auf Hip Hop Produktionen und bieten erstklassige Aufnahme- und Postproduktionsdienstleistungen an.</p><h3>Unsere Ausstattung</h3><p>Das Studio ist mit professionellem Equipment ausgestattet und bietet eine optimale Akustik für Vocals, Instrumentalaufnahmen und Mixdowns.</p><h3>Tipps für deine Session</h3><ul><li>Komm ausgeruht und mit klarer Stimme</li><li>Bring Wasser mit</li><li>Bereite deine Texte vor</li><li>Sei pünktlich – die Sessionzeit beginnt mit deiner gebuchten Uhrzeit</li></ul>',
        ],
        'datenschutz' => [
            'title' => 'Datenschutz & AGB',
            'body'  => '<p><strong>Datenschutzerklärung und AGB</strong> sind auf den entsprechenden Seiten unserer Website einsehbar. Bei Fragen wende dich an uns über den Support-Bereich.</p>',
        ],
        'support' => [
            'title' => 'Support & Kontakt',
            'body'  => '<h3>Kontakt</h3><p><strong>100Studios</strong><br>Ulmenstraße 16<br>47226 Duisburg</p><p>Bei Fragen zu deiner Buchung oder deinem Auftrag melde dich direkt per E-Mail oder über unsere Website.</p><h3>Website</h3><p><a href="https://100studios.de" target="_blank">100studios.de</a></p>',
        ],
    ];

    foreach ($defaults as $key => $data) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE content_key = %s", $key));
        if (!$exists) {
            $wpdb->insert($table, [
                'content_key'   => $key,
                'content_title' => $data['title'],
                'content_body'  => $data['body'],
            ], ['%s','%s','%s']);
        }
    }
}
