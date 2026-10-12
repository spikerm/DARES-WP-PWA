<?php
/**
 * Plugin Name: Dares Piketrooster
 * Description: Piketgroepen, medewerkers, kalender, widgets en veilige overdracht via de website.
 * Version: 2.7.2
 * Author: Dares
 * Text Domain: dares-piketrooster
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) exit;
final class Dares_Piketrooster {
 const VER='2.7.2';
 public static function init(){
  add_action('admin_menu',[__CLASS__,'menu']);
  add_action('admin_post_dares_piket_save',[__CLASS__,'save']);
  add_action('admin_post_dares_piket_delete',[__CLASS__,'delete']);
  add_action('wp_ajax_dares_piket_swap',[__CLASS__,'swap']);
  add_action('wp_ajax_dares_piket_refresh',[__CLASS__,'refresh']);
  add_action('wp_ajax_nopriv_dares_piket_refresh',[__CLASS__,'refresh']);
  add_action('wp_enqueue_scripts',[__CLASS__,'assets']);
  add_shortcode('dares_piket',[__CLASS__,'shortcode']);
  add_shortcode('dares_piket_beheer',[__CLASS__,'shortcode']);
  add_shortcode('dares_piket_kalender',[__CLASS__,'calendar_shortcode']);
  add_action('widgets_init',function(){register_widget('Dares_Piket_Widget');});
  add_action('init',[__CLASS__,'block']);
 }
 static function tables(){global $wpdb;return ['groups'=>$wpdb->prefix.'dares_piket_groups','people'=>$wpdb->prefix.'dares_piket_people','shifts'=>$wpdb->prefix.'dares_piket_shifts','logs'=>$wpdb->prefix.'dares_piket_logs'];}
 static function activate(){global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$t=self::tables();$c=$wpdb->get_charset_collate();
  dbDelta("CREATE TABLE {$t['groups']} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, name varchar(150) NOT NULL, manager_ids text NULL, PRIMARY KEY (id)) $c;");
  dbDelta("CREATE TABLE {$t['people']} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, group_id bigint(20) unsigned NOT NULL, name varchar(150) NOT NULL, phone varchar(60) NOT NULL DEFAULT '', email varchar(190) NOT NULL DEFAULT '', active tinyint(1) NOT NULL DEFAULT 1, PRIMARY KEY (id), KEY group_id (group_id)) $c;");
  dbDelta("CREATE TABLE {$t['shifts']} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, group_id bigint(20) unsigned NOT NULL, person_id bigint(20) unsigned NOT NULL, start_at datetime NOT NULL, end_at datetime NOT NULL, PRIMARY KEY (id), KEY group_start (group_id,start_at), KEY group_end (group_id,end_at)) $c;");
  dbDelta("CREATE TABLE {$t['logs']} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, group_id bigint(20) unsigned NOT NULL, old_person_id bigint(20) unsigned NOT NULL DEFAULT 0, new_person_id bigint(20) unsigned NOT NULL, user_id bigint(20) unsigned NOT NULL, happened_at datetime NOT NULL, action varchar(25) NOT NULL DEFAULT 'swap', PRIMARY KEY (id), KEY group_id (group_id)) $c;");
 }
 static function groups(){global $wpdb;$t=self::tables();return $wpdb->get_results("SELECT * FROM {$t['groups']} ORDER BY name");}
 static function people($gid=0){global $wpdb;$t=self::tables();return $gid?$wpdb->get_results($wpdb->prepare("SELECT * FROM {$t['people']} WHERE group_id=%d AND active=1 ORDER BY name",$gid)):$wpdb->get_results("SELECT * FROM {$t['people']} ORDER BY name");}
 static function allowed($g){if(current_user_can('manage_options'))return true;if(!is_user_logged_in())return false;$ids=array_filter(array_map('absint',explode(',',(string)$g->manager_ids)));return in_array(get_current_user_id(),$ids,true);}
 static function can_edit(){if(!current_user_can('manage_options'))wp_die('Geen toegang.',403);}
 static function menu(){add_menu_page('Dares Piketrooster','Piketrooster','manage_options','dares-piketrooster',[__CLASS__,'admin'],'dashicons-calendar-alt',27);}
 static function save(){self::can_edit();check_admin_referer('dares_piket_save');global $wpdb;$t=self::tables();$type=sanitize_key($_POST['type']??'');$id=absint($_POST['id']??0);$data=[];
  if($type==='group'){$table=$t['groups'];$data=['name'=>sanitize_text_field(wp_unslash($_POST['name']??'')),'manager_ids'=>implode(',',array_unique(array_filter(array_map('absint',explode(',',sanitize_text_field(wp_unslash($_POST['manager_ids']??'')))))))];}
  elseif($type==='person'){$table=$t['people'];$data=['group_id'=>absint($_POST['group_id']??0),'name'=>sanitize_text_field(wp_unslash($_POST['name']??'')),'phone'=>sanitize_text_field(wp_unslash($_POST['phone']??'')),'email'=>sanitize_email(wp_unslash($_POST['email']??'')),'active'=>1];if(!self::group_exists($data['group_id']))wp_die('Onbekende groep.');}
  elseif($type==='shift'){$table=$t['shifts'];$gid=absint($_POST['group_id']??0);$pid=absint($_POST['person_id']??0);$start=self::valid_date($_POST['start_at']??'');$end=self::valid_date($_POST['end_at']??'');if(!$start||!$end||$start>=$end||!self::person_in_group($pid,$gid))wp_die('Ongeldige dienstgegevens.');if(Dares_Piket_Advanced::overlap($start,$end,$id))wp_die('Er bestaat al een piketdienst in deze periode. Binnen DARES kan slechts één groep tegelijk piket hebben.');$data=['group_id'=>$gid,'person_id'=>$pid,'start_at'=>$start,'end_at'=>$end];}
  else wp_die('Ongeldig type.');
  if(empty($data['name'])&&$type!=='shift')wp_die('Naam is verplicht.');
  $old_shift=($type==='shift'&&$id)?$wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['shifts']} WHERE id=%d",$id)):null;
  if($id){$ok=$wpdb->update($table,$data,['id'=>$id]);}else{$ok=$wpdb->insert($table,$data);}if($ok===false)wp_die('Opslaan mislukt.');
  if($type==='shift'&&$ok!==0){Dares_Piket_Rooster_Mail::changed($old_shift,(object)array_merge($data,['id'=>$id?:$wpdb->insert_id]));}
  wp_safe_redirect(admin_url('admin.php?page=dares-piketrooster&saved=1'));exit;
 }
 static function valid_date($raw){$raw=sanitize_text_field(wp_unslash($raw));if(!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',$raw))return false;$d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$raw,wp_timezone());return $d&&$d->format('Y-m-d\TH:i')===$raw?$d->format('Y-m-d H:i:s'):false;}
 static function group_exists($gid){global $wpdb;$t=self::tables();return (bool)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['groups']} WHERE id=%d",$gid));}
 static function person_in_group($pid,$gid){global $wpdb;$t=self::tables();return (bool)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['people']} WHERE id=%d AND group_id=%d AND active=1",$pid,$gid));}
 static function delete(){self::can_edit();$type=sanitize_key($_POST['type']??'');$id=absint($_POST['id']??0);check_admin_referer('dares_piket_delete_'.$type.'_'.$id);global $wpdb;$t=self::tables();$map=['group'=>'groups','person'=>'people','shift'=>'shifts'];if(!$id||!isset($map[$type]))wp_die('Ongeldig verzoek.');
  if($type==='group'){$wpdb->delete($t['shifts'],['group_id'=>$id]);$wpdb->delete($t['people'],['group_id'=>$id]);}
  if($type==='person'){$wpdb->delete($t['shifts'],['person_id'=>$id]);}
  $removed_shift=$type==='shift'?$wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['shifts']} WHERE id=%d",$id)):null;
  $wpdb->delete($t[$map[$type]],['id'=>$id]);
  if($removed_shift)Dares_Piket_Rooster_Mail::changed($removed_shift,null);
  wp_safe_redirect(admin_url('admin.php?page=dares-piketrooster'));exit;}
 static function admin(){self::can_edit();global $wpdb;$t=self::tables();$groups=self::groups();$people=self::people();$shifts=$wpdb->get_results("SELECT s.*,p.name person_name,g.name group_name FROM {$t['shifts']} s LEFT JOIN {$t['people']} p ON p.id=s.person_id LEFT JOIN {$t['groups']} g ON g.id=s.group_id ORDER BY s.start_at DESC LIMIT 150");$logs=$wpdb->get_results("SELECT l.*,g.name group_name,a.name old_name,b.name new_name FROM {$t['logs']} l LEFT JOIN {$t['groups']} g ON g.id=l.group_id LEFT JOIN {$t['people']} a ON a.id=l.old_person_id LEFT JOIN {$t['people']} b ON b.id=l.new_person_id ORDER BY l.id DESC LIMIT 50");
 echo '<div class="wrap"><h1>Dares Piketrooster</h1><p>Beheer groepen, medewerkers en diensten. De tijden volgen de WordPress-tijdzone: <strong>'.esc_html(wp_timezone_string()).'</strong>.</p>';if(isset($_GET['saved']))echo '<div class="notice notice-success"><p>Opgeslagen.</p></div>';
 echo '<h2>Groep toevoegen</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';self::hidden('group');echo '<input required name="name" placeholder="Groepsnaam"> <input name="manager_ids" size="32" placeholder="Beheerders: WordPress gebruikers-ID’s"> <button class="button button-primary">Toevoegen</button></form><p>Een beheerder kan alle groepen wijzigen; groepsbeheerders kunnen alleen via de site piket overdragen.</p>';
 echo '<h2>Groepen</h2><table class="widefat striped"><thead><tr><th>Naam</th><th>Bevoegde gebruikers-ID’s</th><th>Beheer</th></tr></thead><tbody>';foreach($groups as $g){echo '<tr><td colspan="2"><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';self::hidden('group',$g->id);echo '<input required name="name" value="'.esc_attr($g->name).'"> <input name="manager_ids" size="32" value="'.esc_attr($g->manager_ids).'"> <button class="button">Opslaan</button></form></td><td>';self::delete_button('group',$g->id);echo '</td></tr>';}echo '</tbody></table>';
 echo '<h2>Medewerker toevoegen</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';self::hidden('person');self::group_select($groups);echo ' <input required name="name" placeholder="Naam"> <input name="phone" placeholder="Telefoon"> <input type="email" name="email" placeholder="E-mail"> <button class="button button-primary">Toevoegen</button></form>';
 echo '<h2>Medewerkers</h2><table class="widefat striped"><thead><tr><th>Medewerker en gegevens</th><th>Groep</th><th>Actie</th></tr></thead><tbody>';foreach($people as $p){echo '<tr><td><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';self::hidden('person',$p->id);echo '<input required name="name" value="'.esc_attr($p->name).'"> <input name="phone" value="'.esc_attr($p->phone).'"> <input type="email" name="email" value="'.esc_attr($p->email).'">';echo '</td><td>';self::group_select($groups,$p->group_id);echo '</td><td><button class="button">Opslaan</button></form>';self::delete_button('person',$p->id);echo '</td></tr>';}echo '</tbody></table>';
 echo '<h2>Dienst toevoegen</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';self::hidden('shift');echo '<label>Groep ';self::group_select($groups);echo '</label> <label>Medewerker <select name="person_id" required>';foreach($people as $p)echo '<option value="'.esc_attr($p->id).'">'.esc_html($p->name).' — '.esc_html(self::group_name($groups,$p->group_id)).'</option>';echo '</select></label> <label>Vanaf <input required type="datetime-local" name="start_at"></label> <label>Tot <input required type="datetime-local" name="end_at"></label> <button class="button button-primary">Inplannen</button></form><p>Let op: selecteer een medewerker van dezelfde groep. Bij overlappende diensten wordt de laatst gestarte dienst als actief weergegeven.</p>';
 echo '<h2>Geplande diensten</h2><table class="widefat striped"><thead><tr><th>Groep</th><th>Medewerker</th><th>Van</th><th>Tot</th><th>Actie</th></tr></thead><tbody>';foreach($shifts as $s){echo '<tr><td>'.esc_html($s->group_name).'</td><td>'.esc_html($s->person_name).'</td><td>'.esc_html($s->start_at).'</td><td>'.esc_html($s->end_at).'</td><td>';self::delete_button('shift',$s->id);echo '</td></tr>';}echo '</tbody></table>';
 echo '<h2>Overdrachtslogboek</h2><table class="widefat striped"><thead><tr><th>Tijdstip</th><th>Groep</th><th>Van</th><th>Naar</th><th>Gebruiker</th></tr></thead><tbody>';foreach($logs as $l)echo '<tr><td>'.esc_html($l->happened_at).'</td><td>'.esc_html($l->group_name).'</td><td>'.esc_html($l->old_name?:'—').'</td><td>'.esc_html($l->new_name).'</td><td>'.esc_html(get_the_author_meta('display_name',$l->user_id)).'</td></tr>';echo '</tbody></table>';
 echo '<h2>Op de website plaatsen</h2><p><code>[dares_piket]</code> alle groepen; <code>[dares_piket groep="1"]</code> één groep; <code>[dares_piket_kalender]</code> maandkalender; <code>[dares_piket_beheer]</code> actuele piketdienst met wisselfunctie. Tevens te gebruiken als widget of Gutenberg-blok.</p></div>';
 }
 static function group_name($groups,$id){foreach($groups as $g)if((int)$g->id===(int)$id)return $g->name;return '';}
 static function hidden($type,$id=0){echo '<input type="hidden" name="action" value="dares_piket_save"><input type="hidden" name="type" value="'.esc_attr($type).'"><input type="hidden" name="id" value="'.esc_attr($id).'">';wp_nonce_field('dares_piket_save');}
 static function group_select($groups,$selected=0){echo '<select name="group_id" required>';foreach($groups as $g)echo '<option value="'.esc_attr($g->id).'" '.selected($selected,$g->id,false).'>'.esc_html($g->name).'</option>';echo '</select>';}
 static function delete_button($type,$id){echo '<form style="display:inline-block;margin-left:8px" method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(\'Definitief verwijderen?\')"><input type="hidden" name="action" value="dares_piket_delete"><input type="hidden" name="type" value="'.esc_attr($type).'"><input type="hidden" name="id" value="'.esc_attr($id).'">';wp_nonce_field('dares_piket_delete_'.$type.'_'.$id);echo '<button class="button">Verwijderen</button></form>';}
 static function now(){return current_datetime()->format('Y-m-d H:i:s');}
 static function active_shift($gid){global $wpdb;$t=self::tables();return $wpdb->get_row($wpdb->prepare("SELECT s.*,p.name,p.phone,p.email FROM {$t['shifts']} s JOIN {$t['people']} p ON p.id=s.person_id WHERE s.group_id=%d AND s.start_at<=%s AND s.end_at>%s ORDER BY s.start_at DESC,s.id DESC LIMIT 1",$gid,self::now(),self::now()));}
 static function next_shift($gid){global $wpdb;$t=self::tables();return $wpdb->get_row($wpdb->prepare("SELECT s.*,p.name FROM {$t['shifts']} s JOIN {$t['people']} p ON p.id=s.person_id WHERE s.group_id=%d AND s.start_at>%s ORDER BY s.start_at ASC LIMIT 1",$gid,self::now()));}
 static function global_current(){global $wpdb;$t=self::tables();$now=self::now();return $wpdb->get_row($wpdb->prepare("SELECT s.*,p.name,p.phone,p.email,g.name AS group_name FROM {$t['shifts']} s JOIN {$t['people']} p ON p.id=s.person_id JOIN {$t['groups']} g ON g.id=s.group_id WHERE s.start_at<=%s AND s.end_at>%s ORDER BY s.start_at DESC,s.id DESC LIMIT 1",$now,$now));}
 static function global_next(){global $wpdb;$t=self::tables();$rows=$wpdb->get_results($wpdb->prepare("SELECT s.*,p.name,g.name AS group_name FROM {$t['shifts']} s JOIN {$t['people']} p ON p.id=s.person_id JOIN {$t['groups']} g ON g.id=s.group_id WHERE s.start_at>%s ORDER BY s.start_at ASC,s.id ASC LIMIT 100",self::now()));$active=self::global_current();$previous=$active;foreach($rows as $row){if($previous && (int)$previous->person_id===(int)$row->person_id && strtotime($row->start_at)<=strtotime($previous->end_at)+60){$previous=$row;continue;}return $row;}return null;}
 static function refresh(){
  nocache_headers();
  $a=[];foreach(['groep','volgende','wisselen','contact','contact_openbaar','kop','logo'] as $key){if(isset($_POST[$key])&&is_scalar($_POST[$key]))$a[$key]=sanitize_text_field(wp_unslash($_POST[$key]));}
  if(!empty($a['logo']))$a['logo']=esc_url_raw($a['logo']);
  wp_send_json_success(['html'=>self::shortcode($a)]);
 }
 static function date_label($d){return wp_date('d-m-Y H:i',strtotime($d.' UTC') - (new DateTimeImmutable($d,new DateTimeZone('UTC')))->getOffset());}
 static function display_date($d){return date_i18n('d-m-Y H:i',strtotime($d.' UTC'));}
 static function shortcode($atts=[]){
  $a=shortcode_atts(['groep'=>0,'wisselen'=>'ja','volgende'=>'ja','telefoon'=>'nee','contact'=>'nee','contact_openbaar'=>'nee','kop'=>'ja','logo'=>'','rotatie'=>'nee','interval'=>8],(array)$atts,'dares_piket');
  $gid=absint($a['groep']);
  $logo=esc_url($a['logo']);
  $out='<div class="dares-piket-panel" data-piket-refresh="1"';
  foreach(['groep','volgende','wisselen','contact','contact_openbaar','kop','logo'] as $param){$out.=' data-opt-'.esc_attr(str_replace('_','-',$param)).'="'.esc_attr((string)$a[$param]).'"';}
  $out.='>';
  if($a['kop']==='ja'){
   $out.='<div class="dares-piket-topbar">';
   if($logo){$out.='<img class="dares-piket-logo" src="'.$logo.'" alt="DARES logo" loading="lazy">';}
   else{$out.='<span class="dares-piket-logo-default" aria-hidden="true">📡</span>';}
   $out.='<span>DARES Piketrooster</span></div>';
  }
  $all_groups=self::groups();
  $current_global=$gid?null:self::global_current();
  $next_global=$gid?null:self::global_next();
  $visible_group=$current_global?(int)$current_global->group_id:($next_global?(int)$next_global->group_id:0);
  $out.='<div class="dares-piket-grid">';
  foreach($all_groups as $g){
   if($gid&&(int)$g->id!==$gid)continue;
   if(!$gid&&(int)$g->id!==$visible_group)continue;
   $active=$gid?self::active_shift($g->id):$current_global;
   $next=$gid?self::next_shift($g->id):$next_global;
   $out.='<section class="dares-piket-card" data-group="'.esc_attr($g->id).'"><div class="dares-piket-heading"><span>'.esc_html($g->name).'</span><span class="dares-piket-status">'.($active?'● Actief':'○ Geen dienst').'</span></div>';
   if($active){
    $out.='<div class="dares-piket-name">'.esc_html($active->name).'</div><div class="dares-piket-dates">'.esc_html($active->start_at).' – '.esc_html($active->end_at).'</div>';
    $show_contacts=($a['contact']==='ja'||$a['telefoon']==='ja')&&($a['contact_openbaar']==='ja'||is_user_logged_in());
    if($show_contacts){
     $out.='<div class="dares-piket-contacts">';
     if(!empty($active->phone)){
      $phone=preg_replace('/[^0-9+]/','',(string)$active->phone);
      if($phone!=='')$out.='<a class="dares-piket-contact" href="tel:'.esc_attr($phone).'" aria-label="Bel '.esc_attr($active->name).'">☎ '.esc_html($active->phone).'</a>';
     }
     if($a['contact']==='ja'&&!empty($active->email))$out.='<a class="dares-piket-contact" href="mailto:'.esc_attr($active->email).'" aria-label="Mail '.esc_attr($active->name).'">✉ '.esc_html($active->email).'</a>';
     $out.='</div>';
    }
   }else{$out.='<p>Momenteel geen piketdienst ingepland.</p>';}
   if($active){$out.=Dares_Piket_Advanced::shift_badges($active);} if($a['volgende']==='ja'&&$next)$out.='<p class="dares-piket-next">Volgende: <strong>'.esc_html($next->name).'</strong> — '.esc_html($gid?$g->name:$next->group_name).' ('.esc_html($next->start_at).')</p>';
   if($active&&$a['wisselen']==='ja'&&self::allowed($g)){
    $out.='<div class="dares-piket-change"><label for="dares-piket-person-'.esc_attr($g->id).'">Piket overdragen aan</label><select id="dares-piket-person-'.esc_attr($g->id).'">';
    foreach(self::people($g->id) as $p){if($active&&(int)$active->person_id===(int)$p->id)continue;$out.='<option value="'.esc_attr($p->id).'">'.esc_html($p->name).'</option>';}
    $out.='</select><button type="button" data-swap="'.esc_attr($g->id).'"'.(!$active?' disabled title="Geen actieve dienst"':'').'>Piket wisselen</button><div class="dares-piket-message" role="status" aria-live="polite"></div></div>';
   }
   $out.='</section>';
  }
  if(!$gid&&!$visible_group)$out.='<section class="dares-piket-card"><p>Er zijn geen actieve of toekomstige piketdiensten ingepland.</p></section>';
  $out.='</div>';
  return $out.'</div>';
 }
 static function calendar_shortcode($atts=[]){
  global $wpdb;
  $a=shortcode_atts(['groep'=>0,'maand'=>'','weergave'=>'maand'],$atts,'dares_piket_kalender');
  $requested=isset($_GET['dares_piket_month'])&&is_scalar($_GET['dares_piket_month'])?sanitize_text_field(wp_unslash($_GET['dares_piket_month'])):'';
  $month=preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$requested)?$requested:(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$a['maand'])?$a['maand']:current_datetime()->format('Y-m'));
  $start=DateTimeImmutable::createFromFormat('!Y-m-d',$month.'-01',wp_timezone());
  if(!$start)return '';
  $end=$start->modify('first day of next month');
  $gid=absint($a['groep']);$t=self::tables();
  $sql="SELECT s.*,p.name,g.name group_name FROM {$t['shifts']} s JOIN {$t['people']} p ON p.id=s.person_id JOIN {$t['groups']} g ON g.id=s.group_id WHERE s.start_at < %s AND s.end_at > %s";
  $args=[$end->format('Y-m-d H:i:s'),$start->format('Y-m-d H:i:s')];
  if($gid){$sql.=' AND s.group_id=%d';$args[]=$gid;}
  $sql.=' ORDER BY s.start_at ASC,s.id ASC';
  $shifts=$wpdb->get_results($wpdb->prepare($sql,$args));
  // Afwezigheid staat los van ingeplande piketdiensten en verandert deze nooit.
  $absence_sql="SELECT a.*,p.name FROM {$wpdb->prefix}dares_piket_absence a JOIN {$t['people']} p ON p.id=a.person_id WHERE a.start_at < %s AND a.end_at > %s";
  $absence_args=[$end->format('Y-m-d H:i:s'),$start->format('Y-m-d H:i:s')];
  if($gid){$absence_sql.=" AND EXISTS (SELECT 1 FROM {$t['shifts']} sg WHERE sg.person_id=a.person_id AND sg.group_id=%d)";$absence_args[]=$gid;}
  $absence_sql.=' ORDER BY a.start_at ASC';
  $absences=$wpdb->get_results($wpdb->prepare($absence_sql,$absence_args));
  if(isset($a['weergave']) && in_array($a['weergave'],['week','weken'],true)) return self::calendar_weeks($start,$end,$shifts,$absences);
  $today=current_datetime()->format('Y-m-d');
  $prev=$start->modify('-1 month')->format('Y-m');$next=$start->modify('+1 month')->format('Y-m');
  $base=remove_query_arg('dares_piket_month');
  $prevurl=add_query_arg('dares_piket_month',$prev,$base);
  $nexturl=add_query_arg('dares_piket_month',$next,$base);
  $todayurl=add_query_arg('dares_piket_month',current_datetime()->format('Y-m'),$base);
  $out='<section class="dares-piket-calendar dares-calendar-v2" aria-label="DARES piketkalender">';
  $out.='<div class="dares-calendar-toolbar"><div class="dares-calendar-title"><span class="dares-calendar-kicker">DARES · PIKETPLANNING</span><h3>'.esc_html(ucfirst(wp_date('F Y',$start->getTimestamp(),wp_timezone()))).'</h3></div><nav class="dares-calendar-actions" aria-label="Maandnavigatie"><a class="dares-calendar-nav" href="'.esc_url($prevurl).'" aria-label="Vorige maand">&#8249;</a><a class="dares-calendar-today" href="'.esc_url($todayurl).'">Vandaag</a><a class="dares-calendar-nav" href="'.esc_url($nexturl).'" aria-label="Volgende maand">&#8250;</a></nav></div>';
  $out.='<div class="dares-calendar-scroll"><div class="dares-calendar-grid" role="grid">';
  foreach(['Ma','Di','Wo','Do','Vr','Za','Zo'] as $label)$out.='<div class="dares-cal-head" role="columnheader">'.$label.'</div>';
  $offset=(int)$start->format('N')-1;
  for($i=0;$i<$offset;$i++)$out.='<div class="dares-cal-empty" aria-hidden="true"></div>';
  $days=(int)$start->format('t');
  for($day=1;$day<=$days;$day++){
   $cur=$start->setDate((int)$start->format('Y'),(int)$start->format('m'),$day);$daystr=$cur->format('Y-m-d');$nextday=$cur->modify('+1 day');
   $ds=$daystr.' 00:00:00';$de=$nextday->format('Y-m-d H:i:s');
   $items=[];
   foreach($shifts as $shift){
    if($shift->start_at >= $de || $shift->end_at <= $ds)continue;
    $key=(int)$shift->person_id; // A person can have adjoining or overlapping shifts in the same day.
    $from=max($ds,$shift->start_at);$until=min($de,$shift->end_at);
    if(!isset($items[$key]))$items[$key]=['person'=>$shift->name,'group'=>$shift->group_name,'start'=>$from,'end'=>$until,'person_id'=>$key];
    else {if($from<$items[$key]['start'])$items[$key]['start']=$from;if($until>$items[$key]['end'])$items[$key]['end']=$until;}
   }
   uasort($items,static function($x,$y){return strcmp($x['start'],$y['start']);});
   $isToday=$daystr===$today;
   $out.='<div class="dares-cal-day'.($isToday?' is-today':'').(empty($items)?' is-empty':'').'" role="gridcell" aria-label="'.esc_attr(wp_date('j F Y',$cur->getTimestamp(),wp_timezone())).'">';
   $out.='<div class="dares-cal-date"><span class="dares-cal-num">'.(int)$day.'</span>'.($isToday?'<span class="dares-cal-now">Vandaag</span>':'').'</div>';
   if(!$items){$out.='<span class="dares-cal-no-duty" aria-label="Geen dienst gepland">—</span>';}
   $isHandoff=count($items)>1;
   foreach($items as $item){
    $partStart=$item['start']>$ds;$partEnd=$item['end']<$de;
    $timeText='';
    if($isHandoff){$fromTime=substr($item['start'],11,5);$endTime=substr($item['end'],11,5);$timeText=$fromTime.'–'.($item['end']===$de?'24:00':$endTime);}
    elseif($partStart){$timeText='Vanaf '.substr($item['start'],11,5);}
    elseif($partEnd){$timeText='Tot '.substr($item['end'],11,5);}
    $out.='<div class="dares-cal-entry" title="'.esc_attr($item['group'].' — '.$item['person'].($timeText?' ('.$timeText.')':'')).'">';
    $out.='<span class="dares-cal-person">'.esc_html($item['person']).'</span>';
    $out.='<span class="dares-cal-group">'.esc_html($item['group']).'</span>';
    if($timeText)$out.='<span class="dares-cal-time">'.esc_html($timeText).'</span>';
    $out.='</div>';
   }
   foreach($absences as $absence){
    if($absence->start_at >= $de || $absence->end_at <= $ds)continue;
    $out.='<div class="dares-cal-absence" title="'.esc_attr($absence->name.' · Afwezig: '.$absence->start_at.' t/m '.$absence->end_at).'">';
    $out.='<span class="dares-cal-person">'.esc_html($absence->name).'</span><span class="dares-cal-group">Afwezig'.(!empty($absence->note)?' · '.esc_html($absence->note):'').'</span></div>';
   }
   $out.='</div>';
  }
  $out.='</div></div><div class="dares-calendar-foot"><span><i class="dares-cal-legend-dot" aria-hidden="true"></i> Geplande piketdienst · Oranje: afwezigheid</span><span>Bij een wisseling worden beide medewerkers getoond.</span></div></section>';
  return $out;
 }


 static function calendar_weeks($start,$end,$shifts,$absences=[]){
  $first=$start->modify('monday this week');$last=$end->modify('monday this week');
  $out='<section class="dares-piket-calendar dares-calendar-v2 dares-cal-weeks"><div class="dares-calendar-toolbar"><div class="dares-calendar-title"><span class="dares-calendar-kicker">DARES · WEEKROOSTER</span><h3>'.esc_html(ucfirst(wp_date('F Y',$start->getTimestamp(),wp_timezone()))).'</h3></div></div><div class="dares-weeks-scroll"><div class="dares-weeks-body">';
  foreach(new DatePeriod($first,new DateInterval('P7D'),$last->modify('+7 days')) as $week){
   $weekEnd=$week->modify('+7 days');$out.='<div class="dares-week-section"><div class="dares-week-caption">Week '.esc_html($week->format('W')).' · '.esc_html($week->format('d-m')).' t/m '.esc_html($weekEnd->modify('-1 day')->format('d-m')).'</div><div class="dares-week-days">';
   foreach(['Ma','Di','Wo','Do','Vr','Za','Zo'] as $idx=>$day){$d=$week->modify('+'.$idx.' day');$out.='<span>'.esc_html($day).' <b>'.esc_html($d->format('j')).'</b></span>';}
   $out.='</div><div class="dares-week-track">';$count=0;
   foreach($shifts as $shift){$from=new DateTimeImmutable($shift->start_at,wp_timezone());$to=new DateTimeImmutable($shift->end_at,wp_timezone());if($to<=$week || $from>=$weekEnd)continue;
    $left=max(0,($from->getTimestamp()-$week->getTimestamp())/($weekEnd->getTimestamp()-$week->getTimestamp())*100);
    $right=min(100,($to->getTimestamp()-$week->getTimestamp())/($weekEnd->getTimestamp()-$week->getTimestamp())*100);
    if($right-$left<0.1)continue;
    $style='left:'.esc_attr(number_format($left,4,'.','')).'%;width:'.esc_attr(number_format($right-$left,4,'.','')).'%;';
    $desc=$shift->name.' · '.$shift->group_name.' · '.$from->format('d-m H:i').' tot '.$to->format('d-m H:i');
    $out.='<div class="dares-week-bar" style="'.esc_attr($style).'" title="'.esc_attr($desc).'"><strong>'.esc_html($shift->name).'</strong><span>'.esc_html($shift->group_name).'</span></div>';$count++;
   }
   if(!$count)$out.='<div class="dares-week-none">Geen dienst gepland</div>';
   $out.='</div><div class="dares-week-absence-track">';
   foreach($absences as $absence){
    $from=new DateTimeImmutable($absence->start_at,wp_timezone());$to=new DateTimeImmutable($absence->end_at,wp_timezone());if($to<=$week||$from>=$weekEnd)continue;
    $left=max(0,($from->getTimestamp()-$week->getTimestamp())/($weekEnd->getTimestamp()-$week->getTimestamp())*100);
    $right=min(100,($to->getTimestamp()-$week->getTimestamp())/($weekEnd->getTimestamp()-$week->getTimestamp())*100);
    if($right<=$left)continue;
    $style='left:'.number_format($left,4,'.','').'%;width:'.number_format($right-$left,4,'.','').'%;';
    $out.='<div class="dares-week-absence" style="'.esc_attr($style).'" title="'.esc_attr($absence->name.' afwezig van '.$absence->start_at.' tot '.$absence->end_at).'">'.esc_html($absence->name).' · afwezig</div>';
   }
   $out.='</div></div>';
  }
  return $out.'</div></div><div class="dares-calendar-foot">Doorlopende balken tonen de werkelijke begin- en eindtijden. Oranje balken tonen afwezigheid. Dienstwisseling: maandag 18:00.</div></section>';
 }
 static function swap(){if(!is_user_logged_in())wp_send_json_error('Log eerst in.',401);check_ajax_referer('dares_piket_swap','nonce');$gid=absint($_POST['group_id']??0);$pid=absint($_POST['person_id']??0);global $wpdb;$t=self::tables();$g=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['groups']} WHERE id=%d",$gid));if(!$g||!self::allowed($g))wp_send_json_error('Geen toestemming.',403);if(!self::person_in_group($pid,$gid))wp_send_json_error('Ongeldige medewerker.',400);
  $active=self::active_shift($gid);if(!$active)wp_send_json_error('Geen actieve dienst om over te dragen.',400);if((int)$active->person_id===$pid)wp_send_json_error('Deze medewerker heeft al piket.',400);
  $now=self::now();$end=$active->end_at;$ok=$wpdb->update($t['shifts'],['end_at'=>$now],['id'=>$active->id,'end_at'=>$end]);if($ok!==1)wp_send_json_error('Dienst is inmiddels gewijzigd. Vernieuw de pagina.',409);
  $inserted=$wpdb->insert($t['shifts'],['group_id'=>$gid,'person_id'=>$pid,'start_at'=>$now,'end_at'=>$end]);if(!$inserted){$wpdb->update($t['shifts'],['end_at'=>$end],['id'=>$active->id,'end_at'=>$now]);wp_send_json_error('Overdracht mislukt.',500);}
  $wpdb->insert($t['logs'],['group_id'=>$gid,'old_person_id'=>$active->person_id,'new_person_id'=>$pid,'user_id'=>get_current_user_id(),'happened_at'=>$now,'action'=>'swap']);do_action('dares_piket_after_transfer',$active->name,$wpdb->get_var($wpdb->prepare("SELECT name FROM {$t['people']} WHERE id=%d",$pid)),$g->name,(int)$active->person_id,$pid,$now,$wpdb->insert_id);wp_send_json_success(['message'=>'Piket is overgedragen.','html'=>self::shortcode(['groep'=>0,'contact'=>'ja'])]);
 }
 static function assets(){wp_enqueue_style('dares-piket',plugins_url('assets/piket.css',__FILE__),[],self::VER);wp_enqueue_script('dares-piket',plugins_url('assets/piket.js',__FILE__),[],self::VER,true);wp_localize_script('dares-piket','DaresPiket',['ajax'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('dares_piket_swap')]);}
 static function block(){if(!function_exists('register_block_type'))return;register_block_type('dares/piket',['api_version'=>2,'title'=>'Dares Piketrooster','category'=>'widgets','icon'=>'calendar-alt','attributes'=>['groep'=>['type'=>'number','default'=>0]],'render_callback'=>function($attrs){return self::shortcode(['groep'=>$attrs['groep']??0]);}]);}
}
class Dares_Piket_Widget extends WP_Widget {
 function __construct(){parent::__construct('dares_piket_widget','Dares Piketrooster',['description'=>'DARES-widget met gele kopbalk, contactgegevens en piketwissel.']);}
 function widget($args,$instance){
  echo $args['before_widget'];
  echo Dares_Piketrooster::shortcode([
   'groep'=>absint($instance['groep']??0),
   'volgende'=>!empty($instance['volgende'])?'ja':'nee',
   'contact'=>!empty($instance['contact'])?'ja':'nee',
   'contact_openbaar'=>!empty($instance['contact_openbaar'])?'ja':'nee',
   'logo'=>esc_url($instance['logo']??''),
   'kop'=>'ja',
   'rotatie'=>'nee'
  ]);
  echo $args['after_widget'];
 }
 function form($instance){
  $title=$instance['title']??'DARES Piketrooster';$gid=absint($instance['groep']??0);
  echo '<p><label>Naam (widgetbeheer) <input class="widefat" name="'.esc_attr($this->get_field_name('title')).'" value="'.esc_attr($title).'"></label></p>';
  echo '<p><label>Groep <select class="widefat" name="'.esc_attr($this->get_field_name('groep')).'"><option value="0">Alle groepen</option>';
  foreach(Dares_Piketrooster::groups() as $g)echo '<option value="'.esc_attr($g->id).'" '.selected($gid,$g->id,false).'>'.esc_html($g->name).'</option>';
  echo '</select></label></p>';
  echo '<p><label>Logo URL (afbeeldings-URL uit Mediabibliotheek) <input type="url" class="widefat" placeholder="https://.../dares-logo.png" name="'.esc_attr($this->get_field_name('logo')).'" value="'.esc_attr($instance['logo']??'').'"></label></p>';
  echo '<p class="description">Bij Alle groepen verschijnt alleen de momenteel actieve groep, plus de eerstvolgende piketdienst (ook van een andere groep). De weergave ververst automatisch.</p>';
  foreach(['volgende'=>'Volgende dienst tonen','contact'=>'Telefoonnummer en e-mail tonen','contact_openbaar'=>'Contactgegevens ook aan niet-ingelogde bezoekers tonen'] as $key=>$label){
   echo '<p><label><input type="checkbox" name="'.esc_attr($this->get_field_name($key)).'" value="1" '.checked(!empty($instance[$key]),true,false).'> '.esc_html($label).'</label></p>';
  }
  echo '<p class="description">Contactgegevens zijn standaard alleen zichtbaar voor ingelogde bezoekers, wanneer contactweergave is ingeschakeld.</p>';
 }
 function update($new,$old){return [
  'title'=>sanitize_text_field($new['title']??''),
  'groep'=>absint($new['groep']??0),
  'logo'=>esc_url_raw($new['logo']??''),
  'volgende'=>empty($new['volgende'])?0:1,
  'contact'=>empty($new['contact'])?0:1,
  'contact_openbaar'=>empty($new['contact_openbaar'])?0:1
 ];}
}
require_once __DIR__.'/includes/advanced.php';
require_once __DIR__.'/includes/rooster-mail.php';
require_once __DIR__.'/includes/whatsapp.php';
require_once __DIR__.'/includes/v2.php';
require_once __DIR__.'/includes/pwa.php';
register_activation_hook(__FILE__,['Dares_Piketrooster','activate']);
register_activation_hook(__FILE__,['Dares_Piket_Advanced','install']);
Dares_Piketrooster::init(); Dares_Piket_Advanced::init(); Dares_Piket_Rooster_Mail::init(); Dares_Piket_WhatsApp::init();

Dares_Piket_V2::init();
Dares_Piket_PWA::init();

require_once __DIR__.'/includes/push.php';
Dares_Piket_Push::init();

require_once __DIR__.'/includes/p2000.php';
Dares_Piket_P2000::init();

require_once __DIR__.'/includes/github-updater.php';
Dares_Piket_GitHub_Updater::init();
