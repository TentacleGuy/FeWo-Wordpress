<?php
// Isolated admin regression checks, no database or mail required.
define('ABSPATH',__DIR__);
function esc_attr($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
require __DIR__.'/../ferienwohnung-buchung/includes/admin.php';
require __DIR__.'/../ferienwohnung-buchung/includes/management.php';
function check_ui($ok,$message){if(!$ok)throw new RuntimeException($message);echo "OK: $message\n";}
check_ui(!in_array('cancelled',FWB_Management::actions('pending'),true),'Pending requests cannot be cancelled through booking actions');
check_ui(in_array('cancelled',FWB_Management::actions('confirmed'),true),'Confirmed bookings can be cancelled');
$icon=new ReflectionMethod(FWB_Admin::class,'action_icon');
check_ui(str_contains($icon->invoke(null,'rejected'),'dashicons-dismiss'),'Reject uses circled cross');
check_ui(str_contains($icon->invoke(null,'cancelled'),'dashicons-undo'),'Cancel uses return arrow');
check_ui(str_contains($icon->invoke(null,'restore'),'♻'),'Restore uses recycling triangle');
$date=new ReflectionMethod(FWB_Admin::class,'display_date');
check_ui($date->invoke(null,'2026-10-08')==='08.10.2026','German date display');
check_ui($date->invoke(null,'2026-10-08 12:30:00')==='08.10.2026 12:30:00','Log timestamp retains time');
$source=file_get_contents(__DIR__.'/../ferienwohnung-buchung/includes/admin.php');
check_ui(substr_count($source,'class="fwb-action-legend"')===1,'Exactly one legend');
check_ui(str_contains($source,'$wpdb->esc_like($search)')&&str_contains($source,'$wpdb->prepare(\' AND (name LIKE'),'Search escapes SQL wildcards and prepares parameters');
check_ui(str_contains($source,'name="page" value="fwb-bookings"'),'Filter submits to registered admin page');
