# Tranås BoIS – webshop

Fristående BoIS-webshop med medlemskap, Nordic Wellness-förmån, matchställ och förberett supporter-/merchsortiment.

**Aktuellt:** Slutlig kodref `e4ac4ce385fcf751460b4af208756d74e562d54b` publicerades och liveverifierades i Simply-run `36517329717` (success; job `109242442693`). Workflow på `main` `0933351896cf608a121d4cdeda7ce7e0c13b0052` var manuellt skyddad och pinnad till denna kod. P2–P9 + Security controls CI passerade för mobilrättningen (PR #17); de fem berörda workflowarna passerade även för sista browser-testjusteringen (PR #18, Security `36516093807`, P9 `36516093893`). Chromium headless testade 375, 390 och 1280 px, 10 skärmbilder i Actions-artifact `bois-p9-synthetic-browser-36517329717` (7 dagars retention). P8 är TECHNICALLY COMPLETE / NOT ACTIVATED. Ingen Stripe, riktig betalning, extern mejlsändning, extern analytics eller produktionsaktivering; ny extern kostnad 0 kr. Staging är publikt nåbar utan verifierat inloggningsskydd och får inte delas brett. Se `docs/CONSENT-INVENTORY-AND-ACCEPTANCE.md` och `docs/SYNTHETIC-DEMO.md`.

## Status
- P1 ordermotor: **COMPLETE**
- P2 produktionsförberedelse: **COMPLETE**
- P3 Commerce Core / MySQL: **COMPLETE**
- P4 Membership & Nordic Wellness: **COMPLETE / LIVE STAGING VERIFIED**
- P5 Match kit batching: **COMPLETE / LIVE STAGING VERIFIED**
- P6 Payment: **COMPLETE / LIVE STAGING VERIFIED**
- P7 2027 assortment: **COMPLETE / LIVE STAGING VERIFIED**
- P8 production readiness: **TECHNICALLY COMPLETE / NOT ACTIVATED / BOIS-OWNED STAGING VERIFIED**
- P9 sales engine: **COMPLETE / LIVE STAGING VERIFIED**
- Betalning: **ISOLERAD MOCK/TESTMODE I STAGING – RIKTIG PROVIDER AVSTÄNGD**
- Extern mejlsändning: **AVSTÄNGD I STAGING**
- Ny extern driftkostnad: **0 kr**

## Aktiv staging
- Shop: https://alberiq.se/bois-shop-p3/
- Medlemskap + gym: https://alberiq.se/bois-shop-p3/membership.html
- Matchställ: https://alberiq.se/bois-shop-p3/match-kit.html
- Orderstatus: https://alberiq.se/bois-shop-p3/order.html
- Shopadmin: https://alberiq.se/bois-shop-p3/admin.html
- Testbetalning: https://alberiq.se/bois-shop-p3/payment.html

P7:s interna förhandsvisning (`assortment-preview.html`) är publicerad i staging. Produktdata kräver adminnyckel; sidan gör inga produkter orderbara.

Staging använder endast testuppgifter.

## Säljbara kategorier före 1 januari 2027
- medlemskap
- Nordic Wellness gymkort för medlem
- matchställ

Medlemspriser:
- ungdom 200 kr
- vuxen 350 kr
- pensionär 300 kr

Gymkort:
- 2 650 kr för aktiv BoIS-medlem

Matchställ:
- 998 kr är fortfarande **endast staging/testpris**
- verkligt pris och leverantörsdata krävs före skarp handel

## P4 – medlemskap & Nordic Wellness
P4 innehåller ett eget medlemsregister och separata förmånsärenden.

Efter riktig framtida betalning:
- nytt medlemskap → `ACTIVE`
- ny medlem + gym i samma order → `ELIGIBLE`
- gym för redan medlem → `PENDING_MEMBER_VERIFICATION`
- Erik/admin verifierar befintligt medlemskap → `ELIGIBLE`
- därefter `SENT_TO_PARTNER → READY_FOR_PICKUP → ACTIVATED`

Medlemskap kan förnyas utan ny medlemsidentitet. Samma `member_uuid` behålls och giltigheten förlängs.

P4 samlar **inte in personnummer**. Nordic kan nu hanteras med manuell partnerhandoff och CSV-export; senare API/SFTP/portal kan kopplas in utan ombyggnad av medlemslogiken.

## P5 – matchställsbatchning
- **8 betalda matchställ** → automatisk batch
- **7 dagar** max väntetid → automatisk batch
- admin kan välja **Skicka batch nu**
- dubblettskydd, leverantörs-CSV, SHA-256, outbox och retry finns

I staging är mottagare låsta till `example.invalid` och `mail_transport = disabled`.

## P6 – Payment
P6 är live-verifierad i staging med en kostnadsfri, isolerad payment mock.

- serverstyrd checkout för Test-Swish och Test-kort
- signerad HMAC-webhook med timestamp-kontroll
- unik `(provider,event_id)` för event-idempotens
- retry-säker `PAID`-applicering till P4/P5
- `FAILED`, `CANCELLED`, `PARTIALLY_REFUNDED` och `REFUNDED`
- refund går till `REVIEW_REQUIRED` i fulfillment i stället för att automatiskt återkalla redan startad leverans/förmån
- kvitto/refund-outbox med retry/backoff
- payment-mail är avstängt i staging och mottagare tvingas till `example.invalid`

Ingen riktig betalprovider, merchant-onboarding eller providerkostnad är aktiverad.

## Commerce Core
MySQL med separata `bois_`-tabeller för produkter, kunder, order, medlemskap, förmåner, betalning, leverantörer, fulfillment, batcher, outbox och eventlogg.

## 2027-sortiment
P7 föreslår ett litet första sortiment: BoIS 1941 Hoodie, Supporter-T-shirt och BoIS Läktarmössa. Rekommenderade kundpriser är **uppskattningar**, inte godkända skarpa priser. Leverantörskandidat och all ej verifierad inköps-, tryck-, frakt- och SKU-data är tydligt märkt `TBD` i `data/p7-assortment.json` och `docs/P7A-COMMERCIAL-MODEL.md`. Övriga tidigare kandidater är uppskjutna.

P7:s separata `bois_`-tabeller, adminvy och interna preview är live-verifierade i staging. Servern blockerar offentlig katalog och direkta orderanrop före **1 januari 2027**, även om produktflaggor ändras. Efter datumet krävs dessutom uttryckligt godkännande och verifierad kommersiell data. Ingen P7-produkt är godkänd eller orderbar nu. Den publika startsidan visar inga produktnamn från det interna förslaget. P2–P7 CI är grön; Simply-run `36355678030` verifierade slutlig stagingkod.

## Historisk utvecklingsordning (före C1–C4)
1. **P9 är liveverifierad i kostnadsfri staging**: BoIS-ägd run `36445454316` lyckades från `main` `6aabb41c7f0025cf99749919693d84c391f9ce24`, med pinnad P9-kodref `3219dba57fca1eb97b9d50022477131c8db2501b`.
2. Nästa produktfas kan fokusera på pilotdata, CRO-förbättringar och organisk trafik utan betalda tjänster.
3. **Kommersiell P7-beredning** fortsätter parallellt: riktiga offerter, SKU, marginal och BoIS-godkännande före eventuell merchlansering.

P6:s payment gate är den gemensamma gränsen för medlemskap, Nordic och matchställ. P8:s Stripe-adapter, produktionsgrindar och cutover-underlag är implementerade, men Stripe är **inte aktiverat** och staging fortsätter med mock. BoIS-kod, CI och deployment ägs enbart av detta repo. Ersatta BoIS-deploy/readiness-workflows i Work Capture är pensionerade; historiska migrations-, purge- och rollbackspår finns kvar.

## P9 – Sales Engine
First-party UTM/referral → pseudonym session → order → verifierad mock-PAID och adminens funnel/kampanj/produktmix är liveverifierade. Serverstyrd rekommendation ger ingen rabatt. `sales_tracking_enabled=true` gäller endast syntetisk staging; production-default är false. Inga direkta kundidentifierare, IP eller user-agent lagras i sales-tabellerna. P4–P8-grindar passerade i samma körning; extern analytics, annonsering, marketing-mail/SMS, extern e-post och riktig Stripe-betalning är avstängda. Se `docs/P9-SALES-ENGINE.md`.

## Kostnadsprincip
Inga nya betaltjänster, abonnemang eller externa kostnader aktiveras utan uttryckligt godkännande.

### P6 verifieringsskärpning 2026-09-27
Adminens stagingknapp använder samma signerade mock-event som checkout. Webhooken binder order, session, valuta och belopp. P2–P6 CI på `b5b9a157` och Simply staging run `36327128975` lyckades. Endast mock och avstängd e-posttransport används.
