# Tranås BoIS – WORK HANDOFF

## Syfte

Detta är den primära överlämningen för en ny utvecklingstråd. GitHub är source of truth.

## Source of truth

- Repo: `AlbertAndersson/Tran-s-bois`
- Branch: `main`
- P6 slutlig hardening merge: `b5b9a157a7462277cdab27bb304c5c1b31706fa0` (PR #3)
- P7 implementation merge: `49883befb360fddb6e63e5c6b6a622fd2c0eed8b` (PR #6); slutlig publik startsiderättning: `cfe18cce5a19ba4ec05a83323cbcbe07bf1aed29`; kontrollera ny `main` HEAD efter dokumentationscommit
- Verifiera alltid aktuell `main` HEAD innan ändring
- Deployment/source/secrets: `AlbertAndersson/Tran-s-bois` (BoIS äger nu sin egen drift; Work Capture är endast temporär fallback tills första BoIS-ägda P8-deployen verifierats)

Verifiera alltid aktuell HEAD innan du ändrar något.

## Läsordning för ny tråd

1. `README.md`
2. `docs/CURRENT_STATUS.md`
3. `docs/00-WORK-HANDOFF.md`
4. `docs/P3-COMMERCE-CORE.md`
5. `docs/P4-MEMBERSHIP-NORDIC.md`
6. `docs/P5-MATCHKIT-BATCHING.md`
7. `docs/P6-PAYMENT.md`
8. `docs/P7-2027-ASSORTMENT.md`
9. `docs/P8-PAYMENT-STRIPE.md`
10. `docs/P9-SALES-ENGINE.md`
11. `docs/NEXT-THREAD-PROMPT.md`

## Aktiv deployment

Ny canonical BoIS-deployment finns i samma repo:
- `.github/workflows/simply-deploy-bois-p8-readiness.yml`
- `.github/workflows/simply-validate-bois-p8-production.yml`

Första BoIS-ägda P8 readiness-deployen är ännu inte liveverifierad. Fram till dess behålls Work Capture-workflows endast som rollback/fallback och ska pensioneras direkt efter verifierad körning från `Tran-s-bois`.

Senast verifierad P6-deploy:
- run: `36327128975`
- conclusion: **success**
- deployment commit i `work-capture`: `68412823f65e120484febc091c052df8b4bd4564`
- source hardening merge i shop-repot: `b5b9a157a7462277cdab27bb304c5c1b31706fa0`
- snapshot: **38 icke-BoIS-tabeller, identisk hash före/efter**

Äldre P3/P5 deployworkflows är pensionerade så de inte kan skriva över aktuell P6-staging.

P7-workflowen i samma fil är manuellt skyddad (`workflow_dispatch`, `confirm=DEPLOY_BOIS_P7_READY`). Slutlig deployment commit i `work-capture`: `43d32430853b39c4c70a09aba4a98426078d3f24`, pinnad till `cfe18cc`. Slutlig Simply-run `36355678030`: **success**, P7 live-verifierad. Snapshot av icke-BoIS-tabeller var identisk före/efter; körningen mätte **0** sådana tabeller i BoIS-databasen. Den äldre P6-run ovan är historisk baseline.

## Aktiv staging

- Shop: https://alberiq.se/bois-shop-p3/
- Medlemskap + Nordic Wellness: https://alberiq.se/bois-shop-p3/membership.html
- Matchställ: https://alberiq.se/bois-shop-p3/match-kit.html
- Orderstatus: https://alberiq.se/bois-shop-p3/order.html
- Shopadmin: https://alberiq.se/bois-shop-p3/admin.html
- Testbetalning: https://alberiq.se/bois-shop-p3/payment.html
- API health: https://alberiq.se/bois-shop-p3/commerce-api.php?action=health

**Obs:** URL-sökvägen heter fortfarande `bois-shop-p3`, men miljön kör nu P4 + P5 + P6. Byt inte sökväg innan slutlig produktionsdomän är beslutad.

Staging får endast innehålla testuppgifter.

## Fasstatus

- P1 – ordermotor: **COMPLETE**
- P2 – produktionsförberedelse: **COMPLETE**
- P3 – Commerce Core / MySQL: **COMPLETE**
- P4 – Membership & Nordic Wellness: **COMPLETE / LIVE STAGING VERIFIED**
- P5 – Match kit batching: **COMPLETE / LIVE STAGING VERIFIED**
- P6 – Payment: **COMPLETE / LIVE STAGING VERIFIED**
- P7 – 2027 assortment: **COMPLETE / LIVE STAGING VERIFIED**
- P8 – production readiness: **TECHNICALLY COMPLETE / NOT ACTIVATED / BOIS-OWNED STAGING VERIFY PENDING**
- P9 – sales engine: **IMPLEMENTED / COST-FREE STAGING VERIFY PENDING**

P6-betalning kör isolerad `mock`/testmode i staging. Riktig payment provider är avstängd. Extern mejlsändning är avstängd i staging. Ny extern driftkostnad hittills: **0 kr**.

## Affärsbeslut

Före 31 december 2026 får shoppen sälja:
- medlemskap
- Nordic Wellness gymkort för medlem
- matchställ

Från 1 januari 2027 kan supporter-/merchsortiment aktiveras.

Bekräftade priser:
- ungdomsmedlemskap: **200 kr**
- vuxenmedlemskap: **350 kr**
- pensionärsmedlemskap: **300 kr**
- Nordic Wellness gymkort: **2 650 kr** för aktiv medlem

Matchställ:
- nuvarande **998 kr är endast staging/testpris**
- verkligt pris, SKU/artikelnummer och leverantörsformat återstår

## P3 – Commerce Core

MySQL med separata `bois_`-tabeller för:
- produkter och varianter
- kunder
- order och orderrader
- medlemsköp
- betalningar
- leverantörer
- fulfillment rules
- supplier batches
- batch items
- email outbox
- events/migrations

Publikt/orderbart före årsskiftet:
1. medlemskap
2. Nordic Wellness gymkort
3. matchställ

Dolt/ej orderbart till 2027:
- BoIS 1941 Hoodie
- Supporter-T-shirt
- Bandyförälder Hoodie
- Mössa + halsduk
- BoIS Gym Pack
- Knatte Pack
- Presentkort

## P4 – Membership & Nordic Wellness

Kärntabeller:
- `bois_members` – aktuellt medlemsregister
- `bois_benefit_entitlements` – Nordic-/förmånsärenden
- `bois_memberships` – order-/transaktionshistorik

### Ny medlem + gym

Efter framtida verifierad `PAID`:
1. medlemskap → `ACTIVE`
2. medlemsregister skapas/uppdateras
3. Nordic entitlement länkas till medlem
4. Nordic status → `ELIGIBLE`

### Befintlig medlem + gym

Efter `PAID`:
1. entitlement → `PENDING_MEMBER_VERIFICATION`
2. Erik/admin verifierar medlemskap
3. medlem länkas/skapas
4. entitlement → `ELIGIBLE`

### Nordic-statusflöde

`PENDING_PAYMENT`
→ `PENDING_MEMBER_VERIFICATION` vid behov
→ `ELIGIBLE`
→ `SENT_TO_PARTNER`
→ `READY_FOR_PICKUP`
→ `ACTIVATED`

Alternativ slutstatus: `REJECTED`.

### Förnyelse

Förnyelse återanvänder samma medlemsidentitet och förlänger giltigheten i stället för att skapa dubblett.

Staging använder **365 dagar**. BoIS måste bekräfta om produktion ska använda rullande 365 dagar eller annan medlemsperiod.

### Dataminimering

P4 samlar **inte in personnummer**.

Lägg inte till personnummer om inte det nya Nordic-avtalet uttryckligen kräver det och behovet är verifierat.

### Nordic partnerhandoff

P4 är komplett med manuell partnerhandoff och CSV-export.

När Nordic bekräftar sitt faktiska format kan transporten bytas till exempelvis:
- CSV/e-post
- portalimport
- SFTP
- API

utan ombyggnad av eligibility-/medlemslogiken.

## P5 – Match kit batching

Startregel:
- **8 betalda matchställ** → automatisk batch
- **168 timmar / 7 dagar** → automatisk batch
- admin kan välja **Skicka batch nu**

En orderrad får bara batchas om:
- ordern är `PAID`
- `fulfillment_type = BATCH_SUPPLIER`
- `fulfillment_status = WAITING_BATCH`
- raden inte redan finns i `bois_batch_items`

P5 har:
- unikt batch-ID
- transaktions-/radlåsning
- dubblettskydd
- leverantörs-CSV
- SHA-256 av exakt CSV
- e-post-outbox
- retry/backoff
- batchhistorik i admin

I staging:
- leverantör = `supplier@example.invalid`
- cc = `erik@example.invalid`
- `mail_transport = disabled`
- verkliga mejl kan inte skickas

## P6 – Payment

P6 är **COMPLETE / LIVE STAGING VERIFIED**.

Staging:
- `payment_provider = mock`
- Test-Swish och Test-kort
- ingen verklig provider/merchant är aktiverad
- `payment_mail_transport = disabled`
- payment-kvitton tvingas till `customer@example.invalid`
- ny extern kostnad: **0 kr**

Betalningsgränsen:
1. servern skapar checkout/session
2. provider-event verifieras med HMAC-SHA256 + timestamp
3. `(provider,event_id)` är unik och förhindrar dubbelprocessning
4. payment → `PAID`
5. samma verifierade PAID-handler driver P4/P5
6. `effects_status` gör downstream retry-säker
7. medlemskap/Nordic/matchställ går aldrig vidare före verifierad PAID

State machine:
- `PENDING`
- `PAID`
- `FAILED`
- `CANCELLED`
- `PARTIALLY_REFUNDED`
- `REFUND_PENDING`
- `REFUNDED`

Refund:
- uppdaterar finansiell status
- order/fulfillment går till `REVIEW_REQUIRED`
- redan startat medlems-/partner-/leverantörsflöde backas inte automatiskt

Slutverifiering efter P6-hardening:
- PR #3 merge `b5b9a157`: P2–P6 CI **success**
- Simply live deploy run `36327128975`: **success**
- syntetisk checkout → signerad P6-mock → PAID: pass
- medlem ACTIVE / Nordic ELIGIBLE: pass
- upprepad betalhändelse utan nya downstream-effekter: pass
- P5-konfiguration bevarad: pass
- payment mail transport disabled: pass
- 38 icke-BoIS-tabeller med identisk snapshot-hash: pass

## Säkerhetsinvariants

1. GitHub är source of truth.
2. Inga nya kostnader utan uttryckligt godkännande.
3. Inga riktiga kund-/medlemsuppgifter i staging.
4. Medlemskap, Nordic eligibility och matchställ får inte kringgå payment gate.
5. Endast verifierad `PAID` får driva P4/P5 i produktion.
6. Staging får inte skicka verkliga leverantörsmejl.
7. Icke-BoIS-tabeller i delad stagingdatabas får inte ändras.
8. Produktion ska separeras från delad stagingdatabas innan riktiga kunder.
9. Lägg inte till personnummer utan verifierat behov.
10. Supporter-/merchsortiment ska förbli dolt tills Intersport-avtalet är slut.

## Öppna blockers före produktion

### Payment – produktion
P8:s Stripe-kod och produktionsgrindar är implementerade men **inte aktiverade**. Före skarp betalning återstår:
- vem är merchant/betalningsmottagare?
- aktuell Stripe-prisbild och uttryckligt kostnadsgodkännande
- merchant onboarding/KYC och kontoägarskap
- verifiera att Stripe-Swish är tillgängligt i production för BoIS-kontot (Swish är märkt Beta av Stripe 2026-09-27)
- produktionscredentials/secrets
- verklig webhook-konfiguration
- Hosted Stripe Checkout är valt och implementerat; ingen skarp Stripe-session skapas förrän aktivering godkänts
- slutlig refund-policy för medlemskap/Nordic/matchställ

Aktivera ingen kostnad eller betaltjänst utan uttryckligt godkännande.

### Matchställ
- verkligt inköps-/försäljningspris
- leverantör
- leverantörens e-post
- artikel-/SKU-koder
- storlekssortiment
- exakt order-/CSV-format

### Nordic Wellness
- exakt nytt partnerflöde
- om Nordic vill ha CSV, mejl, portal, SFTP eller API
- vilka personuppgifter som faktiskt krävs
- eventuell referens-/aktiveringsmodell

### Medlemskap
- bekräfta slutlig medlemsperiod

### Production
- slutlig domän/subdomän, troligen `shop.tranasbois.se`
- dedikerad BoIS-databas/credential
- backup/gallring
- slutliga villkor/integritet/säljaruppgifter
- riktiga mejlmottagare

## P7 – genomförd och live-verifierad staging

PR #6 (`49883bef`) implementerade fyra verifierade delsteg. P7A föreslår BoIS 1941 Hoodie, Supporter-T-shirt och BoIS Läktarmössa. Rekommenderade priser är `ESTIMATE`; verkliga leverantörspriser, MOQ, SKU och marginaler är `TBD`. Printful är endast kandidat. Se `data/p7-assortment.json` och `docs/P7A-COMMERCIAL-MODEL.md`. Övriga ursprungliga kandidater är uppskjutna.

P7B lägger till endast `bois_p7_assortment` och `bois_p7_variants`, med idempotent seed och strukturerad verifieringsstatus/källa. P7C lägger till adminskyddad översikt och intern `assortment-preview.html`. P7D blockerar katalog och direkta orderanrop före **2027-01-01 Europe/Stockholm** oavsett frontendflaggor. Efter datumet krävs godkänd och verifierad data samt länkade P3-varianter. Ingen produkt är idag godkänd.

P2–P7 CI inklusive MySQL 8.4 är grön. En statisk lista över framtida produktnamn upptäcktes på den publika startsidan vid livegranskning och togs bort i `cfe18cc`; P7 CI run `36355561318` är grön. Den skyddade stagingworkflowen kördes sedan som `36355678030` med **success**. P7 migration, admin/preview, dold publik katalog, syntetisk mockcheckout → PAID → P4 medlemskap ACTIVE/Nordic ELIGIBLE, dubblettskydd och P5:s 8/168-inställning passerade. Extern e-post är disabled, riktig provider saknas och ny extern kostnad är 0 kr. Snapshot före/efter av icke-BoIS-tabeller är identisk (antal 0 i den använda BoIS-databasen). Publik startsida kontrollerades separat efter deploy och visar inga föreslagna merchprodukter.

**Driftnotering:** första försöket `36355192391` stannade före migration på en gammal P6-paketsökväg; `c9da2a69` rättade den. `36355380067` lyckades med P7 men föregick den statiska startsiderättningen. `36355678030` är slutlig verifierad run. Ändra inte P6:s betalningslogik utan en påvisad regression.

**Kommersiella blockerare:** Albert/Erik behöver leverantörsofferter, verifierade kostnader/MOQ/ledtider/SKU, bildrättigheter, slutpriser och godkännande innan någon P7-produkt kan öppnas. Servergaten hindrar försäljning även efter datumet tills varje produkt är verifierad och godkänd.

**P8-avgränsning:** Stripe-valet är dokumenterat för P8. Ingen Stripe-kod, konto, credential, riktig betalning eller ny kostnad ingår i P7.


## P9 – Sales Engine v1

P9 är byggd som kostnadsfri first-party stagingfunktion utan externa marketingtjänster.

Implementerat:
- UTM/referral-attribution,
- pseudonym session → order → PAID-koppling,
- first-party funnel,
- admin sales dashboard,
- campaign link builder,
- attribuerad produktmix,
- zero-discount recommendations.

Sales-tabeller lagrar inte direkta kundidentifierare, IP eller user-agent. Sessionen kan länkas till order internt och behandlas därför som pseudonym data. Production-default är `sales_tracking_enabled=false`; tracking får inte aktiveras skarpt innan integritets-/rättslig grund är verifierad.

P9 stagingworkflow:
`.github/workflows/simply-deploy-bois-p9-staging.yml`

Den ska fortsatt använda mockbetalning, syntetiska testuppgifter, avstängd extern e-post och 0 kr ny extern kostnad.
