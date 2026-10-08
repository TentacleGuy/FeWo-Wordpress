<?php
define('ABSPATH',__DIR__);
function esc_attr($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function esc_html($v){return esc_attr($v);}function esc_url($v){return esc_attr($v);}function esc_textarea($v){return esc_attr($v);}
function admin_url($v){return '/wp-admin/'.$v;}function wp_nonce_field($v){echo '<input name="_wpnonce" value="test">';}
function get_transient($v){return false;}function get_current_user_id(){return 1;}function current_time($v){return '2026-10-08';}
function checked($a,$b,$echo=false){return $a==$b?'checked':'';}function selected($a,$b,$echo=false){return $a==$b?'selected':'';}
function submit_button($v){echo '<button>'.$v.'</button>';}
class FWB_Settings {static function get(){return ['auto_invoice'=>false,'guests_max'=>4];}}
class FWB_I18n {static function current(){return 'de';}static function languages(){return ['de'=>['name'=>'Deutsch']];}}
class FWB_Frontend {static function calendar_markup(){return '<section class="fwb-calendar"></section>';}}
require __DIR__.'/../ferienwohnung-buchung/includes/admin.php';
$m=new ReflectionMethod(FWB_Admin::class,'calendar');ob_start();$m->invoke(null);$html=ob_get_clean();
function verify_calendar($ok,$message){if(!$ok)throw new RuntimeException($message);echo "OK: $message\n";}
verify_calendar(str_contains($html,'fwb-calendar-workspace'),'Shared calendar workspace rendered');
verify_calendar(substr_count($html,'<form ')===2&&substr_count($html,'</form>')===2,'Separate nonnested block and booking forms');
verify_calendar(str_contains($html,'name="status" value="confirmed"')&&!str_contains($html,'select name="status"'),'Confirmed booking without status selector');
verify_calendar(!str_contains($html,'Anstehende Anfragen und Aufenthalte'),'Upcoming list removed');
verify_calendar(str_contains($html,'name="reason" maxlength="200" placeholder="Grund (optional)"'),'Blocking reason optional');
verify_calendar(strpos($html,'fwb-calendar-column')<strpos($html,'id="fwb-manual"'),'Booking form follows calendar column');
verify_calendar(str_contains($html,'id="fwb-calendar-block" disabled'),'Blocking disabled before selecting dates');
