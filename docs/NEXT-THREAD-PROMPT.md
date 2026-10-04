# Nästa uppdrag – fortsätt blockerad P18B lokalt

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
