<?php
defined('ABSPATH') || exit;
final class FWB_Management {
    public static function create(array $p): array {
        $status=$p['status']??'confirmed';
        if(!in_array($status,['pending','confirmed'],true))throw new InvalidArgumentException('Bitte Anfrage oder bestätigte Buchung wählen.');
        $fields=[];
        foreach(['name','email','address','phone','message'] as $key){
            $raw=$p[$key]??'';
            if(!is_string($raw)||mb_strlen($raw)>($key==='message'||$key==='address'?4000:200))throw new InvalidArgumentException('Gästedaten sind ungültig oder zu lang.');
            $fields[$key]=in_array($key,['address','message'],true)?sanitize_textarea_field($raw):sanitize_text_field($raw);
        }
        if($fields['name']==='')throw new InvalidArgumentException('Bitte den Namen des Gastes eingeben.');
        if(($fields['email']!==''&&!is_email($fields['email']))||(!empty($p['send_mail'])&&!is_email($fields['email'])))throw new InvalidArgumentException('Für den Mailversand ist eine gültige E-Mail-Adresse erforderlich.');
        $language=FWB_I18n::validate($p['language']??FWB_I18n::source());
        $s=FWB_I18n::run($language,fn()=>FWB_I18n::settings());$quote=FWB_Domain::quote($p,$s,current_time('Y-m-d'));
        $id=FWB_Store::insert($quote,$fields,$status,'admin',$language);
        FWB_Store::event($id,'manual','Manuell erfasst von Benutzer '.get_current_user_id().'.');
        $message='Reservierung '.$id.' gespeichert. ';
        $message.=self::after_status($id,$status,!empty($p['send_mail']),true);
        return ['id'=>$id,'message'=>$message];
    }
    public static function guests(int $id,array $p): void {
        $fields=[];
        foreach(['name','email','address','phone','message'] as $key){
            $raw=$p[$key]??'';
            if(!is_string($raw)||mb_strlen($raw)>($key==='message'||$key==='address'?4000:200))throw new InvalidArgumentException('Gästedaten sind ungültig oder zu lang.');
            $fields[$key]=in_array($key,['address','message'],true)?sanitize_textarea_field($raw):sanitize_text_field($raw);
        }
        if($fields['name']===''||($fields['email']!==''&&!is_email($fields['email'])))throw new InvalidArgumentException('Name und gegebenenfalls gültige E-Mail eingeben.');
        FWB_Store::locked(static function()use($id,$fields){
            global $wpdb;$b=FWB_Store::get($id);if($b['status']==='blocked')throw new InvalidArgumentException('Keine Gästedaten für Sperrzeiten.');
            $data=$b['data'];$data['fields']=array_merge($data['fields'],$fields);
            if($wpdb->update(FWB_Store::table(),['name'=>$fields['name'],'email'=>$fields['email'],'payload'=>wp_json_encode($data)],['id'=>$id])===false)throw new RuntimeException('Gästedaten konnten nicht gespeichert werden.');
            FWB_Store::event($id,'guest_updated','Gästedaten von Benutzer '.get_current_user_id().' aktualisiert. Keine Mail versendet; ausgestellte Rechnung unverändert.');
        });
    }
    public static function change(int $id,string $next,bool $send): string {
        $before=FWB_Store::get($id);
        if($before['status']==='blocked')$send=false;
        if($send&&!is_email($before['email']))throw new InvalidArgumentException('Keine gültige Gast-E-Mail hinterlegt. Mail-Auswahl deaktivieren.');
        if(!FWB_Store::transition($id,$next))return 'Der Status war bereits gespeichert. Keine erneute Mail versendet.';
        return 'Status gespeichert. '.self::after_status($id,$next,$send,$before['status']!=='blocked');
    }
    public static function actions(string $status): array {
        return match($status){
            'pending'=>['confirmed','rejected','trash'],
            'confirmed'=>['cancelled','trash'], 'blocked'=>['unblock','trash'],
            'cancelled','rejected'=>['confirmed','trash'], 'trash'=>['restore'], default=>[]
        };
    }
    public static function apply(int $id,string $action,bool $send=false): string {
        $b=FWB_Store::get($id);
        if(!in_array($action,self::actions($b['status']),true))throw new InvalidArgumentException('Diese Aktion passt nicht zum aktuellen Status.');
        if(in_array($action,['trash','restore'],true))return self::trash($id,$action==='restore');
        if($action==='unblock')return self::change($id,'cancelled',false);
        return self::change($id,$action,$send);
    }
    private static function trash(int $id,bool $restore): string {
        return FWB_Store::locked(static function()use($id,$restore){
            global $wpdb;$b=FWB_Store::get($id);$data=$b['data'];
            if($restore){
                if($b['status']!=='trash')throw new InvalidArgumentException('Eintrag ist nicht im Papierkorb.');
                $next=$data['trash_status']??'cancelled';
                if(!in_array($next,['pending','confirmed','blocked','cancelled','rejected'],true))throw new InvalidArgumentException('Ursprünglicher Status ist ungültig.');
                if(in_array($next,['confirmed','blocked'],true)&&!FWB_Store::available($b['arrival'],$b['departure'],$id))throw new InvalidArgumentException('Wiederherstellung nicht möglich: Zeitraum inzwischen belegt.');
                unset($data['trash_status'],$data['trashed_at']);
            }else{
                if($b['status']==='trash')throw new InvalidArgumentException('Eintrag ist bereits im Papierkorb.');
                $data['trash_status']=$b['status'];$data['trashed_at']=current_time('mysql',true);$next='trash';
            }
            if($wpdb->update(FWB_Store::table(),['status'=>$next,'payload'=>wp_json_encode($data)],['id'=>$id])===false)throw new RuntimeException('Papierkorb-Aktion konnte nicht gespeichert werden.');
            FWB_Store::event($id,$restore?'restored':'trashed','Benutzer '.get_current_user_id().': '.$b['status'].' → '.$next.'. Keine Mail versendet.');
            return ($restore?'Wiederhergestellt.':'In den Papierkorb verschoben; Zeitraum freigegeben.').' Keine Mail versendet. Rechnungen bleiben erhalten.';
        });
    }
    public static function bulk(array $ids,string $action,bool $send=false): array {
        if(count($ids)>30)throw new InvalidArgumentException('Maximal 30 Einträge pro Sammelaktion.');
        $unique=[];foreach($ids as $id){$id=FWB_Domain::integer($id,1,PHP_INT_MAX);$unique[$id]=$id;}
        if(!$unique)throw new InvalidArgumentException('Bitte mindestens eine Buchung auswählen.');
        if(!in_array($action,['confirmed','cancelled','rejected','unblock','trash','restore'],true))throw new InvalidArgumentException('Bitte eine Sammelaktion auswählen.');
        $results=[];
        foreach($unique as $id){try{$results[]=['id'=>$id,'ok'=>true,'message'=>self::apply($id,$action,$send)];}catch(Throwable $e){$results[]=['id'=>$id,'ok'=>false,'message'=>$e->getMessage()];}}
        return $results;
    }
    private static function after_status(int $id,string $status,bool $send,bool $guest): string {
        $messages=[];
        if($send&&$guest){$kind=$status==='pending'?'request':$status;$messages[]=FWB_Documents::mail($id,$kind)?'Status-Mail an WordPress übergeben.':'Mailversand fehlgeschlagen; siehe Versandprotokoll.';}
        else $messages[]='Keine Mail versendet.';
        if($status==='confirmed'&&FWB_Settings::get()['auto_invoice']){
            try{
                $b=FWB_Store::get($id);
                if(empty($b['data']['fields']['address']))throw new InvalidArgumentException('Rechnungsadresse fehlt.');
                $existing=FWB_Store::invoice($id);FWB_Documents::issue($id);$messages[]='Rechnung erstellt.';
                if(!$existing&&$send)$messages[]=FWB_Documents::mail($id,'invoice')?'Rechnungsmail an WordPress übergeben.':'Rechnungsmail fehlgeschlagen.';
            }catch(Throwable $e){$messages[]='Die Buchung bleibt bestätigt. Rechnung noch nicht erstellt: '.$e->getMessage();FWB_Store::event($id,'invoice_failed',$e->getMessage());}
        }
        return implode(' ',$messages);
    }
    public static function overview(?string $today=null): array {
        global $wpdb;$today=$today??current_time('Y-m-d');$t=FWB_Store::table();
        $year=substr($today,0,4);$start=$year.'-01-01';$end=((int)$year+1).'-01-01';
        $counts=array_fill_keys(['pending','confirmed','cancelled','rejected','blocked'],0);
        foreach($wpdb->get_results("SELECT status,COUNT(*) amount FROM $t GROUP BY status",ARRAY_A) as $r)$counts[$r['status']]=(int)$r['amount'];
        $next=$wpdb->get_row($wpdb->prepare("SELECT id,reference,name,arrival,departure FROM $t WHERE status='confirmed' AND arrival>=%s ORDER BY arrival,id LIMIT 1",$today),ARRAY_A);
        $current=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE status='confirmed' AND arrival<=%s AND departure>%s",$today,$today));
        $rows=$wpdb->get_results($wpdb->prepare("SELECT arrival,departure FROM $t WHERE status='confirmed' AND arrival<%s AND departure>%s",$end,$start),ARRAY_A);
        $months=[];
        for($m=1;$m<=12;$m++){
            $a=sprintf('%s-%02d-01',$year,$m);$d=FWB_Domain::date($a)->modify('+1 month')->format('Y-m-d');$nights=0;$arrivals=0;
            foreach($rows as $r){if($r['arrival']>=$a&&$r['arrival']<$d)$arrivals++;if($r['arrival']<$d&&$r['departure']>$a)$nights+=(int)FWB_Domain::date(max($a,$r['arrival']))->diff(FWB_Domain::date(min($d,$r['departure'])))->days;}
            $days=(int)FWB_Domain::date($a)->diff(FWB_Domain::date($d))->days;
            $months[]=['month'=>$a,'nights'=>$nights,'arrivals'=>$arrivals,'occupancy'=>round($nights/$days*100)];
        }
        return compact('counts','next','current','months','year');
    }
}
