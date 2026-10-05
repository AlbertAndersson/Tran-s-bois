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

# Aktuell status – P18A levererad via PR40

P18A:s kompletta stängda releasepaket, API-felkoder och samlade regressioner är
klara. Kod-head `b165c67a1cbdb2d98e8ca2c7f2f91a03d7b487e8`: 16/16 gröna PR-kontroller,
inklusive [P18A 37235765983](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37235765983).
Slutlig PR-head och main-körning ska vara gröna vid closeout; exakta aktuella
merge-/runreferenser finns i [PR40](https://github.com/AlbertAndersson/Tran-s-bois/pull/40).
Se [releaseacceptans](P18-RELEASE-ACCEPTANCE.md) och [P19-utkast](P19-DECISIONS-DRAFT.md).

**P18: IN PROGRESS. P18A: DONE. P18B: TODO. P19: TODO, utkast förberett. P20: BLOCKED.**
P12 accepterades av Albert; det ersätter inte kvarstående hostbevis. P13–P18A
är kod och isolerad verifiering. Inga P18-hostdeployer, runtimeaktiveringar, verkliga
betalningar, externa mail eller nya betalda tjänster har utförts.

Senast dokumenterad skyddad stagingapp: `276558e9b187aeea7d0324b662ab20c6513d125b`,
run37219659212. Senast dokumenterad stängd socen-app:
`020446867dcf29b7ebe63a060f8594e8f8da8aff`. Ingen av dessa representerar hela
aktuell kod. App-SHA, workflow-SHA och senare dokumentations-SHA skiljs åt.
P18B ska verifiera hostrelease, privat DB/grants/runtime, MFA/drift/loggning,
backupomfattning, återställning och rollback. Cron/offsite/daglig hostbackup är
inte visade som installerade. Befintlig stagingkvot är förbrukad av 31 äldre
syntetiska PAID-gymkort; den har inte återställts. Tester använder disposable DB.

PR35 är avstämd som ersatt av PR38 och P18A:s beroendepaketering; stängs utan
merge vid closeout. P19-tabellen är inte skickad; alla verksamhetssvar är öppna.
Nästa tekniska uppdrag är P18B enligt den justerade SharePoint-kön.

## Historiska leveransnoteringar – tidigare nästa-steg-rader gäller inte

## P17 – DONE, email/outbox production readiness

2026-10-04: Albert accepterade P12 och beställde endast P17. Acceptansen är
ett användarbeslut, inte bevis för nya hosttester eller tillstånd för go-live.
Äldre P12-/P17-status nedan är historisk; P18 har inte startats.

Main före P17: `0637bc2c9771443dd834baa5492f6ca902452e00`. PR #39.
Verifierad kod-head: `5bde80215bb316fa54a8c11594ac6be5a7f8a537`.
P17 [37225130300](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37225130300),
jobb `111503109361`: **success**. Alla 13 startade kontroller på kod-head
är success: P2/P5/P6/P7/P8/P9/P12/P13/P14/P15/P16/P17/Security.
P14 inkluderar Chromium/admin; P8 inkluderar tidigare commerce-regressioner.

Gemensamt renderat mailtransportlager, privat idempotent sink, strikt spärrad
hostadapter, order-/betalningsfel-/avbrotts-/refundmallar och leverantörsmail
med verifierad CSV/MIME är implementerade. Båda köerna låser/revaliderar raden
före transport, behåller fem försök/backoff och generiska privata felkoder.
Signerade mockbetalningar genom hela mailkedjan, samtidiga workers för båda
köerna, injektions-/mottagar-/sinkspärrar och varje aktiveringsgrind passerar.
CLI-only mailworker skapar inga batcher och kör ingen DDL.

Se [P17-runbook](P17-EMAIL-OUTBOX.md). Inga nya tabeller/migrationer,
Simply-deploy, riktiga mottagare, mail, Stripe live, riktiga pengar, DNS-cutover,
cron, productionaktivering eller ny extern kostnad. Båda productiontransporter
förblir disabled; gamla syntetiska köer får inte skickas externt. Framtida host-
provider/avsändare/SPF/DKIM/DMARC och extern leverans kräver separat verifiering
samt uttryckligt godkännande. SMTP exakt-en-gång-leverans utlovas inte.

P17 tekniskt klar; ingen manuell åtgärd behövs nu. Nästa etapp är P18.

## Staging inför nästa P-etapp – verifierad 2026-10-04

**READY FOR NEXT DEVELOPMENT PHASE (P17); NOT PRODUCTION READY.**
Skyddad staging: [37219659212](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37219659212),
jobb `111487249492`: **success**, alla steg inklusive browseracceptans.
Verifierad workflowref: `ed81259c6f725589ecc7acd4b962c69b418a39d4`.
Faktiskt publicerad app-/browserref: `276558e9b187aeea7d0324b662ab20c6513d125b`.
PR #38 innehåller rättningen. Main före rättningen: `fbd3f07b000f419ad05acfaa59eaeed0e47c6d14`.

Root cause för run `37216249742`: ordertestet förutsatte obegränsade gymkort.
Live-diagnos visade limit 20, sold 31, reserved 0, remaining 0; äldre syntetiska
PAID-order från före kvotregeln förbrukar hela stagingkvoten. Servern nekade
korrekt nya gymorder med DomainException, som befintligt API mappar till 401.
Detta var inte en felaktig adminnyckel. Inga secrets roterades eller visades.

Browser-gaten hittade dessutom att `refreshGymAvailability()` aldrig startades:
anropet låg inuti funktionen efter refresh, i stället för vid sidans initiering.
Anropet har flyttats till initieringen. Live Chromium verifierar Slutsåld,
disabled/unchecked gymval på 375/390/1280 px. Kvoten 20 och tidigare order bevaras.

Live verifierat: Basic Auth accepterar korrekt credential och nekar obehöriga/fel
credential; fel/saknad adminnyckel nekas; rätt nyckel fungerar på
admin_orders/catalog/P4/payments/P7/sales. P10 headers/grindar,
serverns slutsåltspärr, medlemsorder → signerad mock-PAID/idempotens,
samtycke/attribution/återkallelse samt browserköp, misslyckad/avbruten betalning,
nytt försök, matchställskö och mockrefund passerar. Befintlig-medlem+gym och
Nordic-aktivering kan inte köpas i denna förbrukade hostkvot; deras funktionella
regression körs i isolerade CI-fixturer. Browserloggen redovisar denna gräns.
Matchbatch körs bara när testets egna väntande rad är ensam; andra rader bevaras.

12 startade CI-workflows på kod-head `ed81259c6f725589ecc7acd4b962c69b418a39d4`
är **success**: P2, P3, P6, P7, P8, P9, P12, P13, P14, P15, P16 och Security.
P3 omfattar 20-gränsen och nekad 21:a order; P8/P16 inkluderar tidigare fasregressioner.
YAML, samtliga Bash-steg och browser-JavaScript har även syntaxkontrollerats.

Nästa utveckling är P17 enligt befintlig kö; P17 har inte startats.
P12:s kvarvarande host-/flödesverifiering är fortfarande uppskjuten enligt beslut.
Denna stagingcloseout ersätter inte P12, produktionsacceptans, fysisk iPhone/Safari
eller föreningens godkännande. Produktion, Stripe live, extern mail och nya kostnader
har inte aktiverats. Tekniskt kvarstående API-förbättring: skilj kvotkonflikt från
autentiseringsfel i HTTP-status; det ingår inte i denna verifieringsfix.

## P16 – DONE, privacy och konfigurerbar retention verifierade

Main före P16: `df6c80a68945a9b6fe7b103a28a66399cf25ea42`. PR #34 är mergad;
kod-main efter merge: `ad035c6288852f354a7b39d433aa8c25b10d6180`.
Slutlig PR-head: `d6f2bbbdaffc35adeb96e6f4419c8a779bbf0131`.
Inventering av 24 tabeller/281 fält och inbäddade JSON/CSV-personuppgifter,
konfigurerbar retention med TBD/null, privat SELECT-only dry-run och guardad,
atomär gallring av valfri sales/inaktiv consent är klara. De 20 övriga tabellerna,
betalnings-/medlems-/leveransspår och åtta ledgerposter bevaras.
P16 [37179059419](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37179059419),
jobb `111367711810`: success. Alla 11 startade PR-kontroller är success;
P16 kör även den syntetiska P6-fixturen för P3–P6-spår. P14 37179059420
inklusive Chromium, P12/P13/P15/P9 och Security passerar.
Se [P16-runbook](P16-PRIVACY-RETENTION.md) och [fältinventering](P16-DATA-FIELDS.json).
Externresurser/sc_clearance/Stripe har granskats tekniskt; leverantörs-/cookie-
verifiering och juridiska retentionbeslut är TBD inför aktivering.
Ingen production-/staginggallring, Simply-deploy, Stripe live, DNS-cutover,
extern mail, ny tjänst/kostnad eller productionaktivering. socen.se är fortsatt
stängd dedikerad production, separat från staging/sandbox på alberiq.se.
P12-verifiering är uppskjuten enligt beslut. Inga tekniska P16-blockerare eller
manuella åtgärder nu. P17 har inte startats. Äldre status nedan är historisk.

## P15 – DONE, observability och driftberedskap verifierade

Main före P15: `18ef6b03605ae96c5c1b84fa6f683037c0d452c8`. PR #33 är mergad;
kod-main efter merge: `f298e69cdc94cffdb7011bac43e98a93ab989f7d`.
Slutlig PR-head: `a7c476658118f908f69ee6f0bf554004b3a2faab`.
Minimal liveness/readiness, privat JSON-loggning, request-ID/P14-auditkorrelation,
read-only DB-/kö-/diskkontroller och incident-/första-veckan-runbook är klara.
P15 [37157817328](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37157817328),
jobb `111304807554`: success. P14 37157817345 inklusive Chromium och
P2–P13/Security är success på slutlig PR-head. Se [P15-runbook](P15-OPERATIONS.md).
Ingen Simply-deploy, riktiga konton, productionaktivering, live Stripe, DNS-cutover,
extern mail eller ny extern tjänst/kostnad. Production är fortsatt stängd på
socen.se; staging/sandbox är separat alberiq.se. P12-verifieringen är uppskjuten
som tidigare beslutat. Inga P15-blockerare eller manuella åtgärder nu.
P16 är nästa etapp och har inte startats. Äldre status nedan är historisk.

## P14 – DONE, personliga konton och MFA verifierade

Baslinje main: `c6e5787fe47cc46642a7b7bafbefbf9221c83eb8`. PR #32.
Personliga lösenord+TOTP-konton, serverstyrda superadmin/club_admin/operator,
privata serversessioner, timeout, CSRF, logout/revoke och audit är implementerade.
Production accepterar inte den delade stagingnyckeln. Inga riktiga konton seedas,
ingen hostdeploy/launch, Stripe live, DNS-cutover, extern mail eller ny kostnad.
PHP/HTTP och Chromium passerade i P14 run 37156273030, jobb 111300181791;
P2–P9, P12/P13 och Security är success på kod-SHA 899913d716d9f820d7c1fda1ddad4392bc0675c6. P12-verifieringen är uppskjuten
som tidigare beslutat. Se [P14-runbook](P14-PERSONAL-ADMIN.md).
P15 har inte startats. Äldre status nedan är historisk.

## P13 DONE – P12-verifiering uppskjuten enligt beslut
P13 closeout: PR #31 är mergad som `814a8105334c8316577b3fe368d05011a700016d`.
Main före P13: `56c2d8ba6455fa385c02867922ec12308a26ae4b`.
Slutlig PR-head: `6e235e9f50072361422122ab9dd12100de904dab`.
P13 [37154784578](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37154784578),
jobb `111295845017`: success. P12 37154784586, P2 37154784622,
P7 37154784615, P8 37154784589, P9 37154784630 och Security 37154784581:
samtliga success på slutlig PR-head. Schema- och datakorruption verifieras
oberoende av varandra. P13-acceptansen är klar med faktisk isolerad restore;
P12:s kvarvarande host-/flödestester är fortsatt uppskjutna enligt beslut.


Användaren har uttryckligen valt att fortsätta med P13 eftersom återstående
P12-verifiering inte kan genomföras nu. P12 är inte DONE; dess kvarvarande
flödes-/hostverifiering är uppskjuten (SKIPPED BY DECISION för dessa kontroller).
BoIS production ligger på den dedikerade Simply-produkten socen.se, staging och
Stripe-sandbox ligger kvar på separat alberiq.se-produkt med separata credentials.
Den skyddade production-kandidaten behåller launch/checkout/betalning/mail av.
P13 gäller backup/restore: isolerad syntetisk MySQL- och filrestore, privat
read-only integritetskontroll samt runbook. Se docs/P13-BACKUP-RESTORE.md och
PR #31; restore-CI 37154604007 är success på kod-SHA 02ac9557c42017e3d166d54a012f347d9d4a2421. P14 är nästa etapp och har inte startats. Ingen ny productiondeploy, DNS-cutover eller Stripe-live-aktivering.
Äldre P12-status nedan är historisk och ersätter inte detta beslut.

## P12 – skyddad production-kandidat installerad och HTTPS-verifierad

2026-10-03: aktuell app-main `020446867dcf29b7ebe63a060f8594e8f8da8aff` är
installerad på `https://socen.se/bois-shop-production/` bakom separat Basic Auth.
Obehöriga nekas (401), behöriga sidor laddas (200) och commerce/admin/köp nekas
av stängd production-grind (503). HTTPS, säkerhetsheaders och privat PHP-loggning
passerar. Production-schema/rader är oförändrade och stängd readiness är grön.
Legacy-webbroten är arkiverad privat. Staging/sandbox, DNS och live Stripe är
orörda. P12 är inte DONE: full syntetisk flödes-E2E och separat SELECT-only
hostkonto återstår. Se P12-SOCEN-HOSTING.md för bevis och avgränsningar.

## Historiskt läge före skyddad appinstallation
## P12 – legacy-databas raderad efter uttryckligt godkännande

2026-10-03: hela `socen_se_db` är raderad efter ny verifierad full backup.
BoIS production-schema/rader är oförändrade och stängd readiness passerar.
Gamla WordPress-webben är stängd (HTTPS 403); webb-filer och backup bevarade.
Staging, DNS, Stripe live och e-post är orörda. Ingen mer manuell åtgärd krävs
för legacy-raderingen. Separat SELECT-only hostkonto och publik app/full E2E
återstår; P12 är inte DONE. Se `P12-SOCEN-HOSTING.md` för backup och efterkontroll.

## Historiskt läge före godkänd legacy-radering
## P12 – SOCen backup och privat production verifierad; DB-grants blockerar

2026-10-03: `socen.se` är beslutad som dedikerad BoIS production-produkt,
skild från staging/sandbox på `alberiq.se`. Inga andra system får installeras där.
Main `1906b1f055b2709c1c9cbaab4d8d600f2caa8e5c` är privat installerad på Simply.
SOCen-filarkiv, full MySQL-dump och konfiguration är privat säkerhetskopierade;
lokal/server SHA-256 och läsbarhet verifierade. Inget legacy-material är raderat.
Ny production-DB skapad utan ny kostnad. P12-bootstrap/readiness är gröna,
upprepad bootstrap och CLI 503-grindar lämnar schema/rader oförändrade.
Production-kontot nekas åtkomst till staging. Launch och externa funktioner är av.

**BLOCKED:** Simply delar produktens DB-användare mellan legacy och production;
kontot saknar CREATE USER. Separat production-only bootstrapkonto och SELECT-only
hostkonto behöver ordnas av Simply. Publik appinstallation, HTTPS/app-E2E,
loggningsacceptans och legacy-nedtagning återstår. GitHub productionsecrets är
inte installerade. Inga DNS-/Stripe-live-/stagingändringar. Se
`P12-SOCEN-HOSTING.md` för backupplatser/hashar, verifieringsgränser och manuell åtgärd.

## Historiskt P12-läge före SOCen-provisionering
## P12 – kod verifierad, produktionsmiljö blockerad

**BLOCKED.** Produktionskonfiguration, idempotent bootstrap, SELECT-only readiness,
launch-preflight och cutover/rollback-runbook är implementerade i PR #30.
Slutlig kod-SHA `7c90987bb75ee4a661ed48aed8fbdf1b081ae1bf`, merge
`b8590e4566f77ed4a2f630c256c2e2626cbe43d3`. Baslinje före P12:
`9d866c436543c0874061ca244915a3327b40041b`.

P12 CI [37136439541](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37136439541)
och P2–P9/Security är success på slutlig PR-head. P12 merge-main 37136549149 och
Security 37136549167 samt tillämplig fasregression är success. Isolerad MySQL
bevisar upprepad bootstrap utan ändring, skrivskyddad readiness, databas- och
credentialseparering, oförändrad syntetisk staging, HTTP 503 före DB, saknade
secrets/beslut och vägran vid kunddata/främmande tabeller/schema-drift.

Faktisk driftkontroll [37136579981](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37136579981)
stannade före DB vid saknade `BOIS_PROD_DB_HOST`, `BOIS_PROD_DB_NAME`,
`BOIS_PROD_DB_USER`, `BOIS_PROD_DB_PASSWORD` och `BOIS_PROD_ADMIN_TOKEN`.
Separat kostnadsfri databasplats hos Simply är ännu inte verifierad. Ingen
produktionsdatabas eller privata produktionscredentials har skapats, ingen
Simply-appdeploy eller DNS-ändring har gjorts. CI-resultatet ersätter inte
verifiering av faktisk hostmiljö. Se `P12-PRODUCTION-CUTOVER.md` för exakt runbook.

Nästa arbete: lös P12:s separata DB/credentials inom befintlig plan utan extra
kostnad, kör privat host-bootstrap och readonly readiness och registrera bevis.
P12 får därefter markeras DONE. P13 har inte startats; fortsätt inte automatiskt
förbi blockeraren.

Live Stripe/riktiga pengar/refund/mail/SMS/extern analytics/annonser/produktion:
NEJ. `production_launch_enabled=false`. Ny extern kostnad: 0 kr. Work Capture,
BoIS stagingdatabas och befintliga skyddade mock/sandboxdeploys bevarade.

## Historiskt läge före P12

## P11 – verifierad Stripe sandbox E2E

**DONE.** Skyddad separat sandbox är publicerad med app-SHA `6d13f811d9bce8ebcefec421ab6d360642353bce`. PR #29 mergeades som `636feb7870ac2212363400f81afa6952dbde1463`.

Run [37131884326](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37131884326), job `111228446294`: **success**. Hosted Checkout med testkort, faktisk signerad Stripe-webhook till PAID, metadata/belopp/SEK, order-idempotens, replay/signatur/timestamp, decline, cancel/return och testrefund till REFUNDED passerade. Mock-stagingens browserregression på 375/390/1280 px passerade också.

Restricted test key och separat signing secret verifierades utan att visa värden: run `37132159485`, job `111229248605`, **success**. Relevant P2/P7/P8/P9/Security CI är grön; P8 inkluderar P3–P7-regression. Se `P11-STRIPE-SANDBOX-E2E.md` för samtliga referenser, verifieringsgränser och de två rättade routingfelen.

Stripe live: NEJ. Riktig betalning/refund: NEJ. Extern mail/SMS/analytics/annonser: NEJ. Produktion aktiv: NEJ (`production_launch_enabled=false`). Ny extern kostnad: 0 kr. Swish är av och inte testat.

P11-blockerare: inga. Nästa öppna etapp: **P12 – Produktionsmiljö och databascutover-readiness**. P12 har inte startats.

## Historiskt läge före P11

# CURRENT STATUS

## P10 – DONE / skyddad staging slutverifierad

Baslinje main: `0a25f1be0114d147222518b69be805fe75c10d30`. PR #28 merge: `73aaefeb102d7c71ba0ae08231d203166aa843c6`. Workflowfix: `bc692e6ae991d606442155b0a9efb236f0803a85`.
Publicerad applikationsref: `f355ff5c26ce11ad20815e2f48b35ec2f4bbc064`.
Slutlig stagingrun: [37128656576](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37128656576), jobb `111219110739`, **success**, samtliga steg gröna. Workflowref `bc692e6ae991d606442155b0a9efb236f0803a85`; senare dokumentationscommits är inte ny appdeploy.

Säkerhetsheaders/CSP på HTML och API, method/origin/JSON/body-kontroller, separat adminbehörighet före DB, privat atomiskt missbruksskydd och interna endpointspärrar är verifierade. HTTP Basic Auth består. Full P4–P9 mock-E2E och Chromium 375/390/1280 passerade: köp utan statistik, nekad/avbruten betalning och nytt försök, samtycke/attribution/återkallelse, medlemsverifiering, batch och mockrefund. Inga främmande tabeller tillkom; tabellnamnshash/count oförändrade, antal 0. Detta är inte en restoreövning.

Stripe live: NEJ. Riktig betalning/refund: NEJ. Extern mail/SMS: NEJ. Extern analytics/annonser: NEJ. Produktion aktiv: NEJ (`production_launch_enabled=false`). Ny extern kostnad: 0 kr.

P10-blockerare: inga. Kvarvarande tekniskt riskregister och HSTS-strategi finns i `P10-PRODUCTION-HARDENING.md`. Personliga adminroller, backup/restore, retention och produktionsdrift hanteras i senare etapper. Nästa öppna etapp: **P11 – Stripe Sandbox E2E**; den har inte startats här.

CI på merge-SHA, samtliga success:

| Kontroll | Run |
| --- | --- |
| P2 | [37128431291](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37128431291) |
| P3 | [37128431333](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37128431333) |
| P4 | [37128431304](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37128431304) |
| P5 | [37128431347](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37128431347) |
| P6 | [37128431339](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37128431339) |
| P7 | [37128431356](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37128431356) |
| P8 | [37128431275](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37128431275) |
| P9 | [37128431363](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37128431363) |
| Security controls | [37128431415](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37128431415) |

Browserartifact: `11276236203`, `bois-p9-synthetic-browser-37128656576`, 1 232 975 byte, digest `sha256:fcb0fcb1019af404ac1d17a3ba56a8203905e9c61967eb3328ce7c15bf74065b`, giltig till 2026-10-10 14:11:43 UTC. Fysisk iPhone/Safari och verksamhetsacceptans är inte testade av Chromiumkontrollen.

Första stagingrun `37128488635` deployade samma appref men stoppades på en ny verifieringscurl utan etablerad User-Agent (HTTP 455). Workflowfixen återanvänder befintlig klientidentifiering. Slutrun ovan passerade hela kedjan.

## Historiskt verifierat läge före P10



Datum: 2026-09-29

## Senaste verifierade läge

**PROTECTED STAGING / LIVE VERIFIED / READY FOR LIMITED SYNTHETIC DEMO**

Stagingens åtkomstskydd och stabila adminåtkomst är nu verifierade. BoIS-ägda run **36906493137** avslutades med **success** och samtliga jobbsteg passerade. Den körningen aktiverade det privata Repository Secret `BOIS_STAGING_ADMIN_TOKEN` i serverns privata runtime och verifierade samma nyckel i API- och browseracceptansen. Detta är verifierad testdrift, inte produktionslansering eller Eriks verksamhetsgodkännande.

| Referens | Värde |
| --- | --- |
| Repository för kod, CI, secrets och deployment | `AlbertAndersson/Tran-s-bois` |
| Workflow | `.github/workflows/simply-deploy-bois-p9-staging.yml` |
| Körning | [36906493137](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/36906493137) |
| Jobb | `110518241965` – `deploy`, success |
| Workflow/main vid körningen | `9922c6362be711b889a032a267685b70f8204f2a` |
| Faktiskt utcheckad och publicerad applikationsref | `e245b5f72e1446656c2bb2f27fddc19180a6f9cd` |
| Avslutad körning | 2026-10-01, 18:25 UTC |

Senare dokumentationscommits ändrar inte den publicerade applikationsrefen. Verifiera alltid aktuell `main` före nästa ändring; återställ inte till ett historiskt SHA.

## Verifierat i den skyddade miljön

- HTTP Basic Auth installerat för hela `/bois-shop-p3/` över HTTPS. Det privata demo-secretet accepterades; lösenordet ska inte förekomma i dokumentationen.
- Obehöriga direkta HTML-, JS- och API-URL:er, inklusive shop, admin, order, payment, consent och query strings, gav 401. Obehöriga POST-anrop till orders, checkout, mockbetalning, consent och sales-event samt fel demoinloggning nekades.
- Behöriga P4–P9-kontroller passerade bakom inloggningen. SHA-256-jämförelse av `.htaccess`, `common.js`, `commerce-api.php`, `consent.php` och `cookies.html` passerade.
- Health verifierade P9, mock/testmode, first-party sales engine, `checkout_enabled=true` för mock, `sales_tracking_enabled=true` för syntetisk staging, extern analytics false och Stripe-/produktionsberedskap false. Statistik kräver dessutom besökarens serververifierade samtycke.
- Ingen statistik utan medgivande; ja → syntetisk attribution → order → signerad mock-PAID; återkallelse blockerade även återanvändning av tidigare samtyckeskaka.
- Medlemskap ACTIVE, Nordic ELIGIBLE/manuell medlemskontroll, matchställskö, syntetisk batch, betalningsfel/avbrott/nytt försök och simulerad refund passerade. P5:s 8/168h-regel och P7:s dolda/blockerade merch bevarades.
- Extern mejltransport var disabled. Antal och hash för listan över icke-BoIS-tabeller var oförändrade före/efter, antal 0. Det är inte en ny fullständig radbackup eller återställningsrevision.

## Webbläsaracceptans och bevis

Chromium headless på Linux testade **375, 390 och 1280 px**. Loggen rapporterade pass för köp utan val/efter nej, befintlig medlem med misslyckad/avbruten betalning och nytt försök, samtycke/attribution/återkallelse samt adminsektioner, medlemsverifiering, matchställ och mockrefund. Testerna kördes med Basic Auth och separat privat adminnyckel.

**10 PNG** sparades i artifact `bois-p9-synthetic-browser-36623915463`, ID `11059388459`, 1 229 695 byte. [Öppna artifacten](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/36623915463/artifacts/11059388459). Den uppgivna giltighetstiden är till **2026-10-06 20:08:02 UTC**. SHA-256 för ZIP: `5199fe10e718cee4de4823ef003fbb76a236ad4319857fe7252143a8bc553f65`. Någon permanent kopia av bilderna har inte skapats i dokumentationscloseouten.

Detta är automatiserad browseracceptans, inte ett test på fysisk iPhone/Safari eller ett redan genomfört godkännande av Erik/BoIS. Se `CONSENT-INVENTORY-AND-ACCEPTANCE.md` och `SYNTHETIC-DEMO.md`.

## Fasstatus och oförändrade gränser

- P1–P3: tekniskt levererade.
- P4–P7: COMPLETE / LIVE STAGING VERIFIED; P7 är inte kommersiellt lanserat.
- P8: **TECHNICALLY COMPLETE / NOT ACTIVATED**.
- P9 och C1–C4 samtycke: implementerat, CI-verifierat och åter verifierat i skyddad syntetisk staging.
- Demoåtkomst: **LIVE VERIFIED** för begränsat antal behöriga granskare. Basic Auth ersätter inte framtida personliga adminkonton, roller eller MFA.
- Stripe/KYC, riktiga betalningar/återbetalningar, extern analytics, annonsering, externa mejl/SMS och produktion: **INTE AKTIVERADE**.
- Ny extern kostnad i leveransen: **0 kr**. Den här closeouten ändrar endast dokumentation.

## Aktiv staging och driftansvar

Shop: https://alberiq.se/bois-shop-p3/

Medlemskap/Nordic: `membership.html`; matchställ: `match-kit.html`; orderstatus: `order.html`; mockbetalning: `payment.html`; admin: `admin.html`; samtyckesinformation: `cookies.html`; intern sortimentsvy: `assortment-preview.html`. Samtliga ligger bakom samma katalogskydd. API: `commerce-api.php`.

BoIS äger ensam kod, CI, secrets och deployment. Den dedikerade stagingdatabasen använder `BOIS_DB_*` i privat runtime. Ingen ny ändring av Work Capture eller databasflytt ingår. Skarp drift ska senare använda separat produktionsdatabas, skild även från BoIS staging. Byt inte adress eller driftupplägg utan beslut.

Demoanvändaren och rotation beskrivs i `STAGING-DEMO-ACCESS.md`. Dela lösenord privat, inte i repo eller Drive-handoff. Adminnyckeln är separat; demonstrationslösenordet är inte adminbehörighet.

## Adminåtkomst inför verksamhetsdemo

Den tidigare deploymodellen skapade en ny slumpmässig adminnyckel vid varje stagingdeploy. Det gjorde automatiserad adminacceptans möjlig men var opraktiskt för Erik/BoIS faktisk demo.

Workflow och admintext är nu ändrade för en stabil, separat stagingnyckel via Repository Secret `BOIS_STAGING_ADMIN_TOKEN`. Den är skild från Basic Auth-lösenordet, får inte exponeras i dokumentation och lagras i privat server-runtime. P9 CI bevakar att stagingworkflowen inte återgår till slumpgenererad adminnyckel.

**LIVE VERIFIED:** run `36906493137` använde den stabila adminnyckeln från `BOIS_STAGING_ADMIN_TOKEN`. Markören `STABLE_STAGING_ADMIN_TOKEN: configured` finns i verifieringsloggen och browser/admin-acceptansen passerade. Nyckeln är nu aktiv på Simply och förblir densamma över framtida deployer tills secretet roteras.

## Stripe sandbox – parallellt integrationsspår

Stripe-kontot **Alberiq** är anslutet i sandbox/test mode. En riktig hosted Checkout Session har skapats med `livemode=false`, vilket verifierar att Checkout fungerar utan riktiga pengar. Kort är tillgängligt; Swish rapporteras för närvarande som `available=false`.

PR #27 mergeades som `571f78acb29e3e8515853f7afd7818cebd8833f5`. Den lägger till restricted test key-stöd, ett separat signaturverifierat webhook-endpoint och en separat Simply-sandboxworkflow på `/bois-shop-stripe-sandbox/`. Erik-demot på `/bois-shop-p3/` fortsätter oförändrat med mock.

Stripe webhook endpoint är skapad i sandbox. Före Simply-deploy återstår att lägga restricted/test API key och webhook signing secret i GitHub Secrets. Inga livebetalningar, KYC-aktiveringar, Swish, externa mejl eller produktionsgrindar har öppnats.

## Nästa steg – Erik/BoIS demo och återkoppling

Använd `SYNTHETIC-DEMO.md` för en begränsad genomgång med syntetiska uppgifter och `example.invalid`-adresser. Samla konkret återkoppling om kundresan, medlems-/Nordic-hanteringen, matchställskön, samtyckesval och begripligheten i admin.

Ingen ny generell utvecklingsfas, ombyggnad eller deploy behövs enbart för att denna dokumentation uppdateras. Prioritera eventuella påvisade fel och önskemål efter genomgången och fatta beslut om ett avgränsat nästa uppdrag. Fysisk iPhone/Safari kan provas manuellt vid demot; redovisa då den faktiska enheten och resultatet.

## Affärsregler och kvarvarande produktionskrav

Medlemskap: ungdom 200 kr, vuxen 350 kr, pensionär 300 kr. Nordic Wellness 2 650 kr för aktiv medlem. Stagingens medlemsperiod är 365 dagar; den skarpa perioden behöver bekräftas. Matchställ **998 kr är endast testpris**; verkliga priser, leverantör, SKU/storlekar och orderformat återstår.

Endast verifierad PAID får driva P4/P5. Befintlig medlem + gym kräver medlemsverifiering. Matchställ batchas vid 8 betalda ställ eller 168 timmar, alternativt via admin. Refund kräver manuell granskning av redan påbörjad fulfillment. Stopp för nya checkouts får inte tappa signerade besked för tidigare betalningar.

P7:s tre förslag – BoIS 1941 Hoodie, Supporter-T-shirt och Läktarmössa – är inte godkända eller orderbara. Priser är ESTIMATE och produktunika kostnader/SKU/marginaler TBD. Printful är endast kandidat. Ingen merch före 1 januari 2027 i Stockholmstid; även därefter krävs verifierad kommersiell data och uttryckligt produktgodkännande. P9-rekommendationer ger ingen rabatt.

Före skarp lansering återstår merchant/kontoägare/KYC, bank, godkända avgifter, Swish-access, domän, separat produktionsdatabas, villkor/integritet/refundpolicy, support och mejltransport, produkt-/partnerunderlag, personliga adminkonton/roller/MFA, missbruksskydd, backup/återställningsprov och slutlig säkerhetsgranskning. Produktionens statistik är fortsatt spärrad. Slutlig samtyckesinformation och gallring samt webbhotellets `sc_clearance`, externa bildresurser och framtida Stripe-konfiguration behöver bedömas före produktion. Ingen av dessa gränser öppnas av demogodkännandet.

## Historik – bevarad, inte nästa arbetsinstruktion

De tidigare fullständiga status- och handofftexterna från main `4918cef10bcc213ef9c756d407ca88d4990971fa` har bevarats oförändrade i `history/CURRENT_STATUS-before-protected-staging-20260929.md` och `history/WORK-HANDOFF-before-protected-staging-20260929.md`. Deras uppgifter om öppen staging och väntande åtkomstdeploy är historiska och ersätts av denna verifiering.

Viktiga revisionsreferenser: legacy-merge `36358028481`, P7-acceptans `36358111506`, cutover-kontroll `36358232965`, purge av exakt 18 äldre BoIS-tabeller `36394705592`, separationsdiagnos `36394944351` och återställd Work Capture-deploy `36403070406`. Privata rollback-backuper och övriga historiska spår har inte ändrats i closeouten. Historiska fasverifieringar: P6 `36327128975`, P7 `36355678030`, P8 `36414148818`, P9 `36445454316`, säkerhetsrättningsdeploy `36463946785` och tidigare öppna C1–C4/browserstaging `36517329717`. Den senaste skyddade stagingverifieringen är **36906493137**. Run `36623915463` är föregående skyddade baseline före den stabila adminnyckeln.

PR #32 merge: `7727dedd50fda3919b3915bb361d3e921d478734`. Main före P14:
`c6e5787fe47cc46642a7b7bafbefbf9221c83eb8`. Koden är mergad, men ingen
productiondeploy har gjorts. P15 är nästa etapp och har inte startats.
