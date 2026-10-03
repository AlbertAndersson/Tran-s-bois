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

# Tranås BoIS – WORK HANDOFF

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



## Aktuell överlämning

**Skyddad staging är publicerad och liveverifierad. Nästa steg är Erik/BoIS demo och återkoppling, inte en ny generell teknisk utvecklingsfas.**

GitHub `AlbertAndersson/Tran-s-bois` är ensam source of truth för kod, CI, secrets och deployment. Läs aktuell `main` innan någon ändring. Gör inte om databassepareringen och återställ inte äldre BoIS-workflows i Work Capture.

## Verifierad bas

- Run [36623915463](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/36623915463), jobb `109595921802`: **success**, samtliga steg passerade.
- Workflow/main vid körningen: `4918cef10bcc213ef9c756d407ca88d4990971fa`.
- Publicerad applikationsref: `c49ebcd9af9f7081e1764d28419d92dd05b4fd48`.
- Workflow: `.github/workflows/simply-deploy-bois-p9-staging.yml`, manuell grind med `DEPLOY_BOIS_P9_READY`.
- Skillnaden mellan workflowref, applikationsref och senare dokumentations-HEAD är avsiktlig. En dokumentationscommit är inte en ny appdeployment.

Jobbsteg, faktisk körningslogg och artifactmetadata har efterkontrollerats. Använd denna körning för den skyddade demomiljön; tidigare run `36517329717` avsåg äldre, öppet åtkomlig staging.

## Läsordning

1. `docs/CURRENT_STATUS.md`
2. `docs/STAGING-DEMO-ACCESS.md`
3. `docs/SYNTHETIC-DEMO.md`
4. `docs/CONSENT-INVENTORY-AND-ACCEPTANCE.md`
5. `docs/NEXT-THREAD-PROMPT.md`
6. `docs/SECURITY-CONTROL-POINTS.md` och `docs/COOKIES-AND-CONSENT-PLAN.md`
7. Vid konkret behov: P3–P9-dokumenten, P8 readiness/cutover och P7:s kommersiella underlag.

## Vad som är klart

P1–P7 är tekniskt levererade. P8 är **TECHNICALLY COMPLETE / NOT ACTIVATED**. P9, C1–C4 och kund-/adminflöden har åter verifierats i syntetisk staging bakom HTTP Basic Auth.

Obehöriga direkta GET- och POST-anrop samt fel demoinloggning nekades med 401. Behörig P4–P9-demo, signerad mock-PAID, medlemskap/Nordic, matchställ/batch, betalningsfel och nytt försök, simulerad refund samt consent inget val/nej/ja/återkallelse passerade. Ingen extern analytics eller mejlsändning aktiverades. Inga främmande tabeller tillkom; tabellnamnsnapshot var identisk före/efter, antal 0. Detta är inte en ny fullständig backuprevision.

Chromium headless på Linux: 375, 390 och 1280 px. 10 PNG i `bois-p9-synthetic-browser-36623915463`, artifact ID `11059388459`, till **2026-10-06 20:08:02 UTC**. Bevislänk och digest finns i `CURRENT_STATUS.md`. Ingen permanent bildkopia har skapats av dokumentationscloseouten. Fysisk iPhone/Safari och faktisk verksamhetsacceptans av Erik/BoIS är inte genomförda genom denna automatiserade körning.

## Demoåtkomst och miljö

https://alberiq.se/bois-shop-p3/

HTTP Basic Auth gäller hela katalogen, inklusive shop, admin, order, betalning, consent, assets och API. Demohemligheten finns som `BOIS_STAGING_DEMO_PASSWORD`; värdet ska inte skrivas i GitHub, Drive, loggar eller skärmbilder. Dela åtkomsten privat med ett begränsat antal behöriga granskare. Se `STAGING-DEMO-ACCESS.md` för användarnamn och rotation.

Admin använder separat runtime-token via `X-Bois-Admin-Token`. Demoåtkomst ger inte i sig adminbehörighet. Personliga konton, roller och MFA återstår före riktiga kunduppgifter.

BoIS staging använder dedikerad MySQL via `BOIS_DB_*` och privat runtime utanför webbroten. Skarp miljö ska använda separat produktionsdatabas, även skild från BoIS staging. Slutlig produktionsadress är inte beslutad i detta uppdrag; byt inte stagingadress eller driftarkitektur nu.

## Nästa uppdrag – demo och återkoppling

Följ befintligt `SYNTHETIC-DEMO.md` med endast påhittade personer och `example.invalid`-adresser. Låt Erik/BoIS granska kundresan och de administrativa arbetsuppgifterna. Registrera observerat beteende, förväntat resultat, prioritet och beslut om åtgärd. Markera tydligt vad deltagarna faktiskt provat och godkänt; automatiserade pass är inte deras godkännande.

