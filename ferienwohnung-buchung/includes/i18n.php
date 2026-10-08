<?php
defined('ABSPATH') || exit;
/** Language-specific content; structural configuration always belongs to the source language. */
final class FWB_I18n {
    private static ?string $context=null;
    public static function boot(): void {
        add_action('admin_init',[self::class,'register']);
        add_filter('pll_admin_languages_filter',static function($items,$all){$page=$_GET['page']??'';return is_string($page)&&in_array($page,['fwb-form','fwb-templates'],true)?$all:$items;},10,2);
    }
    public static function languages(): array {
        if(!function_exists('pll_languages_list'))return ['de'=>['name'=>'Deutsch','locale'=>'de_DE']];
        $slugs=pll_languages_list(['fields'=>'slug','hide_empty'=>false]);
        $names=pll_languages_list(['fields'=>'name','hide_empty'=>false]);
        $locales=pll_languages_list(['fields'=>'locale','hide_empty'=>false]);$out=[];
        foreach($slugs as $i=>$slug)$out[$slug]=['name'=>$names[$i]??$slug,'locale'=>$locales[$i]??$slug];
        return $out?:['de'=>['name'=>'Deutsch','locale'=>'de_DE']];
    }
    public static function source(): string {
        $saved=get_option('fwb_source_language','');if($saved)return $saved;
        $languages=self::languages();
        foreach($languages as $slug=>$info)if(str_starts_with($info['locale'],'de'))return $slug;
        return function_exists('pll_default_language')?(pll_default_language()?:array_key_first($languages)):'de';
    }
    public static function validate($lang): string {
        if(!is_string($lang)||!isset(self::languages()[$lang]))throw new InvalidArgumentException('Ungültige Sprache.');return $lang;
    }
    public static function current(): string {
        if(self::$context!==null)return self::$context;
        if(is_admin()){
            if(isset($_GET['lang'])&&is_string($_GET['lang']))return isset(self::languages()[$_GET['lang']])?$_GET['lang']:self::source();
            $lang=function_exists('pll_current_language')?pll_current_language():false;
            if(!$lang&&function_exists('pll_languages_list'))$lang=get_user_meta(get_current_user_id(),'pll_filter_content',true);
        }else $lang=function_exists('pll_current_language')?pll_current_language():false;
        return is_string($lang)&&isset(self::languages()[$lang])?$lang:self::source();
    }
    public static function set(?string $lang): ?string { $old=self::$context;self::$context=$lang;return $old; }
    public static function run(string $lang,callable $fn){$old=self::set($lang);try{return $fn();}finally{self::set($old);}}
    public static function foreign(): bool { return self::current()!==self::source(); }
    public static function locale(?string $lang=null): string { return self::languages()[$lang??self::current()]['locale']??'de_DE'; }
    public static function date(string $value): string {
        $date=FWB_Domain::date(substr($value,0,10));
        if(str_starts_with(self::locale(),'de'))return $date->format('d.m.Y');
        if(class_exists('IntlDateFormatter')){$f=new IntlDateFormatter(self::locale(),IntlDateFormatter::SHORT,IntlDateFormatter::NONE,'UTC');return (string)$f->format($date);}
        return $date->format('Y-m-d');
    }
    public static function booking(array $b): string { return $b['data']['language']??self::source(); }
    public static function records(string $group,?string $lang=null): array { return (array)get_option('fwb_i18n_'.($lang??self::current()).'_'.$group,[]); }
    public static function english(): array {
        static $map=null;return $map??=require FWB_DIR.'includes/english.php';
    }
    public static function builtin(string $text,?string $lang=null): ?string {
        if(!preg_match('/^en(?:[_-]|$)/i',self::locale($lang)))return null;
        $map=self::english();if(isset($map[$text]))return $map[$text];
        // Retain all markup and placeholders. Translate only recognised text, never guess custom terms.
        $parts=preg_split('/(<[^>]*>)/u',$text,-1,PREG_SPLIT_DELIM_CAPTURE);$translated='';
        foreach($parts as $part){
            if($part===''||$part[0]==='<'){$translated.=$part;continue;}
            $decoded=html_entity_decode($part,ENT_QUOTES|ENT_HTML5,'UTF-8');
            $key=trim(preg_replace('/\s+/u',' ',$decoded));
            if(isset($map[$key])){
                $value=str_contains($text,'<')?esc_html($map[$key]):$map[$key];
                preg_match('/^(\s*).*?(\s*)$/us',$part,$space);$translated.=($space[1]??'').$value.($space[2]??'');
            }elseif(!preg_match('/\p{L}/u',preg_replace('/\{[a-z_]+\}/','',$decoded))){$translated.=$part;}
            else return null;
        }
        return $translated;
    }
    public static function overlay(array $base,string $group): array {
        if(!self::foreign())return $base;$saved=self::records($group);
        foreach($base as $key=>&$value){
            if(isset($saved[$key]['value'])&&$saved[$key]['value']!==$value)$value=$saved[$key]['value'];
            else $value=self::builtin($value)??$value;
        }unset($value);return $base;
    }
    public static function save(array $base,array $values,string $group): void {
        $saved=self::records($group);$out=[];
        foreach($base as $key=>$source){
            $value=$values[$key]??$source;
            // Untouched fallback copy stays untranslated; saving another field cannot freeze it.
            if($value!==$source||isset($saved[$key]))$out[$key]=['source'=>$source,'value'=>$value];
        }
        update_option('fwb_i18n_'.self::current().'_'.$group,$out,false);
    }
    public static function texts(array $layout): array {
        $out=[];foreach($layout['zones'] as $blocks)foreach($blocks as $b){
            foreach(['label','content','summary_template','detail_template'] as $key)if(isset($b[$key]))$out[$b['id'].'/'.$key]=$b[$key];
            foreach($b['calendar_labels']??[] as $key=>$value)$out[$b['id'].'/calendar_labels/'.$key]=$value;
            foreach(['placeholder','help'] as $key)if(isset($b['appearance'][$key]))$out[$b['id'].'/appearance/'.$key]=$b['appearance'][$key];
            foreach($b['field']['options']??[] as $i=>$value)$out[$b['id'].'/options/'.$i]=$value;
            foreach(['attributes','control_attributes'] as $group)foreach($b['advanced'][$group]??[] as $i=>$attr)if(in_array($attr['name'],['title','aria-label','aria-description','uk-tooltip'],true))$out[$b['id'].'/'.$group.'/'.$i]=$attr['value'];
        }return $out;
    }
    public static function with_texts(array $layout,array $texts): array {
        foreach($layout['zones'] as &$blocks)foreach($blocks as &$b){
            foreach(['label','content','summary_template','detail_template'] as $key)if(isset($texts[$b['id'].'/'.$key]))$b[$key]=$texts[$b['id'].'/'.$key];
            foreach($b['calendar_labels']??[] as $key=>$value)$b['calendar_labels'][$key]=$texts[$b['id'].'/calendar_labels/'.$key]??$value;
            foreach(['placeholder','help'] as $key)if(isset($texts[$b['id'].'/appearance/'.$key]))$b['appearance'][$key]=$texts[$b['id'].'/appearance/'.$key];
            if(isset($b['field'])){$b['field']['label']=$b['label'];foreach($b['field']['options']??[] as $i=>$value)$b['field']['options'][$i]=$texts[$b['id'].'/options/'.$i]??$value;}
            foreach(['attributes','control_attributes'] as $group)foreach($b['advanced'][$group]??[] as $i=>$attr)if(in_array($attr['name'],['title','aria-label','aria-description','uk-tooltip'],true))$b['advanced'][$group][$i]['value']=$texts[$b['id'].'/'.$group.'/'.$i]??$attr['value'];
        }unset($blocks,$b);return $layout;
    }
    public static function layout(array $base): array { return self::with_texts($base,self::overlay(self::texts($base),'layout')); }
    public static function save_layout(array $layout): void {
        $base=FWB_Layout::base();$base=FWB_Layout::validate(wp_json_encode($base))['layout'];
        $texts=self::texts($layout);$projected=self::with_texts($base,$texts);
        if($projected!=$layout)throw new InvalidArgumentException('Aufbau, Optionenanzahl und Design bitte in der Ausgangssprache ändern.');
        self::save(self::texts($base),$texts,'layout');
    }
    public static function base_templates(): array { return array_merge(FWB_Settings::template_defaults(),(array)get_option('fwb_templates',[])); }
    public static function formats(): array {
        $formats=(array)get_option('fwb_template_formats',[]);
        if(self::foreign())foreach(['request','confirmed','cancelled','rejected','invoice'] as $kind)if(isset(self::records('templates')[$kind.'_body']))$formats[$kind]='html';
        return $formats;
    }
    public static function signature(): string { return self::overlay(['signature'=>(string)get_option('fwb_mail_signature','')],'templates')['signature']; }
    public static function save_templates(array $t): void {
        if(self::foreign()){
            $base=self::run(self::source(),fn()=>FWB_Designer::templates());self::save($base,$t,'templates');
        }else{
            update_option('fwb_mail_signature',$t['signature'],false);unset($t['signature']);update_option('fwb_templates',$t,false);
            update_option('fwb_template_formats',array_fill_keys(['request','confirmed','cancelled','rejected','invoice'],'html'),false);
        }
    }
    public const GLOBALS=['property_name'=>'Unterkunftsname','rules'=>'Hausregeln','tax_note'=>'Ortstaxenhinweis','invoice_note'=>'Rechnungshinweis','privacy_url'=>'Datenschutz-Link'];
    public static function settings(): array {
        $s=FWB_Settings::get();return array_replace($s,self::overlay(array_intersect_key($s,self::GLOBALS),'settings'));
    }
    public static function register(): void {
        if(!function_exists('pll_register_string'))return;
        if(!pll_languages_list(['hide_empty'=>false]))return;
        if(!get_option('fwb_source_language'))update_option('fwb_source_language',self::source(),false);
        foreach(self::catalog() as $string)pll_register_string('fwb_'.substr(hash('sha256',$string),0,12),$string,'Ferienwohnung · Systemtexte',true);
        self::seed_english_strings();
    }
    public static function seed_english_strings(): void {
        // Optional Polylang persistence makes the supplied copy visible in its string editor.
        // The runtime fallback also works if this internal storage adapter is unavailable.
        if(!current_user_can('manage_options')||!function_exists('PLL')||!class_exists('PLL_MO')||!class_exists('Translation_Entry'))return;
        if(!method_exists('PLL_MO','import_from_db')||!method_exists('PLL_MO','export_to_db'))return;
        $version=hash('sha256',wp_json_encode(self::english()));
        foreach(self::languages() as $slug=>$info){
            if(!preg_match('/^en(?:[_-]|$)/i',$info['locale'])||get_option('fwb_english_strings_'.$slug)===$version)continue;
            $language=PLL()->model->get_language($slug);if(!$language)continue;
            $mo=new PLL_MO();$mo->import_from_db($language);
            foreach(self::catalog() as $text){
                $value=self::builtin($text,$slug);$existing=$mo->translate($text);
                if($value!==null&&($existing===$text||$existing===''))$mo->add_entry(new Translation_Entry(['singular'=>$text,'translations'=>[$value]]));
            }
            $mo->export_to_db($language);update_option('fwb_english_strings_'.$slug,$version,false);
        }
    }
    public static function catalog(): array { return require FWB_DIR.'includes/strings.php'; }
    public static function t(string $text,array $values=[]): string {
        $translated=function_exists('pll_translate_string')?pll_translate_string($text,self::current()):$text;
        if($translated===$text&&self::foreign())$translated=self::builtin($text)??$text;
        return strtr($translated,$values);
    }
    public static function error(string $text): string {
        foreach(['Bitte ausfüllen: ','Zu viele Zeichen: '] as $prefix)if(str_starts_with($text,$prefix))return self::t($prefix.'{field}',['{field}'=>substr($text,strlen($prefix))]);
        if(preg_match('/^Mindestaufenthalt: (\d+) Nächte; maximal 90 Nächte\.$/u',$text,$m))return self::t('Mindestaufenthalt: {nights} Nächte; maximal 90 Nächte.',['{nights}'=>$m[1]]);
        return self::t($text);
    }
    public static function attrs(): string {
        $messages=[];foreach(self::catalog() as $s)$messages[$s]=self::t($s);
        return ' lang="'.esc_attr(str_replace('_','-',self::locale())).'" data-language="'.esc_attr(self::current()).'" data-locale="'.esc_attr(str_replace('_','-',self::locale())).'" data-i18n="'.esc_attr(wp_json_encode($messages)).'"';
    }
    public static function api_url(): string { return add_query_arg('fwb_lang',self::current(),rest_url('fwb/v1/')); }
    public static function bar(string $section): void {
        if(!function_exists('pll_languages_list'))return;
        $lang=self::current();$langs=self::languages();
        echo '<div class="fwb-card fwb-language-bar"><strong>Du bearbeitest: '.esc_html($langs[$lang]['name']??$lang).'</strong><p>';
        foreach($langs as $slug=>$info)echo '<a class="button '.($slug===$lang?'button-primary':'').'" href="'.esc_url(add_query_arg('lang',$slug,FWB_Admin::url($section))).'">'.esc_html($info['name']).'</a> ';
        echo '</p><p>Ausgangssprache: '.esc_html($langs[self::source()]['name']??self::source()).'. Aufbau und Design werden dort für alle Sprachen gepflegt. Übersetzungen und lokale Entwürfe bleiben je Sprache getrennt. Systemmeldungen: <a href="'.esc_url(admin_url('admin.php?page=mlang_strings')).'">Polylang-Stringübersetzungen</a>.</p>';
        if(self::foreign()){
            $base=$section==='form'?self::texts(FWB_Layout::base()):self::run(self::source(),fn()=>FWB_Designer::templates());$group=$section==='form'?'layout':'templates';$saved=self::records($group);$missing=[];
            foreach($base as $key=>$value)if($value!==''&&(isset($saved[$key])?$saved[$key]['source']!==$value:self::builtin($value)===null))$missing[]=$key;
            if(preg_match('/^en(?:[_-]|$)/i',self::locale()))echo '<p>Englische Standardtexte sind bereits enthalten und können hier bearbeitet werden. Eigene Übersetzungen haben Vorrang. Individuelle, unbekannte Ausgangstexte werden unten zur Prüfung aufgelistet.</p>';
            echo '<details><summary>'.count($missing).' Texte fehlen oder Ausgangstext geändert</summary><p>Für bekannte Texte wird die mitgelieferte Übersetzung verwendet. Andere Texte verwenden die Ausgangssprache. Geänderte Ausgangstexte behalten ihre bisherige Übersetzung zur Überprüfung.</p><ul>';
            foreach($missing as $key)echo '<li>'.esc_html($key).'</li>';echo '</ul></details>';
        }
        $s=self::settings();echo '<details><summary>Gemeinsame Textbausteine und Datenschutz-Link übersetzen</summary><div id="fwb-language-settings">';
        $globalRecords=self::records('settings');$sourceSettings=FWB_Settings::get();
        foreach(self::GLOBALS as $key=>$title){
            $pending=self::foreign()&&$sourceSettings[$key]!==''&&(isset($globalRecords[$key])?$globalRecords[$key]['source']!==$sourceSettings[$key]:self::builtin($sourceSettings[$key])===null)&&!in_array($key,['property_name','privacy_url'],true);
            echo '<p><label>'.esc_html($title).($pending?' · Übersetzung fehlt / prüfen':'').'<textarea class="large-text" data-key="'.$key.'" rows="2">'.esc_textarea($s[$key]).'</textarea></label></p>';
        }
        echo '<button type="button" class="button" id="fwb-language-save">Textbausteine speichern</button><p role="status" id="fwb-language-status"></p></div></details></div>';
    }
}
