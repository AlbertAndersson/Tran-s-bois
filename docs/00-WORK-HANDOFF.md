# Tranås BoIS – WORK HANDOFF

## Syfte

Detta är den primära överlämningen för en ny utvecklingstråd. GitHub är source of truth.

## Source of truth

- Repo: `AlbertAndersson/Tran-s-bois`
- Branch: `main`
- Handoff-bas vid skapandet: `d1fdd3336ca386cd4703d75f3a257c6532dcc15e`
- Deployment/secrets: `AlbertAndersson/work-capture`

Verifiera alltid aktuell HEAD innan du ändrar något.

## Läsordning för ny tråd

1. `README.md`
2. `docs/CURRENT_STATUS.md`
3. `docs/00-WORK-HANDOFF.md`
4. `docs/P3-COMMERCE-CORE.md`
5. `docs/P4-MEMBERSHIP-NORDIC.md`
6. `docs/P5-MATCHKIT-BATCHING.md`
7. `docs/NEXT-THREAD-PROMPT.md`

## Aktiv deployment

Aktiv workflow i deployment-repot:
`AlbertAndersson/work-capture/.github/workflows/simply-deploy-bois-p4.yml`

Senast verifierad P4+P5-deploy:
- run: `36323453297`
- conclusion: **success**
- deploy-fix commit: `a586d24d9e76e9c04203509958bbb87e95e73a14`

Äldre P3/P5 deployworkflows är pensionerade så de inte kan skriva över P4+P5.

## Aktiv staging

- Shop: https://alberiq.se/bois-shop-p3/
- Medlemskap + Nordic Wellness: https://alberiq.se/bois-shop-p3/membership.html
- Matchställ: https://alberiq.se/bois-shop-p3/match-kit.html
- Orderstatus: https://alberiq.se/bois-shop-p3/order.html
- Shopadmin: https://alberiq.se/bois-shop-p3/admin.html
- API health: https://alberiq.se/bois-shop-p3/commerce-api.php?action=health

**Obs:** URL-sökvägen heter fortfarande `bois-shop-p3`, men miljön kör nu P4 + P5. Byt inte sökväg innan slutlig produktionsdomän är beslutad.

Staging får endast innehålla testuppgifter.

## Fasstatus

- P1 – ordermotor: **COMPLETE**
- P2 – produktionsförberedelse: **COMPLETE**
- P3 – Commerce Core / MySQL: **COMPLETE**
- P4 – Membership & Nordic Wellness: **COMPLETE / LIVE STAGING VERIFIED**
- P5 – Match kit batching: **COMPLETE / LIVE STAGING VERIFIED**
- P6 – Payment: **NEXT**
- P7 – 2027 assortment: **NOT STARTED**
- P8 – production launch: **NOT STARTED**
- P9 – sales engine: **NOT STARTED**

Betalning är avstängd. Extern mejlsändning är avstängd i staging. Ny extern driftkostnad hittills: **0 kr**.

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

### Payment / P6
- vem är merchant/betalningsmottagare?
- Swish/kort-upplägg
- provider
- webhook
- refunds
- receipt/order confirmation
- eventuell providerkostnad

Aktivera ingen kostnad utan uttryckligt godkännande.

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

## Nästa fas – P6 Payment

Målet är att koppla in riktig payment event source utan att bygga om P4/P5.

P6 bör innehålla:
1. serverstyrd checkout
2. Swish/kort via vald provider
3. webhook-signaturverifiering
4. idempotens
5. payment state machine
6. verifierad `PAID` → gemensam handler
7. P4 membership/benefit activation
8. P5 matchställskö
9. refunds/cancellations
10. kvitto/orderbekräftelse
11. retry/felhantering
12. testmode utan produktionskostnad

## Definition av nästa bra stopp

P6 stagingklar när:
- provider testmode eller isolerad payment mock fungerar
- en verifierad webhook ger exakt en `PAID`
- samma event kan inte dubbelprocessas
- medlemskap aktiveras exakt en gång
- Nordic entitlement uppdateras exakt en gång
- matchställ hamnar i batchkö exakt en gång
- refunds har definierade state transitions
- inga externa leverantörsmejl skickas i staging
- inga nya verkliga kostnader är aktiverade
