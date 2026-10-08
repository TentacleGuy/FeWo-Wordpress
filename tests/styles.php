<?php
require __DIR__.'/../tools/wordpress/wp-load.php';
$checks=0;
function style_check($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "OK: $label\n";}
$before=[];foreach(['fwb_layout','fwb_fields','fwb_design'] as $key)$before[$key]=get_option($key);
try{
    $layout=FWB_Layout::defaults();$schema=FWB_Design::schema();
    $appearance=[];
    foreach($schema as $key=>$def){
        $appearance[$key]=match($def['type']){
            'select'=>array_key_last($def['options']),'checkbox'=>true,'color'=>'#123456',
            'number'=>(string)$def['min'],default=>'Prüfung '.$key
        };
    }
    $layout['zones']['left'][1]['appearance']=$appearance;
    FWB_Layout::save(wp_json_encode($layout));
    $saved=FWB_Layout::get()['zones']['left'][1]['appearance'];
    foreach($appearance as $key=>$value)style_check($saved[$key]===$value,'Persisted: '.$key);
    // Also exercise every selectable option and clearing all values, not just one preset.
    foreach($schema as $key=>$def)if($def['type']==='select')foreach(array_keys($def['options']) as $value){
        $layout['zones']['left'][1]['appearance']=[$key=>$value];
        FWB_Layout::save(wp_json_encode($layout));
        style_check(FWB_Layout::get()['zones']['left'][1]['appearance'][$key]===$value,'Option: '.$key.'='.$value);
    }
    style_check(FWB_Design::clean(['padding'=>'medium'],$schema)['padding']==='default','Legacy medium padding becomes supported UIkit padding');
    $b=['id'=>'test','type'=>'quote','appearance'=>['text_style'=>'lead','text_color'=>'primary','align'=>'right','font_size'=>'21','line_height'=>'1.7','color'=>'#123456']];
    $html=FWB_Design::decorate('<h3 class="uk-h4">Titel</h3><div class="fwb-quote uk-text-small">Preis</div><label class="uk-form-label">Name</label><input class="uk-input"><button type="submit" class="uk-button">Senden</button>',$b,'test-');
    $doc=new DOMDocument();@$doc->loadHTML($html);$xp=new DOMXPath($doc);
    foreach(['h3','div','label','input','button'] as $tag){
        $node=$xp->query('//'.$tag)->item(0);
        style_check(str_contains($node->getAttribute('class'),'uk-text-lead')&&!str_contains($node->getAttribute('class'),'uk-text-small'),'Text style reaches '.$tag);
        style_check(str_contains($node->getAttribute('style'),'font-size:21px !important')&&str_contains($node->getAttribute('style'),'line-height:1.7 !important'),'Explicit font metrics reach '.$tag);
        style_check(str_contains($node->getAttribute('class'),'uk-text-right')&&($tag==='button'||str_contains($node->getAttribute('style'),'color:#123456 !important')),'Alignment and custom color reach '.$tag);
    }
    $b['appearance']['heading_size']='uk-h2';
    $html=FWB_Design::decorate('<h3 class="uk-h4">Titel</h3><p>Text</p>',$b,'test-');
    style_check(str_contains($html,'uk-h2')&&!str_contains(FWB_Design::wrapper($b)['class'],'uk-h2'),'Heading size only applies to heading, not body copy');
    update_option('fwb_design',['label_color'=>'#999999']);
    style_check(!str_contains(FWB_Design::wrapper($b)['class'],'fwb-override-label-color'),'Explicit element color takes priority over global label color');
    $template=FWB_Documents::safe_html('<h5 style="color:#123456">Kleine Überschrift</h5><h6>Untertitel</h6><pre>Zeilen bleiben</pre>');
    style_check(str_contains($template,'<h5')&&str_contains($template,'<h6>')&&str_contains($template,'<pre>'),'Editor heading levels and preformatted text survive template save');
    $b=['id'=>'field','type'=>'field','field'=>['key'=>'test','type'=>'text'],'appearance'=>['color'=>'#111111','input_color'=>'#222222']];
    style_check(str_contains(FWB_Design::decorate('<input class="uk-input">',$b,'test-'),'color:#222222 !important'),'Specific input text color wins over general text color');
    echo "$checks style assertions passed.\n";
}finally{foreach($before as $k=>$v){if($v===false)delete_option($k);else update_option($k,$v,false);}}
