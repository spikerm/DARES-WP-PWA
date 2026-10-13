<?php
if (!defined('ABSPATH')) exit;
/**
 * Downloads the GitHub source archive, then repacks it into a real
 * WordPress plugin archive before the upgrader validates the plugin.
 */
final class Dares_Piket_GitHub_Updater {
 const REPO='spikerm/DARES-WP-PWA';
 const PLUGIN='dares-piketrooster/dares-piketrooster.php';
 const PACKAGE='https://github.com/spikerm/DARES-WP-PWA/archive/refs/heads/main.zip';
 static function init(){
  add_filter('pre_set_site_transient_update_plugins',[__CLASS__,'check']);
  add_filter('upgrader_pre_download',[__CLASS__,'package'],10,4);
 }
 static function remote($path){
  $url='https://raw.githubusercontent.com/'.self::REPO.'/main/'.ltrim($path,'/');
  $r=wp_safe_remote_get($url,['timeout'=>15,'redirection'=>2]);
  return !is_wp_error($r)&&wp_remote_retrieve_response_code($r)===200?wp_remote_retrieve_body($r):'';
 }
 static function check($transient){
  if(!is_object($transient)||!isset($transient->checked[self::PLUGIN]))return $transient;
  $remote=get_transient('dares_piket_github_version');
  if($remote===false){
   $body=self::remote('dares-piketrooster.php');
   $remote=(preg_match('/^\s*\*\s*Version:\s*(\d+\.\d+\.\d+)/mi',$body,$m)&&strpos($body,'Dares_Piketrooster')!==false)?$m[1]:'';
   set_transient('dares_piket_github_version',$remote,300);
  }
  $installed=$transient->checked[self::PLUGIN];
  if($remote&&version_compare($remote,$installed,'>')){
   $transient->response[self::PLUGIN]=(object)[
    'slug'=>'dares-piketrooster','plugin'=>self::PLUGIN,
    'new_version'=>$remote,'url'=>'https://github.com/'.self::REPO,
    'package'=>self::PACKAGE
   ];
  }
  return $transient;
 }
 static function package($reply,$package,$upgrader,$hook_extra){
  if($reply!==false||$package!==self::PACKAGE)return $reply;
  if(!current_user_can('update_plugins'))return new WP_Error('dares_updater_permission','Geen toestemming voor pluginupdates.');
  if(!class_exists('ZipArchive'))return new WP_Error('dares_updater_zip','PHP ZipArchive-extensie ontbreekt.');
  if(!function_exists('download_url'))require_once ABSPATH.'wp-admin/includes/file.php';
  $source=download_url($package,45);
  if(is_wp_error($source))return $source;
  $in=new ZipArchive();
  if($in->open($source)!==true){@unlink($source);return new WP_Error('dares_updater_archive','GitHub ZIP kan niet worden geopend.');}
  $outname=wp_tempnam('dares-piketrooster-update.zip');
  if(!$outname){$in->close();@unlink($source);return new WP_Error('dares_updater_temp','Geen tijdelijk ZIP-bestand beschikbaar.');}
  $out=new ZipArchive();
  if($out->open($outname,ZipArchive::OVERWRITE)!==true){$in->close();@unlink($source);@unlink($outname);return new WP_Error('dares_updater_open','Kan het installatiepakket niet aanmaken.');}
  $count=0;$main=false;$bytes=0;$invalid=false;
  for($i=0;$i<$in->numFiles;$i++){
   $stat=$in->statIndex($i);$full=$stat['name']??'';
   if(substr($full,-1)==='/')continue;
   $parts=explode('/',$full);
   array_shift($parts); // GitHub always wraps the repository in one directory.
   if(!$parts||in_array('', $parts,true)||count($parts)>8){$invalid=true;break;}
   foreach($parts as $part)if($part==='.'||$part==='..'||$part[0]==='.'||!preg_match('/^[a-zA-Z0-9._-]+$/D',$part)){$invalid=true;break;}
   if($invalid)break;
   $relative=implode('/',$parts);
   $size=(int)($stat['size']??0);
   $bytes+=$size;
   if($size>2*1024*1024||$bytes>12*1024*1024||++$count>100){$invalid=true;break;}
   $data=$in->getFromIndex($i);
   if($data===false||!$out->addFromString('dares-piketrooster/'.$relative,$data)){$invalid=true;break;}
   if($relative==='dares-piketrooster.php'&&strpos($data,'Plugin Name: Dares Piketrooster')!==false)$main=true;
  }
  $out->close();$in->close();@unlink($source);
  if($invalid||!$main||$count<5){@unlink($outname);return new WP_Error('dares_updater_invalid','GitHub bevat geen compleet geldig DARES-pluginpakket.');}
  return $outname;
 }
}
