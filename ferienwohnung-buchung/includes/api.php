<?php
defined('ABSPATH') || exit;
final class FWB_API {
    public static function boot(): void {
        add_action('rest_api_init', static function () {
            foreach (['calendar'=>'GET','challenge'=>'GET','quote'=>'POST','request'=>'POST'] as $route=>$method) {
                register_rest_route('fwb/v1', '/' . $route, ['methods'=>$method,'permission_callback'=>'__return_true','callback'=>static fn($r)=>self::dispatch($route,$r)]);
            }
        });
    }
    public static function dispatch(string $route, WP_REST_Request $r) {
        $old=FWB_I18n::set(null);
        try {
            FWB_I18n::set(FWB_I18n::validate($r['fwb_lang']??FWB_I18n::current()));
            if (strlen((string)$r->get_body()) > 32768) { return new WP_Error('too_large',FWB_I18n::t('Anfrage ist zu groß.'),['status'=>413]); }
            $result = self::$route($r);
            $response = new WP_REST_Response($result);
            $response->header('Cache-Control','no-store, private'); return $response;
        } catch (InvalidArgumentException $e) { return new WP_Error('invalid_request',FWB_I18n::error($e->getMessage()),['status'=>400]); }
        catch (Throwable $e) { return new WP_Error('unavailable',FWB_I18n::t('Die Anfrage konnte nicht verarbeitet werden. Bitte später erneut versuchen.'),['status'=>503]); }
        finally { FWB_I18n::set($old); }
    }
    public static function calendar(WP_REST_Request $r): array {
        global $wpdb; $month=(string)($r['month'] ?: current_time('Y-m'));
        $first=FWB_Domain::date($month . '-01'); $end=$first->modify('+1 month')->format('Y-m-d');
        $t=FWB_Store::table();
        return ['ranges'=>$wpdb->get_results($wpdb->prepare("SELECT arrival,departure FROM $t WHERE status IN ('confirmed','blocked') AND arrival < %s AND departure >= %s",$end,$first->format('Y-m-d')),ARRAY_A)];
    }
    private static function client(): string { return hash_hmac('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), wp_salt('nonce')); }
    public static function challenge(WP_REST_Request $r): array {
        // Only REMOTE_ADDR is trusted; forwarding headers are not accepted from clients.
        self::rate('challenge',60);
        $c=['id'=>bin2hex(random_bytes(20)),'expires'=>time()+600,'difficulty'=>3,'client'=>self::client()];
        $token=rtrim(strtr(base64_encode(wp_json_encode($c)),'+/','-_'),'=');
        return ['challenge'=>$token,'signature'=>hash_hmac('sha256',$token,wp_salt('auth')),'difficulty'=>3];
    }
    private static function rate(string $kind, int $limit): void {
        global $wpdb;
        // Unique per-minute ticket keys provide an atomic rate limit even with persistent caches.
        $t=FWB_Store::table('tokens'); $base=hash('sha256',$kind . self::client() . (string)floor(time()/60));
        $wpdb->query($wpdb->prepare("DELETE FROM $t WHERE expires < %d",time()));
        for ($i=0;$i<$limit;$i++) {
            $key=hash('sha256',$base . ':' . $i);
            if ($wpdb->query($wpdb->prepare("INSERT IGNORE INTO $t (token,expires) VALUES (%s,%d)",$key,time()+120)) === 1) { return; }
            if ($wpdb->last_error) { throw new RuntimeException('Rate limiter unavailable'); }
        }
        throw new InvalidArgumentException('Zu viele Versuche. Bitte eine Minute warten.');
    }
    public static function quote(WP_REST_Request $r): array {
        $p=$r->get_json_params(); if (!is_array($p)) { throw new InvalidArgumentException('Ungültige Anfrage.'); }
        $q=FWB_Domain::quote($p,FWB_I18n::settings(),current_time('Y-m-d'));
        if (!FWB_Store::available($q['arrival'],$q['departure'])) { throw new InvalidArgumentException('Der gewünschte Zeitraum ist belegt.'); }
        return $q;
    }
    public static function request(WP_REST_Request $r): array {
        global $wpdb; $p=$r->get_json_params();
        if (!is_array($p)) { throw new InvalidArgumentException('Ungültige Anfrage.'); }
        foreach (['challenge','signature','nonce','website'] as $key) { if (isset($p[$key]) && !is_string($p[$key])) { throw new InvalidArgumentException('Ungültiger Spam-Schutz.'); } }
        if (!empty($p['website']) || empty($p['consent'])) { throw new InvalidArgumentException('Bitte die Datenschutzhinweise und Buchungsbedingungen bestätigen.'); }
        $token=$p['challenge'] ?? ''; $sig=$p['signature'] ?? '';
        if (!hash_equals(hash_hmac('sha256',$token,wp_salt('auth')),$sig)) { throw new InvalidArgumentException('Spam-Schutz ungültig. Bitte erneut absenden.'); }
        $c=json_decode((string)base64_decode(strtr($token,'-_','+/')),true);
        if (!is_array($c) || ($c['expires'] ?? 0)<time() || ($c['client'] ?? '')!==self::client() || !FWB_Domain::proof($token,$p['nonce'] ?? '',3)) { throw new InvalidArgumentException('Spam-Schutz abgelaufen oder ungültig. Bitte erneut absenden.'); }
        $s=FWB_I18n::settings();
        if (!$s['privacy_url']) { throw new InvalidArgumentException('Der Gastgeber muss noch die Datenschutzhinweise einrichten.'); }
        $q=self::quote($r); $fields=[];
            $lengths=[];
            foreach (FWB_Layout::get()['zones'] as $blocks)foreach($blocks as $block)if($block['type']==='field'&&!empty($block['appearance']['maxlength']))$lengths[$block['field']['key']]=(int)$block['appearance']['maxlength'];

        $translatedFields=[];foreach(FWB_Layout::get()['zones'] as $blocks)foreach($blocks as $block)if($block['type']==='field')$translatedFields[]=$block['field'];
        foreach ($translatedFields as $f) {
            $raw=$p['fields'][$f['key']] ?? '';
            if (!is_scalar($raw) || strlen((string)$raw)>4000) { throw new InvalidArgumentException('Feldinhalt zu lang oder ungültig.'); }
            $v=$f['type']==='textarea' ? sanitize_textarea_field($raw) : sanitize_text_field($raw);
            if (isset($lengths[$f['key']]) && mb_strlen($v)>$lengths[$f['key']]) { throw new InvalidArgumentException('Zu viele Zeichen: '.$f['label']); }
            if ($f['required'] && $v==='') { throw new InvalidArgumentException('Bitte ausfüllen: ' . $f['label']); }
            if ($f['type']==='email' && $v!=='' && !is_email($v)) { throw new InvalidArgumentException('Bitte eine gültige E-Mail-Adresse eingeben.'); }
            if ($f['type']==='select' && $v!=='' && !in_array($v,$f['options'],true)) { throw new InvalidArgumentException('Ungültige Auswahl.'); }
            if ($f['type']==='checkbox' && !in_array($v,['','1'],true)) { throw new InvalidArgumentException('Ungültiges Kontrollkästchen.'); }
            $fields[$f['key']]=$v;
        }
        if (strlen($fields['name'])>200 || strlen($fields['email'])>200) { throw new InvalidArgumentException('Name oder E-Mail-Adresse ist zu lang.'); }
        // Consume only after validation. Unique database key prevents concurrent replay.
        $t=FWB_Store::table('tokens');
        if ($wpdb->query($wpdb->prepare("INSERT IGNORE INTO $t (token,expires) VALUES (%s,%d)",hash('sha256','used:' . $c['id']),$c['expires']))!==1) { throw new InvalidArgumentException('Diese Anfrage wurde bereits verarbeitet. Bitte neu laden.'); }
        self::rate('request',5);
        $id=FWB_Store::insert($q,$fields);
        if ($s['auto_mail']) { FWB_Documents::mail($id,'request'); FWB_Documents::notify($id); }
        return ['reference'=>FWB_Store::get($id)['reference'],'message'=>FWB_I18n::t('Vielen Dank! Deine Anfrage wurde gespeichert. Die Buchung gilt erst nach Bestätigung durch den Gastgeber.')];
    }
}
