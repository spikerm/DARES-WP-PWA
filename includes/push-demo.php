<?php
if (!defined('ABSPATH')) exit;
/** Safe, administrator-only test console: never edits the real duty roster or sends actual alerts. */
final class Dares_Piket_Push_Demo {
 const TYPES=[
  'start'=>['Piket begint','Je piketdienst is zojuist begonnen.'],
  'reminder'=>['Piketherinnering','Je hebt binnenkort piketdienst. Bevestig je dienst via de DARES-app.'],
  'assigned'=>['Nieuw piket','Je bent ingepland voor een nieuwe piketdienst.'],
  'confirm'=>['Piketbevestiging nodig','Bevestig je aanstaande piketdienst in het portaal.'],
  'confirmed'=>['Piket bevestigd','Je piketdienst is succesvol bevestigd.'],
  'transfer_in'=>['Piket overgenomen','Het piket is aan jou overgedragen.'],
  'transfer_out'=>['Piket overgedragen','Je piketdienst is overgenomen door een andere medewerker.'],
  'changed'=>['Rooster gewijzigd','Er is een wijziging in je piketrooster.'],
  'cancelled'=>['Piket geannuleerd','Een eerder ingeplande piketdienst is geannuleerd.'],
  'swap'=>['Ruilverzoek','Een collega vraagt om een piketdienst te ruilen.'],
  'deadline'=>['Bevestiging verlopen','Je piketbevestiging is nog niet ontvangen.'],
  'escalation'=>['Escalatie piket','Een piketdienst is nog niet bevestigd.'],
  'alarmstatus'=>['DARES alarmstatus','De DARES alarmstatus is gewijzigd.'],
  'p2000'=>['DARES P2000 alarm','Dit is een gesimuleerde DARES P2000-alarmering.']
 ];
 static function init(){
  add_action('admin_menu',[__CLASS__,'menu']);
  add_action('admin_post_dares_piket_demo_push',[__CLASS__,'send']);
 }
 static function menu(){add_submenu_page('dares-piketrooster','Push demo en tests','Push demo en tests','manage_options','dares-piket-push-demo',[__CLASS__,'admin']);}
 static function devices(){global $wpdb;return $wpdb->get_results('SELECT id,user_id,endpoint_hash,created_at FROM '.Dares_Piket_Push::table().' ORDER BY user_id,id');}
 static function admin(){
  if(!current_user_can('manage_options'))wp_die('Geen toegang');
  $rows=self::devices();
  echo '<div class="wrap"><h1>DARES PWA — Meldingen testen</h1><p>Demomeldingen veranderen geen piketdiensten, verzenden geen e-mails en worden niet in de echte P2000-historie opgeslagen. Alleen geselecteerde PWA-abonnementen ontvangen een bericht. Toestellen hebben een identificatiecode; deze is geen gegarandeerde modelnaam.</p>';
  if(isset($_GET['demo_result'])){ $n=absint($_GET['demo_count']??0);echo '<div class="notice '.($n?'notice-success':'notice-warning').'"><p>'.($n?'Push door provider geaccepteerd voor '.$n.' toestel(len).':'Geen push geaccepteerd. Controleer abonnementen en pushinstellingen.').' Dit bewijst nog niet dat het toestel de melding heeft getoond.</p></div>';}
  echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="dares_piket_demo_push">';
  wp_nonce_field('dares_piket_demo_push');
  echo '<table class="form-table"><tr><th><label for="dares-demo-device">Ontvanger</label></th><td><select id="dares-demo-device" name="device" required><option value="">Selecteer een toestel</option><option value="all">Alle toestellen ('.count($rows).')</option>';
  foreach($rows as $r){$u=get_userdata((int)$r->user_id);$name=$u?$u->display_name:'Account #'.(int)$r->user_id;echo '<option value="'.(int)$r->id.'">'.esc_html($name.' · toestel #'.$r->id.' · '.substr($r->endpoint_hash,0,8)).'</option>';}
  echo '</select> <p class="description">Kies voor een demo bij voorkeur één toestel, zodat je niemand onnodig alarmeert.</p></td></tr><tr><th>Melding</th><td><select name="type" required>';
  foreach(self::TYPES as $id=>$t)echo '<option value="'.esc_attr($id).'">'.esc_html($t[0]).'</option>';
  echo '</select></td></tr></table><p><label><input type="checkbox" name="confirm" value="1" required> Ik begrijp dat dit echte pushberichten naar de geselecteerde telefoon(s) stuurt, herkenbaar als TEST.</label></p>';
  submit_button('Verstuur demonstratiemelding');
  echo '</form><p>Let op: meldingen omzeilen de stille modus of Niet storen niet. Bij een oude of ingetrokken pushregistratie kan de verzending falen.</p></div>';
 }
 static function send(){
  if(!current_user_can('manage_options'))wp_die('Geen toegang',403);
  check_admin_referer('dares_piket_demo_push');
  if(empty($_POST['confirm']))wp_die('Bevestig eerst de testverzending.',400);
  if(!Dares_Piket_Push::opts()['enabled'])wp_die('Schakel eerst PWA-pushmeldingen in.',400);
  $type=sanitize_key(wp_unslash($_POST['type']??''));
  if(!isset(self::TYPES[$type]))wp_die('Ongeldig meldingtype.',400);
  $device=sanitize_text_field(wp_unslash($_POST['device']??''));
  global $wpdb;$table=Dares_Piket_Push::table();
  if($device==='all'){
   $rows=$wpdb->get_results("SELECT * FROM $table ORDER BY id LIMIT 200");
  }else{
   $id=absint($device);
   if(!$id)wp_die('Selecteer eerst een toestel.',400);
   $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$id));
   if(!$row)wp_die('Toestel is niet meer geregistreerd.',404);
   $rows=[$row];
  }
  $n=0;foreach($rows as $row){
   if(Dares_Piket_Push::send($row,'[TEST] DARES · '.self::TYPES[$type][0],self::TYPES[$type][1].' — DEMO, geen echte alarmering.','dares-demo-'.$type))$n++;
  }
  wp_safe_redirect(add_query_arg(['page'=>'dares-piket-push-demo','demo_result'=>1,'demo_count'=>$n],admin_url('admin.php')));exit;
 }
}
