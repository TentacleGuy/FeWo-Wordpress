<?php
/** GitHub release updates using WordPress' native plugin updater. */
defined('ABSPATH') || exit;
final class FWB_Updater {
    public const REPOSITORY='https://github.com/TentacleGuy/FeWo-Wordpress';
    private const API='https://api.github.com/repos/TentacleGuy/FeWo-Wordpress/releases/latest';
    private const CACHE='fwb_github_release_v1';
    public static function boot(): void {
        add_filter('update_plugins_github.com',[self::class,'update'],10,4);
        add_filter('plugins_api',[self::class,'info'],10,3);
        add_action('load-update-core.php',static function(){
            if(current_user_can('update_plugins')&&isset($_GET['force-check']))delete_site_transient(self::CACHE);
        });
    }
    public static function parse(array $release): ?array {
        if(!empty($release['draft'])||!empty($release['prerelease']))return null;
        $tag=$release['tag_name']??'';
        if(!is_string($tag)||!preg_match('/^v?(\d+\.\d+\.\d+)$/D',$tag,$match))return null;
        $version=$match[1];$filename='ferienwohnung-buchung-'.$version.'.zip';
        foreach(($release['assets']??[]) as $asset){
            if(!is_array($asset)||($asset['name']??'')!==$filename||($asset['state']??'')!=='uploaded')continue;
            $url=$asset['browser_download_url']??'';
            // Accept only the exact release asset in the configured repository.
            if($url!==self::REPOSITORY.'/releases/download/'.$tag.'/'.$filename)continue;
            return ['version'=>$version,'package'=>$url,'url'=>self::REPOSITORY.'/releases/tag/'.$tag,'notes'=>is_string($release['body']??null)?$release['body']:''];
        }
        return null;
    }
    private static function release(): ?array {
        $cached=get_site_transient(self::CACHE);
        if($cached!==false)return is_array($cached)&&isset($cached['version'])?$cached:null;
        $response=wp_remote_get(self::API,[
            'timeout'=>10,'limit_response_size'=>1048576,
            'headers'=>['Accept'=>'application/vnd.github+json','User-Agent'=>'Ferienwohnung-Buchung/'.FWB_VERSION],
        ]);
        $release=null;
        if(!is_wp_error($response)&&wp_remote_retrieve_response_code($response)===200){
            $data=json_decode(wp_remote_retrieve_body($response),true);
            if(is_array($data))$release=self::parse($data);
        }
        // Cache failures briefly to avoid repeated requests during an outage/rate limit.
        set_site_transient(self::CACHE,$release??[], $release?HOUR_IN_SECONDS:10*MINUTE_IN_SECONDS);
        return $release;
    }
    public static function update($update,array $plugin_data,string $plugin_file,array $locales) {
        if($plugin_file!==plugin_basename(FWB_DIR.'ferienwohnung-buchung.php'))return $update;
        $release=self::release();
        if(!$release)return false;
        return ['id'=>self::REPOSITORY,'slug'=>'ferienwohnung-buchung','version'=>$release['version'],'url'=>$release['url'],'package'=>$release['package']];
    }
    public static function info($result,string $action,$args) {
        if($action!=='plugin_information'||($args->slug??'')!=='ferienwohnung-buchung')return $result;
        $release=self::release();if(!$release)return $result;
        return (object)[
            'name'=>'Ferienwohnung Buchung','slug'=>'ferienwohnung-buchung','version'=>$release['version'],
            'homepage'=>self::REPOSITORY,'download_link'=>$release['package'],
            'sections'=>['description'=>'Belegungskalender, Buchungsanfragen, E-Mails und PDF-Rechnungen für eine Ferienwohnung.',
                'changelog'=>'<p>'.nl2br(esc_html($release['notes'])).'</p>'],
        ];
    }
}
