# Tranås BoIS – webshop

Fristående BoIS-webshop med medlemskap, Nordic Wellness-förmån, matchställ och förberett supporter-/merchsortiment.

## Status
- P1 ordermotor: **COMPLETE**
- P2 produktionsförberedelse: **COMPLETE**
- P3 Commerce Core / MySQL: **COMPLETE**
- P4 Membership & Nordic Wellness: **COMPLETE / LIVE STAGING VERIFIED**
- P5 Match kit batching: **COMPLETE / LIVE STAGING VERIFIED**
- P6 Payment: **COMPLETE / LIVE STAGING VERIFIED**
- P7 2027 assortment: **COMPLETE / LIVE STAGING VERIFIED**
- P8 production readiness: **TECHNICALLY COMPLETE / NOT ACTIVATED / BOIS-OWNED STAGING VERIFIED**
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

## Nästa utvecklingsordning
1. **P8 readiness är verifierad i staging**: BoIS-ägd Simply-run `36414148818` lyckades från `main` `1a29eb5ddb229144f255fe92a837d20953617832`, med P8-kodref `818c262af23431be972986b9c79f70f319da90e2`. Fortsätt med P9 eller annan utveckling utan att öppna produktionsgrinden.
2. **P9 – sales engine** kan fortsätta parallellt medan merchant/KYC/bank/domän/villkor för skarp P8 inväntas.
3. **Kommersiell P7-beredning**: inhämta riktiga offerter och godkänn data inför eventuell merchlansering.

P6:s payment gate är den gemensamma gränsen för medlemskap, Nordic och matchställ. P8:s Stripe-adapter, produktionsgrindar och cutover-underlag är implementerade, men Stripe är **inte aktiverat** och staging fortsätter med mock. BoIS-kod, CI och deployment ägs enbart av detta repo. Ersatta BoIS-deploy/readiness-workflows i Work Capture är pensionerade; historiska migrations-, purge- och rollbackspår finns kvar.

## Kostnadsprincip
Inga nya betaltjänster, abonnemang eller externa kostnader aktiveras utan uttryckligt godkännande.

### P6 verifieringsskärpning 2026-09-27
Adminens stagingknapp använder samma signerade mock-event som checkout. Webhooken binder order, session, valuta och belopp. P2–P6 CI på `b5b9a157` och Simply staging run `36327128975` lyckades. Endast mock och avstängd e-posttransport används.
