# CURRENT STATUS

Datum: 2026-09-28

## Övergripande status
- **P1 – ordermotor: COMPLETE**
- **P2 – produktionsförberedelse: COMPLETE**
- **P3 – Commerce Core / MySQL: COMPLETE**
- **P4 – Membership & Nordic Wellness: COMPLETE / LIVE STAGING VERIFIED**
- **P5 – Match kit batching: COMPLETE / LIVE STAGING VERIFIED**
- **P6 – Payment: COMPLETE / LIVE STAGING VERIFIED**
- **P7 – 2027 assortment: COMPLETE / LIVE STAGING VERIFIED**
- **P8 – production readiness: TECHNICALLY COMPLETE / NOT ACTIVATED / BOIS-OWNED STAGING VERIFIED**
- **P9 – sales engine: IMPLEMENTED / COST-FREE STAGING VERIFY PENDING**

Betalning: **ISOLERAD MOCK/TESTMODE I STAGING – RIKTIG PROVIDER AVSTÄNGD**  
Extern mejlsändning: **AVSTÄNGD I STAGING**  
Ny extern kostnad: **0 kr**

## Aktiv staging
- Shop: https://alberiq.se/bois-shop-p3/
- Medlemskap + gym: https://alberiq.se/bois-shop-p3/membership.html
- Matchställ: https://alberiq.se/bois-shop-p3/match-kit.html
- Orderstatus: https://alberiq.se/bois-shop-p3/order.html
- Shopadmin P4 + P5 + P6 + P8 readiness: https://alberiq.se/bois-shop-p3/admin.html
- Testbetalning P6/P8 mock: https://alberiq.se/bois-shop-p3/payment.html
- API health: https://alberiq.se/bois-shop-p3/commerce-api.php?action=health

Staging använder endast testuppgifter.

## Affärsbeslut
Säljbara före 31 december 2026:
- medlemskap
- Nordic Wellness gymkort för medlem
- matchställ

Priser:
- ungdomsmedlemskap: 200 kr
- vuxenmedlemskap: 350 kr
- pensionärsmedlemskap: 300 kr
- Nordic Wellness gymkort: 2 650 kr

Matchställspriset 998 kr är fortsatt **endast staging/testpris**.

## P4 – Membership & Nordic Wellness

### Medlemsregister
P4 har:
- `bois_members` för aktuell medlemsstatus
- `bois_benefit_entitlements` för Nordic-förmånsärenden
- `bois_memberships` kvar som order-/transaktionshistorik

Efter `PAID`:
- medlemskap → `ACTIVE`
- medlemsregister skapas/uppdateras
- stagingmedlemsperiod = 365 dagar
- obetalda medlemskap aktiveras aldrig

### Förnyelse
- aktiv medlem med samma e-post återanvänds
- samma `member_uuid` behålls
- giltigheten förlängs
- ingen dubblettmedlem skapas
- ny order sparas separat

### Nordic-flöde
Ny medlem + gym:
- PAID → medlemskap ACTIVE → gym `ELIGIBLE`

Befintlig medlem + gym:
- PAID → `PENDING_MEMBER_VERIFICATION`
- Erik/admin verifierar medlem
- → `ELIGIBLE`

Fortsatt status:
- `ELIGIBLE`
- `SENT_TO_PARTNER`
- `READY_FOR_PICKUP`
- `ACTIVATED`
- eller `REJECTED`

### Admin
Admin stödjer:
- medlemsregister
- aktiva medlemmar
- väntande medlemskontroller
- eligible/aktiverade gymkort
- verifiera befintlig medlem
- Nordic-referens
- markera skickad till Nordic
- markera klar att hämta
- markera aktiverad
- Nordic CSV-export

### Personuppgifter
P4 samlar **inte in personnummer**.

Nordic-exporten innehåller order, namn, e-post, telefon, medlemsnamn, medlemstyp, medlemsperiod och status.

### Partnerintegration
P4 är komplett med manuell partnerhandoff. Nordic-transporten kan senare bytas till API, SFTP, portalimport eller annat format utan att medlems-/eligibilitylogiken byggs om.

