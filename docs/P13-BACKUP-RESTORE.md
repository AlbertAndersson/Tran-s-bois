# P13 – Backup, restore och disaster recovery

Status: IN PROGRESS. Baslinje main `56c2d8ba6455fa385c02867922ec12308a26ae4b`.
PR [#31](https://github.com/AlbertAndersson/Tran-s-bois/pull/31).
Återställningsbevis registreras efter godkänd CI; ingen produktionsrestore utförs.
Användaren har uttryckligen beslutat att fortsätta P13 och skjuta upp återstående
P12-verifiering. Detta är inte ett launch- eller Stripe-live-godkännande.

## Inventering och backupgränser

- BoIS production: Simply-produkten **socen.se**, privat runtime
  `/var/www/socen.se/.bois-production/`, separat produkt från staging **alberiq.se**.
  Production-DB `socen_se_db_BoIS_prod` på `mysql97.unoeuro.com`; staging-DB
  `alberiq_se_db_BoIS` på `mysql115.unoeuro.com`. Inga stagingcredentials behövs i P13.
- Simplys autentiserade kontrollpanel **Återställ data** visar nattlig backup av
  webbhotellets data samt val för filer/mappar och MySQL, med datumval. Endast
  inventerad; leverantörens restoreknapp har inte använts. Retention, faktisk
  senaste lyckade kopia, offsiteplacering, inkluderade privata kataloger och
  återställningsgranularitet är inte bevisade. Produktrestore kan återinföra
  gamla SOCen-filer eller gamla databaser: använd den inte som selektiv BoIS-restore.
- SSH, PHP 8.5.11, `tar`, `mysqldump` och privata kataloger finns. Inga cronjobb
  är konfigurerade. Ingen ny automatisk backupschedule aktiveras i denna etapp.
- GitHub innehåller releasekod, migrationskod och CI. Git är inte backup av
  databas, runtime-secrets, HTTP-auth eller hostkonfiguration. P13-CI exporterar
  inga artifacts och har inga hosting- eller Stripecredentials.
- Tidigare SOCen-backuper är privata och separat märkta, se
  [P12-SOCEN-HOSTING.md](P12-SOCEN-HOSTING.md). De får inte importeras till BoIS.
  BoIS bootstrapdump finns privat lokalt och på hosten, läsbar/checksumverifierad
  i P12. Den innehåller en stängd kandidat utan kunddata, inte en bevisad driftbackup.

## Faktisk isolerad övning

`.github/workflows/p13-ci.yml` skapar tre disponibla databaser på runnerns
loopback-MySQL 8.4: `bois_p13_source`, `bois_p13_restore`, `bois_p13_sentinel`.
Ingen kontakt med Simply eller verklig staging/production sker. Fixtureverktyget
vägrar andra host-/databasnamn och kräver `BOIS_P13_DISPOSABLE=YES`.

1. Befintlig P6-fixture skapar syntetiska mockorder, betalningar och medlemskap,
   inklusive paid/failed/cancelled/refunded, signerade mockevent och receipt retries.
   E-post går till `.invalid`; transport, `mail` och cURL är avstängda.
2. Samtliga migrationer till P12 appliceras. 24 tabeller och åtta ledgerposter
   krävs; inga främmande tabeller accepteras.
3. En read-only consistent snapshot tar schemahash, radantal och ordningsoberoende
   SHA-256 av alla rader. Alla aktuella foreign keys kontrolleras mot orphans.
4. `mysqldump --single-transaction --no-tablespaces --routines --triggers --events`
   skapar en faktisk SQL-backup. Server/ops-filer och syntetisk privat konfiguration
   arkiveras med `tar`; arkiv och individuella filer får kontrollsummor.
5. SQL importeras till separat tomt restoremål. Arkivet extraheras till separat
   katalog. Schema, ledger, rader, foreign keys och en explicit
   order → payment / order item → membership → member-join jämförs.
6. Verifieraren måste upptäcka avsiktlig ändring av en återställd order och
   en extra schemakolumn. Endast det disponibla restoremålet ändras.
7. Ursprunglig syntetisk DB är oförändrad; sentinelraden är kvar. Tillfälliga
   dumpar, credentialfil och snapshots rensas vid exit. Inget artifact laddas upp.

Detta bevisar återställningsmekanismen för BoIS med syntetisk data. Det bevisar
inte leverantörens produktrestore, produktionsvolymens RTO, katastrofåtkomst
eller publikt köp-/admin-/medlemsflöde. P12:s uppskjutna tester kvarstår.

## Runbook: ta en privat BoIS-backup

Utför via dedikerad socen.se SSH-nyckel med verifierad hostnyckel. Kör aldrig med
shell tracing, lösenord i argv eller dumpinnehåll i terminal/CI. Använd privat
`mysql-backup.cnf` (0600), aldrig repo-/artifactsecrets. Läs först
`current-release` och registrera release-SHA, tid, databasmål och ansvarig.

1. Stäng ny order/checkout och externa sidoeffekter. Samordna event/worker-paus
   om betalningar redan finns; hantera kommande webhooks enligt P12-runbook.
   Fördröjda signerade event får inte tappas. Pausa schemaändringar under backup.
2. Verifiera exakt produkt, DB, release, privata paths och tillräckligt diskutrymme.
   `socen_se_db_BoIS_prod` är enda BoIS-productionkälla. Använd aldrig wildcard
   över alla produktens databaser. Alla BoIS-tabeller ska vara InnoDB.
3. Skapa en unik timestampkatalog under `.bois-production/backups/` med
   `umask 077`, katalog 0700, filer 0600. Den får inte ligga under `public_html`.
4. Ta read-only snapshot med granskad P13-kod:
   `BOIS_PUBLIC_ROOT=/var/www/socen.se/public_html php ops/p13-snapshot.php PRIVATE_CONFIG NEW_PRIVATE_SNAPSHOT`.
   Verktyget kräver privata paths, exakt schema/ledger och closed runtime; det
   skriver aldrig till DB. För framtida aktiv runtime krävs separat granskad
   anpassning innan verktyget används. Befintlig snapshot skrivs inte över.
5. Dumpa exakt DB till ny privat fil med
   `mysqldump --defaults-extra-file=PRIVATE_CNF --no-tablespaces --single-transaction --routines --triggers --events --result-file=NEW_PRIVATE_SQL socen_se_db_BoIS_prod`.
   Kontrollera exitstatus, privat stderr och dumpens avslutningsmarkör. DDL får
   inte ändras under körningen. `--databases` undviks så backupen inte väljer källdb vid import.
6. Arkivera BoIS releasekod, wrapper/public-filer, `.htaccess`, privat config,
   authhash, aktuell releasepointer och relevant PHP/cron/TLS/domänkonfiguration.
   Ta loggar endast enligt beslutad retention; nycklar lagras separat säkert.
   Undvik gammal SOCen, mailkonton och andra alias. Gör filmanifest och SHA-256.
7. Lista arkivet och kontrollera alla filers läsbarhet/checksummor. Ta en andra
   DB-snapshot medan skriverier är pausade och jämför med den första.
8. Kopiera över verifierad SSH till privat lokal lagring utanför repo/OneDrive:
   `C:\Users\AlbertAndersson\.codex\private\bois-production\`.
   Kontrollera samma SHA-256 lokalt. ACL begränsas till ägaren och SYSTEM.
   Hosten och lokal arbetsdator är två kopior, men ingen oberoende offsite-/immutable
   DR-kopia är därmed bevisad. Godkänd krypterad destination behövs inför drift.
9. Registrera endast metadata/checkresultat i repo. Behåll själva SQL, config,
   auth och rowhashar privat. Radera aldrig senaste verifierade fungerande kopia.

## Runbook: återställ och verifiera

1. Välj incident/releasepunkt och verifierad backup; kontrollera arkiv-SHA och
   filmanifest före import. Gör först en övning till isolerad tom DB och privat
   katalog med separat målkonfiguration. Kontrollera `SELECT DATABASE()`.
   Restoretarget måste skilja sig från både production och staging. En produktövergripande
   Simply-restore eller overwrite av driftdata kräver nytt uttryckligt beslut.
2. Inspektera SQL privat för `USE`, `CREATE DATABASE`, definers, routines/events
   och oväntade objekt. Stäng event_scheduler och externa transports i målet.
   Importera inte driftcredentials/launchkonfiguration direkt i en öppen testapp.
3. Importera med `mysql --defaults-extra-file=PRIVATE_TARGET_CNF EXACT_EMPTY_TARGET < PRIVATE_SQL`.
   Återställ filer i ny katalog, verifiera bytehashar, sätt katalogrättigheter
   0700 och secrets 0600. Apache-authhash måste ha nödvändig läsbarhet enligt
   P12; hela privat runtime får aldrig göras publik.
4. Med read-only anslutning: jämför P13-snapshot mot backupens baslinje.
   Verifiera 24 schema, åtta migrationer, rowhashar/counts, foreign keys och
   order/medlems-/betalrelationer. Dumpens filhash ensam räcker inte.
   Kör inte bootstrap som skriver över återställd affärsdata; bootstrap är endast
   för en tom stängd P12-kandidat.
5. Håll mail, batchleverans, live Stripe, checkout och analytics av. Köer kan
   innehålla redan skickade brev eller betalningar. Avstäm idempotens/event- och
   outboxstatus mot betalprovider innan workers återstartas. En databasrestore
   återför inte pengar och får inte skapa dubbla betalningar/refunds/partnerutskick.
6. Verifiera privata filrättigheter, TLS, auth, loggning och fail-closed-inställningar.
   Gör admin/medlems/köp-smoke i godkänt säkert testläge. Dokumentera faktisk
   start/sluttid och avvikelser. P12-verifiering får inte markeras klar automatiskt.
7. Driftcutover/DNS/Stripe live kräver separat godkännande. Spara föregående
   driftbackup för rollback och återställ inte över staging. Efter godkännande
   koppla exakt granskad release och återställd DB; återstarta effekter selektivt.

## Föreslagna verksamhetsmål – ej godkända eller driftlöften

RPO: högst 24 timmar under låg volym, med privat backup före varje deploy/migration.
RTO: högst fyra timmar från beslutad incidentrestore till verifierad stängd tjänst.
Vid kunddrift bör betalda order ge striktare RPO eller separat provideravstämning.
Simplys nattliga frekvens är inte bevis för uppnått RPO. Den lilla CI-fixturens tid
är ingen mätning av produktionens RTO.

Förslag retention: 7 dagliga och 4 veckovisa krypterade kopior samt senaste
verifierade releasebackup. Före kunddrift behövs namngiven driftägare,
godkända RPO/RTO/retention, kontrollerad offsiteåtkomst och larm vid misslyckad
backup. Besluten markeras inte godkända i `production_decisions.backup_restore`.
Ingen ny kostnad, leverantör eller cron införs genom denna dokumentation.
