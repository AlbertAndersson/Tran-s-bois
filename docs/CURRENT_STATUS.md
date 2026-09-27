# CURRENT STATUS

Datum: 2026-09-27

## Övergripande status
- **P1 – ordermotor: COMPLETE**
- **P2 – produktionsförberedelse: COMPLETE**
- **P3 – Commerce Core / MySQL: COMPLETE**
- **P4 – Membership & Nordic Wellness: COMPLETE / LIVE STAGING VERIFIED**
- **P5 – Match kit batching: COMPLETE / LIVE STAGING VERIFIED**
- **P6 – Payment: COMPLETE / LIVE STAGING VERIFIED**
- **P7 – 2027 assortment: IMPLEMENTERAD / CI GRÖN; STAGING VÄNTAR**

Betalning: **ISOLERAD MOCK/TESTMODE I STAGING – RIKTIG PROVIDER AVSTÄNGD**  
Extern mejlsändning: **AVSTÄNGD I STAGING**  
Ny extern kostnad: **0 kr**

## Aktiv staging
- Shop: https://alberiq.se/bois-shop-p3/
- Medlemskap + gym: https://alberiq.se/bois-shop-p3/membership.html
- Matchställ: https://alberiq.se/bois-shop-p3/match-kit.html
- Orderstatus: https://alberiq.se/bois-shop-p3/order.html
- Shopadmin P4 + P5 + P6: https://alberiq.se/bois-shop-p3/admin.html
- Testbetalning P6: https://alberiq.se/bois-shop-p3/payment.html
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
Aktiv deployment:
- `work-capture/.github/workflows/simply-deploy-bois-p4.yml`

Workflowen är nu uppgraderad till P6. Äldre P3- och P5-deployworkflow är pensionerade så de inte kan skriva över aktuell staging.

## Kostnad
**Ny extern kostnad: 0 kr.**

## P7 – 2027 assortment
P7A–D är implementerade på `main` via PR #6, merge `49883befb360fddb6e63e5c6b6a622fd2c0eed8b`. P2–P7 CI är grön, inklusive MySQL 8.4, serverklocka före/efter launchdatum samt P4–P6-regression. Staging är **ännu inte migrerad eller live-verifierad**; P7 stängs först efter skyddad deployment.

Föreslaget första sortiment är BoIS 1941 Hoodie, Supporter-T-shirt och BoIS Läktarmössa. Kundpriserna 549/249/199 kr är `ESTIMATE`; produktunika leverantörsofferter, inköpspris, MOQ, tryck/brodyr, frakt, SKU och marginal är `TBD`. Printful är endast en dokumenterad kandidat, inte avtalad leverantör. Se `data/p7-assortment.json` och `docs/P7A-COMMERCIAL-MODEL.md` för källor och blockerare. Alla tre ligger som ej godkända, opublicerade och ej orderbara.

P7 har separata `bois_p7_assortment`/`bois_p7_variants`-tabeller, adminskyddad granskning och intern preview. P3:s serverstyrda katalog och orderupplägg blockerar samtliga supporter-/merchprodukter före 2027-01-01 i Stockholmstid. Efter datumet krävs godkännande, verifierad kommersiell data och länkade varianter. Klientklocka/klientflaggor kan inte öppna gaten. Ingen P7-produkt uppfyller dessa villkor nu.

Deployment-repot `AlbertAndersson/work-capture` har en uppdaterad, **manuellt skyddad** workflow på commit `21249c0354f3b487539fc3500cc1f37f872300be`, med source pin till P7-merge, migrering, snapshot före/efter och syntetiska livekontroller. Workflowen måste köras med `confirm=DEPLOY_BOIS_P7_READY`. Ingen P7-run har körts ännu; senaste live-verifierade tillstånd är P6-run `36327128975`.

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