## P5 – Match kit batching
- tröskel: **8 betalda matchställ**
- max väntetid: **168 timmar / 7 dagar**
- admin kan välja **Skicka batch nu**
- endast `PAID + BATCH_SUPPLIER + WAITING_BATCH` kan batchas
- dubblettskydd
- CSV + SHA-256
- e-post-outbox + retry/backoff
- historik i admin

Staging:
- leverantör = `supplier@example.invalid`
- cc = `erik@example.invalid`
- `mail_transport = disabled`

## P6 – Payment
P6 använder en isolerad `mock`-provider i staging. Ingen verklig betaltransaktion eller extern provider är aktiverad.

Kärnflöde:
- serverstyrd checkout
- Test-Swish och Test-kort
- signerad HMAC-SHA256-webhook med timestamp-tolerans
- unik `(provider,event_id)` för webhook-idempotens
- `effects_status` för retry-säker downstream-applicering
- verifierad `PAID` återanvänder P4/P5:s gemensamma betalgräns
- medlemskap/Nordic/matchställ kan inte gå vidare före `PAID`
- refunds sätter finansiell status och `REVIEW_REQUIRED` för fulfillment
- kvitto/refund-outbox har retry/backoff
- `payment_mail_transport = disabled`
- stagingkvitton tvingas till `customer@example.invalid`

Payment states:
- `PENDING`
- `PAID`
- `FAILED`
- `CANCELLED`
- `PARTIALLY_REFUNDED`
- `REFUND_PENDING`
- `REFUNDED`

## Verifiering

### GitHub CI / MySQL 8.4
P4:
- ny medlem + gym → ELIGIBLE: pass
- befintlig medlem kräver verifiering: pass
- adminverifiering → ELIGIBLE: pass
- förnyelse utan dubblett: pass
- Nordic-status till ACTIVATED: pass
- Nordic CSV utan personnummerfält: pass
- unpaid membership gate: pass
- admin JavaScript: pass
- privacy/safety: pass

P5:
- threshold batch: pass
- max-wait batch: pass
- manual batch: pass
- dubblettskydd: pass
- retry: pass

P6 – GitHub Actions run `36324968897`:
- PHP syntax: pass
- MySQL payment smoke: pass
- korrekt signerad webhook: pass
- ogiltig signatur nekas: pass
- event-idempotens: pass
- P4 först efter verifierad PAID: pass
- P5-kö först efter verifierad PAID: pass
- refund state machine: pass
- receipt outbox retry: pass
- real payment sent: no
- real email sent: no

### AlberIQ / Simply
Senast verifierad P6-hardening-deploy: workflow run `36327128975`, conclusion **success**.

- P6 migration: pass
- filrättigheter/privat runtime: pass
- syntetisk checkout → signerad P6-mock → PAID: pass
- medlemskap ACTIVE: pass
- Nordic ELIGIBLE: pass
- upprepad betalhändelse ger inga nya downstream-effekter: pass
- P5-regel 8 / 168h bevarad: pass
- payment mail transport disabled: pass
- snapshot: 38 icke-BoIS-tabeller, identisk hash före/efter: pass
- real payment provider: no
- new external cost: 0

## Drift
BoIS-specifik deployment ägs nu enbart av detta repo:
- `.github/workflows/simply-deploy-bois-p8-readiness.yml`
- `.github/workflows/simply-validate-bois-p8-production.yml`

P8-readiness-workflowen är manuellt skyddad och använder fortsatt mock, tomma Stripe-secrets, avstängd extern payment-mail och stängd production launch-gate. BoIS-ägd run `36414148818` lyckades från `main` `1a29eb5ddb229144f255fe92a837d20953617832`, med pinnad P8-kodref `818c262af23431be972986b9c79f70f319da90e2`. Ersatta Work Capture-deploy/readiness-workflows är pensionerade. Historiska migrations-/purge-/rollbackspår bevaras.

## Kostnad
**Ny extern kostnad: 0 kr.**

## P7 – 2027 assortment
P7A–D är implementerade på `main` via PR #6, merge `49883befb360fddb6e63e5c6b6a622fd2c0eed8b`. P2–P7 CI är grön, inklusive MySQL 8.4, serverklocka före/efter launchdatum samt P4–P6-regression. En liveupptäckt statisk namnlista togs bort i `cfe18cce5a19ba4ec05a83323cbcbe07bf1aed29`; P7 CI run `36355561318` passerade. Slutlig Simply-run `36355678030` passerade med samma source ref.

