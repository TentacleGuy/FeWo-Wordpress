<?php
defined('ABSPATH') || exit;
final class FWB_Documents {
    public static function variables(array $b, array $invoice=[]): array {
        return FWB_I18n::run(FWB_I18n::booking($b),fn()=>self::localized_variables($b,$invoice));
    }
    private static function localized_variables(array $b,array $invoice): array {
        $s=FWB_I18n::settings(); $q=$b['data']['quote']; $f=$b['data']['fields'];
        $v=array_merge($f,$q,['booking_number'=>$b['reference'],'property_name'=>$s['property_name'],'seller'=>$s['seller'],'tax_id'=>$s['tax_id'],'iban'=>$s['iban'],'invoice_note'=>$s['invoice_note'],'invoice_number'=>$invoice['number'] ?? 'VORSCHAU','invoice_date'=>$invoice['date'] ?? current_time('d.m.Y')]);
        foreach (['total','deposit','balance','local_tax','base','supplement','cleaning','vat'] as $key) { $v[$key]=FWB_Domain::money((int)($q[$key] ?? 0)); }
        foreach (['arrival','departure','due_date','cancel_date'] as $key) { $v[$key]=FWB_I18n::date($q[$key]); }
        $v['rules']=$q['rules']??$s['rules'];$v['tax_note']=$q['tax_note']??$s['tax_note'];
        // Older English bookings may have snapshotted German defaults before English copy was bundled.
        // Translate that exact snapshot, never replace it with today's potentially different conditions.
        if(FWB_I18n::foreign())foreach(['rules','tax_note'] as $key)$v[$key]=FWB_I18n::builtin($v[$key])??$v[$key];
        $invoiceDate=$invoice['date']??current_time('Y-m-d');
        if(preg_match('/^\d{2}\.\d{2}\.\d{4}$/D',$invoiceDate))$invoiceDate=implode('-',array_reverse(explode('.',$invoiceDate)));
        $v['invoice_date']=FWB_I18n::date($invoiceDate);
        $v['vat_note']=$q['vat_percent']>0 ? FWB_I18n::t('Enthaltene Umsatzsteuer ({rate} %): {amount}',['{rate}'=>(string)$q['vat_percent'],'{amount}'=>$v['vat']]) : '';
        return $v;
    }
    public static function render(string $template, array $v, bool $html=false): string {
        $map=[];
        foreach ($v as $key=>$value) {
            if (!is_scalar($value)) { continue; }
            $map['{' . $key . '}']=$html ? nl2br(esc_html((string)$value)) : (string)$value;
        }
        return strtr($template,$map);
    }

