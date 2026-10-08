<?php
defined('ABSPATH') || exit;
final class FWB_Settings {
    public static function defaults(): array {
        return [
            'property_name' => 'Ferienwohnung', 'night_price' => 0, 'max_guests' => 6, 'included_guests' => 2,
            'extra_price' => 2000, 'extra_unit' => 'person', 'extra_period' => 'night', 'cleaning_price' => 0,
            'min_nights' => 3, 'deposit_percent' => 30, 'balance_days' => 1, 'cancel_days' => 14,
            'local_tax' => 450, 'vat_percent' => 0, 'checkin' => '15:00', 'checkout' => '10:00',
            'tax_note' => 'Ortstaxe pro Person und Nacht, vor Ort bar zu bezahlen. Befreit: Kinder und Jugendliche bis einschließlich 18 Jahren sowie Personen mit Behinderung ab 50 %. Bitte nur die Anzahl der abgabepflichtigen Gäste angeben; keine Gesundheitsnachweise hochladen.',
            'rules' => 'Rauchen ist nicht gestattet. Haustiere sind aus Rücksicht auf Allergiker nicht erlaubt.',
            'privacy_url' => '', 'seller' => '', 'tax_id' => '', 'iban' => '', 'invoice_note' => '',
            'admin_email' => get_option('admin_email'), 'auto_mail' => 1, 'auto_invoice' => 0,
            'invoice_prefix' => 'RE', 'custom_css' => '',
            'intro_kicker' => 'ZEIT FÜR EINE AUSZEIT',
            'intro_title' => 'Dein Aufenthalt in {property_name}',
            'intro_text' => 'Wähle deinen Wunschzeitraum und frage unverbindlich an.',
            'form_title' => 'Deine Buchungsanfrage', 'form_intro' => '', 'form_outro' => '',
        ];
    }
    public static function get(): array { return array_merge(self::defaults(), (array)get_option('fwb_settings', [])); }
    public static function sanitize_css(string $css): string {
        if (strlen($css)>50000 || str_contains($css,'<') || str_contains($css,"\0")) {
            throw new InvalidArgumentException('Eigenes CSS: maximal 50.000 Zeichen, nur CSS ohne HTML- oder style-Tags.');
        }
        return trim($css);
    }
    public static function fields(): array {
        return get_option('fwb_fields', [
            ['key'=>'name','label'=>'Vor- und Nachname','type'=>'text','required'=>true,'core'=>true],
            ['key'=>'email','label'=>'E-Mail-Adresse','type'=>'email','required'=>true,'core'=>true],
            ['key'=>'address','label'=>'Rechnungsadresse (Straße, PLZ, Ort, Land)','type'=>'textarea','required'=>true,'core'=>true],
            ['key'=>'phone','label'=>'Telefon','type'=>'tel','required'=>false],
            ['key'=>'message','label'=>'Deine Nachricht','type'=>'textarea','required'=>false],
        ]);
    }
    public static function template_defaults(): array {
        return [
            'request_subject' => 'Deine Anfrage {booking_number} – {property_name}',
            'request_body' => "Hallo {name},\n\nvielen Dank für deine Anfrage vom {arrival} bis {departure} ({nights} Nächte, {guests} Gäste).\nGesamtpreis der Unterkunft: {total}. Ortstaxe separat vor Ort: {local_tax}.\nDies ist noch keine Buchungsbestätigung. Wir melden uns bei dir.\n\n{property_name}",
            'confirmed_subject' => 'Buchungsbestätigung {booking_number} – {property_name}',
            'confirmed_body' => "Hallo {name},\n\nDeine Buchung vom {arrival} bis {departure} ist bestätigt.\nAnreise ab {checkin}, Abreise bis {checkout}.\nGesamtpreis: {total}\nBitte überweise zur Bestätigung die Anzahlung von {deposit}.\nDer Restbetrag von {balance} ist spätestens am {due_date} fällig; bei kurzfristiger Buchung bitte sofort bezahlen.\nBankverbindung: {iban}\nVerwendungszweck: {booking_number}\nKostenfreie Stornierung bis einschließlich {cancel_date}. Danach bitte mit uns Kontakt aufnehmen.\nOrtstaxe separat in bar vor Ort: {local_tax}.\n{tax_note}\n{rules}\n\n{property_name}",
            'cancelled_subject' => 'Stornierung {booking_number}',
            'cancelled_body' => "Hallo {name},\n\nDeine Buchung vom {arrival} bis {departure} wurde storniert.\nEventuelle Erstattungen oder Stornokosten klären wir mit dir gesondert.\n\n{property_name}",
            'rejected_subject' => 'Deine Anfrage {booking_number}',
            'rejected_body' => "Hallo {name},\n\nleider können wir deine Anfrage vom {arrival} bis {departure} nicht bestätigen.\n\n{property_name}",
            'invoice_subject' => 'Deine Rechnung {invoice_number}',
            'invoice_body' => "Hallo {name},\n\nanbei deine Rechnung {invoice_number} für deinen Aufenthalt vom {arrival} bis {departure}.\n\n{property_name}",
            'pdf_header' => '<strong>{property_name}</strong><br>{seller}',
            'pdf_body' => '<h1>Rechnung {invoice_number}</h1><p>Rechnungsdatum: {invoice_date}<br>Buchung: {booking_number}</p><p>{name}<br>{address}</p><h2>Dein Aufenthalt</h2><p>{arrival} bis {departure} · {nights} Nächte · {guests} Gäste</p>{items}<p><strong>Rechnungsbetrag: {total}</strong><br>{vat_note}</p><p>Anzahlung: {deposit}<br>Restbetrag: {balance}, fällig am {due_date}<br>Bankverbindung: {iban}</p><p>Ortstaxe: {local_tax} – separat vor Ort bar zu bezahlen, nicht im Rechnungsbetrag enthalten.</p><p>{invoice_note}</p>',
            'pdf_footer' => '{property_name} · {tax_id}<br>{iban}',
        ];
    }
    public static function templates(): array {
        return FWB_I18n::overlay(FWB_I18n::base_templates(),'templates');
    }

