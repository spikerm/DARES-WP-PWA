DARES PIKETROOSTER v1.4.0
========================
Upgrade: installeer als vervanging van v1.3.0. Dezelfde database-tabellen blijven behouden. Maak vooraf een database-back-up.

WordPress beheer:
- Piketrooster: groepen, medewerkers, handmatige diensten, logboek, widget.
- Piketrooster > Uitbreidingen: accounts koppelen, back-up, afwezigheid, automatisch rooster, Telegram, e-mail, API-adres Alarmstatus, agenda-abonnement, exports.

Shortcodes:
[dares_piket] actuele piketgroep + volgende groep organisatiebreed
[dares_piket_beheer] idem + bevoegd piket wisselen
[dares_piket_kalender] maandkalender
[dares_piket_portaal] diensten, persoonlijke bevestiging en afwezigheid voor ingelogde gekoppelde accounts
[dares_piket_statistieken] statistieken afgelopen jaar voor ingelogde gebruikers
[dares_piket_qr] QR van openbare pagina (gebruikt externe qrserver.com dienst)

REST endpoints:
GET /wp-json/dares-piket/v1/current  publieke naam, groep en diensttijden; geen telefoon/email
GET /wp-json/dares-piket/v1/next     volgende dienst
GET /wp-json/dares-piket/v1/details beheerdersaccount vereist

iCal:
- Download: Piketrooster > Uitbreidingen > Download iCal.
- Abonnement: kopieer de geheime abonnements-URL uit Uitbreidingen; behandelt deze als een wachtwoord.

Automatisch rooster:
- Zet de functie aan, selecteer een groep, begindatum, wisseltijd en duur.
- Klik op 'Genereer ontbrekende diensten'. Bestaande diensten worden niet verwijderd of overschreven.
- Alleen actieve groepsmedewerkers zonder geregistreerde afwezigheid kunnen worden gekozen.
- Bij overlap met een andere groep wordt de periode overgeslagen.
- WP-Cron controleert het rooster ieder uur (afhankelijk van websiteverkeer).

Telegram:
- Maak zelf een Telegram bot en configureer een geldig bot token en chat-ID. Test verzending op een staginginstallatie.
- E-mail werkt via WordPress wp_mail en vergt goed werkende mailconfiguratie.

Alarmstatus:
- Vul optioneel een HTTPS API-URL in die JSON zoals {"status":"groen"} oplevert.
- Bij statuswijziging wordt een melding naar de actieve piketmedewerker gestuurd indien meldingen actief zijn. Dit verandert geen piketdienst.

Let op:
- Deze v1.4.0 is ontwikkeld en PHP-syntactisch gecontroleerd; er is geen live WordPress-integratietest uitgevoerd.
- Er kan organisatiebreed maar één dienst tegelijk worden toegevoegd via het beheerscherm/generator. Bestaande overlappende diensten uit oude versies worden niet automatisch verwijderd.
- Een back-upcontact ontvangt niet automatisch de hoofdrol; overdracht blijft handmatig.
- iCal-abo en Telegram vereisen externe configuratie. QR laadt de afbeelding via een externe dienst.
- Gebruik geen CDN of volledige pagina-cache die gepersonaliseerde portaalpagina's serveert aan anderen.

Versie 1.4.1: automatische planning per geselecteerde medewerker, met handmatig ingestelde volgorde; elke medewerker houdt diens eigen groepskoppeling. Reeds ingeplande diensten worden niet overschreven.

== 1.4.2 ==
Mailtest in Uitbreidingen; reminders en startmeldingen via 5-minuten WP-Cron; overdracht e-mail naar oude en nieuwe medewerker; widget volgt actieve dienst onafhankelijk van bevestiging; directe opvolging door zelfde persoon wordt in volgende-dienstlabel overgeslagen. Voor bevestiging maak een pagina /piket-portaal/ met [dares_piket_portaal]. Optioneel kan via WordPress-optie dares_piket_portal_page een pagina-ID ingesteld worden. WP-Cron vereist bezoeken of servercron.

Versie 1.4.3:
- Nieuw submenu Piketrooster > Rooster mailen: e-mail alle ingeplande medewerkers over een opgegeven periode; iedere ontvanger krijgt alleen eigen diensten.
- Optionele automatische wijzigingsmail bij handmatig toevoegen, wijzigen of verwijderen van piketdiensten.
- Automatisch gegenereerde diensten: één verzamelde rooster-mail per betrokken medewerker na handmatig genereren.
- Vereist geldige medewerker e-mailadressen en een werkende wp_mail/SMTP.


== WhatsApp Business v1.4.4 ==
Via Piketrooster > WhatsApp Business stel je de officiële Meta Cloud API in.
Vereist: Meta Business/WABA, telefoonnummer-ID, permanente access token, goedgekeurde templates
met 3 bodyparameters (medewerker, melding, portaal-URL), opt-in per medewerker.
Gebruik een telefoonnummer in internationaal formaat of een werkelijke BSUID. Een gebruikersnaam
kan alleen als label worden opgeslagen en kan niet direct worden omgezet naar een BSUID.
Templates: dares_piket_herinnering, dares_piket_gestart, dares_piket_overdracht,
dares_piket_wijziging, dares_piket_melding. Namen configureerbaar.
Webhook: /wp-json/dares-piket/v1/whatsapp-webhook. Voor POST is X-Hub-Signature-256
HMAC-verificatie met App Secret verplicht. Testknop toont acceptatie, niet aflevering.
De ingebouwde WP-Cron blijft afhankelijk van websitebezoeken of een server-cron.
Let op: sjablonen moeten door Meta worden goedgekeurd en er kunnen kosten zijn.


