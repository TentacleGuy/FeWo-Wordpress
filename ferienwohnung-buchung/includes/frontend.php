<?php
defined('ABSPATH') || exit;
final class FWB_Frontend {
    private static bool $styles_loaded = false;
    public static function boot(): void {
        // Builder shortcodes need CSS in the head, even outside post_content.
        add_action('wp_enqueue_scripts',[self::class,'styles'],99);
        add_shortcode('ferienwohnung_buchung',[self::class,'shortcode']);
        add_shortcode('ferienwohnung_kalender',static function () { self::assets(); return self::calendar_markup(); });
    }
    public static function styles(): void {
        if (self::$styles_loaded) { return; }
        self::$styles_loaded = true;
        wp_enqueue_style('fwb-front',FWB_URL . 'assets/frontend.css',[],FWB_VERSION);
        $css=FWB_Settings::get()['custom_css'];
        if ($css !== '') { wp_add_inline_style('fwb-front',FWB_Settings::sanitize_css($css)); }
    }
    private static function assets(): void {
        self::styles();
        wp_enqueue_script('fwb-front',FWB_URL . 'assets/frontend.js',[],FWB_VERSION,true);
    }

    public static function calendar_markup(array $block=[]): string {
        $labels=array_merge(['free_label'=>FWB_I18n::t("Frei"),'busy_label'=>FWB_I18n::t("Belegt"),'half_label'=>FWB_I18n::t("An-/Abreise"),'selected_label'=>FWB_I18n::t("Ausgewählt")], $block['calendar_labels']??[]);
        $note=$block['content']??FWB_I18n::t("Halbe Markierung: Vormittag bzw. Nachmittag belegt. Ein Gästewechsel am selben Tag ist möglich.");
        $html='<section'.FWB_I18n::attrs().' class="fwb-calendar" style="' . esc_attr(FWB_Design::variables()) . '" data-api="' . esc_url(FWB_I18n::api_url()) . '" data-today="' . esc_attr(current_time('Y-m-d')) . '">';
        if (!empty($block['label'])) { $html.='<h3 class="uk-h4">' . esc_html($block['label']) . '</h3>'; }
        $html.='<div class="fwb-calendar-top uk-flex uk-flex-middle uk-flex-between uk-margin-bottom"><button type="button" class="fwb-prev uk-button uk-button-default uk-button-small" aria-label="' . esc_html(FWB_I18n::t("Vorheriger Monat")) . '">←</button><h3 class="fwb-month uk-h4 uk-margin-remove uk-text-center" aria-live="polite"></h3><button type="button" class="fwb-next uk-button uk-button-default uk-button-small" aria-label="' . esc_html(FWB_I18n::t("Nächster Monat")) . '">→</button></div><div class="fwb-weekdays uk-text-small uk-text-muted uk-margin-small-bottom" aria-hidden="true"><span>Mo</span><span>Di</span><span>Mi</span><span>Do</span><span>Fr</span><span>Sa</span><span>So</span></div><div class="fwb-days"></div><p class="fwb-selection uk-text-small" aria-live="polite"></p><p class="fwb-calendar-error uk-text-small" role="status"></p><div class="fwb-legend uk-flex uk-flex-wrap uk-text-small uk-margin-top">';
        foreach (['free','busy','half','selected'] as $state) { $html.='<span class="uk-margin-small-right"><i class="' . $state . '"></i> ' . esc_html($labels[$state.'_label']) . '</span>'; }
        return $html.'</div>' . ($note!==''?'<p class="fwb-muted uk-text-small uk-text-muted uk-margin-small-bottom">'.nl2br(esc_html($note),false).'</p>':'') . '</section>';
    }
    private static function field(array $f, string $uid): string {
        $id=$uid.$f['key'];
        $attrs=' id="'.esc_attr($id).'" name="field_'.esc_attr($f['key']).'" data-field="'.esc_attr($f['key']).'"'.($f['required']?' required':'');
        $label=esc_html($f['label']).($f['required']?' *':'');
        if ($f['type']==='checkbox') { return '<label class="fwb-check uk-flex uk-flex-top uk-text-small"><input class="uk-checkbox uk-margin-small-right" type="checkbox" value="1"'.$attrs.'><span>'.$label.'</span></label>'; }
        $html='<label class="uk-form-label" for="'.esc_attr($id).'">'.$label.'</label><div class="uk-form-controls">';
        if ($f['type']==='textarea') { $html.='<textarea class="uk-textarea" rows="3" maxlength="4000"'.$attrs.'></textarea>'; }
        elseif ($f['type']==='select') {
            $html.='<select class="uk-select"'.$attrs.'><option value="">' . esc_html(FWB_I18n::t("Bitte wählen")) . '</option>';
            foreach ($f['options'] as $option) { $html.='<option>'.esc_html($option).'</option>'; }
            $html.='</select>';
        } else { $html.='<input class="uk-input" type="'.esc_attr($f['type']).'" maxlength="'.(in_array($f['key'],['name','email'],true)?200:4000).'"'.$attrs.'>'; }
        return $html.'</div>';
    }
    private static function block(array $b, array $s, string $uid): string {
        $type=$b['type']; $label=$b['label']; $content=$b['content'];
        if ($type==='calendar') { return self::calendar_markup($b); }
        if ($type==='conditions' || $type==='text') {
            return '<div class="'.($type==='conditions'?'fwb-conditions uk-text-small':'fwb-richtext').'">' .
                ($label!==''?'<h3 class="uk-h4">'.esc_html($label).'</h3>':'') . FWB_Layout::html($content) . '</div>';
        }
        if ($type==='heading') { $tag=$b['level']??'h3'; return '<'.$tag.' class="uk-margin-remove">'.FWB_Layout::html(esc_html($label)).'</'.$tag.'>'; }
        if (in_array($type,['arrival','departure','guests','taxable_guests'],true)) {
            $date=in_array($type,['arrival','departure'],true); $id=$uid.$type;
            return '<label class="uk-form-label" for="'.esc_attr($id).'">'.esc_html($label).'</label><div class="uk-form-controls"><input class="uk-input" id="'.esc_attr($id).'" name="'.$type.'" type="'.($date?'date':'number').'" ' .
                ($date?'min="'.esc_attr(current_time('Y-m-d')).'"':'value="'.min(2,(int)$s['max_guests']).'" min="'.($type==='guests'?1:0).'" max="'.(int)$s['max_guests'].'"') . ' required></div>';
        }
        if ($type==='field') { return self::field($b['field'],$uid); }
        if ($type==='quote') {
            return ($label!==''?'<h3 class="uk-h4">'.esc_html($label).'</h3>':'').'<div class="fwb-quote uk-placeholder uk-padding-small uk-text-small" aria-live="polite" data-summary="'.esc_attr($b['summary_template']??'{total} für {nights} Nächte').'" data-detail="'.esc_attr($b['detail_template']??'Anzahlung {deposit} · Restbetrag {balance}. Ortstaxe separat vor Ort: {local_tax}.').'" data-empty="'.esc_attr($content).'">'.esc_html($content).'</div>';
        }
        if ($type==='consent') { return '<label class="fwb-check uk-flex uk-flex-top uk-text-small"><input class="uk-checkbox uk-margin-small-right" type="checkbox" name="consent" required><span>'.FWB_Layout::html(esc_html($label)).'</span></label>'; }
        if ($type==='submit') { return '<button class="fwb-submit uk-button uk-button-primary uk-width-1-1" type="submit">'.esc_html($label).' <span aria-hidden="true">→</span></button><p class="fwb-result uk-text-small" role="status" tabindex="-1"></p>'; }
        return '';
    }
    private static function zone(array $blocks, array $s, string $uid): string {
        if (!$blocks) { return ''; }
        $html='<div class="fwb-form-grid uk-grid uk-grid-small" uk-grid>';
        foreach ($blocks as $b) {
            if (!empty($b['hidden'])) continue;
            $appearance=$b['appearance']??[];
            $width=['full'=>'1-1','half'=>'1-2','third'=>'1-3','two_thirds'=>'2-3'][$b['width']]??'1-1';
            $classes='fwb-block uk-width-'.$width;
            foreach (['s','m','l'] as $bp) { if(!empty($appearance['width_'.$bp]))$classes.=' uk-width-'.$appearance['width_'.$bp].'@'.$bp; }
            $tag=$appearance['tag']??'';if($tag==='')$tag='div';
            $html.='<div class="'.esc_attr($classes).'" data-block="'.esc_attr($b['id']).'"><'.$tag.FWB_Design::attrs(FWB_Design::wrapper($b)).'>'.FWB_Design::decorate(self::block($b,$s,$uid),$b,$uid).'</'.$tag.'></div>';
        }
        return $html.'</div>';
    }
    public static function shortcode(): string {
        self::assets(); $s=FWB_I18n::settings(); $uid=wp_unique_id('fwb-'); $zones=FWB_Layout::get()['zones'];
        $html='<section'.FWB_I18n::attrs().' class="fwb-widget uk-width-1-1" style="' . esc_attr(FWB_Design::variables()) . '" data-worker="'.esc_url(FWB_URL.'assets/pow-worker.js').'" data-api="'.esc_url(FWB_I18n::api_url()).'"><form class="fwb-request uk-form-stacked">';
        if ($zones['top']) { $html.='<div class="fwb-title uk-margin-medium-bottom">'.self::zone($zones['top'],$s,$uid).'</div>'; }
        $html.='<div class="fwb-layout uk-grid uk-grid-medium uk-child-width-1-1 uk-child-width-1-2@m" uk-grid>';
        foreach (['left','right'] as $column) { $html.='<div class="fwb-column">'.self::zone($zones[$column],$s,$uid).'</div>'; }
        $html.='</div>';
        if ($zones['bottom']) { $html.='<div class="fwb-bottom uk-margin-top">'.self::zone($zones['bottom'],$s,$uid).'</div>'; }
        $html.='<div class="fwb-honey" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div><noscript>' . esc_html(FWB_I18n::t("Für Kalender, Preisberechnung und Spam-Schutz ist JavaScript erforderlich.")) . '</noscript></form></section>';
        return (string)preg_replace('/>\s+</','><',str_replace(["\r","\n"],'',$html));
    }
}
