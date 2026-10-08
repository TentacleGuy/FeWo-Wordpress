<?php
require __DIR__ . '/../tools/wordpress/wp-load.php';
$checks=0; $sent=[];
function check($condition,$label) { global $checks; $checks++; if (!$condition) { throw new RuntimeException('FAIL: ' . $label); } echo "OK: $label\n"; }
function rejects(callable $fn,$label) { try { $fn(); } catch (InvalidArgumentException $e) { check(true,$label); return; } throw new RuntimeException('Expected failure: ' . $label); }
add_filter('pre_wp_mail',static function ($pre,$atts) use (&$sent) { $sent[]=$atts; return true; },10,2);
$_SERVER['REMOTE_ADDR']='127.0.0.1';
$s=FWB_Settings::defaults();
$s=array_merge($s,['property_name'=>'Ferienwohnung Bergblick','night_price'=>12000,'privacy_url'=>'https://example.invalid/datenschutz','seller'=>"Maria Muster\nBergstraße 12\n12345 Musterort",'tax_id'=>'TEST-12345','invoice_note'=>'Testdokument – steuerliche Angaben sind nur Beispieldaten.','iban'=>'DE00 0000 0000 0000 0000 00']);
update_option('fwb_settings',$s,false);
global $wpdb;
foreach (['bookings','invoices','events','tokens'] as $suffix) { $wpdb->query('TRUNCATE TABLE ' . FWB_Store::table($suffix)); }
update_option('fwb_invoice_seq',0,false);
$input=['arrival'=>'2027-05-03','departure'=>'2027-05-07','guests'=>'4','taxable_guests'=>'2','consent'=>true,'website'=>'','fields'=>['name'=>'Anna Müller','email'=>'anna@example.invalid','address'=>"Musterstraße 2\n12345 Musterstadt\nDeutschland",'message'=>'Bitte ein Kinderbett.']];
$challenge=FWB_API::challenge(new WP_REST_Request('GET'));
for ($n=0;!FWB_Domain::proof($challenge['challenge'],(string)$n,3);$n++) {}
$request=new WP_REST_Request('POST'); $request->set_header('Content-Type','application/json');
$request->set_body(wp_json_encode(array_merge($input,$challenge,['nonce'=>(string)$n])));
$created=FWB_API::request($request); check(str_starts_with($created['reference'],'FW-'),'Public booking request');
$id=(int)$wpdb->get_var('SELECT id FROM ' . FWB_Store::table() . ' LIMIT 1');
$b=FWB_Store::get($id); check($b['status']==='pending','Pending initially');
check($b['data']['quote']['total']===64000,'Correct price and surcharge');
check(count($sent)===2,'Request and host mail intercepted');
rejects(fn()=>FWB_API::request($request),'CAPTCHA replay rejected');
check(FWB_Store::available('2027-05-03','2027-05-07'),'Pending request does not block');
check(FWB_Store::transition($id,'confirmed'),'Confirm request');
check(!FWB_Store::transition($id,'confirmed'),'Repeated confirmation is idempotent');
check(!FWB_Store::available('2027-05-04','2027-05-06'),'Confirmed dates unavailable');
check(FWB_Store::available('2027-05-07','2027-05-10'),'Arrival on previous departure');
$other=FWB_Domain::quote(array_merge($input,['arrival'=>'2027-05-07','departure'=>'2027-05-10']),$s,'2026-09-26');
$id2=FWB_Store::insert($other,$input['fields']); FWB_Store::transition($id2,'confirmed');
rejects(fn()=>FWB_Store::insert($b['data']['quote'],$input['fields']),'Reject overlapping request');
$calendar=new WP_REST_Request('GET');$calendar->set_param('month','2027-05'); $ranges=FWB_API::calendar($calendar);
check(count($ranges['ranges'])===2 && !str_contains(wp_json_encode($ranges),'anna'),'Public calendar has no personal details');
FWB_Documents::mail($id,'confirmed'); check(str_contains(end($sent)['message'],'192,00'),'Confirmation template deposit');
$invoice=FWB_Documents::issue($id);
check(str_starts_with($invoice['pdf'],'%PDF-'),'Valid PDF header');
check(strlen($invoice['pdf'])>10000,'PDF contains rendered document');
file_put_contents(__DIR__ . '/../tmp/rechnung-test.pdf',$invoice['pdf']);
$s['night_price']=99999;update_option('fwb_settings',$s,false);
check(FWB_Documents::issue($id)['pdf']===$invoice['pdf'],'Issued PDF immutable');
check(FWB_Documents::issue($id)['number']===$invoice['number'],'Stable invoice number');
FWB_Documents::mail($id,'invoice'); check(count(end($sent)['attachments'])===1,'PDF attachment passed to wp_mail');
check(!file_exists(end($sent)['attachments'][0]),'Temporary PDF attachment cleaned');
FWB_Store::transition($id,'cancelled'); check(FWB_Store::available('2027-05-03','2027-05-07'),'Cancellation releases dates');
check(FWB_Store::invoice($id)['pdf']===$invoice['pdf'],'Cancellation retains original invoice');
check(FWB_Store::transition($id,'confirmed')&&!FWB_Store::available('2027-05-03','2027-05-07'),'Cancelled booking can reopen and occupies dates again');
check(FWB_Documents::issue($id)['pdf']===$invoice['pdf'],'Reconfirmation retains original invoice');
FWB_Store::transition($id,'cancelled');
// Competing requests may coexist, but at most one can be confirmed.
$s['night_price']=12000;update_option('fwb_settings',$s,false);
$q=FWB_Domain::quote(array_merge($input,['arrival'=>'2027-06-01','departure'=>'2027-06-04']),$s,'2026-09-26');
$a=FWB_Store::insert($q,$input['fields']);$b2=FWB_Store::insert($q,$input['fields']);
FWB_Store::transition($a,'confirmed'); rejects(fn()=>FWB_Store::transition($b2,'confirmed'),'Competing confirmation rejected');
add_filter('pre_wp_mail','__return_false',99);
check(!FWB_Documents::mail($a,'confirmed'),'Mail failure reported');
$t=FWB_Store::table('events');check($wpdb->get_var("SELECT kind FROM $t ORDER BY id DESC LIMIT 1")==='mail_failed','Mail failure logged');
check(FWB_Documents::render('{name}', ['name'=>'<script>bad</script>'],true)==='&lt;script&gt;bad&lt;/script&gt;','PDF placeholder escaped');
check(!str_contains(FWB_Documents::safe_html('<img src="https://evil.invalid/x"><script>x</script><p>OK</p>'),'<img'),'External PDF images removed');
do_action('rest_api_init');
$resp=rest_do_request(new WP_REST_Request('GET','/fwb/v1/calendar'));
check($resp->get_status()===200,'REST route registered');
check(str_contains(do_shortcode('[ferienwohnung_buchung]'),'fwb-request'),'Frontend shortcode renders');
echo "$checks integration assertions passed; no external mail sent.\n";
