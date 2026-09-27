# Tranås BoIS – WORK HANDOFF

## Syfte

Detta är den primära överlämningen för en ny utvecklingstråd. GitHub är source of truth.

## Source of truth

- Repo: `AlbertAndersson/Tran-s-bois`
- Branch: `main`
- P6 slutlig hardening merge: `b5b9a157a7462277cdab27bb304c5c1b31706fa0` (PR #3)
- Verifiera alltid aktuell `main` HEAD innan ändring
- Deployment/secrets: `AlbertAndersson/work-capture`

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
10. `docs/NEXT-THREAD-PROMPT.md`

## Aktiv deployment

Aktiv workflow i deployment-repot:
`AlbertAndersson/work-capture/.github/workflows/simply-deploy-bois-p4.yml`

Senast verifierad P6-deploy:
- run: `36327128975`
- conclusion: **success**
- deployment commit i `work-capture`: `68412823f65e120484febc091c052df8b4bd4564`
- source hardening merge i shop-repot: `b5b9a157a7462277cdab27bb304c5c1b31706fa0`
- snapshot: **38 icke-BoIS-tabeller, identisk hash före/efter**

Äldre P3/P5 deployworkflows är pensionerade så de inte kan skriva över aktuell P6-staging.

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
- P7 – 2027 assortment: **NEXT**
- P8 – production launch: **NOT STARTED**
- P9 – sales engine: **NOT STARTED**

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
P6-staging är klar och **Stripe är vald som målprovider för P8**. Före skarp betalning återstår:
- vem är merchant/betalningsmottagare?
- aktuell Stripe-prisbild och uttryckligt kostnadsgodkännande
- merchant onboarding/KYC och kontoägarskap
- verifiera att Stripe-Swish är tillgängligt i production för BoIS-kontot (Swish är märkt Beta av Stripe 2026-09-27)
- produktionscredentials/secrets
- verklig webhook-konfiguration
- beslut Stripe Checkout kontra Payment Element
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

## Nästa fas – P7 2027 assortment

Målet är att göra supporter-/merchsortimentet kommersiellt och tekniskt lanseringsklart utan att bryta avtalsgränsen.

Arbeta med:
1. leverantörer
2. verkliga inköpspriser
3. rekommenderade försäljningspriser och marginal
4. SKU/artikelnummer
5. storlekar/varianter
6. produktbilder och copy
7. fulfillmentmodell per produkt
8. lager/direct-supplier-regler
9. returer/reklamationsflöde
10. produktdata i Commerce Core
11. launch gate

**Hård invariant:** supporter-/merchprodukter får inte bli publika/orderbara före **1 januari 2027**.

**P7-avgränsning:** Stripe-valet är dokumenterat för P8. P7 får inte implementera Stripe, skapa Stripe-konto eller aktivera någon betaltjänst.

## Definition av nästa bra stopp

P7 stagingklar när:
- prioriterat 2027-sortiment har verifierad leverantör
- inköpspris, försäljningspris och marginal är dokumenterade
- SKU/varianter är strukturerade
- fulfillment är definierat per produkt
- produkter kan granskas i staging/admin
- launch gate förhindrar publik/orderbar exponering före 1 januari 2027
- inga nya externa kostnader är aktiverade utan godkännande
