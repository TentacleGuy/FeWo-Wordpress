<?php
define('WP_ADMIN',true);
require __DIR__.'/../tools/wordpress/wp-load.php';
$checks=0;$before=[];
$keys=['fwb_layout','fwb_fields','fwb_settings','fwb_templates','fwb_template_formats','fwb_mail_signature','fwb_source_language','fwb_i18n_en_layout','fwb_i18n_en_templates','fwb_i18n_en_settings','fwb_english_strings_en'];
foreach($keys as $key)$before[$key]=get_option($key);
function english_check($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "OK: $label\n";}
function tokens($text){preg_match_all('/\{[a-z_]+\}/',$text,$m);sort($m[0]);return $m[0];}
$en=PLL()->model->get_language('en');$mo=new PLL_MO();$mo->import_from_db($en);$previous=clone $mo;
try{
 wp_set_current_user(get_user_by('login','fwb-test-admin')->ID);
 foreach($keys as $key)delete_option($key);
 update_option('fwb_source_language','de');
 $s=FWB_Settings::defaults();$s['night_price']=15000;$s['privacy_url']='https://example.invalid/privacy';update_option('fwb_settings',$s);
 FWB_I18n::set('de');$source=FWB_Layout::base();$sourceSettings=FWB_Settings::get();
 FWB_I18n::set('en');
 foreach(FWB_I18n::catalog() as $text){
  $translated=FWB_I18n::builtin($text);english_check($translated!==null&&$translated!==$text,'System copy: '.$text);
  english_check(tokens($text)===tokens($translated),'System placeholders: '.$text);
 }
 foreach(FWB_I18n::texts($source) as $key=>$text){
  $translated=FWB_I18n::builtin($text);english_check($translated!==null,'Default layout translated: '.$key);
  english_check(tokens($text)===tokens($translated),'Layout placeholders: '.$key);
 }
 foreach(FWB_Settings::template_defaults() as $key=>$text){
  $translated=FWB_I18n::builtin($text);english_check($translated!==null,'Default template translated: '.$key);
  english_check(tokens($text)===tokens($translated),'Template placeholders: '.$key);
 }
 english_check(str_contains(FWB_Layout::get()['zones']['left'][1]['content'],'Price per night'),'Good to know fully translated with markup');
 english_check(FWB_Settings::get()===$sourceSettings,'Source settings unchanged by English reads');
 english_check(FWB_I18n::builtin('<p class="uk-text-lead">Gut zu wissen</p>')==='<p class="uk-text-lead">Good to know</p>','Custom formatting preserved');
 english_check(FWB_I18n::builtin('<p>Ein individuell vereinbarter Preis.</p>')===null,'Unknown custom terms never replaced by factory terms');
 english_check(FWB_I18n::builtin('Anreise','fr')===null,'Other languages do not inherit English');
 english_check(str_contains(FWB_Designer::templates()['request_body'],'<p>Hello {name},'),'Mail visual editor receives HTML');
 $plain=FWB_Settings::template_defaults();$html=$plain;
 foreach(['request','confirmed','cancelled','rejected','invoice'] as $kind)$html[$kind.'_body']=wpautop(esc_html($plain[$kind.'_body']));
 update_option('fwb_templates',$html);update_option('fwb_template_formats',array_fill_keys(['request','confirmed','cancelled','rejected','invoice'],'html'));
 english_check(str_contains(FWB_Designer::templates()['confirmed_body'],'<p>Hello {name},')&&!str_contains(FWB_Designer::templates()['confirmed_body'],'&lt;p'),'Previously saved HTML defaults translated without double escaping');
 update_option('fwb_mail_signature','<p>Herzliche Grüße<br>{property_name}</p>');
 english_check(FWB_I18n::signature()==='<p>Warm regards<br>{property_name}</p>','Signature text translates while preserving placeholders');
 $layout=FWB_Layout::get();FWB_Layout::save(wp_json_encode($layout));
 english_check(FWB_Layout::get()===$layout&&FWB_Layout::base()===$source,'English layout saves and reloads without altering source structure');
 $layout['zones']['right'][1]['label']='Check-in date';FWB_Layout::save(wp_json_encode($layout));
 english_check(FWB_Layout::get()['zones']['right'][1]['label']==='Check-in date','User wording overrides included English');
 $templates=FWB_Designer::templates();$templates['request_subject']='Custom English {booking_number}';FWB_I18n::save_templates($templates);
 english_check(FWB_Designer::templates()['request_subject']==='Custom English {booking_number}','User mail wording preserved');
 $html=FWB_Frontend::shortcode();
 english_check(str_contains($html,'Good to know')&&str_contains($html,'Billing address')&&str_contains($html,'Guests liable for tourist tax'),'Complete guest form uses English');
 $sample=FWB_Designer::sample();$pdf=FWB_Documents::html($sample,['number'=>'TEST']);
 english_check(str_contains($pdf,'Invoice TEST')&&str_contains($pdf,'Invoice total')&&str_contains($pdf,'Tourist tax'),'PDF headings and body use English');
 $older=$sample;$older['data']['quote']['rules']=FWB_Settings::defaults()['rules'];
 $variables=FWB_Documents::variables($older);
 english_check(str_contains($variables['rules'],'Smoking is not permitted')&&$older['data']['quote']['rules']===FWB_Settings::defaults()['rules'],'Old English booking translates known snapshot without rewriting stored conditions');
 $sent=[];add_filter('pre_wp_mail',static function($pre,$mail)use(&$sent){$sent[]=$mail;return true;},10,2);
 $mo->add_entry(new Translation_Entry(['singular'=>'Frei','translations'=>['Available (custom)']]));
 foreach(['Belegt','Nächster Monat'] as $key)$mo->delete_entry($key);$mo->export_to_db($en);
 FWB_I18n::register();$after=new PLL_MO();$after->import_from_db($en);
 english_check($after->translate('Belegt')==='Booked','Polylang string editor populated automatically');
 english_check($after->translate('Frei')==='Available (custom)','Existing Polylang custom translation preserved');
 english_check(FWB_I18n::t('Frei')==='Available (custom)','Polylang override takes precedence over bundle');
 FWB_I18n::register();$again=new PLL_MO();$again->import_from_db($en);
 english_check($again->entries==$after->entries,'Repeated registration is idempotent');
 ob_start();FWB_I18n::bar('templates');$bar=ob_get_clean();
 english_check(str_contains($bar,'0 Texte fehlen'),'Bundled complete defaults are not reported as missing');
 echo "$checks English translation assertions passed.\n";
}finally{
 $previous->export_to_db($en);FWB_I18n::set(null);
 foreach($before as $key=>$value){if($value===false)delete_option($key);else update_option($key,$value,false);}
}
