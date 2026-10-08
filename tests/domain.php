<?php
define('ABSPATH',__DIR__);
function get_option($key,$default=false) { return $default; }
require __DIR__ . '/../ferienwohnung-buchung/includes/domain.php';
require __DIR__ . '/../ferienwohnung-buchung/includes/settings.php';
$checks=0;
function check($actual,$expected,$message) { global $checks; $checks++; if ($actual!==$expected) { throw new RuntimeException($message . ': ' . var_export($actual,true) . ' != ' . var_export($expected,true)); } }
function rejects(callable $fn,string $message) { try { $fn(); } catch (InvalidArgumentException $e) { check(true,true,$message); return; } throw new RuntimeException('Expected rejection: ' . $message); }
$s=FWB_Settings::defaults(); $s['night_price']=10000;
$input=['arrival'=>'2027-03-27','departure'=>'2027-03-30','guests'=>'4','taxable_guests'=>'2'];
foreach (['night'=>['person'=>12000,'booking'=>6000],'stay'=>['person'=>4000,'booking'=>2000]] as $period=>$units) {
    foreach ($units as $unit=>$expected) {
        $s['extra_period']=$period; $s['extra_unit']=$unit;
        $q=FWB_Domain::quote($input,$s,'2027-01-01');
        check($q['supplement'],$expected,"Supplement $period $unit");
        check($q['nights'],3,'DST calendar nights');
        check($q['local_tax'],2700,'Only taxable guests');
        check($q['total'],30000+$expected,'Tax excluded');
        check($q['deposit']+$q['balance'],$q['total'],'Rounding sum');
        check($q['due_date'],'2027-03-26','Payment deadline');
        check($q['cancel_date'],'2027-03-13','Cancellation deadline');
    }
}
check(FWB_Domain::overlap('2027-01-01','2027-01-04','2027-01-04','2027-01-07'),false,'Same day turnaround');
check(FWB_Domain::overlap('2027-01-01','2027-01-05','2027-01-04','2027-01-07'),true,'Overlap');
check(FWB_Domain::overlap('2027-01-04','2027-01-07','2027-01-01','2027-01-04'),false,'Adjacent reverse');
check(FWB_Domain::cents('4,50'),450,'Comma currency');
rejects(fn()=>FWB_Domain::cents('-1'),'Negative price');
rejects(fn()=>FWB_Domain::cents('1e5'),'Exponent currency');
rejects(fn()=>FWB_Domain::date('2027-02-30'),'Invalid date');
rejects(fn()=>FWB_Domain::quote(array_merge($input,['taxable_guests'=>5]),$s,'2027-01-01'),'Taxable > guests');
rejects(fn()=>FWB_Domain::quote(array_merge($input,['guests'=>3.5]),$s,'2027-01-01'),'Fractional guests');
rejects(fn()=>FWB_Domain::quote(array_merge($input,['departure'=>'2027-03-28']),$s,'2027-01-01'),'Minimum nights');
rejects(fn()=>FWB_Domain::quote($input,$s,'2027-04-01'),'Past arrival');
$q=FWB_Domain::quote(array_merge($input,['guests'=>2,'taxable_guests'=>0]),$s,'2027-01-01');
check($q['supplement'],0,'No supplement for included guests'); check($q['local_tax'],0,'All exempt');
$s['night_price']=9999;$s['vat_percent']=7;
$q=FWB_Domain::quote($input,$s,'2027-01-01'); check($q['vat'],(int)round($q['total']*7/107),'Included VAT');
for($n=0;!FWB_Domain::proof('test',(string)$n,3);$n++) {}
check(FWB_Domain::proof('test',(string)$n,3),true,'Valid proof');
check(FWB_Domain::proof('test','-1',3),false,'Invalid nonce');
echo "$checks domain assertions passed.\n";
