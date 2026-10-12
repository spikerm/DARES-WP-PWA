<?php
if (!defined('ABSPATH')) exit;
/** Persoonsgebonden rooster e-mail en wijzigingsmeldingen. */
final class Dares_Piket_Rooster_Mail {
 const OPT='dares_piket_rooster_mail_settings';
 private static $generated=[];
 static function init(){
  add_action('admin_menu',[__CLASS__,'menu']);
  add_action('admin_post_dares_piket_rooster_send',[__CLASS__,'send_form']);
  add_action('admin_post_dares_piket_rooster_settings',[__CLASS__,'save_settings']);
 }
 static function settings(){return wp_parse_args((array)get_option(self::OPT,[]),['changes'=>1]);}
 static function menu(){add_submenu_page('dares-piketrooster','Rooster mailen','Rooster mailen','manage_options','dares-piket-rooster-mail',[__CLASS__,'page']);}
 static function table($suffix){global $wpdb;return $wpdb->prefix.'dares_piket_'.$suffix;}
 static function roster($from,$until,$person=0){global $wpdb;
  $s=self::table('shifts');$p=self::table('people');$g=self::table('groups');
  $sql="SELECT s.*,p.name,p.email,g.name AS group_name FROM $s s JOIN $p p ON p.id=s.person_id JOIN $g g ON g.id=s.group_id WHERE s.start_at < %s AND s.end_at > %s";
  $args=[$until,$from];if($person){$sql.=' AND s.person_id=%d';$args[]=$person;}
  $sql.=' ORDER BY s.start_at ASC LIMIT 2000';return $wpdb->get_results($wpdb->prepare($sql,...$args));
 }
 static function date_field($s){$s=sanitize_text_field(wp_unslash($s));if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$s))return false;$d=DateTimeImmutable::createFromFormat('!Y-m-d',$s,wp_timezone());return $d&&$d->format('Y-m-d')===$s?$d:false;}
 static function page(){if(!current_user_can('manage_options'))wp_die('Geen toegang');$opts=self::settings();$today=wp_date('Y-m-d');$to=wp_date('Y-m-d',strtotime('+2 months',current_time('timestamp')));
  echo '<div class="wrap"><h1>DARES Piketrooster — rooster mailen</h1>';
  if(isset($_GET['status'])){ $n=absint($_GET['sent']??0);$fail=absint($_GET['failed']??0);echo '<div class="notice notice-info"><p>'.esc_html('Verzendopdracht: '.$n.' geaccepteerd, '.$fail.' mislukt. Aflevering bij ontvangers is niet gegarandeerd.').'</p></div>'; }
  echo '<p>Stuur iedere medewerker die in de gekozen periode piket heeft één e-mail met zijn of haar eigen diensten. Zonder e-mailadres wordt niet verzonden.</p>';
  echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('dares_piket_rooster_send');
  $people=$GLOBALS['wpdb']->get_results('SELECT id,name,email FROM '.self::table('people').' ORDER BY name');
  $groups=$GLOBALS['wpdb']->get_results('SELECT id,name FROM '.self::table('groups').' ORDER BY name');
  echo '<input type="hidden" name="action" value="dares_piket_rooster_send"><table class="form-table"><tbody><tr><th>Vanaf (inclusief)</th><td><input required type="date" name="from" value="'.esc_attr($today).'"></td></tr><tr><th>Tot en met</th><td><input required type="date" name="until" value="'.esc_attr($to).'"></td></tr><tr><th>Ontvangers</th><td>Iedere ingeplande medewerker met een geldig e-mailadres (max. 200 per verzendopdracht).</td></tr>';
  echo '<tr><th>Welk rooster mailen?</th><td><select name="mode" id="dares-rooster-mode"><option value="personal_all">Persoonlijk rooster aan alle ingeplande medewerkers</option><option value="person">Alleen rooster van één medewerker</option><option value="group">Groepsrooster aan ingeplande groepsleden</option><option value="all">Volledig DARES-rooster aan alle ingeplande medewerkers</option></select><p class="description">Bij een groeps- of totaalrooster ontvangt iedere geselecteerde medewerker dezelfde planning. Ontvangers worden per e-mail afzonderlijk aangeschreven.</p></td></tr>';
  echo '<tr><th>Medewerker / ontvanger</th><td><select name="person_id"><option value="0">Selecteer medewerker</option>';foreach($people as $person)echo '<option value="'.esc_attr($person->id).'">'.esc_html($person->name).'</option>';echo '</select></td></tr>';
  echo '<tr><th>Kalenderbestand</th><td><label><input type="checkbox" name="attach_ical" value="1" checked> Voeg het geselecteerde rooster toe als iCalendar-bijlage (.ics), geschikt voor Apple Agenda, Google Agenda en Outlook.</label></td></tr>';
  echo '<tr><th>Groep</th><td><select name="group_id"><option value="0">Selecteer groep</option>';foreach($groups as $group)echo '<option value="'.esc_attr($group->id).'">'.esc_html($group->name).'</option>';echo '</select></td></tr></tbody></table>';
  echo '<p><button class="button button-primary" type="submit" onclick="return confirm(\'Rooster echt per e-mail versturen aan ingeplande medewerkers?\')">Verstuur rooster per e-mail</button></p></form>';
  echo '<hr><h2>Automatische meldingen bij roosterwijzigingen</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('dares_piket_rooster_settings');echo '<input type="hidden" name="action" value="dares_piket_rooster_settings">';
  echo '<label><input type="checkbox" name="changes" value="1" '.checked($opts['changes'],1,false).'> Stuur automatisch e-mail aan betrokken medewerkers bij toevoegen, wijzigen of verwijderen van een dienst, en na genereren van het automatische rooster.</label><p class="description">Bij een wijziging van persoon ontvangen zowel de oude als nieuwe medewerker een bericht. Automatisch gegenereerde diensten worden per medewerker gebundeld. De algemene e-mailfunctie in Uitbreidingen moet ingeschakeld zijn.</p>';
  echo '<p><button class="button">Instelling opslaan</button></p></form></div>';
 }
 static function save_settings(){if(!current_user_can('manage_options'))wp_die('Geen toegang');check_admin_referer('dares_piket_rooster_settings');update_option(self::OPT,['changes'=>empty($_POST['changes'])?0:1],false);wp_safe_redirect(admin_url('admin.php?page=dares-piket-rooster-mail'));exit;}
 static function mail_allowed(){return !empty(Dares_Piket_Advanced::opts()['notify_mail']);}
 static function person($id){global $wpdb;return $wpdb->get_row($wpdb->prepare('SELECT id,name,email FROM '.self::table('people').' WHERE id=%d',absint($id)));}
 /** iCalendar: tijden staan in WordPress lokale tijd, verzending als UTC en stabiele UID per dienst. */
 static function ics_escape($value){return str_replace(["\\", ";", ",", "\r\n", "\n", "\r"],["\\\\", "\\;", "\\,", "\\n", "\\n", ""],(string)$value);}
 static function ics_datetime($value){try{return (new DateTimeImmutable($value,wp_timezone()))->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');}catch(Exception $e){return false;}}
 static function ics_content($rows,$calendar_name='DARES Piketrooster'){
  $domain=(string)parse_url(home_url('/'),PHP_URL_HOST);if(!$domain)$domain='dares.local';
  $lines=['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//DARES//Piketrooster//NL','CALSCALE:GREGORIAN','METHOD:PUBLISH','X-WR-CALNAME:'.self::ics_escape($calendar_name)];
  foreach($rows as $r){$start=self::ics_datetime($r->start_at??'');$end=self::ics_datetime($r->end_at??'');if(!$start||!$end)continue;
   $id=absint($r->id??0);$label=trim(($r->name??'').' — '.($r->group_name??''),' —');
   $lines[]='BEGIN:VEVENT';$lines[]='UID:dares-piket-'.$id.'@'.$domain;$lines[]='DTSTAMP:'.gmdate('Ymd\THis\Z');
   $lines[]='DTSTART:'.$start;$lines[]='DTEND:'.$end;$lines[]='SUMMARY:'.self::ics_escape('DARES Piket — '.$label);
   $lines[]='DESCRIPTION:'.self::ics_escape('Piketdienst bij DARES. Piketportaal: '.self::portal_link());
   $lines[]='END:VEVENT';
  }
  $lines[]='END:VCALENDAR';
  // RFC 5545: regels maximaal 75 octetten, continuatieregel begint met spatie.
  $folded=[];foreach($lines as $line){$remaining=$line;$first=true;while(strlen($remaining)>($first?75:74)){$limit=$first?75:74;$take=$limit;while($take>0 && isset($remaining[$take]) && (ord($remaining[$take])&0xC0)===0x80)$take--;if($take===0)$take=$limit;$folded[]=($first?'':' ').substr($remaining,0,$take);$remaining=substr($remaining,$take);$first=false;}$folded[]=($first?'':' ').$remaining;}
  return implode("\r\n",$folded)."\r\n";
 }
 static function send($person,$subject,$body,$rows=null){
  if(!$person||!is_email($person->email))return false;
  if($rows===null)return (bool)wp_mail($person->email,$subject,$body);
  $temp=wp_tempnam('dares-piketrooster.ics');if(!$temp)return false;
  // WordPress gebruikt het bestandspad om de bijlagenaam te bepalen.
  $ics_path=dirname($temp).'/dares-piketrooster-'.wp_generate_password(12,false,false).'.ics';
  if(!@rename($temp,$ics_path))$ics_path=$temp;
  if(file_put_contents($ics_path,self::ics_content($rows,'DARES Piketrooster'))===false){@unlink($ics_path);return false;}
  try{return (bool)wp_mail($person->email,$subject,$body,[],[$ics_path]);}finally{@unlink($ics_path);}
 }

 static function format_rows($rows){$out='';foreach($rows as $r){$out.=wp_date('d-m-Y H:i',strtotime($r->start_at)).' t/m '.wp_date('d-m-Y H:i',strtotime($r->end_at)).' — '.$r->group_name."\n";}return $out;}
 static function portal_link(){return Dares_Piket_Advanced::portal_url();}
 static function send_form(){if(!current_user_can('manage_options'))wp_die('Geen toegang');check_admin_referer('dares_piket_rooster_send');
  $from=self::date_field($_POST['from']??'');$through=self::date_field($_POST['until']??'');if(!$from||!$through||$through<$from||$from->diff($through)->days>730)wp_die('Kies een geldige periode van maximaal twee jaar.');
  $rows=self::roster($from->format('Y-m-d').' 00:00:00',$through->modify('+1 day')->format('Y-m-d').' 00:00:00');
  $mode=sanitize_key($_POST['mode']??'personal_all');
  if(!in_array($mode,['personal_all','person','group','all'],true))wp_die('Ongeldige selectie.');
  $person_id=absint($_POST['person_id']??0);$group_id=absint($_POST['group_id']??0);
  if($mode==='person'&&!$person_id)wp_die('Selecteer een medewerker.');
  if($mode==='group'&&!$group_id)wp_die('Selecteer een groep.');
  $selected=[];foreach($rows as $r){if($mode==='person'&&(int)$r->person_id!==$person_id)continue;if($mode==='group'&&(int)$r->group_id!==$group_id)continue;$selected[]=$r;}
  $recipients=[];foreach($selected as $r)$recipients[(int)$r->person_id]=true;
  if($mode==='person')$recipients=[$person_id=>true];
  if(count($recipients)>200)wp_die('Te veel ontvangers. Verkort de periode.');
  $attach_ical=!empty($_POST['attach_ical']);$ok=0;$failed=0;
  foreach(array_keys($recipients) as $id){
   $p=self::person($id);$items=$mode==='personal_all'||$mode==='person'?array_values(array_filter($selected,static function($r)use($id){return (int)$r->person_id===$id;})):$selected;
   $label=$mode==='group'?'groepsrooster':($mode==='all'?'DARES-rooster':'persoonlijke piketrooster');
   $body="Beste ".($p?$p->name:'medewerker').",\n\nHierbij het ".$label." van ".$from->format('d-m-Y').' t/m '.$through->format('d-m-Y').":\n\n".self::format_rows($items)."\nBekijk het piketportaal: ".self::portal_link()."\n\nMet vriendelijke groet,\nDARES Piketrooster";
   if($attach_ical)$body.="\n\nBijgevoegd vind je het rooster als .ics-agendabestand. Open de bijlage op je telefoon en voeg de afspraken toe aan je agenda.\n";
   if(self::send($p,'DARES — '.$label,$body,$attach_ical?$items:null))$ok++;else $failed++;
  }
  wp_safe_redirect(add_query_arg(['page'=>'dares-piket-rooster-mail','status'=>1,'sent'=>$ok,'failed'=>$failed],admin_url('admin.php')));exit;
 }
 static function changed($before,$after){if(class_exists('Dares_Piket_Push')){ $kind=(!$before?'toegevoegd':(!$after?'verwijderd':'gewijzigd'));foreach(array_unique(array_filter([absint($before->person_id??0),absint($after->person_id??0)])) as $piket_pid)Dares_Piket_Push::queue('changes',$piket_pid,'DARES: rooster '.$kind,'Bekijk je gewijzigde planning in het piketportaal.');}if(class_exists('Dares_Piket_WhatsApp'))Dares_Piket_WhatsApp::roster_changed($before,$after);if(!self::settings()['changes']||!self::mail_allowed())return;
  $ids=array_unique(array_filter([absint($before->person_id??0),absint($after->person_id??0)]));
  foreach($ids as $id){$p=self::person($id);if(!$p)continue;
   if(!$before)$action='toegevoegd';elseif(!$after)$action='verwijderd';else $action='gewijzigd';
   $body="Beste {$p->name},\n\nEen piketdienst is {$action}.\n\n";
   if($before)$body.="Vorige planning: ".$before->start_at.' t/m '.$before->end_at."\n";
   if($after)$body.="Nieuwe planning: ".$after->start_at.' t/m '.$after->end_at."\n";
   if($before&&$after&&(int)$before->person_id!==(int)$after->person_id)$body.=((int)$after->person_id===$id?"Je bent nu ingeroosterd.\n":"Deze dienst is aan iemand anders toegewezen.\n");
   $body.="\nBekijk je actuele diensten en bevestig via ".self::portal_link()."\n\nDARES Piketrooster";
   $items=self::roster(Dares_Piketrooster::now(),(new DateTimeImmutable('now',wp_timezone()))->modify('+2 years')->format('Y-m-d H:i:s'),$id);
   self::send($p,'DARES — piketrooster '.$action,$body,$items);
  }
 }
 static function record_generated($pid){self::$generated[absint($pid)]=true;}
 static function send_generated(){if(class_exists('Dares_Piket_Push'))foreach(array_keys(self::$generated) as $push_pid)Dares_Piket_Push::queue('changes',$push_pid,'DARES: nieuw piketrooster','Er zijn nieuwe piketdiensten ingepland. Bekijk je rooster in de app.');if(class_exists('Dares_Piket_WhatsApp'))foreach(array_keys(self::$generated) as $pid)Dares_Piket_WhatsApp::send($pid,'change','Er zijn nieuwe piketdiensten voor je ingepland. Bekijk je persoonlijke rooster en bevestig in het piketportaal.','generated-'.date('YmdHi').'-'.$pid);if(!self::$generated||!self::settings()['changes']||!self::mail_allowed())return;
  foreach(array_keys(self::$generated) as $id){$p=self::person($id);if(!$p)continue;$from=Dares_Piketrooster::now();$to=(new DateTimeImmutable($from,wp_timezone()))->modify('+2 years')->format('Y-m-d H:i:s');$items=self::roster($from,$to,$id);
   if($items)self::send($p,'DARES — je piketrooster is bijgewerkt',"Beste {$p->name},\n\nEr zijn nieuwe diensten voor je ingepland. Dit is je actuele rooster:\n\n".self::format_rows($items)."\nJe vindt ook een iCalendar (.ics) als bijlage.\nBevestig je diensten in het piketportaal: ".self::portal_link()."\n\nDARES Piketrooster",$items);
  }self::$generated=[];
 }
}
