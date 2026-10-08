<?php
defined('ABSPATH') || exit;
final class FWB_Admin {
    public static function boot(): void {
        add_action('admin_menu',static function () {
            add_menu_page('Ferienwohnung','Ferienwohnung','manage_options','fwb',[self::class,'page'],'dashicons-calendar-alt',26);
            foreach(self::sections() as $key=>$label)add_submenu_page('fwb',$label,$label,'manage_options',$key==='dashboard'?'fwb':'fwb-'.$key,[self::class,'page']);
        });
        add_action('wp_dashboard_setup',static function(){
            if(!current_user_can('manage_options'))return;
            wp_add_dashboard_widget('fwb_summary','Ferienwohnung · Buchungen',[self::class,'dashboard_summary']);
            wp_add_dashboard_widget('fwb_next','Ferienwohnung · Nächste Anreise',[self::class,'dashboard_next']);
        });
        add_action('admin_post_fwb_action',[self::class,'action']);
        add_action('admin_enqueue_scripts',static function ($hook) {
            if (!in_array(sanitize_key($_GET['page']??''),array_merge(['fwb'],array_map(static fn($key)=>'fwb-'.$key,array_keys(self::sections()))),true)) { return; }
            wp_enqueue_style('fwb-admin',FWB_URL . 'assets/admin.css',[],FWB_VERSION);
            wp_enqueue_style('fwb-front',FWB_URL . 'assets/frontend.css',[],FWB_VERSION);
            wp_enqueue_editor(); wp_enqueue_media();
            wp_enqueue_script('fwb-designer',FWB_URL.'assets/designer.js',['jquery'],FWB_VERSION,true);
            wp_localize_script('fwb-designer','FWBDesigner',FWB_Designer::config());
            wp_enqueue_script('fwb-admin',FWB_URL . 'assets/admin.js',['fwb-designer'],FWB_VERSION,true);
            wp_enqueue_script('fwb-front',FWB_URL . 'assets/frontend.js',[],FWB_VERSION,true);
        });
    }
    public static function sections(): array { return ['dashboard'=>'Dashboard','bookings'=>'Buchungen','calendar'=>'Kalender','form'=>'Formularbaukasten','templates'=>'Vorlagen','design'=>'Design','settings'=>'Einstellungen']; }
    public static function url(string $section='dashboard'): string { return admin_url('admin.php?page='.($section==='dashboard'?'fwb':'fwb-'.$section)); }
    private static function section(): string {
        $legacy=sanitize_key($_GET['tab']??'');if(isset(self::sections()[$legacy]))return $legacy;
        $page=sanitize_key($_GET['page']??'fwb');$section=str_replace('fwb-','',$page);
        if(isset(self::sections()[$section]))return $section;
        return isset($_GET['booking'])||isset($_GET['status'])||isset($_GET['paged'])?'bookings':'dashboard';
    }
    private static function form(string $op, int $id=0): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('fwb_action');
        echo '<input type="hidden" name="fwb_lang" value="'.esc_attr(FWB_I18n::current()).'">';
        echo '<input type="hidden" name="action" value="fwb_action"><input type="hidden" name="op" value="' . esc_attr($op) . '"><input type="hidden" name="id" value="' . $id . '">';
    }
    public static function action(): void {
        if (!current_user_can('manage_options')) { wp_die('Keine Berechtigung.','',['response'=>403]); }
        check_admin_referer('fwb_action');
        global $wpdb;
        $p=wp_unslash($_POST); $op=sanitize_key($p['op'] ?? ''); $id=absint($p['id'] ?? 0); $tab='bookings'; $notice='Gespeichert.';
        try {
            FWB_I18n::set(FWB_I18n::validate($p['fwb_lang']??FWB_I18n::source()));
            switch ($op) {
                case 'settings': FWB_Settings::save($p); $tab='settings'; break;
                case 'layout': $tab='form'; FWB_Layout::save($p['layout_json'] ?? ''); break;
                case 'templates':
                    if(FWB_I18n::foreign())throw new InvalidArgumentException('Übersetzungen bitte im Vorlagen-Designer speichern.');
                    $t=FWB_Settings::templates();
                    foreach ($t as $key=>$value) {
                        $raw=(string)($p[$key] ?? '');
                        if (strlen($raw)>30000) { throw new InvalidArgumentException('Vorlage zu lang.'); }
                        $t[$key]=str_starts_with($key,'pdf_') ? FWB_Documents::safe_html($raw) : sanitize_textarea_field($raw);
                    }
                    update_option('fwb_templates',$t,false); $tab='templates'; break;
                case 'guests': FWB_Management::guests($id,$p);break;
                case 'signature':
                    if(FWB_I18n::foreign())throw new InvalidArgumentException('Übersetzungen bitte im Vorlagen-Designer speichern.');
                    $raw=(string)($p['signature']??'');if(strlen($raw)>30000)throw new InvalidArgumentException('Signatur zu lang.');
                    update_option('fwb_mail_signature',FWB_Documents::safe_html($raw),false);$tab='templates';break;
                case 'custom_css':
                    $settings=FWB_Settings::get();$settings['custom_css']=FWB_Settings::sanitize_css((string)($p['custom_css']??''));
                    update_option('fwb_settings',$settings,false);$tab='design';break;
                case 'manual':
                    $tab='calendar';$p['status']='confirmed';$result=FWB_Management::create($p);$notice=$result['message'];
                    delete_transient('fwb_manual_'.get_current_user_id());break;
                case 'booking_action':
                    $notice=FWB_Management::apply($id,sanitize_key($p['booking_action']??''),!empty($p['send_mail']));break;
                case 'bulk':
                    if(!is_array($p['booking_ids']??null))throw new InvalidArgumentException('Bitte Buchungen auswählen.');
                    $results=FWB_Management::bulk($p['booking_ids'],sanitize_key($p['bulk_action']??''),!empty($p['send_mail']));
                    $success=count(array_filter($results,static fn($r)=>$r['ok']));
                    $notice=$success.' von '.count($results).' Aktionen ausgeführt. '.implode(' | ',array_map(static fn($r)=>'#'.$r['id'].': '.$r['message'],$results));
                    $id=0;break;
                case 'status':
                    $notice=FWB_Management::change($id,sanitize_key($p['status']??''),isset($p['mail_choice'])?!empty($p['send_mail']):(bool)FWB_Settings::get()['auto_mail']);
                    break;
                case 'block':
                    $tab='calendar';
                    $a=FWB_Domain::date((string)($p['arrival'] ?? '')); $d=FWB_Domain::date((string)($p['departure'] ?? ''));
                    if ($a >= $d || $a->diff($d)->days>1095) { throw new InvalidArgumentException('Sperrzeit muss zwischen 1 und 1095 Nächten liegen.'); }
                    FWB_Store::insert(['arrival'=>$a->format('Y-m-d'),'departure'=>$d->format('Y-m-d')],['name'=>(sanitize_text_field($p['reason'] ?? '') ?: 'Sperrzeit')],'blocked'); break;
                case 'paid':
                    $b=FWB_Store::get($id); $paid=FWB_Domain::cents($p['paid'] ?? '0');
                    if (!isset($b['data']['quote']['total'])) { throw new InvalidArgumentException('Sperrzeiten haben keinen Zahlungsstatus.'); }
                    if ($wpdb->update(FWB_Store::table(),['paid'=>$paid],['id'=>$id])===false) { throw new RuntimeException('Zahlung konnte nicht gespeichert werden.'); }
                    FWB_Store::event($id,'payment','Erfasster Zahlungseingang: ' . FWB_Domain::money($paid)); break;
                case 'invoice': FWB_Documents::issue($id); break;
                case 'mail':
                    $b=FWB_Store::get($id); $kind=sanitize_key($p['kind'] ?? '');
                    $allowed=['pending'=>'request','confirmed'=>'confirmed','cancelled'=>'cancelled','rejected'=>'rejected'];
                    if ($kind!=='invoice' && ($allowed[$b['status']] ?? '')!==$kind) { throw new InvalidArgumentException('E-Mail passt nicht zum aktuellen Status.'); }
                    if (!FWB_Documents::mail($id,$kind)) { throw new RuntimeException('E-Mail konnte nicht versendet werden. Siehe Versandprotokoll.'); } break;
                case 'download':
                    $i=FWB_Store::invoice($id); if (!$i) { throw new InvalidArgumentException('Rechnung nicht gefunden.'); }
                    nocache_headers(); header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="' . sanitize_file_name($i['number']) . '.pdf"'); echo $i['pdf']; exit;
                case 'preview':
                    $b=FWB_Store::get($id);
                    if ($b['status']==='blocked') { throw new InvalidArgumentException('Keine Rechnung für Sperrzeiten.'); }
                    $pdf=FWB_Documents::pdf(FWB_Documents::html($b,['number'=>'ENTWURF - NICHT AUSGESTELLT']));
                    nocache_headers(); header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="rechnungsentwurf.pdf"'); echo $pdf; exit;
                default: throw new InvalidArgumentException('Unbekannte Aktion.');
            }
        } catch (Throwable $e) {
            $notice=$e->getMessage();
            if($op==='manual'){
                $draft=[];foreach(['arrival','departure','guests','taxable_guests','name','email','address','phone','message','status','send_mail','language'] as $key)if(isset($p[$key])&&is_scalar($p[$key]))$draft[$key]=mb_substr(sanitize_textarea_field((string)$p[$key]),0,4000);
                set_transient('fwb_manual_'.get_current_user_id(),$draft,30*MINUTE_IN_SECONDS);
            }
        }
        set_transient('fwb_notice_' . get_current_user_id(),$notice,60);
        $return=self::url($tab).($id&&empty($p['from_table'])?'&booking='.$id:'');
        if(in_array($op,['bulk','booking_action'],true)&&!empty($p['from_table'])){
            $filter=sanitize_key($p['return_status']??'');if(isset(self::labels()[$filter]))$return.='&status='.$filter;
            $return=add_query_arg('search',sanitize_text_field(wp_unslash($p['return_search']??'')),$return);
            $return.='&paged='.max(1,absint($p['return_page']??1));
        }
        $return=add_query_arg('lang',FWB_I18n::current(),$return);
        wp_safe_redirect($return); exit;
    }
    public static function page(): void {
        if (!current_user_can('manage_options')) { return; }
        $tab=self::section();
        echo '<div class="wrap fwb-admin"><div class="fwb-admin-head"><div><span class="fwb-eyebrow">DEINE GASTGEBER-ZENTRALE</span><h1>Ferienwohnung</h1><p>Anfragen, Aufenthalte und Rechnungen an einem Ort.</p></div><span class="fwb-version">Version ' . FWB_VERSION . '</span></div>';
        $notice=get_transient('fwb_notice_' . get_current_user_id());
        if ($notice) { echo '<div class="notice notice-info"><p>' . esc_html($notice) . '</p></div>'; delete_transient('fwb_notice_' . get_current_user_id()); }
        $s=FWB_Settings::get();
        if (!$s['night_price'] || !$s['privacy_url']) { echo '<div class="notice notice-warning"><p>Einrichtung abschließen: Nachtpreis und Datenschutz-Link unter Einstellungen hinterlegen. Bis dahin werden keine Anfragen angenommen.</p></div>'; }
        try {
            if(in_array($tab,['form','templates'],true))FWB_I18n::bar($tab);
            if ($tab==='dashboard') { self::dashboard(); }
            elseif ($tab==='design') { self::design(); }
            elseif ($tab==='settings') { self::settings(); }
            elseif ($tab==='form') { self::builder(); }
            elseif ($tab==='templates') { self::templates(); }
            elseif ($tab==='calendar') { self::calendar(); }
            elseif (!empty($_GET['booking'])) { self::detail(absint($_GET['booking'])); }
            else { self::bookings(); }
        } catch (Throwable $e) { echo '<p>' . esc_html($e->getMessage()) . '</p>'; }
        echo '</div>';
    }
    public static function labels(): array { return ['pending'=>'Anfrage','confirmed'=>'Bestätigt','cancelled'=>'Storniert','rejected'=>'Abgelehnt','blocked'=>'Gesperrt','trash'=>'Papierkorb']; }
    private static function display_date(string $value): string {
        return preg_replace_callback('/\b(\d{4})-(\d{2})-(\d{2})\b/',static fn($m)=>$m[3].'.'.$m[2].'.'.$m[1],$value);
    }
    private static function action_icon(string $action): string {
        if($action==='restore')return '<span class="fwb-recycle-icon" aria-hidden="true">♻</span>';
        $icons=['edit'=>'edit','confirmed'=>'yes-alt','cancelled'=>'undo','rejected'=>'dismiss','trash'=>'trash','unblock'=>'unlock','email'=>'email'];
        return '<span class="dashicons dashicons-'.esc_attr($icons[$action]??'marker').'" aria-hidden="true"></span>';
    }
    private static function bookings(): void {
        global $wpdb; $t=FWB_Store::table(); $page=max(1,absint($_GET['paged'] ?? 1)); $filter=sanitize_key($_GET['status'] ?? '');
        if(!isset(self::labels()[$filter]))$filter='';
        $search=sanitize_text_field(wp_unslash($_GET['search']??''));
        $where=$filter!==''?$wpdb->prepare('WHERE status=%s',$filter):"WHERE status <> 'trash'";
        if($search!==''){
            $like='%'.$wpdb->esc_like($search).'%';
            $date=null;
            if(preg_match('/^\d{2}\.\d{2}\.\d{4}$/',$search)){
                $parsed=DateTimeImmutable::createFromFormat('!d.m.Y',$search);
                if($parsed&&$parsed->format('d.m.Y')===$search)$date=$parsed->format('Y-m-d');
            }elseif(preg_match('/^\d{4}-\d{2}-\d{2}$/',$search))$date=$search;
            $dateLike='%'.$wpdb->esc_like($date??$search).'%';
            $where.=$wpdb->prepare(' AND (name LIKE %s OR reference LIKE %s OR arrival LIKE %s OR departure LIKE %s)',$like,$like,$dateLike,$dateLike);
        }
        $rows=$wpdb->get_results($wpdb->prepare("SELECT id,reference,status,arrival,departure,name,email,paid,payload FROM $t $where ORDER BY created_at DESC,id DESC LIMIT 30 OFFSET %d",($page-1)*30),ARRAY_A);
        $count=(int)$wpdb->get_var("SELECT COUNT(*) FROM $t $where");
        echo '<div class="fwb-card"><h2>Buchungsübersicht</h2><form method="get" action="'.esc_url(admin_url('admin.php')).'" class="fwb-booking-filters">';
        echo '<input type="hidden" name="page" value="fwb-bookings"><input type="hidden" name="tab" value="bookings"><input type="hidden" name="lang" value="'.esc_attr(FWB_I18n::current()).'">';
        echo '<label for="fwb-status-filter">Status<select id="fwb-status-filter" name="status"><option value="">Alle (ohne Papierkorb)</option>';
        foreach(self::labels() as $key=>$label)echo '<option value="'.esc_attr($key).'" '.selected($filter,$key,false).'>'.esc_html($label).'</option>';
        echo '</select></label><label for="fwb-booking-search">Suche<input id="fwb-booking-search" type="search" name="search" value="'.esc_attr($search).'" placeholder="Name, Buchungsnummer oder TT.MM.JJJJ"></label><button class="button button-primary">Filtern</button><a class="button" href="'.esc_url(self::url('bookings')).'">Zurücksetzen</a></form>';
        echo '<p class="fwb-filter-summary" role="status">Aktiver Filter: <strong>'.esc_html($filter!==''?self::labels()[$filter]:'Alle (ohne Papierkorb)').'</strong>'.($search!==''?' · Suche: <strong>'.esc_html($search).'</strong>':'').' · '.$count.' Treffer</p>';
        echo '<form id="fwb-bulk" method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="fwb-bulk-bar">';wp_nonce_field('fwb_action');
        echo '<input type="hidden" name="fwb_lang" value="'.esc_attr(FWB_I18n::current()).'">';
        echo '<input type="hidden" name="action" value="fwb_action"><input type="hidden" name="op" value="bulk"><input type="hidden" name="from_table" value="1"><input type="hidden" name="return_search" value="'.esc_attr($search).'"><input type="hidden" name="return_status" value="'.esc_attr($filter).'"><input type="hidden" name="return_page" value="'.$page.'"><label class="screen-reader-text" for="fwb-bulk-action">Sammelaktion</label><select name="bulk_action" id="fwb-bulk-action" required><option value="">Sammelaktion wählen</option>';
        foreach(['confirmed'=>'Annehmen','cancelled'=>'Stornieren','rejected'=>'Ablehnen','unblock'=>'Entsperren','trash'=>'Löschen (Papierkorb)','restore'=>'Wiederherstellen'] as $key=>$label)echo '<option value="'.$key.'">'.$label.'</option>';
        echo '</select><label><input type="checkbox" name="send_mail" value="1"> Status-Mail senden</label><button class="button" data-confirm="Sammelaktion ausführen? Löschen verschiebt in den Papierkorb und gibt belegte Zeiträume frei. Löschen, Wiederherstellen und Entsperren versenden keine Mail.">Anwenden</button><span id="fwb-selection-count" role="status">0 ausgewählt</span></form>';
        echo '<div class="fwb-action-legend" aria-label="Legende der Buchungsaktionen"><strong>Aktionen:</strong>';
        foreach(['edit'=>'Bearbeiten','confirmed'=>'Annehmen','cancelled'=>'Stornieren','rejected'=>'Ablehnen','trash'=>'Papierkorb','unblock'=>'Entsperren','restore'=>'Wiederherstellen','email'=>'Status-Mail senden (Häkchen)'] as $action=>$label)echo '<span><span class="fwb-legend-icon fwb-action-'.esc_attr($action).'">'.self::action_icon($action).'</span> '.esc_html($label).'</span>';
        echo '</div>';
        echo '<div class="fwb-table-scroll"><table class="widefat striped fwb-booking-table"><thead><tr><th class="fwb-select-cell"><input type="checkbox" id="fwb-select-all" aria-label="Alle Buchungen dieser Seite auswählen"></th><th>Buchung / Gast</th><th>Aufenthalt</th><th>Status</th><th>Gesamtpreis</th><th>Eingegangen</th><th>Aktionen</th></tr></thead><tbody>';
        foreach ($rows as $b) {
            $data=json_decode($b['payload'],true);
            echo '<tr><td class="fwb-select-cell"><input type="checkbox" name="booking_ids[]" value="'.(int)$b['id'].'" form="fwb-bulk" class="fwb-booking-select" aria-label="'.esc_attr('Buchung '.$b['reference'].' auswählen').'"></td><td><a href="' . esc_url(self::url('bookings').'&booking='.$b['id']) . '"><strong>' . esc_html($b['reference']) . '</strong></a><br>' . esc_html($b['name']) . '</td><td>' . esc_html(self::display_date($b['arrival']) . ' → ' . self::display_date($b['departure'])) . '</td><td><span class="fwb-badge ' . esc_attr($b['status']) . '">' . esc_html(self::labels()[$b['status']]) . '</span></td><td>' . (isset($data['quote']['total'])?esc_html(FWB_Domain::money($data['quote']['total'])):'—') . '</td><td>' . esc_html(FWB_Domain::money((int)$b['paid'])) . '</td><td>';
            self::row_actions($b);echo '</td></tr>';
        }
        if (!$rows) { echo '<tr><td colspan="7">Noch keine Einträge vorhanden. Binde <code>[ferienwohnung_buchung]</code> auf einer WordPress-Seite ein.</td></tr>'; }
        echo '</tbody></table></div><p>' . esc_html((string)$count) . ' Einträge · Seite ' . $page . '</p>';
        if ($page>1) { echo '<a class="button" href="' . esc_url(add_query_arg('paged',$page-1)) . '">Zurück</a> '; }
        if ($page*30<$count) { echo '<a class="button" href="' . esc_url(add_query_arg('paged',$page+1)) . '">Weiter</a>'; }
        echo '</div>';
    }
    private static function block_form(): void {
        echo '<div class="fwb-card"><h2>Zeitraum manuell sperren</h2><p>Für Eigennutzung, Wartung oder externe Buchungen. Ende entspricht dem Abreisetag; danach ist eine neue Anreise möglich.</p>';
        self::form('block');
        echo '<div class="fwb-inline"><label>Beginn <input type="date" name="arrival" required></label><label>Ende <input type="date" name="departure" required></label><label>Interner Grund <input name="reason" required maxlength="200"></label><button class="button button-primary">Sperren</button></div></form></div>';
    }
    private static function detail(int $id): void {
        global $wpdb; $b=FWB_Store::get($id); $q=$b['data']['quote'];
        echo '<div class="fwb-card"><a href="' . esc_url(self::url('bookings')) . '">← Übersicht</a><h2>' . esc_html($b['reference']) . ' · ' . esc_html($b['name']) . '</h2><p><strong>' . esc_html(self::labels()[$b['status']]) . '</strong> · ' . esc_html(self::display_date($b['arrival']) . ' bis ' . self::display_date($b['departure'])) . '</p>';
        foreach ($b['data']['fields'] as $key=>$value) { echo '<p><strong>' . esc_html($key) . '</strong><br>' . nl2br(esc_html((string)$value)) . '</p>'; }
        $language=FWB_I18n::booking($b);echo '<p><strong>Buchungssprache:</strong> '.esc_html(FWB_I18n::languages()[$language]['name']??$language).'</p>';
        if (isset($q['total'])) {
            echo '<div class="fwb-metrics">';
            foreach (['total'=>'Gesamtpreis','deposit'=>'Anzahlung','balance'=>'Restbetrag','local_tax'=>'Ortstaxe vor Ort'] as $key=>$label) { echo '<div><span>' . $label . '</span><strong>' . esc_html(FWB_Domain::money($q[$key])) . '</strong></div>'; }
            echo '</div><p>Restzahlung fällig: ' . esc_html(self::display_date($q['due_date'])) . ' · Kostenfrei stornierbar bis: ' . esc_html(self::display_date($q['cancel_date'])) . '</p>';
            if ($b['status']==='cancelled') { echo '<p class="fwb-warning">Eine Stornierung erzeugt keine Rechnungskorrektur oder Erstattung. Bereits ausgestellte Rechnungen bleiben erhalten; eine erforderliche Gutschrift bitte separat bearbeiten.</p>'; }
        }
        echo '<div class="fwb-inline">';self::row_actions($b,false);
        echo '</div></div>';
        if (isset($q['total'])) {
            echo '<div class="fwb-card"><h2>Gästedaten bearbeiten</h2><p>Keine automatische Mail. Bereits ausgestellte Rechnungen bleiben unverändert.</p>';
            self::form('guests',$id);echo '<div class="fwb-settings-grid">';
            foreach(['name'=>'Vor- und Nachname','email'=>'E-Mail-Adresse','phone'=>'Telefon'] as $key=>$label)echo '<label>'.$label.'<input name="'.$key.'" type="'.($key==='email'?'email':'text').'" maxlength="200" value="'.esc_attr((string)($b['data']['fields'][$key]??'')).'"'.($key==='name'?' required':'').'></label>';
            echo '</div>';
            foreach(['address'=>'Rechnungsadresse','message'=>'Nachricht / Absprachen'] as $key=>$label)echo '<p><label>'.$label.'<textarea class="large-text" rows="3" name="'.$key.'" maxlength="4000">'.esc_textarea((string)($b['data']['fields'][$key]??'')).'</textarea></label></p>';
            submit_button('Gästedaten speichern');echo '</form></div>';
            echo '<div class="fwb-card"><h2>Zahlungseingang</h2><p>Bereits eingegangen: ' . esc_html(FWB_Domain::money((int)$b['paid'])) . ' · Noch offen: ' . esc_html(FWB_Domain::money(max(0,$q['total']-(int)$b['paid']))) . '</p>';
            self::form('paid',$id); echo '<label>Summe aller eingegangenen Zahlungen (€) <input name="paid" value="' . esc_attr(number_format($b['paid']/100,2,'.','')) . '" required></label> <button class="button">Speichern</button></form></div>';
            echo '<div class="fwb-card"><h2>Rechnung und E-Mails</h2><div class="fwb-inline">';
            self::form('preview',$id); echo '<button class="button">PDF-Entwurf ansehen</button></form>';
            $i=FWB_Store::invoice($id);
            if ($i) { self::form('download',$id); echo '<button class="button">' . esc_html($i['number']) . ' herunterladen</button></form>'; }
            elseif ($b['status']==='confirmed') { self::form('invoice',$id); echo '<button class="button button-primary" data-confirm="Rechnung jetzt verbindlich ausstellen? Nummer und Dokument werden fest gespeichert.">Rechnung ausstellen</button></form>'; }
            $kind=['pending'=>'request','confirmed'=>'confirmed','cancelled'=>'cancelled','rejected'=>'rejected'][$b['status']] ?? '';
            if ($kind) { self::form('mail',$id); echo '<input type="hidden" name="kind" value="' . esc_attr($kind) . '"><button class="button" data-confirm="Status-E-Mail erneut versenden?">Status-E-Mail senden</button></form>'; }
            if ($i) { self::form('mail',$id); echo '<input type="hidden" name="kind" value="invoice"><button class="button" data-confirm="Rechnung als PDF-Anhang versenden?">Rechnung per E-Mail senden</button></form>'; }
            echo '</div></div>';
        }
        $t=FWB_Store::table('events'); $events=$wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE booking_id=%d ORDER BY id DESC LIMIT 100",$id),ARRAY_A);
        echo '<div class="fwb-card"><h2>Verlauf und Versandprotokoll</h2><p>„Übergeben“ bedeutet, dass WordPress die E-Mail angenommen hat; dies bestätigt nicht die Zustellung.</p><ul>';
        foreach ($events as $e) { echo '<li><code>' . esc_html(self::display_date($e['created_at'])) . ' UTC</code> · ' . esc_html($e['detail']) . '</li>'; }
        echo '</ul></div>';
    }
    private static function settings(): void {
        $s=FWB_Settings::get(); echo '<div class="fwb-card"><h2>Preise und Buchungsregeln</h2><p>Preise in Euro für die gesamte Wohnung. Mindestaufenthalt wird als Anzahl Nächte gerechnet.</p>'; self::form('settings');
        $groups=[
            'Unterkunft'=>['property_name'=>'Name der Unterkunft','night_price'=>'Grundpreis pro Nacht (€)','max_guests'=>'Maximale Gästezahl','included_guests'=>'Im Grundpreis enthaltene Gäste','extra_price'=>'Aufschlag (€)','cleaning_price'=>'Endreinigung einmalig (€)'],
            'Fristen und Ortstaxe'=>['min_nights'=>'Mindestaufenthalt (Nächte)','deposit_percent'=>'Anzahlung (%)','balance_days'=>'Restzahlung: Tage vor Anreise','cancel_days'=>'Kostenfreie Stornierung: Tage vor Anreise','local_tax'=>'Ortstaxe pro Person und Nacht (€)','checkin'=>'Anreise ab (HH:MM)','checkout'=>'Abreise bis (HH:MM)'],
            'Kommunikation und Rechnungen'=>['admin_email'=>'E-Mail des Gastgebers','privacy_url'=>'Link zur Datenschutzerklärung','invoice_prefix'=>'Rechnungspräfix','tax_id'=>'Steuerkennung / USt-ID','iban'=>'Kontoinhaber / IBAN / BIC','vat_percent'=>'Im Unterkunftspreis enthaltene Umsatzsteuer (%)'],
        ];
        foreach ($groups as $heading=>$fields) {
            echo '<h3>' . esc_html($heading) . '</h3><div class="fwb-settings-grid">';
            foreach ($fields as $key=>$label) {
                $value=in_array($key,['night_price','extra_price','cleaning_price','local_tax'],true)?number_format($s[$key]/100,2,'.',''):$s[$key];
                echo '<label>' . esc_html($label) . '<input name="' . esc_attr($key) . '" value="' . esc_attr((string)$value) . '"' . (in_array($key,['checkin','checkout'],true)?' type="time"':'') . '></label>';
            }
            echo '</div>';
        }
        echo '<h3>Aufschlag kombinieren</h3><div class="fwb-inline"><label>Zeiteinheit <select name="extra_period">';
        foreach (['night'=>'Pro Nacht','stay'=>'Pro Aufenthalt'] as $key=>$label) { echo '<option value="' . $key . '" ' . selected($s['extra_period'],$key,false) . '>' . $label . '</option>'; }
        echo '</select></label><label>Berechnungsbasis <select name="extra_unit">';
        foreach (['person'=>'Pro zusätzlicher Person','booking'=>'Pro Buchung (sobald mehr als Inklusivgäste)'] as $key=>$label) { echo '<option value="' . $key . '" ' . selected($s['extra_unit'],$key,false) . '>' . $label . '</option>'; }
        echo '</select></label></div>';
        foreach (['rules'=>'Hausregeln','tax_note'=>'Hinweise und Befreiungen zur Ortstaxe','seller'=>'Rechnungssteller: vollständiger Name und Anschrift','invoice_note'=>'Steuer-/Rechnungshinweis (bei 0 %: zutreffenden Grund ergänzen)'] as $key=>$label) { echo '<p><label>' . esc_html($label) . '<textarea name="' . esc_attr($key) . '" rows="4" class="large-text">' . esc_textarea($s[$key]) . '</textarea></label></p>'; }
        echo '<p><label><input type="checkbox" name="auto_mail" value="1" ' . checked($s['auto_mail'],1,false) . '> Automatische E-Mails bei Anfrage und Statusänderung</label></p><p><label><input type="checkbox" name="auto_invoice" value="1" ' . checked($s['auto_invoice'],1,false) . '> Bei Bestätigung automatisch Rechnung ausstellen (und bei aktiven automatischen E-Mails mitsenden)</label></p><p>Die Rechnungseinstellungen müssen zur Unterkunft und steuerlichen Situation passen. Es werden klassische PDF-Rechnungen erzeugt, keine strukturierten E-Rechnungen.</p>';
        echo '<h3>Texte und Formularaufbau</h3><p>Überschriften, „Gut zu wissen“, Texte und Felder bearbeitest du jetzt gemeinsam im <a href="' . esc_url(self::url('form')) . '">Formularbaukasten</a>.</p>';
        submit_button('Einstellungen speichern'); echo '</form></div>';
    }
    private static function builder(): void {
        echo '<div class="fwb-card"><h2>Formularbaukasten</h2>';
        if(FWB_I18n::foreign())echo '<p>Übersetzungsmodus: Texte über das Stiftsymbol bearbeiten. Neue Elemente, Reihenfolge und Stil in der Ausgangssprache ändern. Änderungen an Texten werden nur für die ausgewählte Sprache gespeichert.</p>';
        else echo '<p>Ziehe Elemente aus der Auswahl in die linke oder rechte Spalte. Öffne die Einstellungen über das Stiftsymbol. Der Griff dient nur zum Verschieben. Aufbau und Design gelten für alle Sprachen.</p><p>Alternativ kannst du Elemente per Klick hinzufügen und mit Pfeilen oder der Bereichsauswahl verschieben. Notwendige Buchungsfelder sind mit „Pflicht“ markiert und bleiben erhalten.</p>';
        self::form('layout');
        echo '<div id="fwb-studio-toolbar"></div><div class="fwb-workspace"><input type="hidden" name="layout_json" id="fwb-layout-json"><div id="fwb-builder" data-layout="' . esc_attr(wp_json_encode(FWB_Layout::get())) . '" data-catalog="' . esc_attr(wp_json_encode(FWB_Layout::catalog())) . '" data-conditions="' . esc_attr(FWB_Layout::conditions_default()) . '"></div>';
        echo '<div id="fwb-inspector" class="fwb-inspector" role="dialog" aria-modal="true" aria-labelledby="fwb-inspector-title" hidden><div class="fwb-modal-sheet"><button type="button" class="button" id="fwb-back-layout">Fertig / schließen</button><h3 id="fwb-inspector-title" tabindex="-1">Element bearbeiten</h3><div id="fwb-inspector-tabs"></div><div id="fwb-inspector-fields" class="fwb-settings-grid"></div><div id="fwb-rich-panel" hidden><p>Text mit Formatierungen, Listen und Links bearbeiten. Die Platzhalter beziehen ihre Werte weiterhin aus den Buchungseinstellungen.</p><p class="description">Platzhalter: <code>' . esc_html(implode(' ',array_map(static fn($key)=>'{'.$key.'}',array_keys(FWB_Layout::placeholders())))) . '</code></p>';
        wp_editor('', 'fwb_rich_content', ['textarea_name'=>'fwb_rich_content','textarea_rows'=>12,'editor_height'=>300,'media_buttons'=>false,'tinymce'=>['toolbar1'=>'formatselect,bold,italic,bullist,numlist,link,unlink,undo,redo','toolbar2'=>''],'quicktags'=>true]);
        echo '</div><div class="fwb-modal-footer"><p>Änderungen gelten nach „Baukasten speichern“ für die Website.</p><button type="button" class="button button-primary" id="fwb-modal-save">Baukasten speichern</button><p id="fwb-modal-status" role="status"></p></div></div></div></div><p id="fwb-builder-status" role="status" aria-live="polite"></p>';
        submit_button('Baukasten speichern');
        echo '</form></div>';
    }
    private static function design(): void {
        $s=FWB_Settings::get();
        echo '<div class="fwb-card"><h2>Design</h2><p>Leere Werte übernehmen die Theme-Gestaltung. Eigene Einstellungen an einem Element haben Vorrang.</p><div id="fwb-global-design"></div><p id="fwb-design-status" role="status"></p></div><div class="fwb-card">';
        self::form('custom_css');
        echo '<h3>Darstellung / eigenes CSS</h3><p>Das Frontend verwendet die UIkit-Klassen von YOOtheme. Eigenes CSS wird nach dem Plugin-CSS eingebunden, nicht im Backend oder in PDFs. Regeln bitte auf <code>.fwb-widget</code> bzw. <code>.fwb-calendar</code> begrenzen. Ohne <code>&lt;style&gt;</code>-Tags; leeren und speichern entfernt die Anpassungen.</p><label for="fwb-custom-css">Eigenes CSS</label><textarea id="fwb-custom-css" name="custom_css" class="large-text code" rows="10" spellcheck="false" placeholder=".fwb-widget .fwb-request { border-radius: 12px; }">' . esc_textarea($s['custom_css']) . '</textarea>';
        submit_button('Eigenes CSS speichern');echo '</form></div>';
    }
    private static function templates(): void {
        echo '<div class="fwb-card"><h2>Vorlagen-Designer</h2><p>Vorlage auswählen, Text gestalten und Platzhalter an der Cursorposition einfügen. Die Vorschau und der Testversand verändern keine Buchung.</p>';
        echo '<div id="fwb-template-designer" data-templates="'.esc_attr(wp_json_encode(FWB_Designer::templates())).'" data-tokens="'.esc_attr(wp_json_encode(FWB_Designer::tokens())).'" data-bookings="'.esc_attr(wp_json_encode(FWB_Designer::bookings())).'">';
        echo '<div id="fwb-template-toolbar"></div><div class="fwb-template-workspace"><aside id="fwb-template-list"></aside><main class="fwb-template-main"><h3 id="fwb-template-title"></h3><p id="fwb-signature-help" hidden>Diese Signatur wird an alle Plugin-Mails angehängt. Leer lassen zum Deaktivieren. Mit „Vorlagen speichern“ übernehmen.</p><label id="fwb-subject-label">Betreff<input id="fwb-template-subject" class="large-text"></label>';
        wp_editor('', 'fwb_template_content', ['textarea_name'=>'fwb_template_content','editor_height'=>400,'media_buttons'=>false,'tinymce'=>['toolbar1'=>'formatselect,bold,italic,underline,alignleft,aligncenter,alignright,bullist,numlist,link,unlink,forecolor,backcolor,removeformat,undo,redo','toolbar2'=>''],'quicktags'=>true]);
        echo '<div id="fwb-template-preview" hidden><p id="fwb-preview-subject"></p><iframe title="Vorlagenvorschau" sandbox="" style="width:100%;height:650px;background:white"></iframe></div></main><aside id="fwb-template-options"></aside></div><p id="fwb-template-status" role="status"></p></div></div>';
    }
    private static function calendar(): void {
        echo '<div class="fwb-calendar-workspace"><div class="fwb-card fwb-calendar-column"><h2>Zeitraum auswählen</h2><p>Wähle zuerst die Anreise, danach die Abreise. Der Abreisetag bleibt für eine neue Anreise frei.</p>'.FWB_Frontend::calendar_markup();
        self::form('block');
        echo '<div class="fwb-calendar-block"><input type="hidden" name="arrival"><input type="hidden" name="departure"><button class="button" id="fwb-calendar-block" disabled>Sperren</button><label class="screen-reader-text" for="fwb-block-reason">Grund (optional)</label><input id="fwb-block-reason" name="reason" maxlength="200" placeholder="Grund (optional)"></div></form></div>';
        self::manual_form();
        echo '</div>';
    }    private static function row_actions(array $b,bool $table=true): void {
        $data=$b['data']??json_decode($b['payload']??'{}',true);
        $actions=FWB_Management::actions($b['status']);
        if(!isset($data['quote']['total']))$actions=array_values(array_diff($actions,['confirmed']));
        $labels=['confirmed'=>'Annehmen','cancelled'=>'Stornieren','rejected'=>'Ablehnen','trash'=>'Löschen (Papierkorb)','unblock'=>'Entsperren','restore'=>'Wiederherstellen'];
        $icons=['confirmed'=>'yes-alt','cancelled'=>'calendar-alt','rejected'=>'no-alt','trash'=>'trash','unblock'=>'unlock','restore'=>'undo'];
        echo '<div class="fwb-icon-actions'.(!$table?' fwb-detail-actions':'').'">';
        if($table)echo '<a class="button fwb-icon-button" aria-label="Bearbeiten" title="Bearbeiten" href="'.esc_url(self::url('bookings').'&booking='.$b['id']).'"><span class="dashicons dashicons-edit" aria-hidden="true"></span></a>';
        self::form('booking_action',(int)$b['id']);
        if($table)echo '<input type="hidden" name="return_search" value="'.esc_attr(sanitize_text_field(wp_unslash($_GET['search']??''))).'"><input type="hidden" name="from_table" value="1"><input type="hidden" name="return_status" value="'.esc_attr(sanitize_key($_GET['status']??'')).'"><input type="hidden" name="return_page" value="'.max(1,absint($_GET['paged']??1)).'">';
        foreach($actions as $next){
            $confirm=$next==='trash'?'In den Papierkorb verschieben? Ein belegter Zeitraum wird freigegeben. Keine Mail; Rechnungen bleiben erhalten.':($next==='restore'?'Eintrag mit ursprünglichem Status wiederherstellen? Keine Mail.':$labels[$next].'?');
            echo '<button name="booking_action" value="'.$next.'" class="button fwb-icon-button fwb-action-'.$next.' '.($next==='confirmed'?'button-primary':'').'" title="'.$labels[$next].'" aria-label="'.$labels[$next].'"'.($next!=='confirmed'?' data-confirm="'.esc_attr($confirm).'"':'').'>'.self::action_icon($next).''.(!$table?'<span>'.$labels[$next].'</span>':'').'</button>';
        }
        if(in_array('confirmed',$actions,true)||$b['status']==='confirmed'){
            $email=$b['email']??FWB_Store::get((int)$b['id'])['email'];
            echo '<label class="fwb-icon-mail" title="Status-Mail senden (bei Bestätigung ggf. auch Rechnung). Papierkorb-Aktionen versenden keine Mail."><input type="checkbox" name="send_mail" value="1" aria-label="Status-Mail senden" '.checked((bool)FWB_Settings::get()['auto_mail']&&is_email($email),true,false).'><span class="dashicons dashicons-email" aria-hidden="true"></span>'.(!$table?'<span>Status-Mail senden</span>':'').'</label>';
        }
        echo '</form></div>';
    }
    private static function manual_form(): void {
        $s=FWB_Settings::get();$draft=(array)get_transient('fwb_manual_'.get_current_user_id());
        $value=static fn($key,$default='')=>esc_attr((string)($draft[$key]??$default));
        echo '<div class="fwb-card" id="fwb-manual"><h2>Reservierung anlegen</h2><p>Die Buchung wird sofort bestätigt. Es gelten die hinterlegten Preise, Gästegrenzen und Mindestnächte.</p>';
        self::form('manual');echo '<p><label>Buchungssprache <select name="language">';foreach(FWB_I18n::languages() as $slug=>$info)echo '<option value="'.esc_attr($slug).'" '.selected($draft['language']??FWB_I18n::current(),$slug,false).'>'.esc_html($info['name']).'</option>';echo '</select></label></p>';echo '<div class="fwb-settings-grid">';
        foreach(['arrival'=>'Anreise','departure'=>'Abreise'] as $key=>$label)echo '<label>'.$label.'<input type="date" name="'.$key.'" value="'.$value($key).'" min="'.esc_attr(current_time('Y-m-d')).'" required></label>';
        foreach(['guests'=>'Gäste gesamt','taxable_guests'=>'Davon ortstaxenpflichtig'] as $key=>$label)echo '<label>'.$label.'<input type="number" name="'.$key.'" min="'.($key==='guests'?1:0).'" max="'.(int)$s['max_guests'].'" value="'.$value($key,min(2,$s['max_guests'])).'" required></label>';
        echo '<input type="hidden" name="status" value="confirmed"></div><p><button type="button" class="button" id="fwb-manual-quote">Preis und Verfügbarkeit prüfen</button></p><p id="fwb-manual-result" role="status">Der Preis wird aus deinen Einstellungen berechnet und beim Anlegen gespeichert.</p><h3>Gästedaten</h3><div class="fwb-settings-grid">';
        foreach(['name'=>'Vor- und Nachname','email'=>'E-Mail (optional ohne Mailversand)','phone'=>'Telefon'] as $key=>$label)echo '<label>'.$label.'<input name="'.$key.'" type="'.($key==='email'?'email':($key==='phone'?'tel':'text')).'" maxlength="200" value="'.$value($key).'"'.($key==='name'?' required':'').'></label>';
        echo '</div>';
        foreach(['address'=>'Rechnungsadresse (für Rechnungen erforderlich)','message'=>'Nachricht / Absprachen (kann über {message} in Vorlagen erscheinen)'] as $key=>$label)echo '<p><label>'.$label.'<textarea name="'.$key.'" rows="3" maxlength="4000" class="large-text">'.esc_textarea((string)($draft[$key]??'')).'</textarea></label></p>';
        echo '<p><label><input type="checkbox" name="send_mail" value="1" '.checked(!empty($draft['send_mail']),true,false).'> Bestätigungs-Mail an den Gast senden'.($s['auto_invoice']?' (ggf. zusätzlich Rechnungsmail)':'').'</label></p><p>Ohne Häkchen werden für diese Reservierung keine Mails versendet. Rechnungen kannst du anschließend in den Buchungsdetails erstellen.'.($s['auto_invoice']?' Die automatische Rechnungserstellung bei Bestätigung ist aktiviert.':'').'</p>';
        submit_button('Buchung anlegen');echo '</form></div>';
    }
    public static function dashboard_summary(): void {
        if(!current_user_can('manage_options'))return;
        $o=FWB_Management::overview();
        echo '<p><strong>'.(int)$o['counts']['pending'].'</strong> offene Anfragen · <strong>'.(int)$o['counts']['confirmed'].'</strong> bestätigte Buchungen insgesamt</p><p><a href="'.esc_url(self::url('dashboard')).'">Zur Gastgeber-Übersicht</a></p>';
    }
    public static function dashboard_next(): void {
        if(!current_user_can('manage_options'))return;
        self::next_card(FWB_Management::overview()['next']);
    }
    private static function next_card(?array $b): void {
        if(!$b){echo '<p>Keine kommende bestätigte Anreise vorhanden.</p>';return;}
        echo '<p class="fwb-next-date">'.esc_html(wp_date('d.m.Y',FWB_Domain::date($b['arrival'])->getTimestamp(),new DateTimeZone('UTC'))).'</p><h3>'.esc_html($b['name']).'</h3><p>Abreise: '.esc_html(self::display_date($b['departure'])).' · '.esc_html($b['reference']).'</p><a class="button" href="'.esc_url(self::url('bookings').'&booking='.$b['id']).'">Buchung öffnen</a>';
    }
    private static function dashboard(): void {
        $o=FWB_Management::overview();
        echo '<h2>Deine Übersicht</h2><div class="fwb-dashboard-grid">';
        foreach([['Offene Anfragen',$o['counts']['pending'],'pending'],['Bestätigte Buchungen insgesamt',$o['counts']['confirmed'],'confirmed'],['Stornierungen insgesamt',$o['counts']['cancelled'],'cancelled'],['Heute bewohnt',$o['current']?'Ja':'Nein','']] as [$label,$value,$filter])echo '<a class="fwb-stat" href="'.esc_url(self::url($filter?'bookings':'calendar').($filter?'&status='.$filter:'')).'"><span>'.esc_html($label).'</span><strong>'.esc_html((string)$value).'</strong></a>';
        echo '</div><div class="fwb-card fwb-next-booking"><span class="fwb-eyebrow">NÄCHSTE BESTÄTIGTE ANREISE</span>';self::next_card($o['next']);
        echo '</div><div class="fwb-card"><h2>Belegung '.$o['year'].'</h2><p>Bestätigte Buchungen: Nächte werden dem jeweiligen Monat zugeordnet. Offene Anfragen, Stornierungen und Sperrzeiten zählen nicht mit.</p><div class="fwb-table-scroll"><table class="widefat striped"><thead><tr><th>Monat</th><th>Anreisen</th><th>Gebuchte Nächte</th><th>Belegung</th></tr></thead><tbody>';
        foreach($o['months'] as $m)echo '<tr><td>'.esc_html(wp_date('F',FWB_Domain::date($m['month'])->getTimestamp(),new DateTimeZone('UTC'))).'</td><td>'.(int)$m['arrivals'].'</td><td>'.(int)$m['nights'].'</td><td><meter min="0" max="100" value="'.(int)$m['occupancy'].'" aria-label="Belegung '.esc_attr($m['month']).'">'.(int)$m['occupancy'].' %</meter> '.(int)$m['occupancy'].' %</td></tr>';
        echo '</tbody></table></div></div>';
    }
}
