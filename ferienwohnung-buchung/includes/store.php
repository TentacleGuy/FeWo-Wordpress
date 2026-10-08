<?php
defined('ABSPATH') || exit;
final class FWB_Store {
    public static function table(string $suffix = 'bookings'): string { global $wpdb; return $wpdb->prefix . 'fwb_' . $suffix; }
    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate(); $t = self::table();
        dbDelta("CREATE TABLE $t (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            reference varchar(32) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            arrival date NOT NULL,
            departure date NOT NULL,
            name varchar(200) NOT NULL,
            email varchar(200) NOT NULL,
            payload longtext NOT NULL,
            paid bigint NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY reference (reference),
            KEY dates (status,arrival,departure)
        ) ENGINE=InnoDB $charset;");
        $t = self::table('invoices');
        dbDelta("CREATE TABLE $t (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint unsigned NOT NULL,
            number varchar(64) NOT NULL,
            html longtext NOT NULL,
            pdf longblob NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY booking_id (booking_id),
            UNIQUE KEY number (number)
        ) ENGINE=InnoDB $charset;");
        $t = self::table('events');
        dbDelta("CREATE TABLE $t (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint unsigned NOT NULL,
            kind varchar(40) NOT NULL,
            detail text NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY booking_id (booking_id)
        ) ENGINE=InnoDB $charset;");
        $t = self::table('tokens');
        dbDelta("CREATE TABLE $t (
            token varchar(64) NOT NULL,
            expires bigint unsigned NOT NULL,
            PRIMARY KEY  (token),
            KEY expires (expires)
        ) ENGINE=InnoDB $charset;");
        FWB_Settings::migrate_copy();
        update_option('fwb_version', FWB_VERSION, false);
    }
    // All availability changes share a per-site database lock, including admin confirmations.
    public static function locked(callable $callback) {
        global $wpdb;
        $name = 'fwb_' . md5(DB_NAME . $wpdb->prefix);
        if ((string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $name)) !== '1') { throw new RuntimeException('Buchungssystem ist beschäftigt. Bitte erneut versuchen.'); }
        try { return $callback(); } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name)); }
    }
    public static function get(int $id): array {
        global $wpdb; $t = self::table();
        $b = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", $id), ARRAY_A);
        if (!$b) { throw new InvalidArgumentException('Buchung nicht gefunden.'); }
        $b['data'] = json_decode($b['payload'], true); return $b;
    }
    public static function available(string $start, string $end, int $except = 0): bool {
        global $wpdb; $t = self::table();
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE status IN ('confirmed','blocked') AND arrival < %s AND departure > %s AND id <> %d", $end, $start, $except));
        if ($wpdb->last_error) { throw new RuntimeException('Verfügbarkeit konnte nicht geprüft werden.'); }
        return (int)$count === 0;
    }
    public static function insert(array $quote, array $fields, string $status = 'pending', string $source = 'web', ?string $language = null): int {
        global $wpdb;
        return self::locked(static function () use ($quote, $fields, $status, $source, $language, $wpdb) {
            if (!self::available($quote['arrival'], $quote['departure'])) { throw new InvalidArgumentException('Dieser Zeitraum ist inzwischen belegt. Bitte andere Daten wählen.'); }
            $ok = $wpdb->insert(self::table(), [
                'reference'=>'FW-' . strtoupper(bin2hex(random_bytes(5))), 'status'=>$status,
                'arrival'=>$quote['arrival'],'departure'=>$quote['departure'],'name'=>$fields['name'] ?? 'Sperrzeit', 'email'=>$fields['email'] ?? '',
                'payload'=>wp_json_encode(['language'=>$language??FWB_I18n::current(),'quote'=>$quote,'fields'=>$fields,'source'=>$source,'consent_at'=>$source==='web'?current_time('mysql', true):null,'privacy_url'=>$source==='web'?FWB_Settings::get()['privacy_url']:'']),
                'created_at'=>current_time('mysql',true),
            ]);
            if (!$ok) { throw new RuntimeException('Die Anfrage konnte nicht gespeichert werden.'); }
            $id = (int)$wpdb->insert_id; self::event($id, 'created', $status); return $id;
        });
    }
    public static function transition(int $id, string $next): bool {
        global $wpdb;
        return self::locked(static function () use ($id, $next, $wpdb) {
            $b = self::get($id);
            if ($b['status'] === $next) { return false; }
            $allowed = ['pending'=>['confirmed','rejected','cancelled'],'confirmed'=>['cancelled'],'blocked'=>['cancelled'],'cancelled'=>['confirmed'],'rejected'=>['confirmed']];
            if (!in_array($next, $allowed[$b['status']] ?? [], true)) { throw new InvalidArgumentException('Dieser Statuswechsel ist nicht zulässig.'); }
            if ($next === 'confirmed' && !isset($b['data']['quote']['total'])) { throw new InvalidArgumentException('Eine entsperrte Sperrzeit kann nicht als Gästebuchung bestätigt werden.'); }
            if ($next === 'confirmed' && !self::available($b['arrival'], $b['departure'], $id)) { throw new InvalidArgumentException('Bestätigung nicht möglich: Der Zeitraum ist bereits belegt.'); }
            if (false === $wpdb->update(self::table(), ['status'=>$next], ['id'=>$id])) { throw new RuntimeException('Status konnte nicht gespeichert werden.'); }
            self::event($id, 'status', $b['status'] . ' → ' . $next . ' (Benutzer ' . get_current_user_id() . ')'); return true;
        });
    }
    public static function event(int $id, string $kind, string $detail): void {
        global $wpdb; $wpdb->insert(self::table('events'), ['booking_id'=>$id,'kind'=>$kind,'detail'=>$detail,'created_at'=>current_time('mysql',true)]);
    }
    public static function invoice(int $id): ?array {
        global $wpdb; $t=self::table('invoices');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE booking_id=%d",$id),ARRAY_A) ?: null;
    }
}
