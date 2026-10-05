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
