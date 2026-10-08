<?php
defined('ABSPATH') || exit;
final class FWB_Design {
    public static function schema(): array {
        $select=static fn($label,$options)=>['label'=>$label,'type'=>'select','options'=>$options];
        $number=static fn($label,$min,$max)=>['label'=>$label,'type'=>'number','min'=>$min,'max'=>$max];
        $inherit=[''=>'Vom Theme übernehmen'];
        $space=[''=>'Vom Theme übernehmen','remove'=>'Kein Abstand','small'=>'Klein','default'=>'Normal','medium'=>'Mittel','large'=>'Groß'];
        return [
            'text_style'=>$select('Text-Stil',$inherit+['meta'=>'Meta','lead'=>'Lead','small'=>'Small','large'=>'Large']),
            'text_color'=>$select('Textfarbe',$inherit+array_combine(['muted','emphasis','primary','secondary','success','warning','danger'],['Gedämpft','Betont','Primär','Sekundär','Erfolg','Warnung','Fehler'])),
            'align'=>$select('Ausrichtung',$inherit+['left'=>'Links','center'=>'Zentriert','right'=>'Rechts','justify'=>'Blocksatz']),
            'font_size'=>$number('Schriftgröße (px)',8,100),'line_height'=>$number('Zeilenhöhe',0.8,3),
            'color'=>['label'=>'Eigene Textfarbe','type'=>'color'],
            'dropcap'=>['label'=>'Initiale aktivieren','type'=>'checkbox'],
            'columns'=>$select('Textspalten',[''=>'Keine','1-2'=>'Hälften','1-3'=>'Drittel','1-4'=>'Viertel','1-5'=>'Fünftel','1-6'=>'Sechstel']),
            'column_breakpoint'=>$select('Textspalten ab',[''=>'Immer','s'=>'Small','m'=>'Medium','l'=>'Large','xl'=>'X-Large']),
            'column_divider'=>['label'=>'Spaltentrenner','type'=>'checkbox'],
            'heading_size'=>$select('Optische Überschriftengröße',$inherit+['uk-h1'=>'H1','uk-h2'=>'H2','uk-h3'=>'H3','uk-h4'=>'H4','uk-heading-small'=>'Heading Small','uk-heading-medium'=>'Heading Medium']),
            'margin'=>$select('Außenabstand oben/unten',$space),'padding'=>$select('Innenabstand',array_diff_key($space,['medium'=>true])),
            'background'=>['label'=>'Hintergrundfarbe','type'=>'color'],'border_color'=>['label'=>'Rahmenfarbe','type'=>'color'],
            'border_width'=>$number('Rahmenstärke (px)',0,12),'radius'=>$number('Rundung (px)',0,100),
            'shadow'=>$select('Schatten',$inherit+['small'=>'Klein','medium'=>'Mittel','large'=>'Groß','xlarge'=>'Sehr groß']),
            'width_s'=>$select('Breite ab Small',[''=>'Wie Grundbreite','1-1'=>'Ganz','1-2'=>'Halb','1-3'=>'Drittel','2-3'=>'Zwei Drittel']),
            'width_m'=>$select('Breite ab Medium',[''=>'Wie zuvor','1-1'=>'Ganz','1-2'=>'Halb','1-3'=>'Drittel','2-3'=>'Zwei Drittel']),
            'width_l'=>$select('Breite ab Large',[''=>'Wie zuvor','1-1'=>'Ganz','1-2'=>'Halb','1-3'=>'Drittel','2-3'=>'Zwei Drittel']),
            'tag'=>$select('HTML-Element',[''=>'div','section'=>'section','aside'=>'aside','address'=>'address','footer'=>'footer']),
            'input_size'=>$select('Feldgröße',$inherit+['small'=>'Klein','large'=>'Groß']),
            'input_background'=>['label'=>'Feld-Hintergrund','type'=>'color'],'input_color'=>['label'=>'Feld-Textfarbe','type'=>'color'],
            'input_border'=>['label'=>'Feld-Rahmenfarbe','type'=>'color'],
            'placeholder'=>['label'=>'Eingabe-Platzhalter','type'=>'text'],'help'=>['label'=>'Hilfetext','type'=>'text'],
            'rows'=>$number('Textfeld-Zeilen',2,20),'maxlength'=>$number('Maximale Textlänge',1,4000),
            'autocomplete'=>$select('Autovervollständigung',[''=>'Automatisch','off'=>'Aus','name'=>'Name','email'=>'E-Mail','tel'=>'Telefon','street-address'=>'Anschrift']),
            'inputmode'=>$select('Bildschirmtastatur',[''=>'Automatisch','text'=>'Text','email'=>'E-Mail','tel'=>'Telefon','numeric'=>'Zahlen','decimal'=>'Dezimalzahlen']),
            'button_style'=>$select('Button-Stil',$inherit+['default'=>'Standard','primary'=>'Primär','secondary'=>'Sekundär','danger'=>'Danger','text'=>'Text','link'=>'Link']),
            'button_size'=>$select('Button-Größe',$inherit+['small'=>'Klein','large'=>'Groß']),
            'button_width'=>$select('Button-Breite',[''=>'Ganze Breite','auto'=>'Automatisch']),
            'button_background'=>['label'=>'Button-Hintergrund','type'=>'color'],'button_color'=>['label'=>'Button-Text','type'=>'color'],
            'button_hover'=>['label'=>'Button-Hover-Hintergrund','type'=>'color'],'button_hover_color'=>['label'=>'Button-Hover-Text','type'=>'color'],
        ];
    }
    public static function global_schema(): array {
        $out=[];
        foreach ([
            'free'=>'Kalender: frei','busy'=>'Kalender: belegt','selection'=>'Kalender: ausgewählt',
            'day_text'=>'Kalender: Tageszahl','busy_text'=>'Kalender: belegte Tageszahl','selection_text'=>'Kalender: ausgewählter Text',
            'past_background'=>'Vergangene Tage: Hintergrund','past_text'=>'Vergangene Tage: Schrift','disabled_background'=>'Nicht auswählbare belegte Tage: Hintergrund','disabled_text'=>'Nicht auswählbare belegte Tage: Schrift',
            'today'=>'Kalender: Rahmen heute','calendar_hover'=>'Kalender: Hover-Rahmen','focus'=>'Fokus-Rahmen',
            'calendar_border'=>'Kalender: Tagesrahmen','selection_border'=>'Kalender: Auswahlrahmen',
            'input_background'=>'Feld-Hintergrund','input_color'=>'Feld-Text','input_border'=>'Feld-Rahmen',
            'label_color'=>'Beschriftungen','help_color'=>'Hilfetexte','button_background'=>'Button-Hintergrund',
            'button_color'=>'Button-Text','button_hover'=>'Button-Hover','button_hover_color'=>'Button-Hover-Text',
            'error'=>'Fehlermeldung','success'=>'Erfolgsmeldung',
        ] as $key=>$label) { $out[$key]=['label'=>$label,'type'=>'color']; }
        foreach (['radius'=>'Allgemeine Rundung (px)','calendar_radius'=>'Kalender-Rundung (px)','field_radius'=>'Feld-Rundung (px)','column_gap'=>'Spaltenabstand (px)','element_gap'=>'Elementabstand (px)','field_border_width'=>'Feld-Rahmenstärke (px)','calendar_font_size'=>'Kalender-Schriftgröße (px)','calendar_height'=>'Kalender-Tageshöhe (px)'] as $key=>$label) { $out[$key]=['label'=>$label,'type'=>'number','min'=>$key==='calendar_height'?24:($key==='calendar_font_size'?8:0),'max'=>$key==='calendar_height'?100:80]; }
        return $out;
    }
    public static function defaults(): array {
        return ['past_background'=>'#e7e9eb','past_text'=>'#687078','disabled_background'=>'','disabled_text'=>'','free'=>'#dff3e3','busy'=>'#f7d4d4','selection'=>'#ffdc35','day_text'=>'#254b38','busy_text'=>'#742929','selection_text'=>'#302600','selection_border'=>'#725700'];
    }
    public static function clean(array $input, array $schema): array {
        $out=[];
        foreach ($schema as $key=>$def) {
            $v=$input[$key]??'';
            if($key==='padding'&&$v==='medium')$v='default';
            if (!is_scalar($v)) { throw new InvalidArgumentException('Ungültige Einstellung: '.$def['label']); }
            if ($def['type']==='checkbox') { $out[$key]=!empty($v); continue; }
            if ((string)$v==='') { $out[$key]=''; continue; }
            if ($def['type']==='color') {
                if (!preg_match('/^#[0-9a-f]{6}$/iD',(string)$v)) { throw new InvalidArgumentException('Ungültige Farbe: '.$def['label']); }
                $out[$key]=strtolower($v);
            } elseif ($def['type']==='number') {
                if (!is_numeric($v) || (float)$v<$def['min'] || (float)$v>$def['max']) { throw new InvalidArgumentException('Ungültiger Wert: '.$def['label']); }
                $out[$key]=(string)(0+$v);
            } elseif ($def['type']==='select') {
                if (!array_key_exists((string)$v,$def['options'])) { throw new InvalidArgumentException('Ungültige Auswahl: '.$def['label']); }
                $out[$key]=(string)$v;
            } else { $out[$key]=mb_substr(sanitize_text_field((string)$v),0,1000); }
        }
        return $out;
    }
    public static function get(): array { return array_merge(self::defaults(),(array)get_option('fwb_design',[])); }
    public static function classes(string $value): string {
        if (strlen($value)>1000) { throw new InvalidArgumentException('Zu viele CSS-Klassen.'); }
        $parts=preg_split('/\s+/',trim($value));
        foreach ($parts as $part) { if ($part!==''&&!preg_match('/^[a-zA-Z_][a-zA-Z0-9_:@-]*$/D',$part)) { throw new InvalidArgumentException('Ungültige CSS-Klasse.'); } }
        return implode(' ',array_unique(array_filter($parts)));
    }
    public static function attributes(array $rows): array {
        if (count($rows)>30) { throw new InvalidArgumentException('Maximal 30 Attribute pro Element.'); }
        $out=[];
        foreach ($rows as $row) {
            $name=strtolower(trim((string)($row['name']??''))); if ($name==='')continue;
            if (isset($out[$name])) { throw new InvalidArgumentException('Doppeltes Attribut: '.$name); }
            $allowed=preg_match('/^(data-[a-z][a-z0-9_-]*|aria-(label|description|describedby|details)|title)$/D',$name)
                || in_array($name,['uk-tooltip','uk-icon'],true);
            if (!$allowed||in_array($name,['data-api','data-worker','data-field','data-date','data-block','data-summary','data-detail','data-empty'],true)||str_starts_with($name,'data-fwb')) { throw new InvalidArgumentException('Dieses Attribut ist geschützt oder nicht erlaubt: '.$name); }
            $out[$name]=mb_substr(sanitize_text_field((string)($row['value']??'')),0,1000);
        }
        return array_map(static fn($k,$v)=>['name'=>$k,'value'=>$v],array_keys($out),$out);
    }
    public static function advanced(array $input): array {
        $id=(string)($input['html_id']??'');
        if ($id!==''&&(!preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,79}$/D',$id)||str_starts_with($id,'fwb-'))) { throw new InvalidArgumentException('HTML-ID: Buchstaben, Zahlen, _ und -, ohne Präfix fwb-.'); }
        return ['html_id'=>$id,'classes'=>self::classes((string)($input['classes']??'')),'control_classes'=>self::classes((string)($input['control_classes']??'')),
            'attributes'=>self::attributes((array)($input['attributes']??[])),'control_attributes'=>self::attributes((array)($input['control_attributes']??[]))];
    }
    public static function attrs(array $map): string {
        $html='';foreach ($map as $key=>$value) { if ($value!=='')$html.=' '.$key.'="'.esc_attr((string)$value).'"'; }return $html;
    }
    public static function wrapper(array $b): array {
        $a=$b['appearance']??[];$x=$b['advanced']??[];$classes=['fwb-element'];$css=[];
        foreach (['text_style'=>'uk-text-','text_color'=>'uk-text-','align'=>'uk-text-','shadow'=>'uk-box-shadow-'] as $key=>$prefix) { if (!empty($a[$key]))$classes[]=$prefix.$a[$key]; }
        if (!empty($a['columns']))$classes[]='uk-column-'.$a['columns'].(!empty($a['column_breakpoint'])?'@'.$a['column_breakpoint']:'');
        if (!empty($a['columns'])&&!empty($a['column_divider']))$classes[]='uk-column-divider';
        if (!empty($a['dropcap']))$classes[]='fwb-dropcap';
        foreach (['margin','padding'] as $key) { if (!empty($a[$key]))$classes[]='uk-'.$key.($a[$key]==='default'||($key==='padding'&&$a[$key]==='medium')?'':'-'.$a[$key]); }
        foreach (['color'=>'color','background'=>'background-color','border_color'=>'border-color','border_width'=>'border-width','radius'=>'border-radius','font_size'=>'font-size','line_height'=>'line-height'] as $key=>$prop) {
            if (isset($a[$key])&&$a[$key]!=='')$css[]=$prop.':'.$a[$key].(in_array($key,['border_width','radius','font_size'],true)?'px':'');
        }
        if (isset($a['border_width'])&&$a['border_width']!=='')$css[]='border-style:solid';
        foreach (['input_background','input_color','input_border','button_background','button_color','button_hover','button_hover_color'] as $key) { if (!empty($a[$key]))$css[]='--fwb-'.str_replace('_','-',$key).':'.$a[$key]; }
        if(($b['type']??'')==='submit'&&!empty($a['color'])&&empty($a['button_color'])){
            $css[]='--fwb-button-color:'.$a['color'];$classes[]='fwb-override-button-color';
        }
        foreach (array_merge(self::get(),array_filter($a,static fn($v)=>$v!=='')) as $key=>$value) {
            if(in_array($key,['label_color'],true)&&(!empty($a['color'])||!empty($a['text_color'])))continue;
            if ($value!=='' && in_array($key,['input_background','input_color','input_border','button_background','button_color','button_hover','button_hover_color','field_border_width','field_radius','label_color','help_color'],true))$classes[]='fwb-override-'.str_replace('_','-',$key);
        }
        if (!empty($x['classes']))$classes[]=$x['classes'];
        $attrs=['class'=>implode(' ',$classes),'style'=>implode(';',$css),'id'=>$x['html_id']??''];
        foreach ($x['attributes']??[] as $attr) { $attrs[$attr['name']]=$attr['value']; }
        return $attrs;
    }
    public static function decorate(string $html,array $b,string $uid): string {
        $a=$b['appearance']??[];$x=$b['advanced']??[];$p=new WP_HTML_Tag_Processor($html);
        $help=$a['help']??'';$help_id=$uid.'help-'.$b['id'];
        while ($p->next_tag()) {
            $tag=$p->get_tag();$class=(string)$p->get_attribute('class');
            if (in_array($tag,['INPUT','SELECT','TEXTAREA'],true) || ($tag==='BUTTON'&&$p->get_attribute('type')==='submit')) {
                foreach (explode(' ',$x['control_classes']??'') as $c) { if($c!=='')$p->add_class($c); }
                foreach ($x['control_attributes']??[] as $attr) { $p->set_attribute($attr['name'],$attr['value']); }
                if ($help!=='')$p->set_attribute('aria-describedby',trim(($p->get_attribute('aria-describedby')??'').' '.$help_id));
                if ($tag==='BUTTON') {
                    if (!empty($a['button_style'])) { foreach(['primary','default','secondary','danger','text','link'] as $v)$p->remove_class('uk-button-'.$v);$p->add_class('uk-button-'.$a['button_style']); }
                    if(!empty($a['button_size']))$p->add_class('uk-button-'.$a['button_size']);
                    if(($a['button_width']??'')==='auto')$p->remove_class('uk-width-1-1');
                } else {
                    if(!empty($a['input_size']))$p->add_class('uk-form-'.$a['input_size']);
                    foreach(['placeholder','autocomplete','inputmode'] as $key)if(!empty($a[$key]))$p->set_attribute($key,$a[$key]);
                    if($tag==='TEXTAREA'&&!empty($a['rows']))$p->set_attribute('rows',(int)$a['rows']);
                    if($b['type']==='field'&&!empty($a['maxlength'])&&in_array($b['field']['type'],['text','tel','textarea','email'],true))$p->set_attribute('maxlength',min((int)$a['maxlength'],in_array($b['field']['key'],['name','email'],true)?200:4000));
                }
            }
            $heading=in_array($tag,['H1','H2','H3','H4','H5','H6'],true);
            // Theme form controls and nested plugin utilities do not inherit wrapper typography.
            // Put explicit choices on the actual text-bearing elements.
            $text_target=$heading||in_array($tag,['LABEL','INPUT','SELECT','TEXTAREA','BUTTON'],true)
                || preg_match('/(?:^| )(?:fwb-conditions|fwb-richtext|fwb-quote|fwb-muted|fwb-legend|fwb-weekdays|fwb-selection|fwb-result)(?: |$)/',$class);
            if($text_target){
                if(!empty($a['text_style']) && (!$heading||empty($a['heading_size']))){
                    foreach(['meta','lead','small','large'] as $v)$p->remove_class('uk-text-'.$v);
                    $p->add_class('uk-text-'.$a['text_style']);
                    if($heading)$p->add_class('fwb-heading-text-style');
                }
                if(!empty($a['text_color'])){
                    foreach(['muted','emphasis','primary','secondary','success','warning','danger'] as $v)$p->remove_class('uk-text-'.$v);
                    $p->add_class('uk-text-'.$a['text_color']);
                }
                if(!empty($a['align'])){
                    foreach(['left','center','right','justify'] as $v)$p->remove_class('uk-text-'.$v);
                    $p->add_class('uk-text-'.$a['align']);
                }
                $styles=[];
                foreach(['color','font_size','line_height'] as $key)if(isset($a[$key])&&$a[$key]!==''){
                    $value=$a[$key];
                    if($key==='color'&&in_array($tag,['INPUT','SELECT','TEXTAREA'],true)&&!empty($a['input_color']))$value=$a['input_color'];
                    if($key==='color'&&$tag==='BUTTON'&&$p->get_attribute('type')==='submit')continue;
                    $styles[]=str_replace('_','-',$key).':'.$value.($key==='font_size'?'px':'').' !important';
                }
                if($styles)$p->set_attribute('style',trim((string)$p->get_attribute('style'),';').';'.implode(';',$styles));
            }
            if (!empty($a['heading_size'])&&$heading) {
                foreach(['uk-h1','uk-h2','uk-h3','uk-h4','uk-heading-small','uk-heading-medium'] as $c)$p->remove_class($c);
                $p->add_class($a['heading_size']);
            }
        }
        return $p->get_updated_html().($help!==''?'<p class="fwb-help uk-text-small uk-text-muted" id="'.esc_attr($help_id).'">'.esc_html($help).'</p>':'');
    }
    public static function variables(?array $design=null): string {
        $parts=[];foreach ($design??self::get() as $key=>$value) { if($value==='')continue;$number=isset(self::global_schema()[$key])&&self::global_schema()[$key]['type']==='number';$parts[]='--fwb-'.str_replace('_','-',$key).':'.$value.($number?'px':''); }return implode(';',$parts);
    }
}
