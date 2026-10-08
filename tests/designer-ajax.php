<?php
define('DOING_AJAX',true);
require __DIR__.'/../tools/wordpress/wp-load.php';
class DesignerJsonExit extends Exception {}
add_filter('wp_die_ajax_handler',static fn()=>static function(){global $designer_capture; if($designer_capture===null)$designer_capture=ob_get_contents(); ob_clean(); throw new DesignerJsonExit();});
$checks=0;
function verify_ajax($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "OK: $label\n";}
function designer_ajax($op,$payload=[],$nonce=null) {
    $_POST=['op'=>$op,'payload'=>wp_slash(wp_json_encode($payload))];
    $_REQUEST=['nonce'=>$nonce??wp_create_nonce('fwb_designer')];
    global $designer_capture;$designer_capture=null;ob_start();try{FWB_Designer::ajax();}catch(DesignerJsonExit $e){}ob_end_clean();return json_decode($designer_capture,true);
}
$keys=['fwb_layout','fwb_fields','fwb_design','fwb_design_backup','fwb_pdf_design','fwb_templates','fwb_template_formats'];
$before=[];foreach($keys as $k)$before[$k]=get_option($k);
try{
    wp_set_current_user(0);$r=designer_ajax('design_save',['free'=>'#000000']);
    verify_ajax(!$r['success'],'Unauthenticated mutation denied');
    wp_set_current_user(get_user_by('login','fwb-test-admin')->ID);
    $r=designer_ajax('design_save',['free'=>'#000000'],'invalid');
    verify_ajax($r===null||$r===-1,'Invalid nonce denied');
    $backup=designer_ajax('design_export')['data'];
    $incoming=$backup;$incoming['design']['free']='#ccffcc';
    $bad=$incoming;$bad['layout']['zones']['right']=[];
    verify_ajax(!designer_ajax('design_import',$bad)['success'],'Incomplete import denied');
    verify_ajax(get_option('fwb_design')===$before['fwb_design'],'Rejected import does not partially change design');
    verify_ajax(designer_ajax('design_import',$incoming)['success'],'Valid design and layout import succeeds');
    verify_ajax(FWB_Design::get()['free']==='#ccffcc'&&get_option('fwb_design_backup')['design']===$backup['design'],'Import applies color and saves previous state');
    verify_ajax(designer_ajax('design_restore')['success']&&FWB_Design::get()===$backup['design'],'Previous design restored');
    $payload=['templates'=>FWB_Designer::templates(),'pdf'=>FWB_Documents::pdf_defaults(),'kind'=>'confirmed','booking'=>0];
    $r=designer_ajax('template_preview',$payload);
    verify_ajax($r['success']&&str_contains($r['data']['html'],'Alex Muster'),'Mail preview resolves example placeholders');
    $payload['kind']='pdf_body';$r=designer_ajax('template_preview',$payload);
    verify_ajax($r['success']&&str_starts_with(base64_decode($r['data']['pdf']),'%PDF-'),'PDF preview returns an actual PDF');
    verify_ajax(get_option('fwb_templates')===$before['fwb_templates'],'Previews do not save templates');
    $payload['templates']['request_body']='<p>Hallo <strong>{name}</strong></p>';
    verify_ajax(designer_ajax('templates_save',$payload)['success']&&get_option('fwb_template_formats')['request']==='html','Visual template save enables HTML mail format');
    echo "$checks designer AJAX assertions passed.\n";
}finally{foreach($before as $k=>$v){if($v===false)delete_option($k);else update_option($k,$v,false);}}