v1.4.5: vernieuwde maandkalender met maandnavigatie, vandaagmarkering, compacte naam- en groepslabels en samengevoegde overlappende diensten per medewerker per dag. Piketplanning en andere functies ongewijzigd.


v1.4.6: Automatische dienstwisseling uitsluitend maandag 18:00 (7 dagen). Onder Uitbreidingen is een expliciete herstelknop voor toekomstige diensten vanaf de volgende maandag 18:00, met database-snapshot in wp_options; historische diensten blijven behouden. Weekbalken via [dares_piket_kalender weergave="week"].

Version 1.4.7: Afwezigheid zichtbaar in maand- en weekkalender; rooster mailen per medewerker, per groep of organisatiebreed.

Version 1.4.8: Roostermails bevatten optioneel een persoonlijke/groepsbrede/organisatiebrede .ics kalenderbijlage, inclusief wijzigingen en automatisch gegenereerde roosters. De .ics kan op telefoon of desktop worden geïmporteerd.

=== Versie 2.0.0 (aanvullende functies) ===
Deze update behoudt alle bestaande tabellen van v1.4.8, de WordPress-widget,
de kalender, de maandagcyclus, e-mailmeldingen en de bestaande integraties.

Nieuwe pagina: Piketrooster > Piketbeheer 2.0.
Nieuwe shortcodes: [dares_piket_dashboard], [dares_piket_jaarplanner],
[dares_piket_mijn_profiel], [dares_piket_ruilen].
- Persoonlijke privé iCal-links via beheerpaneel; nieuwe link maakt oude ongeldig.
- Piketruil aanvragen > acceptatie door medewerker > goedkeuring beheerder.
- Roosterconflicten, bevestigingsdeadline, beheer-dashboard en logboek.
- Jaarplanner met jaarnavigatie, printweergave met browser 'Opslaan als PDF'.
- Agenda-annuleringen worden bij de volgende periodieke controle ontdekt en
  in persoonlijke feeds opgenomen. iCal-apps bepalen zelf de synchronisatietijd.
- Basale PWA-installatie via webmanifest en pictogrammen; voor veilige
  ingelogde pagina's is bewust geen offline cache/service-worker toegevoegd.

Beperkingen: geen drag-and-drop-bewerking in de jaarplanner; de bestaande
DARES Alarmstatus-koppeling blijft van de URL/JSON-configuratie afhankelijk.
Een agenda-abonnement is geen pushdienst: wijzigingen verschijnen wanneer
de telefoon de feed opnieuw ophaalt. Gebruik HTTPS en behandel links als geheim.
WP-Cron heeft verkeer of server-cron nodig voor stipte waarschuwingen.
Test eerst op een staging WordPress-site met een databasekopie.

2.1.0 - uitgebreide PWA
- Aan na inschakeling PWA in Piketrooster > Piketbeheer 2.0 en HTTPS.
- Open /?dares-piket-pwa=app, of shortcode [dares_piket_app].
- Manifest, installatieprompt, Android/iOS aanwijzingen, netwerkstatus en handmatig verversen.
- Service worker werkt uitsluitend network-first voor navigatie; geen privégegevens worden offline opgeslagen.
- Geen pushmeldingen in deze versie: vereist apart VAPID-abonnement en toestemming.
- Installeer op staging en controleer service-worker-scope i.c.m. eventuele andere PWA plugins.


Version 2.3.0 — zelfstandige mobiele appweergave
- /?dares-piket-pwa=app gebruikt geen themaheader of themafooter meer.
- De gele DARES-sitebalk en de groene vaste alarmstatus worden niet in het PWA-document geladen.
- Gewone website en widgets blijven onveranderd.
- WordPress-login, beveiligingsnonces, formulieren en push blijven werken via wp_head()/wp_footer().
- Na update de geïnstalleerde PWA sluiten en opnieuw openen; zo nodig browsercache verversen.

2.4.0: PWA-logo via mediabibliotheek; standaard WordPress-site-icoon, ook voor Apple en Android.

Versie 2.5.0: Jaarrooster binnen PWA onder Rooster bekijken, per maand uitklapbare diensten en afwezigheid.

Versie 2.6.0: bevestigingskaart en persoonlijke afwezigheden in PWA; optionele DARES P2000 pushkoppeling.
P2000 REST POST /wp-json/dares-piket/v1/p2000-alert met X-Dares-P2000-Key en JSON: {"id":"unieke-melding-id","dares":true,"message":"DARES alarmering ..."}. Alleen meldingen die door de bronsystemen aantoonbaar als DARES zijn geclassificeerd mogen dares=true krijgen. De P2000-plugin zelf is nog niet aangepast.

Versie 2.7.0: P2000-meldingenhistorie in PWA, per gebruiker gelezenstatus en instelbare standaardretentie van 90 dagen; GitHub-updater voor spikerm/DARES-WP-PWA (vereist volledige bron op main).
