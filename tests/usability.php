<?php
require __DIR__.'/../tools/wordpress/wp-load.php';
$checks=0;$ids=[];$sent=[];
function usability_check($v,$message){global $checks;if(!$v)throw new RuntimeException($message);$checks++;echo "OK: $message\n";}
function usability_reject(callable $fn,$message){try{$fn();}catch(InvalidArgumentException $e){usability_check(true,$message);return;}throw new RuntimeException($message);}
$oldSettings=get_option('fwb_settings');$oldSignature=get_option('fwb_mail_signature');$oldDesign=get_option('fwb_design');
add_filter('pre_wp_mail',static function($pre,$atts)use(&$sent){$sent[]=$atts;return true;},10,2);
try{
    $s=FWB_Settings::get();$s['night_price']=12000;$s['auto_mail']=1;$s['auto_invoice']=0;update_option('fwb_settings',$s,false);
    $p=['arrival'=>'2027-11-29','departure'=>'2027-12-03','guests'=>'2','taxable_guests'=>'1','name'=>'Telefon Test','email'=>'','phone'=>'012345','address'=>'Testadresse','status'=>'confirmed'];
    $base=FWB_Management::overview('2027-01-01');
    $result=FWB_Management::create($p);$ids[]=$id=$result['id'];$b=FWB_Store::get($id);
    usability_check($b['status']==='confirmed','Manual booking confirmed');
    usability_check($b['data']['quote']['nights']===4&&$b['data']['quote']['total']===48000,'Manual booking uses pricing rules');
    usability_check($b['data']['source']==='admin'&&$b['data']['consent_at']===null,'Telephone booking does not invent online consent');
    usability_check(count($sent)===0,'No mail when manual checkbox is off, even with auto_mail on');
    usability_check(!FWB_Store::available('2027-11-30','2027-12-02'),'Confirmed manual booking blocks dates');
    usability_reject(fn()=>FWB_Management::create($p),'Overlapping booking rejected');
    usability_reject(fn()=>FWB_Management::create(array_merge($p,['send_mail'=>1])),'Mail without address rejected');
    usability_reject(fn()=>FWB_Management::create(array_merge($p,['status'=>'cancelled'])),'Invalid initial status rejected');
    usability_reject(fn()=>FWB_Management::create(array_merge($p,['guests'=>'999'])),'Guest limit validated');
    usability_reject(fn()=>FWB_Management::create(array_merge($p,['departure'=>'2027-11-30'])),'Minimum nights validated');
    $o=FWB_Management::overview('2027-11-29');
    usability_check($o['months'][10]['nights']===$base['months'][10]['nights']+2&&$o['months'][11]['nights']===$base['months'][11]['nights']+2,'Monthly nights split across month boundary');
    usability_check($o['months'][10]['arrivals']===$base['months'][10]['arrivals']+1&&$o['months'][11]['arrivals']===$base['months'][11]['arrivals'],'Only arrival month receives arrival count');
    usability_check($o['current']===1,'Arrival day counted as occupied');
    usability_check(FWB_Management::overview('2027-12-03')['current']===0,'Departure day excluded from current occupancy');
    usability_check((int)$o['next']['id']===$id,'Next confirmed arrival shown');
    FWB_Management::change($id,'cancelled',false);
    usability_check(FWB_Store::available('2027-11-29','2027-12-03'),'Cancellation releases dates');
    usability_check(count($sent)===0,'Explicit no-mail also respected for cancellation');
    usability_check(FWB_Management::overview('2027-01-01')['months'][10]['nights']===$base['months'][10]['nights'],'Cancelled bookings excluded from statistics');
    update_option('fwb_mail_signature','<p>Viele Grüße<br>{property_name}</p><script>alert(1)</script>');
    $p['status']='pending';$p['email']='telephone@example.invalid';$p['send_mail']=1;
    $result=FWB_Management::create($p);$ids[]=$second=$result['id'];
    usability_check(count($sent)===1,'Optional request mail sent once');
    usability_check(str_contains(end($sent)['message'],'Viele Grüße'),'Signature appended to legacy plain-text mail');
    usability_check(FWB_Store::available($p['arrival'],$p['departure']),'Manual pending request does not block');
    FWB_Management::change($second,'confirmed',true);
    usability_check(count($sent)===2,'Table confirmation sends selected status mail');
    FWB_Management::change($second,'confirmed',true);
    usability_check(count($sent)===2,'Repeated status submission sends no duplicate mail');
    $html=FWB_Documents::email_html('<p>Nachricht</p>',FWB_Documents::variables(FWB_Store::get($second)));
    usability_check(substr_count($html,'fwb-mail-signature')===1&&strpos($html,'Nachricht')<strpos($html,'Viele Grüße'),'HTML signature appended once after body');
    usability_check(!str_contains($html,'<script>'),'Signature HTML sanitized');
    $s['auto_invoice']=1;$s['seller']='';update_option('fwb_settings',$s,false);
    FWB_Management::change($second,'cancelled',false);
    $p['status']='confirmed';$p['send_mail']=0;
    $r=FWB_Management::create($p);$ids[]=$r['id'];
    usability_check(str_contains($r['message'],'bleibt bestätigt')&&FWB_Store::get($r['id'])['status']==='confirmed','Invoice failure does not misreport successful booking as failed');
    usability_check(count($sent)===2,'Silent manual booking stays silent on invoice failure');
    $design=FWB_Design::clean(['disabled_background'=>'#abcdef','disabled_text'=>'#123456'],FWB_Design::global_schema());update_option('fwb_design',$design,false);
    $markup=FWB_Frontend::calendar_markup();
    usability_check(str_contains($markup,'--fwb-disabled-background:#abcdef')&&str_contains($markup,'--fwb-disabled-text:#123456'),'Disabled colors reach standalone calendar');
    $before=FWB_Store::get($r['id']);$mailCount=count($sent);
    FWB_Management::guests($r['id'],['name'=>'Telefon aktualisiert','email'=>'updated@example.invalid','address'=>'Neue Testadresse','phone'=>'1234','message'=>'Bitte zurückrufen']);
    $after=FWB_Store::get($r['id']);
    usability_check($after['name']==='Telefon aktualisiert'&&$after['email']==='updated@example.invalid'&&$after['data']['fields']['address']==='Neue Testadresse','Contact data can be completed after telephone booking');
    usability_check($after['data']['quote']===$before['data']['quote']&&$after['status']===$before['status']&&count($sent)===$mailCount,'Contact edit preserves price and status and sends no mail');
    usability_reject(fn()=>FWB_Management::guests($r['id'],['name'=>'Test','email'=>'invalid']),'Invalid contact email rejected');
    $s['seller']='Testbetrieb, Musterstraße 1';$s['tax_id']='TEST-123';$s['invoice_note']='Testrechnung';update_option('fwb_settings',$s,false);
    $invoice=FWB_Documents::issue($r['id']);
    FWB_Management::guests($r['id'],['name'=>'Nochmals aktualisiert','email'=>'updated@example.invalid','address'=>'Andere Adresse','phone'=>'1234','message'=>'']);
    usability_check(FWB_Store::invoice($r['id'])['pdf']===$invoice['pdf'],'Contact edits preserve issued PDF byte for byte');
    FWB_Management::change($r['id'],'cancelled',false);
    $p['arrival']='2027-12-10';$p['departure']='2027-12-14';$p['send_mail']=0;
    $quiet=FWB_Management::create($p);$ids[]=$quiet['id'];
    usability_check(FWB_Store::invoice($quiet['id'])!==null,'Automatic invoice also created for manual confirmed bookings');
    usability_check(count($sent)===$mailCount,'No status or invoice mail for silent booking even when automatic invoice succeeds');
    usability_check(isset(FWB_Design::global_schema()['past_background'],FWB_Design::global_schema()['past_text']),'Past day colors independently configurable');
    usability_check(count(FWB_Admin::sections())===7,'Dashboard and six sidebar sections');
    echo "$checks usability assertions passed; all mail intercepted.\n";
}finally{
    global $wpdb;foreach($ids as $id)foreach(['events','invoices','bookings'] as $suffix)$wpdb->delete(FWB_Store::table($suffix),[$suffix==='bookings'?'id':'booking_id'=>$id]);
    foreach(['fwb_settings'=>$oldSettings,'fwb_mail_signature'=>$oldSignature,'fwb_design'=>$oldDesign] as $key=>$value){if($value===false)delete_option($key);else update_option($key,$value,false);}
}