Öppna inte en ny utvecklingsfas i förväg. Ändra först efter konkret återkoppling och ett avgränsat beslut. Genomför ingen ny deploy enbart för dokumentationsändringarna. Manuell fysisk iPhone/Safari-kontroll kan dokumenteras i samband med demot.

Identifiera demots order-ID:n privat. Använd endast dokumenterad FK-säker städning efter beslut om revisionsspår. Inga generella DELETE, ingen databasåterställning och ingen radering av andra order, betalhistorik eller privata rollback-backuper.

## Hårda gränser

- Ingen Stripe-aktivering, KYC, extern Stripe-API-körning eller riktig betalning/refund.
- Inga externa mejl/SMS, annonser, externa analystjänster eller nya kostnader.
- Produktionsgrinden och produktionsspårningen förblir stängda; samtyckeskomponenten är inte ett aktiveringsbeslut.
- Staging använder mock/testmode och syntetiska uppgifter. Statistik kräver både teknisk tillåtelse och ett serververifierat giltigt statistikval; ett ordergodkännande räcker inte.
- P4–P9-regressioner, lanseringskontrollen i Stripe-checkout och P7:s produktspärrar ska bevaras.
- Stopp för nya köp får inte stoppa signerade besked för redan påbörjade betalningar.
- GitHub-historik, databasmigration, purgebevis och privata återställningskopior ska bevaras. Work Capture ska inte ändras.

## Affärsregler att behålla

Ungdomsmedlemskap 200 kr, vuxen 350 kr, pensionär 300 kr. Nordic Wellness gymkort 2 650 kr för aktiv medlem. Matchställ **998 kr är testpris**, inte skarpt pris. Stagingmedlemskap gäller 365 dagar; slutlig period återstår att besluta. Personnummer ingår inte och ska inte läggas till utan verifierat behov.

Ny medlem + gym: verifierad PAID → medlemskap ACTIVE → Nordic ELIGIBLE. Befintlig medlem + gym går via PENDING_MEMBER_VERIFICATION. Nordic kan hanteras via manuell partnerhandoff/CSV; senare transportformat ändrar inte medlemslogiken. Förnyelse behåller medlemsidentiteten.

P5: endast PAID + BATCH_SUPPLIER + WAITING_BATCH, ej redan batchad rad. 8 betalda ställ eller 168 timmar, med möjlighet att skapa batch manuellt. CSV/hash, dubblettskydd, outbox och retry bevaras. Mockrefund leder till manuell fulfillment-granskning, inte tyst återkallad leverans/förmån.

P7:s aktiva förslag är Hoodie, T-shirt och Läktarmössa; övriga ursprungliga kandidater är uppskjutna. Kundpriser är ESTIMATE, verifierade kostnader/MOQ/ledtid/SKU och marginaler TBD; Printful är endast kandidat. Ingen supporter-/merchförsäljning före **2027-01-01 Europe/Stockholm**, och därefter krävs uttryckligt godkännande, verifierad kommersiell data och länkade varianter. P9:s rekommendationer ändrar inte priser eller ger rabatt.

## Kvar före skarp drift

Merchant, Stripe-kontoägare/KYC, bank, godkända avgifter, Swish-access, domän och separat produktionsdatabas; slutliga köpvillkor/integritet/återbetalning, support och mailtransport; verkliga matchställs- och Nordic-underlag samt medlemsperiod; personliga adminkonton/roller/MFA, missbruksskydd, backup/återställningsprov och slutlig säkerhetsgranskning.

Samtyckesinformationen, databaser/loggars gallring, webbhotellets `sc_clearance`, externa bildresurser och framtida Stripe-lagring behöver produktionsbedömas. 180 dagars samtyckesgiltighet är en staginginställning, inte ett generellt lagkrav. Inga sådana godkännanden är ersatta av stagingtesterna.

## Historiska revisionsspår

De tidigare fullständiga status- och handofftexterna finns oförändrade i `docs/history/CURRENT_STATUS-before-protected-staging-20260929.md` och `docs/history/WORK-HANDOFF-before-protected-staging-20260929.md`. Historiska instruktioner där om väntande demoåtkomst är inte aktuella.

Databasisolering: merge `36358028481`, P7-acceptans `36358111506`, cutover `36358232965`, purge `36394705592`, diagnos `36394944351`, Work Capture recovery `36403070406`. Fasbevis: P6 `36327128975`, P7 `36355678030`, BoIS-ägd P8 `36414148818`, P9 `36445454316`, säkerhetsrättningsdeploy `36463946785`, tidigare C1–C4/browserstaging `36517329717`. Senast skyddad demo: **36623915463**. Ingen kod, runtime, hemlighet eller databas ändras av den här dokumentationscloseouten.
