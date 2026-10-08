<?php
require __DIR__.'/../tools/wordpress/wp-load.php';
$checks=0;$ids=[];$sent=[];
function compact_check($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "OK: $label\n";}
function compact_reject(callable $f,$label){try{$f();}catch(InvalidArgumentException $e){compact_check(true,$label);return;}throw new RuntimeException($label);}
$old=get_option('fwb_settings');$sig=get_option('fwb_mail_signature');
add_filter('pre_wp_mail',static function($pre,$atts)use(&$sent){$sent[]=$atts;return true;},10,2);
try{
 $s=FWB_Settings::get();$s['auto_invoice']=0;$s['night_price']=12000;update_option('fwb_settings',$s,false);
 $q=FWB_Domain::quote(['arrival'=>'2028-03-01','departure'=>'2028-03-05','guests'=>'2','taxable_guests'=>'2'],$s,current_time('Y-m-d'));
 $fields=['name'=>'Sammeltest','email'=>'bulk@example.invalid','address'=>'Testadresse'];
 $ids[]=$a=FWB_Store::insert($q,$fields);$ids[]=$b=FWB_Store::insert($q,$fields);
 $r=FWB_Management::bulk([$a,$b],'confirmed',false);
 compact_check($r[0]['ok']&&!$r[1]['ok'],'Bulk confirmation reports overlap per row');
 compact_check(FWB_Store::get($a)['status']==='confirmed'&&FWB_Store::get($b)['status']==='pending','Failed item remains pending');
 compact_check(count($sent)===0,'Bulk no-mail is respected');
 compact_reject(fn()=>FWB_Management::apply($a,'unblock'), 'Unblock cannot cancel a guest booking');
 $data=FWB_Store::get($a)['data'];FWB_Management::apply($a,'trash',true);
 compact_check(FWB_Store::get($a)['status']==='trash'&&FWB_Store::available($q['arrival'],$q['departure']),'Trash releases occupied dates');
 compact_check(count($sent)===0&&FWB_Store::get($a)['data']['quote']===$data['quote'],'Trash retains booking data and sends no mail');
 FWB_Management::apply($b,'confirmed');
 compact_reject(fn()=>FWB_Management::apply($a,'restore'),'Restore detects newly occupied dates');
 compact_check(FWB_Store::get($a)['status']==='trash','Failed restore stays in trash');
 FWB_Management::apply($b,'cancelled');FWB_Management::apply($a,'restore');
 compact_check(FWB_Store::get($a)['status']==='confirmed','Restore returns original confirmed status');
 FWB_Management::apply($a,'cancelled');
 $ids[]=$block=FWB_Store::insert(['arrival'=>'2028-04-01','departure'=>'2028-04-03'],['name'=>'Sperre'],'blocked');
 FWB_Management::apply($block,'trash');FWB_Management::apply($block,'restore');
 compact_check(FWB_Store::get($block)['status']==='blocked','Blocked period restored as a block');
 FWB_Management::apply($block,'unblock',true);
 compact_check(FWB_Store::get($block)['status']==='cancelled'&&count($sent)===0,'Unblock releases block without mail');
 compact_reject(fn()=>FWB_Management::bulk([],'trash'),'Empty selection rejected');
 compact_reject(fn()=>FWB_Management::bulk(array_fill(0,31,$a),'trash'),'Oversized selection rejected');
 compact_reject(fn()=>FWB_Management::bulk([$a],'unknown'),'Unknown action rejected');
 compact_reject(fn()=>FWB_Management::bulk(['1 OR 1=1'],'trash'),'Malformed IDs rejected');
 $r=FWB_Management::bulk([$a,$a,$b],'trash');compact_check(count($r)===2&&$r[0]['ok']&&$r[1]['ok'],'Duplicate selection processed once');
 $r=FWB_Management::bulk([$a,$block],'restore');compact_check($r[0]['ok']&&!$r[1]['ok'],'Mixed-status bulk gives individual outcome');
 compact_check(in_array('confirmed',FWB_Management::actions('cancelled'),true)&&in_array('confirmed',FWB_Management::actions('rejected'),true),'Cancelled and rejected bookings offer confirmation');
 FWB_Management::apply($a,'confirmed',false);
 compact_check(FWB_Store::get($a)['status']==='confirmed'&&count($sent)===0,'Cancelled booking reconfirmed without mail');
 FWB_Management::apply($a,'cancelled',false);
 $ids[]=$c=FWB_Store::insert($q,$fields);FWB_Management::apply($c,'rejected',false);
 $render=new ReflectionMethod(FWB_Admin::class,'row_actions');$render->setAccessible(true);
 foreach([$a,$c] as $id){ob_start();$render->invoke(null,FWB_Store::get($id));$html=ob_get_clean();compact_check(str_contains($html,'aria-label="Annehmen"')&&str_contains($html,'name="send_mail"'),'Reconfirmation icon and mail choice rendered for '.FWB_Store::get($id)['status']);}
 FWB_Management::apply($c,'confirmed',true);
 compact_check(FWB_Store::get($c)['status']==='confirmed'&&count($sent)===1,'Rejected booking reconfirmed with one confirmation mail');
 compact_reject(fn()=>FWB_Management::apply($a,'confirmed',true),'Reconfirmation rejects occupied dates');
 compact_check(FWB_Store::get($a)['status']==='cancelled'&&count($sent)===1,'Failed reconfirmation preserves status and sends no mail');
 FWB_Management::apply($c,'cancelled',false);
 $ids[]=$d=FWB_Store::insert($q,$fields);FWB_Management::apply($d,'rejected',false);
 $r=FWB_Management::bulk([$d,$a],'confirmed',false);
 compact_check($r[0]['ok']&&!$r[1]['ok']&&FWB_Store::get($d)['status']==='confirmed'&&FWB_Store::get($a)['status']==='cancelled','Bulk reconfirmation checks conflicts between rejected and cancelled bookings');
 compact_reject(fn()=>FWB_Management::apply($block,'confirmed'),'Released block cannot become a guest booking');
 ob_start();$render->invoke(null,FWB_Store::get($block));$html=ob_get_clean();compact_check(!str_contains($html,'aria-label="Annehmen"'),'Released block has no guest confirmation icon');
 update_option('fwb_mail_signature','<p>Bestehende Signatur</p>');
 compact_check(FWB_Designer::templates()['signature']==='<p>Bestehende Signatur</p>','Existing signature loaded into template list');
 $t=FWB_Designer::clean_templates(FWB_Designer::templates());compact_check($t['signature']==='<p>Bestehende Signatur</p>','Signature survives template sanitization');
 $html=FWB_Documents::email_html('<p>Nachricht</p>',[],'<p>Entwurf</p>');
 compact_check(str_contains($html,'Entwurf')&&!str_contains($html,'Bestehende Signatur'),'Preview uses draft signature');
 compact_check(get_option('fwb_mail_signature')==='<p>Bestehende Signatur</p>','Preview does not save signature');
 compact_check(!str_contains(FWB_Documents::email_html('<p>Nachricht</p>',[],''),'fwb-mail-signature'),'Empty signature preview removes footer');
 compact_check(FWB_Designer::clean_templates([])['signature']==='<p>Bestehende Signatur</p>','Older template payload preserves stored signature');
 compact_reject(fn()=>FWB_Designer::clean_templates(['signature'=>str_repeat('a',30001)]),'Oversized signature rejected');
 echo "$checks compact-action assertions passed; mail intercepted.\n";
}finally{
 global $wpdb;foreach($ids as $id)foreach(['events','invoices','bookings'] as $suffix)$wpdb->delete(FWB_Store::table($suffix),[$suffix==='bookings'?'id':'booking_id'=>$id]);
 update_option('fwb_settings',$old,false);if($sig===false)delete_option('fwb_mail_signature');else update_option('fwb_mail_signature',$sig,false);
}
