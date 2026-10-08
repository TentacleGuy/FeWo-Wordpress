<?php
define('ABSPATH',__DIR__);define('FWB_DIR','/plugins/ferienwohnung-buchung/');define('FWB_VERSION','1.5.4');define('HOUR_IN_SECONDS',3600);define('MINUTE_IN_SECONDS',60);
$cache=false;$calls=0;$response=['code'=>200];
function plugin_basename($p){return 'ferienwohnung-buchung/ferienwohnung-buchung.php';}
function get_site_transient($key){global $cache;return $cache;}
function set_site_transient($key,$v,$ttl){global $cache;$cache=$v;}
function wp_remote_get($url,$args){global $calls,$response;$calls++;return $response;}
function is_wp_error($r){return false;}
function wp_remote_retrieve_response_code($r){return $r['code'];}
function wp_remote_retrieve_body($r){return $r['body']??'';}
function esc_html($v){return htmlspecialchars($v,ENT_QUOTES);}
require __DIR__.'/../ferienwohnung-buchung/includes/updater.php';
$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException($message);$n++;echo "OK: $message\n";}
$r=['tag_name'=>'v1.5.5','draft'=>false,'prerelease'=>false,'body'=>'<script>alert(1)</script>','assets'=>[['name'=>'ferienwohnung-buchung-1.5.5.zip','state'=>'uploaded','browser_download_url'=>FWB_Updater::REPOSITORY.'/releases/download/v1.5.5/ferienwohnung-buchung-1.5.5.zip']]];
verify(FWB_Updater::parse($r)['version']==='1.5.5','Stable release selects matching asset');
foreach(['draft','prerelease'] as $flag){$bad=$r;$bad[$flag]=true;verify(FWB_Updater::parse($bad)===null,$flag.' ignored');}
$bad=$r;$bad['assets']=[];verify(FWB_Updater::parse($bad)===null,'Source archive alone is ignored');
$bad=$r;$bad['assets'][0]['browser_download_url']='https://example.com/plugin.zip';verify(FWB_Updater::parse($bad)===null,'Foreign package URL rejected');
$bad=$r;$bad['tag_name']='v1.5.5-beta';verify(FWB_Updater::parse($bad)===null,'Nonstable version tag rejected');
$response['body']=json_encode($r);
verify(FWB_Updater::update('untouched',[],'other/plugin.php',[])==='untouched'&&$calls===0,'Other GitHub plugins unaffected');
$u=FWB_Updater::update(false,[],'ferienwohnung-buchung/ferienwohnung-buchung.php',[]);
verify(version_compare($u['version'],FWB_VERSION,'>')&&$u['package']===$r['assets'][0]['browser_download_url'],'Newer version delivered to WordPress');
FWB_Updater::update(false,[],'ferienwohnung-buchung/ferienwohnung-buchung.php',[]);verify($calls===1,'Release cache avoids duplicate requests');
$info=FWB_Updater::info(false,'plugin_information',(object)['slug'=>'ferienwohnung-buchung']);verify(!str_contains($info->sections['changelog'],'<script>'),'Release notes escaped');
$cache=false;$response=['code'=>403];verify(FWB_Updater::update(false,[],'ferienwohnung-buchung/ferienwohnung-buchung.php',[])===false,'Rate limit yields no broken update');
$before=$calls;FWB_Updater::update(false,[],'ferienwohnung-buchung/ferienwohnung-buchung.php',[]);verify($before===$calls,'Failed requests cached briefly');
$cache=false;$response=['code'=>200,'body'=>'invalid json'];verify(FWB_Updater::update(false,[],'ferienwohnung-buchung/ferienwohnung-buchung.php',[])===false,'Malformed response handled');
echo "$n updater checks passed.\n";
