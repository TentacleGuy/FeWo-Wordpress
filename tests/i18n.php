<?php
define('WP_ADMIN',true);
require __DIR__.'/../tools/wordpress/wp-load.php';
if(!function_exists('pll_languages_list')||!in_array('fr',pll_languages_list(),true))throw new RuntimeException('Requires local Polylang with de/en/fr.');
$checks=0;$ids=[];$sent=[];$before=[];
foreach(['fwb_layout','fwb_fields','fwb_settings','fwb_templates','fwb_template_formats','fwb_mail_signature','fwb_source_language','fwb_i18n_en_layout','fwb_i18n_fr_layout','fwb_i18n_en_templates','fwb_i18n_fr_templates','fwb_i18n_en_settings'] as $key)$before[$key]=get_option($key);
function lang_check($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "OK: $label\n";}
function lang_reject($fn,$label){try{$fn();}catch(InvalidArgumentException $e){lang_check(true,$label);return;}throw new RuntimeException($label);}
function lang_label($layout,$id,$value){foreach($layout['zones'] as &$blocks)foreach($blocks as &$b)if($b['id']===$id){$b['label']=$value;if(isset($b['field']))$b['field']['label']=$value;}return $layout;}
add_filter('pre_wp_mail',static function($pre,$mail)use(&$sent){$sent[]=$mail;return true;},10,2);
$en=PLL()->model->get_language('en');$mo=new PLL_MO();$mo->import_from_db($en);$moBefore=clone $mo;
try{
 update_option('fwb_source_language','de');FWB_I18n::set('de');
 foreach(['en','fr'] as $lang)foreach(['layout','templates','settings'] as $group)delete_option('fwb_i18n_'.$lang.'_'.$group);
 wp_set_current_user(get_user_by('login','fwb-test-admin')->ID);
 $s=FWB_Settings::get();$s['night_price']=12000;$s['auto_invoice']=0;$s['auto_mail']=0;$s['privacy_url']='https://example.invalid/privacy';update_option('fwb_settings',$s,false);
 $base=FWB_Layout::base();FWB_Layout::save(wp_json_encode($base));$original=get_option('fwb_layout');
 lang_check(array_keys(FWB_I18n::languages())===['de','en','fr'],'All configured Polylang languages discovered');
 FWB_I18n::set(null);$_GET['lang']='en';lang_check(FWB_I18n::current()==='en','Admin toolbar language en is used');$_GET['lang']='all';lang_check(FWB_I18n::current()==='de','All languages edits the source');unset($_GET['lang']);
 FWB_I18n::set('en');lang_check(FWB_I18n::texts(FWB_Layout::get())['arrival/label']==='Arrival','English uses bundled copy without manual translation');
 $translated=lang_label($base,'arrival','Arrival');$translated=lang_label($translated,'submit','Send request');
 FWB_Layout::save(wp_json_encode($translated));lang_check(get_option('fwb_layout')===$original,'Translation save preserves source layout');
 lang_check(FWB_I18n::texts(FWB_Layout::get())['arrival/label']==='Arrival','English field persists');
 FWB_I18n::set('fr');FWB_Layout::save(wp_json_encode(lang_label(FWB_Layout::get(),'arrival','Arrivée')));
 lang_check(FWB_I18n::texts(FWB_Layout::get())['arrival/label']==='Arrivée','Third language has independent content');
 FWB_I18n::set('de');$changed=$base;$changed['zones']['right'][0]['appearance']['color']='#123456';FWB_Layout::save(wp_json_encode($changed));
 FWB_I18n::set('en');$enLayout=FWB_Layout::get();lang_check($enLayout['zones']['right'][0]['appearance']['color']==='#123456'&&FWB_I18n::texts($enLayout)['arrival/label']==='Arrival','Shared design update retains translated copy');
 $bad=$enLayout;$bad['zones']['right'][0]['width']='third';lang_reject(fn()=>FWB_Layout::save(wp_json_encode($bad)),'Foreign layout cannot overwrite shared structure');
 FWB_I18n::save(array_intersect_key(FWB_Settings::get(),FWB_I18n::GLOBALS),['rules'=>'No smoking.','tax_note'=>'Local tax payable on site.','privacy_url'=>'https://example.invalid/en/privacy'],'settings');
 lang_check(FWB_I18n::settings()['rules']==='No smoking.'&&FWB_Settings::get()['rules']!=='No smoking.','Translated global text leaves source untouched');
 $t=FWB_Designer::templates();$sourceTemplates=get_option('fwb_templates');$t['confirmed_subject']='Confirmed {booking_number}';$t['confirmed_body']='<p>Hello {name}. {rules}</p>';$t['request_body']='<p>Request {booking_number}</p>';$t['pdf_body']='<h1>Invoice {invoice_number}</h1>{items}<p>{total}</p>';$t['signature']='<p>English signature</p>';
 FWB_I18n::save_templates(FWB_Designer::clean_templates($t));
 lang_check(get_option('fwb_templates')===$sourceTemplates,'Template translation does not change source templates');
 lang_check(FWB_Designer::templates()['confirmed_body']===$t['confirmed_body'],'Translated HTML is not escaped or converted twice');
 FWB_I18n::set('fr');$fr=FWB_Designer::templates();$fr['confirmed_subject']='Confirmation {booking_number}';FWB_I18n::save_templates($fr);
 FWB_I18n::set('en');lang_check(FWB_Settings::templates()['confirmed_subject']==='Confirmed {booking_number}','Third-language template save preserves English');
 $mo->add_entry(new Translation_Entry(['singular'=>'Bitte wählen','translations'=>['Please choose']]));
 $mo->add_entry(new Translation_Entry(['singular'=>'Der Gastgeber hat die Preise noch nicht freigeschaltet.','translations'=>['Prices are not available yet.']]));
 $mo->add_entry(new Translation_Entry(['singular'=>'Leistung','translations'=>['Service']]));$mo->export_to_db($en);
 lang_check(FWB_I18n::t('Bitte wählen')==='Please choose','Real Polylang string translation resolves');
 $html=FWB_Frontend::shortcode();lang_check(str_contains($html,'data-language="en"')&&str_contains($html,'fwb_lang=en')&&str_contains($html,'Arrival')&&str_contains($html,'Send request'),'Frontend copy and REST language match');
 $temp=FWB_Settings::get();$temp['night_price']=0;update_option('fwb_settings',$temp,false);
 $req=new WP_REST_Request('POST','/fwb/v1/quote');$req->set_param('fwb_lang','en');$req->set_header('Content-Type','application/json');$req->set_body(wp_json_encode(['arrival'=>'2028-08-01','departure'=>'2028-08-05','guests'=>'2','taxable_guests'=>'2']));
 FWB_I18n::set('fr');$res=FWB_API::dispatch('quote',$req);lang_check(is_wp_error($res)&&$res->get_error_message()==='Prices are not available yet.','REST errors use explicit visitor language');lang_check(FWB_I18n::current()==='fr','REST restores caller language');update_option('fwb_settings',$s,false);
 $req->set_param('fwb_lang','unknown');lang_check(is_wp_error(FWB_API::dispatch('quote',$req)),'Unknown language rejected');
 FWB_I18n::set('en');$q=FWB_Domain::quote(['arrival'=>'2028-08-01','departure'=>'2028-08-05','guests'=>'2','taxable_guests'=>'2'],FWB_I18n::settings(),current_time('Y-m-d'));
 $ids[]=$id=FWB_Store::insert($q,['name'=>'Alex','email'=>'alex@example.invalid','address'=>'Test address'],'confirmed');$b=FWB_Store::get($id);
 lang_check($b['data']['language']==='en','Booking persists visitor language');FWB_I18n::set('de');
 FWB_Documents::mail($id,'confirmed');$mail=end($sent);lang_check(str_starts_with($mail['subject'],'Confirmed ')&&str_contains($mail['message'],'English signature')&&str_contains($mail['message'],'No smoking.'),'Later mail uses booking language and translated signature despite German admin');
 lang_check(FWB_I18n::current()==='de','Mail restores admin language');
 $html=FWB_Documents::html($b,['number'=>'TEST']);lang_check(str_contains($html,'Invoice TEST')&&str_contains($html,'Service')&&str_contains($html,'lang="en-GB"'),'PDF content and static table headings use booking language');
 $request=new WP_REST_Request('POST','/fwb/v1/request');$request->set_param('fwb_lang','en');$request->set_header('Content-Type','application/json');
 $challenge=FWB_API::challenge(new WP_REST_Request());$nonce=0;while(!FWB_Domain::proof($challenge['challenge'],(string)$nonce,3))$nonce++;
 $request->set_body(wp_json_encode(array_merge($challenge,['nonce'=>(string)$nonce,'website'=>'','consent'=>true,'arrival'=>'2028-10-01','departure'=>'2028-10-05','guests'=>'2','taxable_guests'=>'2','fields'=>['name'=>'Web English','email'=>'web@example.invalid','address'=>'Test address']])));
 FWB_I18n::set('fr');$response=FWB_API::dispatch('request',$request);lang_check($response instanceof WP_REST_Response,'Public request accepts explicit language and valid proof');
 global $wpdb;$webId=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.FWB_Store::table().' WHERE reference=%s',$response->get_data()['reference']));$ids[]=$webId;
 lang_check(FWB_Store::get($webId)['data']['language']==='en'&&FWB_Store::get($webId)['data']['quote']['rules']==='No smoking.','Public booking stores language and translated rules snapshot');
 $legacy=$b;unset($legacy['data']['language']);lang_check(FWB_I18n::booking($legacy)==='de','Old bookings retain source language');
 $manual=FWB_Management::create(['arrival'=>'2028-09-01','departure'=>'2028-09-05','guests'=>'2','taxable_guests'=>'2','name'=>'Téléphone','language'=>'fr','email'=>'','status'=>'pending']);$ids[]=$manual['id'];lang_check(FWB_Store::get($manual['id'])['data']['language']==='fr','Telephone booking stores explicitly selected language');
 FWB_I18n::set('en');$bad=$t;$bad['confirmed_body']='<script>alert(1)</script><p>Hello</p>';lang_check(!str_contains(FWB_Designer::clean_templates($bad)['confirmed_body'],'<script>'),'Translated HTML remains sanitized');
 FWB_I18n::register();lang_check(count(FWB_I18n::catalog())>50,'System string catalog registered');
 echo "$checks Polylang integration assertions passed; mail intercepted.\n";
}finally{
 FWB_I18n::set(null);$moBefore->export_to_db($en);
 foreach($before as $key=>$value){if($value===false)delete_option($key);else update_option($key,$value,false);}
 global $wpdb;foreach($ids as $id)foreach(['events','invoices','bookings'] as $suffix)$wpdb->delete(FWB_Store::table($suffix),[$suffix==='bookings'?'id':'booking_id'=>$id]);
}