Föreslaget första sortiment är BoIS 1941 Hoodie, Supporter-T-shirt och BoIS Läktarmössa. Kundpriserna 549/249/199 kr är `ESTIMATE`; produktunika leverantörsofferter, inköpspris, MOQ, tryck/brodyr, frakt, SKU och marginal är `TBD`. Printful är endast en dokumenterad kandidat, inte avtalad leverantör. Se `data/p7-assortment.json` och `docs/P7A-COMMERCIAL-MODEL.md` för källor och blockerare. Alla tre ligger som ej godkända, opublicerade och ej orderbara.

P7 har separata `bois_p7_assortment`/`bois_p7_variants`-tabeller, adminskyddad granskning och intern preview. P3:s serverstyrda katalog och orderupplägg blockerar samtliga supporter-/merchprodukter före 2027-01-01 i Stockholmstid. Efter datumet krävs godkännande, verifierad kommersiell data och länkade varianter. Klientklocka/klientflaggor kan inte öppna gaten. Ingen P7-produkt uppfyller dessa villkor nu.

Deployment-repot `AlbertAndersson/work-capture` har en **manuellt skyddad** workflow på `43d32430853b39c4c70a09aba4a98426078d3f24`, pinnad till slutlig P7-kod. Run `36355678030` genomförde idempotent P4–P7-migration och syntetiska livekontroller: mockcheckout → PAID, medlemskap ACTIVE, Nordic ELIGIBLE, upprepat event utan dubbel effekt, P5 8/168-konfiguration, admin/preview, dold publik katalog och avstängd mejltransport. Snapshot av icke-BoIS-tabeller var identisk före/efter; BoIS-databasen innehöll **0** sådana tabeller i den körningen. Inga riktiga betalningar eller externa mejl skickades. Run `36355192391` stannade före migration på en felaktig paketkatalog, rättad i `c9da2a69`; run `36355380067` verifierade den första P7-deployen före rättningen av statisk startsida.

Före kommersiell lansering återstår verkliga offerter, SKU och kostnader, marginalberäkning, produktbilder och BoIS godkännande. Detta är inte ett skarpt lanseringsbeslut.

P6 förblir testmode under P7. **Stripe är vald som målprovider för P8**, men merchant, aktuell prisbild, produktionsupplägg och aktivering ska godkännas uttryckligen innan skarp drift. Stripe-Swish ska verifieras för BoIS-kontot eftersom Stripe 2026-09-27 märker Swish som Beta.

## P6 final hardening verification
- PR #3 merge: `b5b9a157a7462277cdab27bb304c5c1b31706fa0`.
- P2–P6 CI på merge-commit: **success**.
- Simply deploy run `36327128975`: **success**.
- Direkt adminväg till PAID är borttagen; staging-simulering går via signerad P6-mock.
- Webhook binder order, session, valuta och belopp och skyddar mot dubbelbehandling.
- REFUND_PENDING, partiell återbetalning och manuell fulfillment-granskning ingår.
- Snapshot av 38 icke-BoIS-tabeller var identisk före/efter.

## Payment provider-beslut inför P8
- Provider: **Stripe**.
- P7 påverkas inte och ska inte implementera Stripe.
- P8 ska implementera Stripe bakom befintlig P6 provider-adapter.
- Kort är målmetod.
- Swish är önskat via Stripe men produktionsåtkomst måste verifieras för merchant-kontot eftersom Stripe märker Swish som Beta 2026-09-27.
- Ingen Stripe-aktivering, merchantkostnad eller extern kostnad är godkänd ännu.
- Se `docs/P8-PAYMENT-STRIPE.md`.

## Dedikerad BoIS-databas och källrensning – 2026-09-28

BoIS staging använder den separata dedikerade databasen genom privat runtime `$HOME/.bois-p3/config.php`. Den vanliga P7-deployen använder endast `BOIS_DB_HOST`, `BOIS_DB_NAME`, `BOIS_DB_USER`, `BOIS_DB_PASSWORD` och valfri `BOIS_DB_PORT` (3306 om ej satt); den ska inte använda `WORKCAPTURE_DB_*`. Inga hemligheter eller databasnamn finns i repositoryt.

