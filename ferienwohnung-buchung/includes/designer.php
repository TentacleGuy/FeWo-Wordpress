<?php
defined('ABSPATH') || exit;
final class FWB_Designer {
    public static function boot(): void {
        add_action('wp_ajax_fwb_designer',[self::class,'ajax']);
        add_action('template_redirect',static function(){
            if (!isset($_GET['fwb_preview']))return;
            if(!current_user_can('manage_options'))wp_die('Keine Berechtigung.','',['response'=>403]);
            check_ajax_referer('fwb_designer','nonce');
            nocache_headers();
            try {
                FWB_I18n::set(FWB_I18n::validate($_POST['fwb_lang']??FWB_I18n::source()));
                $data=FWB_Layout::validate(wp_unslash((string)($_POST['payload']??'')));
                FWB_Layout::$preview=$data['layout'];
                $html=FWB_Frontend::shortcode();
                $html=str_replace('class="fwb-widget ','data-preview="1" class="fwb-widget ',$html);
                echo '<!doctype html><html lang="'.esc_attr(str_replace('_','-',FWB_I18n::locale())).'"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
                wp_head();
                $dark=($_POST['theme']??'')==='dark';
                echo '</head><body style="margin:0;padding:24px;background:'.($dark?'#15222b':'#fff').'" class="'.($dark?'uk-light':'uk-dark').'">'.$html;
                wp_footer();echo '</body></html>';
            }catch(Throwable $e){status_header(400);echo esc_html($e->getMessage());}
            exit;
        });
    }
    public static function config(): array {
        return ['ajax'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('fwb_designer'),'preview'=>add_query_arg('fwb_preview','1',home_url('/')),
            'schema'=>FWB_Design::schema(),'globalSchema'=>FWB_Design::global_schema(),'design'=>FWB_Design::get(),'defaults'=>FWB_Design::defaults(),
            'pdfSchema'=>FWB_Documents::pdf_schema(),'pdfDesign'=>FWB_Documents::pdf_design(),
            'language'=>FWB_I18n::current(),'translation'=>FWB_I18n::foreign(),'sourceEditor'=>add_query_arg('lang',FWB_I18n::source(),FWB_Admin::url('form')),'user'=>get_current_user_id(),'site'=>home_url('/')];
    }
    public static function templates(): array {
        $t=FWB_Settings::templates();$formats=FWB_I18n::formats();
        foreach(['request','confirmed','rejected','cancelled','invoice'] as $kind)if(($formats[$kind]??'text')!=='html')$t[$kind.'_body']=wpautop(esc_html($t[$kind.'_body']));
        $t['signature']=FWB_I18n::signature();return $t;
    }
    public static function clean_templates(array $input): array {
        $out=[];
        foreach(FWB_Settings::template_defaults() as $key=>$default) {
            $raw=$input[$key]??$default;
            if(!is_string($raw)||strlen($raw)>50000)throw new InvalidArgumentException('Vorlage zu lang oder ungültig.');
            $out[$key]=str_ends_with($key,'_subject')?sanitize_text_field($raw):FWB_Documents::safe_html($raw);
        }
        $signature=$input['signature']??FWB_I18n::signature();
        if(!is_string($signature)||strlen($signature)>30000)throw new InvalidArgumentException('Signatur zu lang oder ungültig.');
        $out['signature']=FWB_Documents::safe_html($signature);return $out;
    }
    public static function sample(int $id=0): array {
        if($id){$b=FWB_Store::get($id);if(!isset($b['data']['quote']['total']))throw new InvalidArgumentException('Bitte eine Buchung mit Preis wählen.');return $b;}
        $s=FWB_I18n::settings();$s['night_price']=max(12000,$s['night_price']);
        $a=(new DateTimeImmutable('today'))->modify('+45 days')->format('Y-m-d');
        $d=(new DateTimeImmutable($a))->modify('+'.max(4,(int)$s['min_nights']).' days')->format('Y-m-d');
        $q=FWB_Domain::quote(['arrival'=>$a,'departure'=>$d,'guests'=>min(2,$s['max_guests']),'taxable_guests'=>min(2,$s['max_guests'])],$s,current_time('Y-m-d'));
        $f=['name'=>'Alex Muster','email'=>'alex@example.invalid','address'=>"Musterstraße 12\n12345 Musterstadt",'phone'=>'+49 123 456789','message'=>'Wir freuen uns auf den Aufenthalt.'];
        foreach(FWB_Settings::fields() as $field)if(!isset($f[$field['key']]))$f[$field['key']]='Beispiel: '.$field['label'];
        return ['reference'=>'FW-VORSCHAU','data'=>['language'=>FWB_I18n::current(),'fields'=>$f,'quote'=>$q],'email'=>$f['email'],'name'=>$f['name']];
    }
    public static function tokens(): array {
        $groups=[
            'Gast'=>['name','email','address','phone','message','guests'],
            'Buchung'=>['booking_number','property_name','arrival','departure','nights','checkin','checkout','rules','tax_note','cancel_date'],
            'Zahlung'=>['total','deposit','balance','due_date','local_tax','iban'],
            'Rechnung'=>['seller','tax_id','invoice_number','invoice_date','invoice_note','vat_note','items'],
        ];
        foreach(FWB_Settings::fields() as $field)if(!in_array($field['key'],array_merge(...array_values($groups)),true))$groups['Eigene Felder'][]=$field['key'];
        return $groups;
    }
    public static function bookings(): array {
        global $wpdb;$rows=$wpdb->get_results('SELECT id,reference,name FROM '.FWB_Store::table()." WHERE status NOT IN ('blocked','trash') ORDER BY id DESC LIMIT 100",ARRAY_A);
        $out=['0'=>'Beispieldaten'];foreach($rows as $r)$out[$r['id']]=$r['reference'].' · '.$r['name'];return $out;
    }
    public static function ajax(): void {
        if(!current_user_can('manage_options'))wp_send_json_error(['message'=>'Keine Berechtigung.'],403);
        check_ajax_referer('fwb_designer','nonce');
        try {
            $op=sanitize_key($_POST['op']??'');
            FWB_I18n::set(FWB_I18n::validate($_POST['fwb_lang']??FWB_I18n::source()));
            if(FWB_I18n::foreign()&&in_array($op,['design_save','design_import','design_restore'],true))throw new InvalidArgumentException('Design bitte in der Ausgangssprache bearbeiten.');
            $raw=wp_unslash((string)($_POST['payload']??'{}'));
            if(strlen($raw)>1000000)throw new InvalidArgumentException('Daten zu groß.');
            $p=json_decode($raw,true);if(!is_array($p))throw new InvalidArgumentException('Ungültige Daten.');
            switch($op){
                case 'language_settings':
                    $values=[];foreach(FWB_I18n::GLOBALS as $key=>$label){$raw=$p[$key]??'';if(!is_string($raw)||strlen($raw)>10000)throw new InvalidArgumentException('Text ungültig oder zu lang.');$values[$key]=$key==='privacy_url'?esc_url_raw($raw):sanitize_textarea_field($raw);}
                    if(FWB_I18n::foreign())FWB_I18n::save(array_intersect_key(FWB_Settings::get(),FWB_I18n::GLOBALS),$values,'settings');
                    else update_option('fwb_settings',array_replace(FWB_Settings::get(),$values),false);
                    $result=['message'=>'Textbausteine für diese Sprache gespeichert.'];break;
                case 'manual_quote':
                    $q=FWB_Domain::quote($p,FWB_Settings::get(),current_time('Y-m-d'));
                    if(!FWB_Store::available($q['arrival'],$q['departure']))throw new InvalidArgumentException('Der Zeitraum ist bereits belegt.');
                    $result=['message'=>'Aktuell verfügbar · '.$q['nights'].' Nächte · Gesamtpreis '.FWB_Domain::money($q['total']).' · Anzahlung '.FWB_Domain::money($q['deposit']).' · Ortstaxe vor Ort '.FWB_Domain::money($q['local_tax']).'. Beim Anlegen wird erneut geprüft.'];break;
                case 'layout_save':FWB_Layout::save(wp_json_encode($p));$result=['message'=>'Baukasten gespeichert.','layout'=>FWB_Layout::get()];break;
                case 'design_save':
                    $d=FWB_Design::clean($p,FWB_Design::global_schema());update_option('fwb_design',$d,false);$result=['message'=>'Design gespeichert.','design'=>$d];break;
                case 'design_export':$result=['format'=>'fwb-design','version'=>1,'design'=>FWB_Design::get(),'layout'=>FWB_Layout::base(),'pdf'=>FWB_Documents::pdf_design()];break;
                case 'design_import':
                    if(($p['format']??'')!=='fwb-design'||($p['version']??0)!==1)throw new InvalidArgumentException('Keine passende Design-Datei.');
                    $d=FWB_Design::clean((array)($p['design']??[]),FWB_Design::global_schema());
                    $l=FWB_Layout::validate(wp_json_encode($p['layout']??[]));$pdf=FWB_Documents::clean_pdf((array)($p['pdf']??[]));
                    update_option('fwb_design_backup',['design'=>FWB_Design::get(),'layout'=>FWB_Layout::get(),'pdf'=>FWB_Documents::pdf_design()],false);
                    update_option('fwb_design',$d,false);FWB_Layout::save(wp_json_encode($l['layout']));update_option('fwb_pdf_design',$pdf,false);
                    $result=['message'=>'Design importiert. Die vorherige Gestaltung wurde gesichert.'];break;
                case 'design_restore':
                    $b=get_option('fwb_design_backup');if(!is_array($b))throw new InvalidArgumentException('Keine Sicherung vorhanden.');
                    FWB_Layout::save(wp_json_encode($b['layout']));update_option('fwb_design',$b['design'],false);update_option('fwb_pdf_design',$b['pdf'],false);
                    $result=['message'=>'Vorherige Gestaltung wiederhergestellt.'];break;
                case 'templates_save':
                    $t=self::clean_templates((array)($p['templates']??[]));$d=FWB_Documents::clean_pdf((array)($p['pdf']??[]));
                    FWB_I18n::save_templates($t);if(!FWB_I18n::foreign())update_option('fwb_pdf_design',$d,false);
                    $result=['message'=>'Vorlagen gespeichert.'];break;
                case 'template_preview':
                case 'test_mail':
                    $t=self::clean_templates((array)($p['templates']??[]));$d=FWB_Documents::clean_pdf((array)($p['pdf']??[]));
                    $b=self::sample(absint($p['booking']??0));$b['data']['language']=FWB_I18n::current();$kind=$p['kind']??'request';
                    if(str_starts_with($kind,'pdf_')){
                        if($op==='test_mail')throw new InvalidArgumentException('Testversand ist für E-Mail-Vorlagen verfügbar.');
                        $pdf=FWB_Documents::pdf(FWB_Documents::html($b,['number'=>'ENTWURF – NICHT AUSGESTELLT'],$t,$d));
                        $result=['pdf'=>base64_encode($pdf)];
                    }else{
                        if(!in_array($kind,['request','confirmed','rejected','cancelled','invoice','signature'],true))throw new InvalidArgumentException('Unbekannte Vorlage.');
                        $v=FWB_Documents::variables($b);$html=FWB_Documents::email_html($kind==='signature'?'':$t[$kind.'_body'],$v,$t['signature']);$subject=$kind==='signature'?'Mail-Signatur':sanitize_text_field(FWB_Documents::render($t[$kind.'_subject'],$v));
                        if($op==='test_mail'){
                            $to=sanitize_email($p['recipient']??'');if(!is_email($to))throw new InvalidArgumentException('Gültige Testadresse eingeben.');
                            $ok=FWB_Documents::send_html($to,'[TEST] '.$subject,$html);
                            if(!$ok)throw new RuntimeException('Testmail konnte nicht übergeben werden.');
                            $result=['message'=>'Testmail an den WordPress-Mailtransport übergeben.'];
                        }else $result=['html'=>$html,'subject'=>$subject];
                    }break;
                default:throw new InvalidArgumentException('Unbekannte Aktion.');
            }
            wp_send_json_success($result);
        }catch(Throwable $e){wp_send_json_error(['message'=>$e->getMessage()],400);}
    }
}
