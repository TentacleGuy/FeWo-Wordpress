<?php
require __DIR__.'/../tools/wordpress/wp-load.php';
$before=[];foreach(['fwb_layout','fwb_fields','fwb_settings'] as $key){$before[$key]=get_option($key);}
$count=0;
function check_layout(bool $ok,string $label):void {global $count;if(!$ok)throw new RuntimeException($label);$count++;echo "OK: $label\n";}
function reject_layout(array $data,string $label):void {try{FWB_Layout::save(wp_json_encode($data));}catch(InvalidArgumentException $e){check_layout(true,$label);return;}throw new RuntimeException('Accepted invalid layout: '.$label);}
try {
    delete_option('fwb_layout');$layout=FWB_Layout::get();
    check_layout(count($layout['zones']['left'])===2,'Existing calendar and conditions migrate to left column');
    $base=FWB_Layout::validate(wp_json_encode($layout));
    check_layout(count($base['fields'])===count(FWB_Settings::fields()),'Existing contact fields preserved');
    // Move arrival across columns, retain editable, safe rich text and live prices.
    foreach($layout['zones']['right'] as $i=>$b){if($b['type']==='arrival'){$arrival=$b;array_splice($layout['zones']['right'],$i,1);break;}}
    $layout['zones']['left'][]=$arrival;
    $layout['zones']['left'][1]['content']='<h4>Dein Urlaub</h4><p><strong>Preis: {night_price}</strong></p><script>alert(1)</script><img src=x onerror=alert(1)>';
    FWB_Layout::save(wp_json_encode($layout));
    $saved=FWB_Layout::get();$html=FWB_Frontend::shortcode();
    check_layout(!str_contains($saved['zones']['left'][1]['content'],'<script')&&!str_contains($html,'onerror='),'Rich text strips executable HTML');
    check_layout(str_contains($html,'<strong>Preis: '.esc_html(FWB_Domain::money(FWB_Settings::get()['night_price']))),'Formatted text resolves live price placeholder');
    $doc=new DOMDocument();@$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);$xp=new DOMXPath($doc);
    check_layout($xp->query('//form')->length===1,'Exactly one form owns both columns');
    check_layout($xp->query('//div[contains(concat(" ",normalize-space(@class)," ")," fwb-column ")][1]//input[@name="arrival"]')->length===1,'Arrival rendered in left column');
    check_layout($xp->query('//form//input[@name="arrival"]')->length===1&&$xp->query('//form//input[@name="departure"]')->length===1,'Required date controls rendered exactly once');
    $s=FWB_Settings::get();$s['night_price']=13500;update_option('fwb_settings',$s);
    check_layout(str_contains(FWB_Frontend::shortcode(),'Preis: 135,00'),'Price placeholders follow settings after layout save');
    $invalid=$saved;$invalid['zones']['left'][]=$arrival;reject_layout($invalid,'Duplicate date element rejected');
    $invalid=$saved;foreach($invalid['zones'] as &$blocks){$blocks=array_values(array_filter($blocks,fn($b)=>$b['type']!=='submit'));}unset($blocks);reject_layout($invalid,'Missing submit rejected');
    $invalid=$saved;foreach($invalid['zones'] as &$blocks){foreach($blocks as &$b){if($b['type']==='consent')$b['label']='Kein Link';}unset($b);}unset($blocks);reject_layout($invalid,'Consent requires privacy link');
    $invalid=$saved;foreach($invalid['zones'] as &$blocks){$blocks=array_values(array_filter($blocks,fn($b)=>!($b['type']==='field'&&$b['field']['key']==='email')));}unset($blocks);reject_layout($invalid,'Required email cannot be removed');
    check_layout(get_option('fwb_layout')===$saved,'Rejected saves leave the layout unchanged');
    $without=$saved;$without['zones']['left']=array_values(array_filter($without['zones']['left'],fn($b)=>$b['type']!=='calendar'));FWB_Layout::save(wp_json_encode($without));
    check_layout(!str_contains(FWB_Frontend::shortcode(),'class="fwb-calendar"'),'Calendar may be removed without removing date controls');
    echo "$count layout assertions passed.\n";
} finally {foreach($before as $key=>$value){if($value===false)delete_option($key);else update_option($key,$value,false);}}
