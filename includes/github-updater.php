<?php
if (!defined('ABSPATH')) exit;
final class Dares_Piket_GitHub_Updater {
 const REPO='spikerm/DARES-WP-PWA'; const PLUGIN='dares-piketrooster/dares-piketrooster.php';
 static function init(){add_filter('pre_set_site_transient_update_plugins',[__CLASS__,'check']);add_filter('upgrader_source_selection',[__CLASS__,'source'],1,4);}
 static function remote($path){$url='https://raw.githubusercontent.com/'.self::REPO.'/main/'.ltrim($path,'/');$r=wp_safe_remote_get($url,['timeout'=>12,'redirection'=>2]);return !is_wp_error($r)&&wp_remote_retrieve_response_code($r)===200?wp_remote_retrieve_body($r):'';}
 static function check($transient){if(!is_object($transient)||!isset($transient->checked[self::PLUGIN]))return $transient;
  $remote=get_transient('dares_piket_github_version');if($remote===false){$body=self::remote('dares-piketrooster.php');$remote=(preg_match('/^\s*\*\s*Version:\s*(\d+\.\d+\.\d+)/mi',$body,$m)&&strpos($body,'Dares_Piketrooster')!==false)?$m[1]:'';set_transient('dares_piket_github_version',$remote,300);}
  $installed=$transient->checked[self::PLUGIN];if($remote && version_compare($remote,$installed,'>')){$transient->response[self::PLUGIN]=(object)['slug'=>'dares-piketrooster','plugin'=>self::PLUGIN,'new_version'=>$remote,'url'=>'https://github.com/'.self::REPO,'package'=>'https://github.com/'.self::REPO.'/archive/refs/heads/main.zip'];}return $transient;}
 static function source($source,$remote_source,$upgrader,$hook_extra){
  if(is_wp_error($source)) return $source;
  $target=($hook_extra['plugin']??'')===self::PLUGIN || in_array(self::PLUGIN,(array)($hook_extra['plugins']??[]),true);
  if(!$target) return $source;
  if(!is_dir($source) || !is_file($source.'/dares-piketrooster.php'))
   return new WP_Error('dares_update_structure','GitHub plugin-archief bevat geen geldige hoofdplugin in de repositoryroot.');
  $dest=$remote_source.'/dares-piketrooster';
  if(realpath($source)===realpath($dest)) return $source;
  if(is_dir($dest)) return new WP_Error('dares_update_folder','De doelmap bestaat al.');
  if(!rename($source,$dest)) return new WP_Error('dares_update_rename','Kan de pluginmap niet hernoemen.');
  return $dest;
 }
}
