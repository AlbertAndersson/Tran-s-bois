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
