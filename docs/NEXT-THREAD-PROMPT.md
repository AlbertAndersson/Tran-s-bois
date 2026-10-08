## Aktuell teknisk acceptans 2026-10-08

P18A och P18B är tekniskt klara för stängd production, med Alberts uttryckligt
accepterade SELECT-only-undantag. Backupretention 30 dagar är beslutad för nya
backuper; befintliga äldre backuper bevaras och ingen raderingskod är byggd.
Full hostbrowseracceptans, DB + runtime + HTTPS-DR och ny tidsstyrd
Simply→Windows→Besovida-kedja har passerat. Cronincidenten med CRLF är rättad
och redovisad. Se [closeout](P18B-CLOSEOUT-20261008.md) och
[godkända driftbeslut](P18-OPS-DECISIONS-20261008.md).

P19-verksamhetsbeslut och publik P20-aktivering återstår. Production är fortsatt
stängd; Stripe live och slutlig DNS-cutover kräver separat godkännande.
Äldre motstridiga statusuppgifter nedan är bevarad historik.

## Nästa arbete efter teknisk P18-acceptans

1. Verifiera aktuell main och closeout; bevara senare arbete. Återupprepa inte
   de sex levererade P18-punkterna som om de vore blockerade.
2. Slutför verkliga P19-verksamhetsbeslut enligt P19-underlaget. Backupretention
   och SELECT-only-undantag är godkända; övriga affärs-/privacy-/adminbeslut är
   fortfarande öppna och får inte antas av assistenten.
3. Inför go-live måste backupmodellen fungera även vid öppnad production,
   personliga production-admins införas säkert och SELECT-only-undantaget
   omprövas i slutgranskningen. Behåll stängda flaggor medan detta förbereds.
4. Albert följer ordinarie cron 03:00/15:00 och Windows-cadencen. Cron som
   skrivs från Windows måste använda LF och rå byteverifiering. Dokumentera
   nya verkliga driftbevis och fel; en tidigare grön körning är ingen SLA.
5. Ingen backupgallring byggs/körs genom detta closeout. En senare beställning
   ska använda beslutad 30-dagarsregel och skydda befintliga äldre backuper.
6. Publik aktivering, Stripe live och slutlig DNS-cutover kräver sina separata
   uttryckliga godkännanden när ett konkret granskat underlag är färdigt.

## Aktuell driftverifiering 2026-10-07

PR46 är mergad. Windows-task är installerad och ett verkligt tidsutlöst
relayprov har passerat. Simply-backupen 03:00 är observerad. Crontab är nu
03:00 och 15:00 för marginal till RPO; nya schemat är ännu inte observerat.
Fullständigt
DR med DB + runtime + HTTPS från Besovida passerade på 216,437 sekunder,
inklusive korrigerade försök; production och ursprungliga testdata bevarades.
Retention, SELECT-only och sammanhängande hostbrowseracceptans återstår.
P18B/P18 och publik P20-aktivering är fortsatt BLOCKED. Se [aktuella bevis](P18B-OPS-EVIDENCE-20261007.md).

Äldre motstridiga uppgifter nedan är historik.

# Nästa uppdrag – fortsätt P18B och verksamhetsunderlag genom P20

## Aktuell lokal hoststatus 2026-10-06

Stängd production på dedikerad socen.se-produkt är uppgraderad till main
`cd76ea8ffedd6ca63eaf3905eda40031327421dc` efter PR42. Verifierad privat
backup, isolerad restore, rollback, readiness och syntetiska hostprov finns.
Verifierad krypterad offsitekopia finns på Besovida. Daglig Simply-backup är
installerad. Albert har accepterat driftansvar, RPO 24 h och RTO 4 h som mål.
Separat nyckelförvaring i iPhones Lösenord bekräftad av Albert 2026-10-06.
Albert har nu beställt fortsättning genom P20. Go-live-underlag förbereds,
men publik aktivering kräver kvarstående P18B-bevis, P19-verksamhetsbeslut
samt separat godkännande av Stripe live och slutlig DNS-cutover.
Se [P20-plan och lanseringsgrindar](P20-GO-LIVE.md).
P18A är DONE; P18B/P18 är fortsatt BLOCKED vid SELECT-only, automatisk
offsiteöverföring, övervakning/retention och full hostbrowseracceptans. Se [nya hostbevis och begränsningar](P18B-LOCAL-HOST-EVIDENCE.md).
Den äldre molnkörningens credentialblockerare nedan är historik och gäller
inte som aktuell lokal status. Staging är separat och har inte ändrats.

