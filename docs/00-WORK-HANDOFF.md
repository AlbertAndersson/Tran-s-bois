# Tranås BoIS – WORK HANDOFF

## P10 – aktuellt auktoriserat uppdrag

Albert har beställt nästa etapp enligt Drive-kön “01 – Tranås BoIS – Work utvecklingskö till go-live”. P10 är IN PROGRESS: säkerhetsförstärkning implementeras och verifieras före eventuell stängning. Tidigare instruktioner nedan om att invänta demoåterkoppling är historiskt nuläge och begränsar inte detta nya uppdrag.

Baslinje main: `0a25f1be0114d147222518b69be805fe75c10d30`. Se `P10-PRODUCTION-HARDENING.md` för kontrollpunkter, riskregister och verifieringsläge. P11–P20 ingår inte. Alla produktions-, betalnings-, mail- och kostnadsspärrar består.


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
