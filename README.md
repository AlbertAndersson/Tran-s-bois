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
1. **Kommersiell P7-beredning**: inhämta riktiga offerter och godkänn data inför eventuell lansering; nuvarande förslag är inte säljklart.
2. **P8 – production launch**: Stripe som vald payment provider, produktionsdatabas, domän, villkor och skarpa integrationsuppgifter.
3. **P9 – sales engine**.

P6:s payment gate är nu den gemensamma gränsen för medlemskap, Nordic och matchställ. **Stripe är vald som målprovider för P8**, men ska inte implementeras eller aktiveras i P7. Stripe ska senare kopplas bakom samma checkout/webhook-kontrakt utan att bygga om P4/P5.

## Kostnadsprincip
Inga nya betaltjänster, abonnemang eller externa kostnader aktiveras utan uttryckligt godkännande.

### P6 verifieringsskärpning 2026-09-27
Adminens stagingknapp använder samma signerade mock-event som checkout. Webhooken binder order, session, valuta och belopp. P2–P6 CI på `b5b9a157` och Simply staging run `36327128975` lyckades. Endast mock och avstängd e-posttransport används.