## Nästa konkreta P18B-körning efter PR46

1. På Alberts Windows-dator: kör först preview av
   `scripts/install-p18-offsite-task.ps1` med befintlig privat katalog och
   betrodd Python. Installera endast efter att previewens paths/user stämmer.
2. Verifiera Task Scheduler-resultatet: kör tasken manuellt en gång, kontrollera
   exit 0, `p18-offsite-relay-status.json`, health gate och Besovida-readback.
   Prova därefter ett kontrollerat failurefall som ger privat alertfil och lokal
   Windows-notis utan extern transport.
3. Observera minst en verklig tidsstyrd körning och första tidsstyrda Simply-
   backupen. Dokumentera att Windows-modellen kräver Alberts inloggning efter
   reboot; ingen Windows-credential lagras av installeraren.
4. Ta retentionbeslut innan någon radering byggs eller körs. Nuvarande kod
   raderar inget.
5. Genomför full tidsatt DR till isolerat mål och verifiera DB + runtime +
   service/HTTPS mot RTO-målet.
6. Öppna den syntetiska hostytan endast för kontrollerad full browseracceptans,
   därefter stäng den igen och bevara testdata.
7. SELECT-only: hostbevis + Simply-dokumentation stödjer providerbegränsning.
   Antingen ordnas providerlösning eller Albert accepterar uttryckligen ett
   dokumenterat tekniskt undantag med kompensationskontroller. Anta aldrig
   undantaget automatiskt.

## Tidigare dokumentation (historik)

## Aktuell styrning 2026-10-05

P18A är DONE och mergad via PR40. P18B är beställd men **BLOCKED**:
run37241703902 på main `4e4e2349631d434160b77bd9b2b2d3e7be7db349`
saknar produktionscredentials i GitHub och stannade före DB-anslutning.
Befintlig privat produktionsåtkomst är dokumenterad på Alberts lokala dator.
Ingen ny hostdeployment, migration eller aktivering utfördes.
P12 är accepterad av Albert; återstående hostbevis ligger i P18B.
P13–P17 är klara i kod/isolering och får inte beskrivas som fullt installerade.
P18 och P20 förblir BLOCKED vid hostacceptans; P19:s verksamhetssvar är öppna.
Se [aktuell hostacceptans och lokal fortsättning](P18B-HOST-ACCEPTANCE.md).
Äldre nästa-etapp-rader nedan är historik och gäller inte som arbetsinstruktion.

## Genomförande


1. Läs aktuell main, denna fil, `P18-RELEASE-ACCEPTANCE.md`,
   `NEXT-THREAD-PROMPT.md` och SharePoint-utvecklingskön. Jämför opushat lokalt arbete
   innan ändringar. Bevara senare arbete och registrera branch/PR.
2. Använd befintlig privat socen-SSH/config. Skriv aldrig credentials,
   kontaktdata, DB-innehåll, TOTP-seeds eller backups till GitHub/SharePoint/loggar.
   Bekräfta host fingerprint med befintlig källa; ingen säkerhetsbypass.
3. Läs faktisk release och stängda runtimeflaggor. Ta verifierad privat
   DB-/release-/runtimebackup innan uppgradering. Ingen äldre backup får antas aktuell.
4. Kör ovanstående acceptanspunkter och P18B-planen. Produktion ska förbli
   production och stängd; alla fulla köpprov sker i separat syntetisk testinstans.
   Skapa inte testkonton i production och ändra inte demoordrar/årsgräns.
5. Installera komplett P18A-paket först när backup och rollback är verifierade.
   Redovisa hostens begränsningar, inklusive eventuellt SELECT-only-konto och
   offsite/cron. Osäkra eller saknade bevis förblir tekniska blockerare.
6. Uppdatera GitHub-status/runbooks samt båda SharePoint-överlämningarna med
   datum, app-/workflow-SHA, resultat och begränsningar. P18B får markeras DONE
   först när kriterierna faktiskt är uppfyllda.

P20, Stripe live, extern mail, skarp gallring, DNS-cutover och nya externa
kostnader ingår inte. Följ befintlig releaseacceptans; ingen ny affärsacceptans
fabriceras. Erik-utkastet med leverantörsexempel är sparat i Outlook men inte
skickat av assistenten; det är inte ett verksamhetsgodkännande.
