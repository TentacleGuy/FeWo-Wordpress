<?php
// Uses only the local WordPress test installation; restores its settings afterward.
require __DIR__ . '/../tools/wordpress/wp-load.php';
$checks=0;
function verify($ok,$label) { global $checks; if (!$ok) { throw new RuntimeException($label); } $checks++; echo "OK: $label\n"; }
$before=get_option('fwb_settings');
$before_templates=get_option('fwb_templates');
$before_fields=get_option('fwb_fields');
$before_layout=get_option('fwb_layout');
delete_option('fwb_layout');
try {
    $s=FWB_Settings::get();
    foreach (['night_price','extra_price','cleaning_price','local_tax'] as $k) { $s[$k]=number_format($s[$k]/100,2,'.',''); }
    $css="/* Test */\n.fwb-widget > .fwb-title { letter-spacing: .01em; }\n@media (max-width: 600px) { .fwb-widget .uk-button { font-size: 14px; } }";
    $s['custom_css']=$css;
    FWB_Settings::save($s);
    verify(FWB_Settings::get()['custom_css']===$css,'CSS saved: selectors, comments and media rules retained');
    FWB_Frontend::styles();
    verify(in_array($css,wp_styles()->get_data('fwb-front','after'),true),'Custom CSS enqueued after stylesheet');
    FWB_Frontend::styles();
    verify(count(wp_styles()->get_data('fwb-front','after'))===1,'No duplicate inline CSS');
    foreach (['</style><script>alert(1)</script>','<style>.x{color:red}</style>',str_repeat('a',50001)] as $bad) {
        try { FWB_Settings::sanitize_css($bad); throw new RuntimeException('Unsafe CSS accepted'); }
        catch (InvalidArgumentException $e) { verify(true,'Invalid CSS rejected'); }
    }
    $s['custom_css']=''; FWB_Settings::save($s);
    verify(FWB_Settings::get()['custom_css']==='','CSS can be cleared');
    $html=do_shortcode('[ferienwohnung_buchung]');
    $doc=new DOMDocument(); @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html); $xp=new DOMXPath($doc);
    verify($xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," fwb-layout ")]/div')->length===2,'Grid has exactly two column wrappers');
    verify($xp->query('//form[contains(@class,"uk-form-stacked") and not(contains(@class,"uk-dark"))]')->length===1,'UIkit form inherits theme color context');
    verify($xp->query('//input[@type="date" and contains(@class,"uk-input")]')->length===2,'Date inputs use UIkit');
    verify($xp->query('//textarea[contains(@class,"uk-textarea")]')->length>=1,'Textarea uses UIkit');
    verify(!str_contains($html,"\n"),'Builder markup contains no auto-paragraph line breaks');
    verify(!str_contains(FWB_Frontend::calendar_markup(),'<script'),'Standalone calendar is safe markup');

    verify(!str_contains($html,'Spam-Schutz ohne Bilderrätsel'),'CAPTCHA explanation removed');
    verify(str_contains($html,'Deine Buchungsanfrage') && !str_contains($html,'Wählen Sie'),'Frontend uses informal address');
    $s['intro_title']='Willkommen in {property_name}';
    $s['form_intro']="Erste Zeile\nZweite Zeile <b>ohne HTML</b>";
    $s['form_outro']='Danke & bis bald!';
    FWB_Settings::save($s);
    $copy=FWB_Settings::get();
    verify($copy['form_intro']==="Erste Zeile\nZweite Zeile ohne HTML",'Editable copy preserves newlines and strips HTML');
    $html=FWB_Frontend::shortcode();
    verify(str_contains($html,'Willkommen in ' . esc_html($s['property_name'])),'Property placeholder rendered');
    verify(str_contains($html,'Erste Zeile<br') && str_contains($html,'Danke &amp; bis bald!'),'Custom copy rendered and escaped');
    verify(strpos($html,'data-block="form_outro"')>strpos($html,'data-block="submit"'),'Migrated outro follows submit block');
    foreach (['intro_kicker','intro_title','intro_text','form_title','form_intro','form_outro'] as $key) { $s[$key]=''; }
    FWB_Settings::save($s); $html=FWB_Frontend::shortcode();
    verify(!str_contains($html,'fwb-title ') && !str_contains($html,'fwb-form-intro') && !str_contains($html,'fwb-form-outro'),'Empty copy sections hidden');
    update_option('fwb_templates',['request_subject'=>'Ihre Anfrage {booking_number} – {property_name}','invoice_body'=>'Eigene Vorlage: Ihre individuelle Nachricht']);
    update_option('fwb_fields',[['key'=>'message','label'=>'Ihre Nachricht'],['key'=>'special','label'=>'Ihr eigener Feldtitel']]);
    FWB_Settings::migrate_copy();
    verify(FWB_Settings::templates()['request_subject']==='Deine Anfrage {booking_number} – {property_name}','Unchanged saved factory template upgraded');
    verify(FWB_Settings::templates()['invoice_body']==='Eigene Vorlage: Ihre individuelle Nachricht','Custom template preserved');
    verify(FWB_Settings::fields()[0]['label']==='Deine Nachricht' && FWB_Settings::fields()[1]['label']==='Ihr eigener Feldtitel','Only factory message label upgraded');
    echo "$checks frontend assertions passed.\n";
} finally {
    update_option('fwb_settings',$before,false);
    foreach (['fwb_templates'=>$before_templates,'fwb_fields'=>$before_fields,'fwb_layout'=>$before_layout] as $key=>$value) {
        if ($value === false) { delete_option($key); } else { update_option($key,$value,false); }
    }
}
