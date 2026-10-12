# DARES Piketrooster / PWA

WordPress-piketrooster voor DARES Zuid-Holland Zuid.

## Eerste installatie
De eerste broncode-import moet de volledige map `dares-piketrooster/` bevatten met `dares-piketrooster.php`, `includes/` en `assets/`. Installeer eerst het ZIP-pakket v2.7.0 via WordPress. Upload daarna de volledige pluginmap naar de **root** van deze repository. De GitHub-updater leest de versie uit `main/dares-piketrooster/dares-piketrooster.php` en biedt vervolgens nieuwe versies aan bij WordPress → Plugins.

## Belangrijk
De repository bevat op dit moment nog niet de volledige WordPress-plugin. Plaats eerst de volledige broncode voordat GitHub-updates actief kunnen worden. Gebruik geen lege of onvolledige ZIP's als update.

## PWA / P2000
De WordPress-plugin ontvangt `dares_p2000_alert` van P2000 Regio 18 en registreert nieuwe meldingen in een persoonlijke meldingenhistorie met leesstatus. Kritieke iOS-alarmeringsrechten zijn niet beschikbaar voor standaard Web Push in een PWA.

## Updates
Versienummers in hoofdpluginheader en PHP-constante synchroon bijwerken. Maak vóór productie-updates een WordPress-databaseback-up. Test via staging.