Merge-run `36358028481` bevarade äldre BoIS-rader tillsammans med P7-data och målbasens migrationsledger. P7-acceptans `36358111506` skrev en ny syntetisk order endast till den dedikerade databasen; läsande cutover-kontroll `36358232965` passerade. Exakt 18 äldre `bois_*`-tabeller i Work Capture-källan droppades via skyddad run `36394705592` efter förnyad data-, runtime- och backupkontroll. Privata rollback-backuper behölls utanför webbroten. Diagnos `36394944351` bekräftade 0 BoIS-tabeller och 0 andra främmande tabeller i Work Capture, komplett Work Capture-ledger samt 20 BoIS-tabeller utan främmande tabeller i målet. Work Capture AI-01 + Trackson-deploy återställdes därefter i run `36403070406` (success; befintlig data bevarad, AI-10/AI-01/Trackson-preview och regressioner gröna, testdata städad). BoIS P4–P7-staging och mockbetalning är fortsatt det verifierade produktläget.


## P8 – tekniskt genomförd, extern aktivering blockerad – 2026-09-28

P8A–D är mergeat på `main` i commit `818c262af23431be972986b9c79f70f319da90e2`.

Implementerat:
- hosted Stripe Checkout bakom P6 payment gate,
- Stripe-Signature-verifiering och eventnormalisering,
- PaymentIntent/refund-mappning till P6 state machine,
- Swish bakom explicit feature flag,
- fail-closed production readiness,
- maskinläsbar readiness och cutover/rollback-runbook,
- offline Stripe-kontrakttest utan externa API-anrop.

Full P2–P8 CI var grön på P8-PR-head. Inga Stripe credentials, KYC, riktiga betalningar, externa mejl eller nya kostnader aktiverades.

BoIS-ägd workflow `Simply - deploy Tranås BoIS P8 readiness staging` kördes med `DEPLOY_BOIS_P8_READY`: run `36414148818`, job `108901126748`, **success**. Source SHA för körningens `main` är `1a29eb5ddb229144f255fe92a837d20953617832`; den pinnade P8-kodrefen är `818c262af23431be972986b9c79f70f319da90e2`. Loggarna visar `phase=P8`, `payment_provider=mock`, `payment_mode=testmode`, `stripe_ready_for_test=false`, `production_launch_ready=false`; P4–P8-migration ready/pass. Syntetisk servercheckout, signerad mock-webhook → PAID, medlem ACTIVE, Nordic ELIGIBLE och dubblett utan ny downstream-effekt passerade. P5 8/168h och P7 admin/preview samt dold/blockerad merch passerade. Betalningsmejl är disabled, icke-BoIS-tabeller oförändrade, `REAL_PAYMENT_PROVIDER=no`, `REAL_STRIPE_CALLS=no`, `PRODUCTION_TRAFFIC=no`, `NEW_EXTERNAL_COST=0`. Stripe/KYC/credentials/Swish och skarp trafik är fortsatt blockerade. P8A–D är **TECHNICALLY COMPLETE / NOT ACTIVATED**; P9 eller annan utveckling kan fortsätta medan externa beslut inväntas.


## P9 – Sales Engine v1 – 2026-09-28

P9 är implementerad och CI-verifierad men inväntar BoIS-ägd liveverifiering i kostnadsfri staging.

Scope:
- first-party pseudonym sessionspårning med UTM/ref-attribution,
- funnel: page → product → checkout → order → PAID,
- orderkoppling utan direkta kundidentifierare i sales-tabellerna,
- admin-KPI för sessioner, konvertering, testvärde, kampanjer och produktmix,
- lokal kampanjlänksbyggare,
- serverstyrda zero-discount recommendations,
- event-idempotens och per-session eventtak.

Säkerhets-/kostnadsgräns:
- ingen extern analytics,
- inga annonser,
- inga marketing-mail/SMS,
- Stripe fortsatt ej aktiverat,
- extern e-post fortsatt disabled i staging,
- P7 launch gate oförändrad,
- `sales_tracking_enabled=false` är production-default,
- P9-staging använder endast syntetisk data,
- ny extern kostnad: 0 kr.

Canonical dokument: `docs/P9-SALES-ENGINE.md`.
