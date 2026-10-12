<?php
if (!defined('ABSPATH')) exit;
/** DARES PWA 2.1: installable app shell, deliberately never caches private responses. */
final class Dares_Piket_PWA {
 static function active(){return is_ssl() && !empty(Dares_Piket_V2::settings()['pwa']);}
 static function init(){
  add_action('wp_head',[__CLASS__,'head'],20);
  add_action('template_redirect',[__CLASS__,'route'],0);
  add_action('wp_enqueue_scripts',[__CLASS__,'assets']);
  add_shortcode('dares_piket_app',[__CLASS__,'app']);
  self::admin_hooks();
 }
 static function logo_id(){return absint(get_option('dares_piket_pwa_logo_id',0));}
 static function logo($size=512){
  $id=self::logo_id();
  if(!$id)$id=(int)get_option('site_icon',0); // Existing round DARES site icon by default.
  if($id){$url=wp_get_attachment_image_url($id,[$size,$size]);if($url)return $url;}
  return plugins_url('../assets/icon-'.($size<=192?'192':'512').'.png',__FILE__);
 }
 static function icon_entries(){
  $entries=[];foreach([192,512] as $size){$url=self::logo($size);$type='image/png';$path=wp_parse_url($url,PHP_URL_PATH);if($path){$ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));if($ext==='webp')$type='image/webp';elseif(in_array($ext,['jpg','jpeg'],true))$type='image/jpeg';}
   $entries[]=['src'=>$url,'sizes'=>$size.'x'.$size,'type'=>$type,'purpose'=>'any'];}
  return $entries;
 }
 static function admin_hooks(){
  add_action('admin_post_dares_piket_pwa_logo',[__CLASS__,'save_logo']);
  add_action('admin_enqueue_scripts',function($hook){if(!isset($_GET['page'])||sanitize_key($_GET['page'])!=='dares-piket-v2')return;wp_enqueue_media();});
 }
 static function save_logo(){
  if(!current_user_can('manage_options'))wp_die('Geen toegang');check_admin_referer('dares_piket_pwa_logo');
  $id=absint($_POST['pwa_logo_id']??0);
  if($id && (!wp_attachment_is_image($id)||!get_post($id)))wp_die('Selecteer een geldige afbeelding uit de mediabibliotheek.');
  update_option('dares_piket_pwa_logo_id',$id,false);
  wp_safe_redirect(admin_url('admin.php?page=dares-piket-v2&pwa_logo_saved=1'));exit;
 }
 static function admin_fields(){
  $id=self::logo_id();$src=self::logo(192);
  echo '<h2>PWA-logo en app-icoon</h2><p>Standaard wordt het ingestelde WordPress-site-icoon gebruikt (het ronde DARES-logo, als dat als site-icoon is ingesteld). Kies hieronder eventueel een eigen PWA-logo. Aanbevolen: vierkante PNG van minimaal 512 × 512 pixels.</p>';
  echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" id="dares-pwa-logo-form"><input type="hidden" name="action" value="dares_piket_pwa_logo">';wp_nonce_field('dares_piket_pwa_logo');
  echo '<input type="hidden" id="dares-pwa-logo-id" name="pwa_logo_id" value="'.esc_attr($id).'"><p><img id="dares-pwa-logo-preview" src="'.esc_url($src).'" width="96" height="96" style="object-fit:contain;border-radius:18px;background:#ffca05;padding:8px"></p>';
  echo '<p><button type="button" class="button" id="dares-pwa-choose">Kies logo uit mediabibliotheek</button> <button type="button" class="button" id="dares-pwa-reset">Gebruik standaard DARES-logo</button> <button class="button button-primary" type="submit">PWA-logo opslaan</button></p></form>';
  echo '<p><small>Na wijziging kan iOS het oude beginschermicoon bewaren. Verwijder dan de bestaande PWA van het beginscherm en voeg deze opnieuw toe.</small></p>';
  echo '<script>jQuery(function($){var picker;$("#dares-pwa-choose").on("click",function(e){e.preventDefault();if(!window.wp||!wp.media)return;if(!picker){picker=wp.media({title:"Kies DARES PWA-logo",button:{text:"Gebruik dit logo"},library:{type:"image"},multiple:false});picker.on("select",function(){var a=picker.state().get("selection").first().toJSON();$("#dares-pwa-logo-id").val(a.id);$("#dares-pwa-logo-preview").attr("src",a.sizes&&a.sizes.thumbnail?a.sizes.thumbnail.url:a.url);});}picker.open();});$("#dares-pwa-reset").on("click",function(){$("#dares-pwa-logo-id").val(0);$("#dares-pwa-logo-preview").attr("src",'.wp_json_encode(self::logo(192)).');});});</script>';
 }
 static function endpoint($name){return add_query_arg('dares-piket-pwa',$name,home_url('/'));}
 static function head(){if(!self::active())return;
  echo '<link rel="manifest" href="'.esc_url(self::endpoint('manifest')).'">' . "\n";
  echo '<meta name="theme-color" content="#f4c400"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><meta name="apple-mobile-web-app-title" content="DARES Piket">' . "\n";
  echo '<link rel="apple-touch-icon" href="'.esc_url(self::logo(192)).'">' . "\n";
 }
 static function route(){if(!isset($_GET['dares-piket-pwa']))return;
  $kind=sanitize_key(wp_unslash($_GET['dares-piket-pwa']));
  if(!self::active()){status_header(404);exit;}
  if($kind==='manifest'){
   nocache_headers();header('Content-Type: application/manifest+json; charset=utf-8');header('X-Content-Type-Options: nosniff');
   echo wp_json_encode(['id'=>home_url('/?dares-piket-pwa=app'),'name'=>'DARES Piketrooster','short_name'=>'DARES Piket','description'=>'Piketbezetting, bevestiging en rooster van DARES','lang'=>'nl-NL','start_url'=>self::endpoint('app'),'scope'=>trailingslashit(wp_parse_url(home_url('/'),PHP_URL_PATH)?:'/'),'display'=>'standalone','display_override'=>['standalone','minimal-ui'],'theme_color'=>'#f4c400','background_color'=>'#101c2c','icons'=>self::icon_entries()]);exit;
  }
  if($kind==='sw'){
   nocache_headers();header('Content-Type: text/javascript; charset=utf-8');header('Service-Worker-Allowed: '.(trailingslashit(wp_parse_url(home_url('/'),PHP_URL_PATH)?:'/')));header('X-Content-Type-Options: nosniff');
   // Service worker uses network-only for all personal pages/API routes. Offline fallback is static.
   readfile(dirname(__DIR__).'/assets/pwa-sw.js');exit;
  }
  if($kind==='app'){
   nocache_headers();
   if(!is_user_logged_in()){wp_safe_redirect(wp_login_url(self::endpoint('app')));exit;}
   // A dedicated app document: never load theme header/footer or sticky theme bars.
   // Preserve WP head/footer hooks so nonces, frontend forms and push JS still function.
   status_header(200);
   header('Content-Type: text/html; charset='.get_bloginfo('charset'));
   ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
 <meta charset="<?php echo esc_attr(get_bloginfo('charset')); ?>">
 <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
 <meta name="robots" content="noindex,nofollow">
 <title><?php echo esc_html('DARES Piketrooster'); ?></title>
 <?php wp_head(); ?>
</head>
<body class="dares-piket-app-document">
 <main id="dares-pwa-main" class="dares-pwa-standalone-wrap">
 <?php echo do_shortcode('[dares_piket_app]'); // Escaped by shortcode renderer. ?>
 </main>
 <?php wp_footer(); ?>
</body></html><?php
   exit;
  }
  status_header(404);exit;
 }
 static function assets(){if(!self::active())return;
  wp_enqueue_style('dares-piket-pwa',plugins_url('../assets/pwa.css',__FILE__),[], '2.5.0');
  wp_enqueue_script('dares-piket-pwa',plugins_url('../assets/pwa.js',__FILE__),[], '2.5.0',true);
  wp_add_inline_script('dares-piket-pwa','window.DaresPiketPwa='.wp_json_encode(['worker'=>self::endpoint('sw'),'app'=>self::endpoint('app'),'scope'=>trailingslashit(wp_parse_url(home_url('/'),PHP_URL_PATH)?:'/'),'rest'=>rest_url('dares-piket/v1/push/'),'nonce'=>wp_create_nonce('wp_rest')]).';','before');
 }
 static function year_roster(){
  if(!is_user_logged_in())return '';
  global $wpdb;
  $year=isset($_GET['pwa_jaar'])?absint($_GET['pwa_jaar']):(int)wp_date('Y');
  $year=max(2020,min(2100,$year));
  $from=sprintf('%04d-01-01 00:00:00',$year);
  $to=sprintf('%04d-01-01 00:00:00',$year+1);
  $t=Dares_Piketrooster::tables();
  $rows=$wpdb->get_results($wpdb->prepare('SELECT s.id,s.start_at,s.end_at,p.name,g.name AS group_name FROM '.$t['shifts'].' s JOIN '.$t['people'].' p ON p.id=s.person_id JOIN '.$t['groups'].' g ON g.id=s.group_id WHERE s.start_at < %s AND s.end_at > %s ORDER BY s.start_at ASC LIMIT 1200',$to,$from));
  $abs=$wpdb->get_results($wpdb->prepare('SELECT a.start_at,a.end_at,a.note,p.name FROM '.Dares_Piket_Advanced::t('absence').' a JOIN '.$t['people'].' p ON p.id=a.person_id WHERE a.start_at < %s AND a.end_at > %s ORDER BY a.start_at ASC LIMIT 1200',$to,$from));
  $url=self::endpoint('app');
  $prev=add_query_arg('pwa_jaar',$year-1,$url).'#jaarrooster';
  $next=add_query_arg('pwa_jaar',$year+1,$url).'#jaarrooster';
  $out='<section id="jaarrooster" class="dares-pwa-year" aria-label="DARES jaarrooster"><div class="dares-pwa-year-head"><div><small>ORGANISATIEBREED</small><h2>Jaarrooster '.$year.'</h2><p>Piketdiensten en afwezigheid per maand. Reguliere dienstwissel: maandag 18:00 uur.</p></div><div class="dares-pwa-year-controls"><a href="'.esc_url($prev).'" aria-label="Vorig jaar">←</a><a href="'.esc_url($next).'" aria-label="Volgend jaar">→</a></div></div><div class="dares-pwa-month-grid">';
  $todayMonth=(int)wp_date('n');$todayYear=(int)wp_date('Y');
  $months=[1=>'januari','februari','maart','april','mei','juni','juli','augustus','september','oktober','november','december'];
  foreach($months as $m=>$label){
   $ms=sprintf('%04d-%02d-01 00:00:00',$year,$m);
   $me=(new DateTimeImmutable($ms,wp_timezone()))->modify('+1 month')->format('Y-m-d H:i:s');
   $events=[];
   foreach($rows as $r){if($r->start_at >= $me || $r->end_at <= $ms)continue;
    $a=new DateTimeImmutable($r->start_at,wp_timezone());$b=new DateTimeImmutable($r->end_at,wp_timezone());
    $events[]=['start'=>$r->start_at,'type'=>'shift','name'=>$r->name,'group'=>$r->group_name,'period'=>$a->format('d-m H:i').' – '.$b->format('d-m H:i')];}
   foreach($abs as $a){if($a->start_at >= $me || $a->end_at <= $ms)continue;
    $events[]=['start'=>$a->start_at,'type'=>'absence','name'=>$a->name,'group'=>'Afwezig'.($a->note?' · '.$a->note:''),'period'=>(new DateTimeImmutable($a->start_at,wp_timezone()))->format('d-m').' – '.(new DateTimeImmutable($a->end_at,wp_timezone()))->format('d-m')];}
   usort($events,static function($a,$b){return strcmp($a['start'],$b['start']);});
   $open=($year===$todayYear && $m===$todayMonth)?' open':'';
   $out.='<details class="dares-pwa-month"'.$open.'><summary><span>'.esc_html(ucfirst($label)).'</span><small>'.count($events).' vermeldingen</small></summary><div class="dares-pwa-month-events">';
   if(!$events)$out.='<p class="dares-pwa-empty">Geen diensten of afwezigheid ingepland.</p>';
   foreach($events as $ev){$out.='<div class="dares-pwa-event '.($ev['type']==='absence'?'is-absence':'is-shift').'"><strong>'.esc_html($ev['name']).'</strong><span>'.esc_html($ev['group']).'</span><time>'.esc_html($ev['period']).'</time></div>';}
   $out.='</div></details>';
  }
  return $out.'</div></section>';
 }
 static function personal_overview(){
  global $wpdb; $uid=get_current_user_id();if(!$uid)return '';
  $people=Dares_Piket_Advanced::t('people');$meta=Dares_Piket_Advanced::t('meta');$shifts=Dares_Piket_Advanced::t('shifts');$absence=Dares_Piket_Advanced::t('absence');$confirm=Dares_Piket_Advanced::t('confirm');
  $myids=$wpdb->get_col($wpdb->prepare("SELECT p.id FROM $people p JOIN $meta m ON m.person_id=p.id WHERE m.user_id=%d",$uid));
  $out='<section class="dares-pwa-personal"><h2>Mijn piket en afwezigheid</h2>';
  if(!$myids)return $out.'<p>Je WordPress-account is nog niet gekoppeld aan een piketmedewerker.</p></section>';
  $ids=implode(',',array_map('absint',$myids));$now=Dares_Piketrooster::now();
  $upcoming=$wpdb->get_row($wpdb->prepare("SELECT s.*,p.name FROM $shifts s JOIN $people p ON p.id=s.person_id LEFT JOIN $confirm c ON c.shift_id=s.id WHERE s.person_id IN ($ids) AND s.end_at>%s AND c.shift_id IS NULL ORDER BY s.start_at LIMIT 1",$now));
  if($upcoming){
   $out.='<div class="dares-pwa-attention"><strong>Piketbevestiging nodig</strong><p>'.esc_html($upcoming->name.' · '.$upcoming->start_at.' t/m '.$upcoming->end_at).'</p>';
   $out.='<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="dares_piket_adv"><input type="hidden" name="kind" value="confirm"><input type="hidden" name="shift_id" value="'.(int)$upcoming->id.'">'.wp_nonce_field('dares_piket_adv_confirm','_wpnonce',true,false).'<button class="dares-pwa-confirm" type="submit">✓ Piketdienst bevestigen</button></form></div>';
  }else{$out.='<p class="dares-pwa-confirmed">✓ Geen onbevestigde komende piketdiensten</p>';}
  $rows=$wpdb->get_results($wpdb->prepare("SELECT a.*,p.name FROM $absence a JOIN $people p ON p.id=a.person_id WHERE a.person_id IN ($ids) AND a.end_at>=%s ORDER BY a.start_at LIMIT 60",$now));
  $out.='<h3>Mijn opgegeven afwezigheid</h3>';
  if(!$rows)$out.='<p>Geen actuele of toekomstige afwezigheid geregistreerd.</p>';
  foreach($rows as $a){$out.='<div class="dares-pwa-absence"><strong>'.esc_html($a->name).'</strong><span>'.esc_html($a->start_at).' – '.esc_html($a->end_at).'</span>';if(!empty($a->note))$out.='<small>'.esc_html($a->note).'</small>';$out.='</div>';}
  return $out.'</section>';
 }
 static function app(){if(!self::active())return '<p>De PWA is niet ingeschakeld of de website gebruikt geen HTTPS. Activeer PWA via Piketrooster → Piketbeheer 2.0.</p>';
  $login=is_user_logged_in();$current=Dares_Piketrooster::global_current();$next=method_exists('Dares_Piketrooster','global_next')?Dares_Piketrooster::global_next():null;
  $out='<section class="dares-pwa-app"><header class="dares-pwa-top"><img src="'.esc_url(self::logo(192)).'" alt="" width="44" height="44"><div><strong>DARES Piketrooster</strong><small>Mobiel piketportaal</small></div><span data-dares-connection aria-live="polite">Online</span></header>';
  $out.='<div class="dares-pwa-hero"><span class="dares-pwa-eyebrow">ACTUELE PIKETDIENST</span><h2>'.($current?esc_html($current->name):'Geen actieve dienst').'</h2><p>'.($current?esc_html($current->group_name):'Neem contact op met de coördinator').'</p>'.($current?'<small>'.esc_html($current->start_at.' — '.$current->end_at).'</small>':'').'</div>';
  if($next){$out.='<div class="dares-pwa-next"><b>Volgende dienst</b><p>'.esc_html($next->name.' · '.$next->group_name).'</p><small>'.esc_html($next->start_at).'</small></div>';}
  if($login)$out.=self::personal_overview();
  $out.='<nav class="dares-pwa-nav"><a href="#rooster">Al mijn diensten</a><a href="#jaarrooster">Jaarrooster bekijken</a></nav>';
  $out.=self::year_roster();
  if($login)$out.=Dares_Piket_P2000::history();
  $out.='<div id="rooster" class="dares-pwa-content">';
  if($login){$out.=do_shortcode('[dares_piket_portaal]');$out.=do_shortcode('[dares_piket_ruilen]');}else{$out.='<p>Log in om je eigen diensten en piketbevestiging te bekijken.</p>';}
  $out.='</div><div class="dares-pwa-push"><h3>Pushmeldingen</h3><p>Ontvang herinneringen en wijzigingen op dit toestel.</p><button type="button" data-dares-push-enable>Meldingen inschakelen</button> <button type="button" data-dares-push-test>Testmelding</button> <button type="button" data-dares-push-disable>Meldingen uitschakelen</button><p data-dares-push-status role="status"></p></div><footer class="dares-pwa-footer"><button type="button" data-dares-install hidden>Installeer DARES-app</button><button type="button" data-dares-refresh>Ververs gegevens</button><span data-dares-install-hint></span><p>Bij geen internet zijn actuele piketgegevens niet beschikbaar.</p></footer></section>';
  return $out;
 }
}