    // Upgrade only unchanged factory copy; preserve individually edited templates.
    public static function migrate_copy(): void {
        $legacy = [
            'request_subject' => 'Ihre Anfrage {booking_number} – {property_name}',
            'request_body' => "Guten Tag {name},\n\nvielen Dank für Ihre Anfrage vom {arrival} bis {departure} ({nights} Nächte, {guests} Gäste).\nGesamtpreis der Unterkunft: {total}. Ortstaxe separat vor Ort: {local_tax}.\nDies ist noch keine Buchungsbestätigung. Wir melden uns bei Ihnen.\n\n{property_name}",
            'confirmed_body' => "Guten Tag {name},\n\nIhre Buchung vom {arrival} bis {departure} ist bestätigt.\nAnreise ab {checkin}, Abreise bis {checkout}.\nGesamtpreis: {total}\nBitte überweisen Sie zur Bestätigung die Anzahlung von {deposit}.\nDer Restbetrag von {balance} ist spätestens am {due_date} fällig; bei kurzfristiger Buchung bitte sofort bezahlen.\nBankverbindung: {iban}\nVerwendungszweck: {booking_number}\nKostenfreie Stornierung bis einschließlich {cancel_date}. Danach bitte mit uns Kontakt aufnehmen.\nOrtstaxe separat in bar vor Ort: {local_tax}.\n{tax_note}\n{rules}\n\n{property_name}",
            'cancelled_body' => "Guten Tag {name},\n\nIhre Buchung vom {arrival} bis {departure} wurde storniert.\nEventuelle Erstattungen oder Stornokosten klären wir mit Ihnen gesondert.\n\n{property_name}",
            'rejected_subject' => 'Ihre Anfrage {booking_number}',
            'rejected_body' => "Guten Tag {name},\n\nleider können wir Ihre Anfrage vom {arrival} bis {departure} nicht bestätigen.\n\n{property_name}",
            'invoice_subject' => 'Ihre Rechnung {invoice_number}',
            'invoice_body' => "Guten Tag {name},\n\nanbei Ihre Rechnung {invoice_number} für Ihren Aufenthalt vom {arrival} bis {departure}.\n\n{property_name}",
            'pdf_body' => '<h1>Rechnung {invoice_number}</h1><p>Rechnungsdatum: {invoice_date}<br>Buchung: {booking_number}</p><p>{name}<br>{address}</p><h2>Ihr Aufenthalt</h2><p>{arrival} bis {departure} · {nights} Nächte · {guests} Gäste</p>{items}<p><strong>Rechnungsbetrag: {total}</strong><br>{vat_note}</p><p>Anzahlung: {deposit}<br>Restbetrag: {balance}, fällig am {due_date}<br>Bankverbindung: {iban}</p><p>Ortstaxe: {local_tax} – separat vor Ort bar zu bezahlen, nicht im Rechnungsbetrag enthalten.</p><p>{invoice_note}</p>',
        ];
        $templates = (array)get_option('fwb_templates', []);
        $defaults = self::template_defaults();
        $changed = false;
        foreach ($legacy as $key=>$value) {
            if (isset($templates[$key]) && $templates[$key] === $value) {
                $templates[$key] = $defaults[$key]; $changed = true;
            }
        }
        if ($changed) { update_option('fwb_templates', $templates, false); }
        $fields = get_option('fwb_fields', false);
        if (is_array($fields)) {
            foreach ($fields as &$field) {
                if (($field['key'] ?? '') === 'message' && ($field['label'] ?? '') === 'Ihre Nachricht') {
                    $field['label'] = 'Deine Nachricht';
                }
            }
            unset($field);
            update_option('fwb_fields', $fields, false);
        }
    }
    public static function save(array $p): void {
        $s = self::get();
        foreach (['night_price','extra_price','cleaning_price','local_tax'] as $key) { $s[$key] = FWB_Domain::cents($p[$key] ?? '0'); }
        foreach (['max_guests'=>[1,50],'included_guests'=>[1,50],'min_nights'=>[1,90],'deposit_percent'=>[0,100],'balance_days'=>[0,365],'cancel_days'=>[0,365]] as $key=>$range) {
            $s[$key] = FWB_Domain::integer($p[$key] ?? '', ...$range);
        }
        if ($s['included_guests'] > $s['max_guests']) { throw new InvalidArgumentException('Inklusivgäste dürfen die maximale Belegung nicht überschreiten.'); }
        $s['vat_percent'] = FWB_Domain::cents($p['vat_percent'] ?? '0') / 100;
        if ($s['vat_percent'] > 100) { throw new InvalidArgumentException('Ungültiger Umsatzsteuersatz.'); }
        foreach (['property_name','tax_id','iban','invoice_prefix'] as $key) { $s[$key] = sanitize_text_field($p[$key] ?? ''); }
        if (!preg_match('/^[A-Za-z0-9-]{1,15}$/D', $s['invoice_prefix'])) { throw new InvalidArgumentException('Rechnungspräfix: 1–15 Buchstaben, Zahlen oder Bindestriche.'); }
        foreach (['seller','rules','tax_note','invoice_note'] as $key) { $s[$key] = sanitize_textarea_field($p[$key] ?? ''); }
        $s['privacy_url'] = esc_url_raw($p['privacy_url'] ?? '', ['https','http']);
        $s['admin_email'] = sanitize_email($p['admin_email'] ?? '');
        if (!is_email($s['admin_email'])) { throw new InvalidArgumentException('Gültige Gastgeber-E-Mail erforderlich.'); }
        foreach (['checkin','checkout'] as $key) {
            $s[$key] = sanitize_text_field($p[$key] ?? '');
            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $s[$key])) { throw new InvalidArgumentException('Ungültige An-/Abreisezeit.'); }
        }
        if ($s['checkout'] >= $s['checkin']) { throw new InvalidArgumentException('Die Abreisezeit muss vor der Anreisezeit liegen.'); }
        $s['extra_period'] = ($p['extra_period'] ?? '') === 'stay' ? 'stay' : 'night';
        $s['extra_unit'] = ($p['extra_unit'] ?? '') === 'booking' ? 'booking' : 'person';
        foreach (['auto_mail','auto_invoice'] as $key) { $s[$key] = empty($p[$key]) ? 0 : 1; }
        if (array_key_exists('custom_css',$p)) { $s['custom_css']=self::sanitize_css((string)$p['custom_css']); }
        foreach (['intro_kicker','intro_title','intro_text','form_title','form_intro','form_outro'] as $key) {
            if (array_key_exists($key,$p)) { $s[$key] = sanitize_textarea_field((string)$p[$key]); }
        }
        update_option('fwb_settings', $s, false);
    }
    public static function save_fields(string $json): void {
        update_option('fwb_fields', self::validate_fields($json), false);
    }
    public static function validate_fields(string $json): array {
        $input = json_decode($json, true);
        if (!is_array($input) || count($input) > 30) { throw new InvalidArgumentException('Ungültiges Formular.'); }
        $fields = []; $keys = [];
        foreach ($input as $f) {
            $key = sanitize_key($f['key'] ?? '');
            if (!$key || isset($keys[$key]) || !preg_match('/^[a-z][a-z0-9_]{0,39}$/D', $key)) { throw new InvalidArgumentException('Feldkennungen müssen eindeutig sein.'); }
            $keys[$key] = true;
            $type = in_array($f['type'] ?? '', ['text','email','tel','textarea','select','checkbox'], true) ? $f['type'] : 'text';
            if (in_array($key, ['name','email','address'], true)) { $type = ['name'=>'text','email'=>'email','address'=>'textarea'][$key]; }
            $field = ['key'=>$key,'label'=>sanitize_text_field($f['label'] ?? ''),'type'=>$type,'required'=>!empty($f['required']) || in_array($key,['name','email','address'],true),'core'=>in_array($key,['name','email','address'],true)];
            if (!$field['label']) { throw new InvalidArgumentException('Jedes Feld benötigt eine Beschriftung.'); }
            $field['options'] = array_slice(array_values(array_filter(array_map('sanitize_text_field', explode("\n", (string)($f['options'] ?? ''))))), 0, 30);
            if ($type === 'select' && !$field['options']) { throw new InvalidArgumentException('Auswahlfelder benötigen Optionen.'); }
            $fields[] = $field;
        }
        foreach (['name','email','address'] as $key) { if (!isset($keys[$key])) { throw new InvalidArgumentException('Name, E-Mail und Rechnungsadresse sind Pflichtfelder.'); } }
        return $fields;
    }
}
