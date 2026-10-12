<?php
if (!defined('ABSPATH')) exit;
final class Dares_Piket_System_Status {
 static function init(){
  add_action('admin_menu',[__CLASS__,'menu']);
  add_action('p2000_r18_collect',[__CLASS__,'cron_seen'],99);
  add_shortcode('dares_piket_systeemstatus',[__CLASS__,'shortcode']);
 }
 static function cron_seen(){update_option('dares_piket_p2000_cron_seen',time(),false);}
 static function menu(){add_submenu_page('dares-piketrooster','Systeemstatus','Systeemstatus','manage_options','dares-piket-status',[__CLASS__,'admin']);}
 static function fmt($ts){return $ts?esc_html(wp_date('d-m-Y H:i:s',(int)$ts)):'Nog geen gegevens';}
 static function badge($ts,$limit){if(!$ts)return '<span style="color:#986300">Onbekend</span>';return time()-(int)$ts>$limit?'<strong style="color:#b42318">Vertraagd</strong>':'<strong style="color:#087443">Recent</strong>';}
 static function render(){
  if(!current_user_can('manage_options'))return '<p>Alleen voor beheerders.</p>';
  global $wpdb;
  $sync=(array)get_option('p2000_r18_last_sync',[]);
  $sync_at=(int)($sync['at']??0);$attempt=(int)($sync['attempt_at']??0);
  $failure=(int)($sync['failed_at']??0);
  $next=wp_next_scheduled('p2000_r18_collect');
  $cron_seen=(int)get_option('dares_piket_p2000_cron_seen',0);
  $push=(array)get_option('dares_piket_last_p2000_push',[]);
  $table=Dares_Piket_P2000::table();
  $exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)));
  $latest=$exists?$wpdb->get_row("SELECT received_at, push_count FROM $table ORDER BY id DESC LIMIT 1"):null;
  $opts=Dares_Piket_P2000::settings();
  $lines=[
   ['P2000 laatste succesvolle synchronisatie',self::fmt($sync_at).' · '.self::badge($sync_at,180)],
   ['P2000 laatste poging',self::fmt($attempt)],
   ['P2000 laatste fout',(!empty($sync['error'])?esc_html($sync['error']):'Geen fout geregistreerd').($failure?' · '.self::fmt($failure):'')],
   ['P2000 geplande cron',($next?self::fmt($next):'Niet gepland')],
   ['P2000 cron laatst aangeroepen',self::fmt($cron_seen)],
   ['Laatste ontvangen DARES-alarm',$latest?esc_html($latest->received_at).' UTC · '.(int)$latest->push_count.' push-aanbiedingen':'Nog geen meldingen'],
   ['Laatste P2000-pushverwerking',!empty($push['at'])?self::fmt($push['at']).' · '.(int)($push['count']??0).' aangeboden':'Nog geen verwerking geregistreerd'],
   ['P2000 pushkoppeling',!empty($opts['enabled'])?'Ingeschakeld':'Uitgeschakeld'],
   ['WordPress cron uitgeschakeld op paginaweergaven',defined('DISABLE_WP_CRON')&&DISABLE_WP_CRON?'Ja (server-cron vereist)':'Nee of onbekend']
  ];
  $out='<section class="dares-system-status"><h2>DARES systeemstatus</h2><p>Momentopname: '.self::fmt(time()).'. Een geslaagde API-pushaanbieding is geen bewijs van ontvangst op de telefoon. Server-cron wordt buiten WordPress ingesteld.</p><div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse">';
  foreach($lines as $line)$out.='<tr><th style="text-align:left;padding:10px;border-bottom:1px solid #ddd">'.esc_html($line[0]).'</th><td style="padding:10px;border-bottom:1px solid #ddd">'.$line[1].'</td></tr>';
  return $out.'</table></div><p><a href="'.esc_url(admin_url('admin.php?page=dares-piket-status')).'">Status verversen</a></p></section>';
 }
 static function shortcode(){return self::render();}
 static function admin(){if(!current_user_can('manage_options'))wp_die('Geen toegang');echo '<div class="wrap">'.self::render().'</div>';}
}
