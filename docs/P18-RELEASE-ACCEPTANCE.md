## Aktuell styrning 2026-10-05

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

---

# P18A – samlad kodrelease och verifierat leveranspaket

Beställd av Albert 2026-10-04. Baseline main:
`fd36b6ad3e70010bad73aa5b9dae3a1432df48f1` (P17, PR39).
P18A levereras via PR40. Kod-head `b165c67a1cbdb2d98e8ca2c7f2f91a03d7b487e8`
har 16/16 gröna PR-kontroller; P18-run
[37235765983](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37235765983)
är success. Slutlig PR-head och main verifieras på nytt före closeout.
P18B hostacceptans återstår. P18 är IN PROGRESS.

## Konkreta rättningar

- P5/P6 kräver P17-mailmodulen. De manuella staging/sandbox/P8-paketen
  kopierar nu den och dess worker om den valda apprevisionen innehåller dem,
  samt http-errors och supporterpreview. Oförändrade äldre apppinnar stöds.
  Statisk PHP-beroendekontroll körs både på byggt och slutligt transportpaket.
  Senare moduler, inklusive consent, är villkorliga för äldre pinnar som föregår
  modulerna; saknat krävt beroende nekas ändå. CI hämtar de faktiska P8/P9/sandbox-
  pinnarna och bygger deras PHP-fillistor från nuvarande workflowdefinitioner.
- Commerce API skiljer autentisering (401), origin/roll/CSRF/aktiveringsförbud
  (403), och affärskonflikt (409). Slutsålt och återanvänt event-ID med annat
  innehåll är konflikter. Fel kundtoken och okänt order-ID ger båda generiskt
  401 för att inte avslöja orderexistens. 400/404/405/413/415/422/429/500/503
  behåller sina befintliga betydelser. UI visar API:ets befintliga affärsmeddelande.
- `scripts/p18-release.cjs` bygger ett separat, stängt komplett paket:
  public UI/wrapper, privata server-/ops-/datafiler och configmall. Manifestet
  innehåller exakt source-SHA och SHA256 för varje fil; verifieraren nekar
  saknade/extra/ändrade filer, symlänkar och saknade statiska PHP-importer.
  Manifest och configmall ligger utanför public. Paketet innehåller inga
  credentials eller verksamhetsgodkännanden.

## Verifiering och begränsning

`P18A Complete Release` kör syntax på all PHP/JS, negativ manifestkontroll,
två oberoende PHP-processer vid 19/20 gymreservationer (exakt en accepterad),
utgången obetald reservation kontra betald kvot, och Chromium på 375/390/1280.
Browsern kör P4–P9:s fulla syntetiska köpresa mot paketets privata runtime:
medlemskap/gym, matchtröja, batch, misslyckad/avbruten betalning/nytt försök,
mockrefund, consent nej/ja/återkallelse och admin. Därefter slutsålt/UI,
supporterpreview utan formulär, samt verkliga HTTP 401/403/409/404/422/500/503.
Stängd produktion provas med otillgänglig DB och nekade anrop skapar inga order.
P14-regressionen provar även inloggad otillräcklig roll och saknad CSRF som 403.
Övriga tillämpliga CI-gater täcker MFA, P7-datumspärr, privat mail/signerade
betalningar, backup/restore, säkerhet, drift och retention. Inga hostsecrets
används; PHP mail/curl är avstängda i nya integrationstester.

CI:s stängda artifact och syntetiska browser-PNG sparas 14 dagar. Paketet har
samma SHA som workflow-checkouten.
PR-körningar använder GitHubs testmerge-SHA; main-körningens artifact följer main-SHA.
Efter nedladdning: `node scripts/p18-release.cjs verify /absolut/paket`.
Byggning/acceptans ändrar aldrig runtimeflaggor. Dynamisk privat configväg,
DB/grants, PHP och Apache/.htaccess/Basic Auth verifieras först på host i P18B.
CI Chromium är inte ett fysiskt iPhone/Safari-test eller Erik/BoIS-acceptans.

## PR35 avstämd

PR35 `9c5b5d76260deea045c9642e964cbd1531041d21` är en äldre diagnostikgren.
Rotorsaken (förbrukad syntetisk gymkvot och utebliven UI-initiering) samt
liveacceptans finns i PR38/run37219659212. Dess föreslagna borttagning av
production/P14/P15 ur payload är ersatt av explicit beroendepaketering och
oförändrade apppinnar. Inget separat funktionellt arbete återstår från PR35;
den stängs utan merge efter P18A-verifiering. Ingen hostdata eller nyckel återställs.

## P18B – återstående teknisk acceptans

Registrera faktisk app-SHA, workflow-SHA, miljö och datum var för sig. Senast
dokumenterad stagingapp är `276558e9b187aeea7d0324b662ab20c6513d125b`;
stängd socen-app `020446867dcf29b7ebe63a060f8594e8f8da8aff`.
P13–P18:s senare kod får inte beskrivas som installerad där.
Verifiera isolerade productioncredentials/grants, privata filer/rättigheter,
MFA/prober/drift samt hostens backupomfattning, återställning och rollback.
Bevara stagingens 31 äldre syntetiska betalda gymkort; ingen kvotreset ingår.
Ett hostköpprov med ledig kvot behöver uttryckligen avgränsad testfixture.
Varken offsite-backup eller cron/daglig backup är visad som installerad.
Endast när A och B är accepterade kan samlad RC/tag och teknisk go-live-status sättas.
Stripe live, extern mail och launch förblir spärrade. P19-underlaget finns i
[P19-DECISIONS-DRAFT.md](P19-DECISIONS-DRAFT.md).