    public static function safe_html(string $html): string {
        $tags=[];
        foreach (['p','br','strong','b','em','i','u','h1','h2','h3','h4','h5','h6','pre','blockquote','table','thead','tbody','tr','th','td','div','span','ul','ol','li','hr'] as $tag) { $tags[$tag]=['style'=>true]; }
        $tags['a']=['href'=>true,'title'=>true];
        $clean=wp_kses($html,$tags);
        $p=new WP_HTML_Tag_Processor($clean);
        while ($p->next_tag()) {
            $style=(string)$p->get_attribute('style');
            if (preg_match('/url\s*\(|expression|@|\\\\/i',$style))$p->remove_attribute('style');
        }
        return $p->get_updated_html();
    }
    public static function pdf_schema(): array {
        $schema=[
            'font'=>['label'=>'Schrift','type'=>'select','options'=>['sans'=>'DejaVu Sans','serif'=>'DejaVu Serif','mono'=>'DejaVu Sans Mono']],
            'font_size'=>['label'=>'Schriftgröße (pt)','type'=>'number','min'=>7,'max'=>22],
            'text_color'=>['label'=>'Textfarbe','type'=>'color'],'accent'=>['label'=>'Akzentfarbe','type'=>'color'],
            'table_background'=>['label'=>'Tabellenkopf-Hintergrund','type'=>'color'],'table_color'=>['label'=>'Tabellenkopf-Text','type'=>'color'],
            'table_border'=>['label'=>'Tabellenlinien','type'=>'color'],
            'alignment'=>['label'=>'Textausrichtung','type'=>'select','options'=>['left'=>'Links','center'=>'Zentriert','right'=>'Rechts']],
            'page_numbers'=>['label'=>'Seitenzahlen anzeigen','type'=>'checkbox'],
            'logo_id'=>['label'=>'Logo aus der Mediathek','type'=>'number','min'=>0,'max'=>2147483647],
            'logo_width'=>['label'=>'Logo-Breite (mm)','type'=>'number','min'=>10,'max'=>100],
        ];
        foreach (['margin'=>'Seitenrand links/rechts (mm)','top'=>'Abstand oben (mm)','bottom'=>'Abstand unten (mm)','header_height'=>'Kopfzeilenhöhe (mm)','footer_height'=>'Fußzeilenhöhe (mm)'] as $key=>$label)$schema[$key]=['label'=>$label,'type'=>'number','min'=>5,'max'=>60];
        return $schema;
    }
    public static function pdf_defaults(): array { return ['font'=>'sans','font_size'=>'10','text_color'=>'#26352f','accent'=>'#326451','table_background'=>'#edf4ed','table_color'=>'#26352f','table_border'=>'#dddddd','alignment'=>'left','page_numbers'=>true,'logo_id'=>'0','logo_width'=>'35','margin'=>'17','top'=>'12','bottom'=>'12','header_height'=>'36','footer_height'=>'20']; }
    public static function pdf_design(): array { return array_merge(self::pdf_defaults(),(array)get_option('fwb_pdf_design',[])); }
    public static function clean_pdf(array $input): array {
        $clean=FWB_Design::clean($input,self::pdf_schema());
        foreach (self::pdf_defaults() as $key=>$value)if(!isset($clean[$key])||$clean[$key]==='')$clean[$key]=$value;
        if((float)$clean['header_height']+(float)$clean['footer_height']+(float)$clean['top']+(float)$clean['bottom']>180)throw new InvalidArgumentException('Kopf-/Fußzeilen und Ränder lassen zu wenig Platz für den Inhalt.');
        if (!empty($clean['logo_id']))self::logo((int)$clean['logo_id']);
        return $clean;
    }
    public static function logo(int $id): string {
        if (!$id)return '';
        $file=realpath((string)get_attached_file($id));$uploads=wp_get_upload_dir();$root=realpath($uploads['basedir']);
        if (!$file||!$root||!str_starts_with(wp_normalize_path($file),trailingslashit(wp_normalize_path($root)))||filesize($file)>3000000)throw new InvalidArgumentException('Logo: bitte ein lokales PNG/JPEG aus der Mediathek bis 3 MB wählen.');
        $size=@getimagesize($file);
        if(!$size||!in_array($size[2],[IMAGETYPE_PNG,IMAGETYPE_JPEG],true))throw new InvalidArgumentException('Für das Logo sind PNG und JPEG erlaubt.');
        return 'data:'.$size['mime'].';base64,'.base64_encode(file_get_contents($file));
    }
    public static function html(array $b, array $invoice, ?array $templates=null, ?array $design=null): string {
        return FWB_I18n::run(FWB_I18n::booking($b),fn()=>self::localized_html($b,$invoice,$templates,$design));
    }
    private static function localized_html(array $b,array $invoice,?array $templates,?array $design): string {
        $t=$templates??FWB_Settings::templates();$d=$design??self::pdf_design();$v=self::variables($b,$invoice);$q=$b['data']['quote'];
        $items='<table><thead><tr><th>'.esc_html(FWB_I18n::t("Leistung")).'</th><th>'.esc_html(FWB_I18n::t("Betrag")).'</th></tr></thead><tbody>';
        foreach (['base'=>FWB_I18n::t('Unterkunft ({nights} Nächte)',['{nights}'=>(string)$q['nights']]),'supplement'=>FWB_I18n::t("Aufschlag für zusätzliche Gäste"),'cleaning'=>FWB_I18n::t("Endreinigung")] as $key=>$label) {
            if ($key==='base' || $q[$key]>0) { $items.='<tr><td>'.esc_html($label).'</td><td>'.esc_html(FWB_Domain::money($q[$key])).'</td></tr>'; }
        }
        $items.='</tbody></table>';
        $body=self::safe_html(self::render(self::safe_html($t['pdf_body']),$v,true));
        $body=str_replace('{items}',$items,$body);
        $font=['sans'=>'DejaVu Sans','serif'=>'DejaVu Serif','mono'=>'DejaVu Sans Mono'][$d['font']];
        $top=(float)$d['top']+(float)$d['header_height'];$bottom=(float)$d['bottom']+(float)$d['footer_height'];
        $head=(float)$d['header_height'];$foot=(float)$d['footer_height'];
        $css='@page{margin:'.$top.'mm '.$d['margin'].'mm '.$bottom.'mm}body{font-family:"'.$font.'";font-size:'.$d['font_size'].'pt;color:'.$d['text_color'].';line-height:1.5;text-align:'.$d['alignment'].'}header{position:fixed;top:-'.$head.'mm;left:0;right:0;height:'.max(1,$head-3).'mm;border-bottom:1pt solid '.$d['accent'].'}footer{position:fixed;bottom:-'.$foot.'mm;left:0;right:0;height:'.max(1,$foot-3).'mm;border-top:1pt solid '.$d['table_border'].';font-size:8pt}h1,h2,h3{color:'.$d['accent'].'}h1{font-size:22pt}h2{font-size:14pt}table{width:100%;border-collapse:collapse;margin:12pt 0}th,td{border-bottom:1px solid '.$d['table_border'].';padding:8pt 5pt;text-align:left}th{background:'.$d['table_background'].';color:'.$d['table_color'].'}td:last-child,th:last-child{text-align:right}tr{page-break-inside:avoid}p{orphans:3;widows:3}header p,footer p{margin:0 0 4pt}header{font-size:9pt;line-height:1.35}';
        $css.='header{padding-right:'.(!empty($d['logo_id'])?((float)$d['logo_width']+6):0).'mm}';
        $logo=!empty($d['logo_id'])?'<img alt="" src="'.self::logo((int)$d['logo_id']).'" style="position:absolute;right:0;top:0;width:'.$d['logo_width'].'mm;max-height:'.max(5,$head-6).'mm">':'';
        return '<!doctype html><html lang="'.esc_attr(str_replace('_','-',FWB_I18n::locale())).'"><head><meta charset="utf-8"><meta name="fwb-page-numbers" content="'.(!empty($d['page_numbers'])?'1':'0').'"><style>'.$css.'</style></head><body><header>'.$logo.self::safe_html(self::render(self::safe_html($t['pdf_header']),$v,true)).'</header><footer>'.self::safe_html(self::render(self::safe_html($t['pdf_footer']),$v,true)).'</footer>'.$body.'</body></html>';
    }
    public static function email_html(string $body,array $variables,?string $signature=null): string {
        return '<!doctype html><html lang="'.esc_attr(str_replace('_','-',FWB_I18n::locale())).'"><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;font-size:16px;line-height:1.6;color:#24362e">'.self::safe_html(self::render(self::safe_html($body),$variables,true)).self::signature($variables,false,$signature).'</body></html>';
    }
    public static function signature(array $variables,bool $plain=false,?string $content=null): string {
        $html=self::safe_html(self::render(self::safe_html($content??FWB_I18n::signature()),$variables,true));
        if(trim(self::plain($html))==='')return '';
        return $plain?"\n\n".self::plain($html):'<div class="fwb-mail-signature" style="margin-top:24px">'.$html.'</div>';
    }
    public static function plain(string $html): string {
        $html=preg_replace('/<(br\s*\/?|\/p|\/div|\/li|\/h[1-6])>/i',"\n",$html);
        return trim(html_entity_decode(wp_strip_all_tags($html),ENT_QUOTES|ENT_HTML5,'UTF-8'));
    }
    public static function send_html(string $to,string $subject,string $html,array $attachments=[]): bool {
        $plain=self::plain($html);
        $hook=static function($mailer) use($html,$plain){ if($mailer->Body===$html)$mailer->AltBody=$plain; };
        add_action('phpmailer_init',$hook);
        try{return wp_mail($to,$subject,$html,['Content-Type: text/html; charset=UTF-8'],$attachments);}
        finally{remove_action('phpmailer_init',$hook);}
    }
    public static function pdf(string $html): string {
        $loader=FWB_DIR . 'lib/dompdf/autoload.inc.php';
        if (!file_exists($loader)) { throw new RuntimeException('Die PDF-Bibliothek fehlt. Bitte die vollständige Plugin-ZIP installieren.'); }
        require_once $loader;
        $options=new \Dompdf\Options();
        $options->set('isRemoteEnabled',false); $options->set('isPhpEnabled',false); $options->set('isJavascriptEnabled',false);
        $options->set('chroot',FWB_DIR . 'lib/dompdf');
        $options->set('tempDir',get_temp_dir());
        $dompdf=new \Dompdf\Dompdf($options); $dompdf->setPaper('A4'); $dompdf->loadHtml($html,'UTF-8'); $dompdf->render();
        $canvas=$dompdf->getCanvas(); $font=$dompdf->getFontMetrics()->getFont('DejaVu Sans','normal');
        if (!str_contains($html,'name="fwb-page-numbers" content="0"'))$canvas->page_text(480,820,'{PAGE_NUM} / {PAGE_COUNT}',$font,8,[0.4,0.4,0.4]);
        return $dompdf->output();
    }
    public static function issue(int $id): array {
        global $wpdb;
        return FWB_Store::locked(static function () use ($id,$wpdb) {
            $existing=FWB_Store::invoice($id); if ($existing) { return $existing; }
            $b=FWB_Store::get($id); $s=FWB_Settings::get();
            if ($b['status']!=='confirmed') { throw new InvalidArgumentException('Rechnungen können nur für bestätigte Buchungen erstellt werden.'); }
            if(empty($b['data']['fields']['name'])||empty($b['data']['fields']['address']))throw new InvalidArgumentException('Bitte Name und Rechnungsadresse des Gastes in den Buchungsdetails ergänzen.');
            if (!$s['seller'] || !$s['tax_id'] || !$s['invoice_note']) { throw new InvalidArgumentException('Bitte zuerst Rechnungssteller, Steuerkennung und Steuer-/Rechnungshinweis in den Einstellungen ergänzen.'); }
            $seq=(int)get_option('fwb_invoice_seq',0)+1;
            $number=$s['invoice_prefix'] . '-' . current_time('Y') . '-' . str_pad((string)$seq,6,'0',STR_PAD_LEFT);
            $html=self::html($b,['number'=>$number,'date'=>current_time('d.m.Y')]);
            $pdf=self::pdf($html);
            if (!$wpdb->insert(FWB_Store::table('invoices'),['booking_id'=>$id,'number'=>$number,'html'=>$html,'pdf'=>$pdf,'created_at'=>current_time('mysql',true)])) { throw new RuntimeException('Rechnung konnte nicht gespeichert werden.'); }
            update_option('fwb_invoice_seq',$seq,false);
            FWB_Store::event($id,'invoice',$number . ' erstellt.'); return FWB_Store::invoice($id);
        });
    }
    public static function mail(int $id, string $kind): bool {
        $b=FWB_Store::get($id);return FWB_I18n::run(FWB_I18n::booking($b),fn()=>self::localized_mail($id,$kind));
    }
    private static function localized_mail(int $id,string $kind): bool {
        $b=FWB_Store::get($id); $t=FWB_Settings::templates(); $file=null;
        if (!isset($t[$kind . '_body']) || !is_email($b['email'])) { return false; }
        try {
            $invoice=[];
            if ($kind==='invoice') {
                $i=FWB_Store::invoice($id); if (!$i) { throw new RuntimeException('Rechnung fehlt.'); }
                $invoice=['number'=>$i['number'],'date'=>substr($i['created_at'],0,10)];
                $file=tempnam(get_temp_dir(),'fwb-');
                if (!$file || file_put_contents($file,$i['pdf'])===false) { throw new RuntimeException('PDF-Anhang konnte nicht erzeugt werden.'); }
                // PHPMailer derives the attachment's display name from this temporary path.
                $named=$file . '-' . sanitize_file_name($i['number']) . '.pdf';
                if (!rename($file,$named)) { throw new RuntimeException('PDF-Anhang konnte nicht benannt werden.'); } $file=$named;
            }
            $v=self::variables($b,$invoice);
            $subject=sanitize_text_field(self::render($t[$kind . '_subject'],$v));
            $formats=FWB_I18n::formats();
            $ok=($formats[$kind]??'text')==='html'
                ?self::send_html($b['email'],$subject,self::email_html($t[$kind.'_body'],$v),$file?[$file]:[])
                :wp_mail($b['email'],$subject,self::render($t[$kind.'_body'],$v).self::signature($v,true),['Content-Type: text/plain; charset=UTF-8'],$file?[$file]:[]);
            FWB_Store::event($id,'mail_' . ($ok?'accepted':'failed'),$kind . ': ' . ($ok?'An WordPress-Mailtransport übergeben (keine Zustellbestätigung).':'Versand fehlgeschlagen.'));
            return $ok;
        } catch (Throwable $e) { FWB_Store::event($id,'mail_failed',$kind . ': ' . $e->getMessage()); return false; }
        finally { if ($file && file_exists($file)) { unlink($file); } }
    }
    public static function notify(int $id): void {
        $b=FWB_Store::get($id); $s=FWB_Settings::get();
        $ok=wp_mail($s['admin_email'],'Neue Buchungsanfrage ' . $b['reference'],"Neue Anfrage von " . $b['name'] . "\n" . $b['arrival'] . ' bis ' . $b['departure'] . "\n\n" . admin_url('admin.php?page=fwb-bookings&booking=' . $id).self::signature(self::variables($b),true));
        FWB_Store::event($id,$ok?'mail_accepted':'mail_failed','Benachrichtigung an Gastgeber.');
    }
}
