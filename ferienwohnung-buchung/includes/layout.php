<?php
defined('ABSPATH') || exit;
final class FWB_Layout {
    public const ZONES = ['top'=>'Über beiden Spalten','left'=>'Linke Spalte','right'=>'Rechte Spalte','bottom'=>'Unter beiden Spalten'];
    public const REQUIRED = ['arrival','departure','guests','taxable_guests','quote','consent','submit'];
    public static function catalog(): array {
        return [
            'calendar'=>'Belegungskalender','conditions'=>'Gut zu wissen','heading'=>'Überschrift','text'=>'Freier Text',
            'arrival'=>'Anreise','departure'=>'Abreise','guests'=>'Gäste gesamt','taxable_guests'=>'Davon ortstaxenpflichtig',
            'quote'=>'Preisberechnung','consent'=>'Zustimmung / Datenschutz','submit'=>'Absenden',
            'text_field'=>'Textfeld','email_field'=>'E-Mail-Feld','tel_field'=>'Telefonfeld',
            'textarea_field'=>'Nachrichtenfeld','select_field'=>'Auswahlfeld','checkbox_field'=>'Kontrollkästchen',
        ];
    }
    public static function conditions_default(): string {
        return '<ul><li>Preis pro Nacht für die gesamte Wohnung: {night_price}, für bis zu {included_guests} Gäste.</li><li>Aufschlag bei mehr Gästen: {extra_price} {extra_basis}.</li><li>Mindestaufenthalt: {min_nights} Nächte. Anreise ab {checkin}, Abreise bis {checkout}.</li><li>Anzahlung: {deposit_percent} %. Restzahlung spätestens {balance_days} Tag(e) vor Anreise per Überweisung.</li><li>Kostenfreie Stornierung bis {cancel_days} Tage vor Anreise. Spätere Stornierung nach Rücksprache.</li><li>Ortstaxe: {local_tax}. {tax_note}</li></ul><p>{cleaning_note}</p><p>{rules}</p>';
    }
    public static function block(string $type, string $label='', string $content=''): array {
        return ['id'=>$type,'type'=>$type,'label'=>$label,'content'=>$content,'width'=>'full'];
    }
    public static function defaults(): array {
        $s=FWB_Settings::get(); $zones=array_fill_keys(array_keys(self::ZONES),[]);
        foreach (['intro_kicker','intro_title','intro_text'] as $key) {
            if ($s[$key]==='') { continue; }
            $b=self::block($key==='intro_title'?'heading':'text', $key==='intro_title'?$s[$key]:'', $key==='intro_title'?'':wpautop(esc_html($s[$key])));
            $b['id']=$key; $b['level']='h2'; $zones['top'][]=$b;
        }
        $calendar=self::block('calendar','Belegungskalender','Halbe Markierung: Vormittag bzw. Nachmittag belegt. Ein Gästewechsel am selben Tag ist möglich.');
        $zones['left']=[$calendar,self::block('conditions','Gut zu wissen',self::conditions_default())];
        if ($s['form_title']!=='') { $b=self::block('heading',$s['form_title']); $b['id']='form_title'; $b['level']='h3'; $zones['right'][]=$b; }
        if ($s['form_intro']!=='') { $b=self::block('text','',wpautop(esc_html($s['form_intro']))); $b['id']='form_intro'; $zones['right'][]=$b; }
        foreach (['arrival','departure','guests','taxable_guests'] as $type) {
            $b=self::block($type,self::catalog()[$type]); $b['width']='half'; $zones['right'][]=$b;
        }
        $zones['right'][]=self::block('quote','Preisberechnung','Wähle deine Reisedaten, um den Preis zu berechnen.');
        foreach (FWB_Settings::fields() as $field) {
            $b=self::block('field',$field['label']); $b['id']='field_' . $field['key']; $b['field']=$field; $zones['right'][]=$b;
        }
        $zones['right'][]=self::block('consent','Ich habe die Buchungsbedingungen und die {privacy_link} gelesen.');
        $zones['right'][]=self::block('submit','Unverbindlich anfragen');
        if ($s['form_outro']!=='') { $b=self::block('text','',wpautop(esc_html($s['form_outro']))); $b['id']='form_outro'; $zones['right'][]=$b; }
        return ['version'=>1,'zones'=>$zones];
    }
    public static ?array $preview = null;
    public static function get(): array {
        if (self::$preview !== null) return self::$preview;
        return FWB_I18n::layout(self::base());
    }
    public static function base(): array {
        $saved=get_option('fwb_layout');
        return self::validate(wp_json_encode(is_array($saved) && isset($saved['zones']) ? $saved : self::defaults()))['layout'];
    }
    public static function placeholders(): array {
        $s=FWB_I18n::settings(); $values=[];
        foreach (['property_name','included_guests','min_nights','checkin','checkout','deposit_percent','balance_days','cancel_days','tax_note','rules'] as $key) { $values[$key]=nl2br(esc_html((string)$s[$key]),false); }
        foreach (['extra_price','local_tax','cleaning_price'] as $key) { $values[$key]=esc_html(FWB_Domain::money($s[$key])); }
        $values['night_price']=$s['night_price']?esc_html(FWB_Domain::money($s['night_price'])):FWB_I18n::t("noch nicht festgelegt");
        $values['extra_basis']=($s['extra_unit']==='person'?FWB_I18n::t("je zusätzlicher Person"):FWB_I18n::t("pro Buchung")) . ' ' . ($s['extra_period']==='night'?FWB_I18n::t("und Nacht"):FWB_I18n::t("je Aufenthalt"));
        $values['cleaning_note']=$s['cleaning_price']?FWB_I18n::t("Endreinigung einmalig: ") . $values['cleaning_price'] . '.':'';
        $values['privacy_link']='<a href="' . esc_url($s['privacy_url']) . '" target="_blank" rel="noopener">' . esc_html(FWB_I18n::t("Datenschutzhinweise")) . '</a>';
        return $values;
    }
    public static function html(string $content): string {
        $map=[]; foreach (self::placeholders() as $key=>$value) { $map['{'.$key.'}']=$value; }
        // Sanitize after substitution too, including placeholders in attributes.
        return wp_kses_post(strtr(wp_kses_post($content),$map));
    }
    public static function validate(string $json): array {
        if (strlen($json)>300000) { throw new InvalidArgumentException('Der Baukasten ist zu groß.'); }
        $input=json_decode($json,true);
        if (!is_array($input) || !isset($input['zones']) || !is_array($input['zones'])) { throw new InvalidArgumentException('Ungültiger Baukasten.'); }
        $zones=[]; $html_ids=[]; $ids=[]; $types=[]; $fields=[]; $count=0;
        foreach (self::ZONES as $zone=>$title) {
            if (!isset($input['zones'][$zone]) || !is_array($input['zones'][$zone])) { throw new InvalidArgumentException('Alle Layoutbereiche müssen vorhanden sein.'); }
            $zones[$zone]=[];
            foreach ($input['zones'][$zone] as $b) {
                if (!is_array($b) || ++$count>80) { throw new InvalidArgumentException('Maximal 80 Elemente möglich.'); }
                $type=$b['type']??''; $id=$b['id']??'';
                if (!is_string($type) || !in_array($type,['calendar','conditions','heading','text','arrival','departure','guests','taxable_guests','quote','consent','submit','field'],true) ||
                    !is_string($id) || !preg_match('/^[a-z][a-z0-9_-]{0,79}$/D',$id) || isset($ids[$id])) { throw new InvalidArgumentException('Ungültiges oder doppeltes Element.'); }
                $ids[$id]=true; $types[$type]=($types[$type]??0)+1;
                if (!in_array($type,['field','heading','text'],true) && $types[$type]>1) { throw new InvalidArgumentException('Buchungselemente dürfen nur einmal vorhanden sein.'); }
                $label=sanitize_text_field((string)($b['label']??''));
                $content=(string)($b['content']??'');
                if (strlen($content)>30000 || strlen($label)>500) { throw new InvalidArgumentException('Text zu lang.'); }
                $out=['id'=>$id,'type'=>$type,'label'=>$label,'content'=>in_array($type,['text','conditions'],true)?wp_kses_post($content):sanitize_textarea_field($content),'width'=>in_array($b['width']??'',['full','half','third','two_thirds'],true)?$b['width']:'full'];
                $out['appearance']=FWB_Design::clean((array)($b['appearance']??[]),FWB_Design::schema());
                $out['advanced']=FWB_Design::advanced((array)($b['advanced']??[]));
                $out['hidden']=!empty($b['hidden']) && !in_array($type,self::REQUIRED,true) && !($type==='field'&&!empty($b['field']['required']));
                if (!empty($b['hidden']) && ($type==='field' && (!empty($b['field']['required']) || in_array($b['field']['key']??'',['name','email','address'],true)))) { throw new InvalidArgumentException('Pflichtfelder können nicht ausgeblendet werden.'); }
                $html_id=$out['advanced']['html_id'];
                if ($html_id!==''&&isset($html_ids[$html_id])) { throw new InvalidArgumentException('Doppelte HTML-ID: '.$html_id); }
                if ($html_id!=='')$html_ids[$html_id]=true;
                if (in_array($type,['arrival','departure','guests','taxable_guests','consent','submit'],true) && $label==='') { throw new InvalidArgumentException('Buchungsfelder benötigen eine Beschriftung.'); }
                if ($type==='consent' && !str_contains($label,'{privacy_link}')) { throw new InvalidArgumentException('Die Zustimmung benötigt den Platzhalter {privacy_link}.'); }
                if ($type==='calendar') {
                    $out['calendar_labels']=[];
                    foreach (['free_label'=>'Frei','busy_label'=>'Belegt','half_label'=>'An-/Abreise','selected_label'=>'Ausgewählt'] as $key=>$default) {
                        $out['calendar_labels'][$key]=sanitize_text_field((string)($b['calendar_labels'][$key]??$default));
                    }
                }
                if ($type==='quote') {
                    $out['summary_template']=sanitize_text_field((string)($b['summary_template']??'{total} für {nights} Nächte'));
                    $out['detail_template']=sanitize_textarea_field((string)($b['detail_template']??'Anzahlung {deposit} · Restbetrag {balance}. Ortstaxe separat vor Ort: {local_tax}.'));
                    foreach (['{total}'=>'summary_template','{deposit}'=>'detail_template','{balance}'=>'detail_template','{local_tax}'=>'detail_template'] as $token=>$key) {
                        if (!str_contains($out[$key],$token)) { throw new InvalidArgumentException('In der Preisberechnung fehlt ' . $token); }
                    }
                }
                if ($type==='heading') { $out['level']=in_array($b['level']??'',['h2','h3','h4'],true)?$b['level']:'h3'; }
                if ($type==='field') {
                    if (!is_array($b['field']??null)) { throw new InvalidArgumentException('Feldkonfiguration fehlt.'); }
                    $f=$b['field']; $f['label']=$label;
                    if (!is_string($f['key']??null) || sanitize_key($f['key'])!==$f['key']) { throw new InvalidArgumentException('Feldkennungen: nur Kleinbuchstaben, Zahlen und Unterstriche.'); }
                    if (is_array($f['options']??null)) { $f['options']=implode("\n",$f['options']); }
                    $fields[]=$f; $out['field']=$f;
                }
                $zones[$zone][]=$out;
            }
        }
        foreach (self::REQUIRED as $required) { if (($types[$required]??0)!==1) { throw new InvalidArgumentException('Notwendiges Element fehlt: ' . self::catalog()[$required]); } }
        $validated=FWB_Settings::validate_fields(wp_json_encode($fields)); $by_key=array_column($validated,null,'key');
        foreach ($zones as &$blocks) { foreach ($blocks as &$b) { if ($b['type']==='field') { $b['field']=$by_key[$b['field']['key']]; } } unset($b); } unset($blocks);
        return ['layout'=>['version'=>1,'zones'=>$zones],'fields'=>$validated];
    }
    public static function save(string $json): void {
        $validated=self::validate($json); // Validate everything before writing either option.
        if(FWB_I18n::foreign()){FWB_I18n::save_layout($validated['layout']);return;}
        update_option('fwb_fields',$validated['fields'],false);
        update_option('fwb_layout',$validated['layout'],false);
    }
}
