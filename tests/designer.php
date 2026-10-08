<?php
require __DIR__.'/../tools/wordpress/wp-load.php';
$checks=0;
function verify($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "OK: $label\n";}
function rejects($fn,$label){try{$fn();}catch(InvalidArgumentException $e){verify(true,$label);return;}throw new RuntimeException("Accepted invalid input: $label");}
$keys=['fwb_design','fwb_layout','fwb_fields','fwb_templates','fwb_template_formats','fwb_pdf_design'];
$before=[];foreach($keys as $k)$before[$k]=get_option($k);
try {
    foreach(['fwb_design','fwb_layout','fwb_pdf_design'] as $k)delete_option($k);
    $l=FWB_Layout::defaults();
    $l['zones']['left'][1]['appearance']=['text_style'=>'large','columns'=>'1-2','column_breakpoint'=>'m','column_divider'=>true,'tag'=>'aside','background'=>'#eeffee'];
    $l['zones']['left'][1]['advanced']=['html_id'=>'gut-wissen','classes'=>'uk-margin-large uk-text-left@m','attributes'=>[['name'=>'data-section','value'=>'info']]];
    foreach($l['zones']['right'] as &$b)if($b['type']==='field'&&$b['field']['key']==='name') {
        $b['appearance']=['input_size'=>'large','placeholder'=>'Dein Name','help'=>'Wie dürfen wir dich ansprechen?','maxlength'=>'80'];
        $b['advanced']=['control_classes'=>'name-field','control_attributes'=>[['name'=>'autocomplete','value'=>'name']]];
        // autocomplete belongs to the controlled input setting, not arbitrary attributes.
        rejects(fn()=>FWB_Design::advanced($b['advanced']),'Reserved control attributes rejected');
        $b['advanced']['control_attributes']=[['name'=>'data-purpose','value'=>'contact']];
    }unset($b);
    FWB_Layout::save(wp_json_encode($l));$html=FWB_Frontend::shortcode();
    verify(str_contains($html,'uk-column-1-2@m')&&str_contains($html,'uk-column-divider'),'UIkit text styles and columns rendered');
    verify(str_contains($html,'<aside')&&str_contains($html,'id="gut-wissen"')&&str_contains($html,'data-section="info"'),'Semantic wrapper, ID and attributes rendered');
    verify(str_contains($html,'name-field')&&str_contains($html,'data-purpose="contact"'),'Control classes and attributes rendered');
    verify(str_contains($html,'placeholder="Dein Name"')&&str_contains($html,'maxlength="80"')&&str_contains($html,'aria-describedby='),'Input options and accessible help rendered');
    verify(!str_contains($html,'esc_attr')&&str_contains($html,'--fwb-selection:#ffdc35'),'Color variables are actual CSS, no leaked code');
    verify(!str_contains($html,'fwb-override-input-background'),'Empty styling preserves theme inheritance');
    update_option('fwb_design',['input_background'=>'#fafafa']);
    verify(str_contains(FWB_Frontend::shortcode(),'fwb-override-input-background'),'Global input override applied intentionally');
    rejects(fn()=>FWB_Design::clean(['input_background'=>'red;display:none'],FWB_Design::global_schema()),'CSS injection rejected');
    foreach(['onclick','style','name','type','data-api','data-date','data-fwb-preview'] as $name)rejects(fn()=>FWB_Design::attributes([['name'=>$name,'value'=>'bad']]),'Protected attribute rejected: '.$name);
    $dup=$l;$dup['zones']['left'][0]['advanced']=['html_id'=>'gut-wissen'];
    rejects(fn()=>FWB_Layout::validate(wp_json_encode($dup)),'Duplicate HTML IDs rejected');
    $bad=$l;foreach($bad['zones']['right'] as &$b)if($b['type']==='field'&&$b['field']['key']==='email')$b['hidden']=true;unset($b);
    rejects(fn()=>FWB_Layout::validate(wp_json_encode($bad)),'Required fields cannot be hidden');
    $clean=FWB_Documents::safe_html('<p style="color:#123456;text-align:center">Hallo <strong>{name}</strong></p><img src="https://example.invalid/x"><script>alert(1)</script><p onclick="bad()" style="background-image:url(https://example.invalid/x)">Text</p>');
    verify(str_contains($clean,'color:')&&str_contains($clean,'<strong>'),'WYSIWYG formatting retained');
    verify(!str_contains($clean,'<script')&&!str_contains($clean,'<img')&&!str_contains($clean,'onclick')&&!str_contains($clean,'url('),'Template executable HTML and remote resources removed');
    update_option('fwb_templates',['request_body'=>"Hallo {name},\n\nDanke & bis bald."]);
    delete_option('fwb_template_formats');
    $t=FWB_Designer::templates();verify(str_contains($t['request_body'],'<p>Hallo')&&str_contains($t['request_body'],'&amp;'),'Legacy plain mail converted without losing paragraphs');
    $email=FWB_Documents::email_html('<p>Hallo <strong>{name}</strong></p>',['name'=>'<b>Alex</b>']);
    verify(str_contains($email,'&lt;b&gt;Alex&lt;/b&gt;'),'Guest placeholder values escaped');
    verify(FWB_Documents::plain('<p>Hallo Alex</p><p>Bis bald<br>Dein Gastgeber</p>')==="Hallo Alex\nBis bald\nDein Gastgeber",'Mail plain alternative retains paragraphs');
    // Exercise the actual PHPMailer hook without network delivery.
    remove_all_filters('pre_wp_mail'); add_filter('wp_mail_from',static fn()=> 'local@example.invalid');
    require_once ABSPATH.WPINC.'/PHPMailer/PHPMailer.php';
    require_once ABSPATH.WPINC.'/PHPMailer/SMTP.php';
    require_once ABSPATH.WPINC.'/PHPMailer/Exception.php';
    class LocalDesignerMailer extends PHPMailer\PHPMailer\PHPMailer {public function send(){return true;}}
    global $phpmailer;$phpmailer=new LocalDesignerMailer(true);
    verify(FWB_Documents::send_html('test@example.invalid','Test',$email),'HTML mail accepted by local test transport');
    verify($phpmailer->ContentType==='text/html'&&str_contains($phpmailer->AltBody,'Alex'),'PHPMailer receives HTML and plain alternative');
    $sample=FWB_Designer::sample();$t=FWB_Settings::template_defaults();$d=FWB_Documents::clean_pdf(FWB_Documents::pdf_defaults());
    $doc=FWB_Documents::html($sample,['number'=>'VORSCHAU-120'],$t,$d);
    verify(str_contains($doc,'VORSCHAU-120')&&!str_contains($doc,'{items}'),'Invoice placeholders and items rendered');
    file_put_contents(__DIR__.'/../tmp/designer-invoice.pdf',FWB_Documents::pdf($doc));
    $t['pdf_body'].=str_repeat('<p>Zusätzliche Information zur Musterrechnung für den mehrseitigen Layouttest. Alle Daten sind Beispieldaten.</p>',35);
    file_put_contents(__DIR__.'/../tmp/designer-multipage.pdf',FWB_Documents::pdf(FWB_Documents::html($sample,['number'=>'VORSCHAU-120'],$t,$d)));
    rejects(fn()=>FWB_Documents::clean_pdf(['logo_id'=>'2147483647']),'Invalid logo rejected');
    rejects(fn()=>FWB_Documents::clean_pdf(['header_height'=>60,'footer_height'=>60,'top'=>60,'bottom'=>60]),'Overlapping PDF page geometry rejected');
    echo "$checks designer assertions passed; no network mail sent.\n";
}finally{
    foreach($before as $k=>$v){if($v===false)delete_option($k);else update_option($k,$v,false);}
}

