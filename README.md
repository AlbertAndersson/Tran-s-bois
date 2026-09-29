# Tranås BoIS – webshop

Fristående BoIS-webshop med medlemskap, Nordic Wellness-förmån, matchställ och förberett supporter-/merchsortiment.

**Aktuellt: PROTECTED STAGING / LIVE VERIFIED / READY FOR LIMITED SYNTHETIC DEMO.** Run [36623915463](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/36623915463), job `109595921802`, avslutades med **success**. Workflow/main vid körningen var `4918cef10bcc213ef9c756d407ca88d4990971fa`; publicerad applikationsref är `c49ebcd9af9f7081e1764d28419d92dd05b4fd48`. Obehörig direktåtkomst nekades med 401 och behöriga P4–P9-/samtyckes-/adminflöden passerade bakom Basic Auth över HTTPS. Chromium headless testade 375/390/1280 px. 10 PNG i artifact `bois-p9-synthetic-browser-36623915463`, ID `11059388459`, till 2026-10-06 20:08:02 UTC. Ingen permanent bildkopia har skapats i dokumentationscloseouten. Fysisk iPhone/Safari och faktisk återkoppling från Erik/BoIS återstår.

P8 är **TECHNICALLY COMPLETE / NOT ACTIVATED**. Stripe, riktig betalning, extern mejlsändning/analytics, annonsering och produktion är inte aktiverade; ny extern kostnad 0 kr. Den tidigare blockeraren om öppen staging är löst. Endast ett begränsat antal behöriga granskare får tillgång och endast syntetiska uppgifter används. Demoåtkomst ersätter inte separat adminbehörighet eller framtida personliga konton/MFA. Se `docs/CURRENT_STATUS.md`, `docs/STAGING-DEMO-ACCESS.md` och `docs/SYNTHETIC-DEMO.md`.

## Nästa steg

**Genomför Eriks/BoIS faktiska demo och samla återkoppling.** Använd det befintliga syntetiska demomanuset och prioritera konkreta observationer innan nästa avgränsade ändring beslutas. Starta inte mer generell teknisk utveckling eller en ny deploy enbart för att slutdokumentationen uppdateras. Automatiserade testpass är inte samma sak som verksamhetens godkännande.

## Status
- P1 ordermotor: **COMPLETE**
- P2 produktionsförberedelse: **COMPLETE**
- P3 Commerce Core / MySQL: **COMPLETE**
- P4 Membership & Nordic Wellness: **COMPLETE / LIVE STAGING VERIFIED**
- P5 Match kit batching: **COMPLETE / LIVE STAGING VERIFIED**
- P6 Payment: **COMPLETE / LIVE STAGING VERIFIED**
- P7 2027 assortment: **COMPLETE / LIVE STAGING VERIFIED**
- P8 production readiness: **TECHNICALLY COMPLETE / NOT ACTIVATED / BOIS-OWNED STAGING VERIFIED**
- P9 sales engine och C1–C4: **LIVE VERIFIED IN PROTECTED SYNTHETIC STAGING**
- Demoåtkomst: **IMPLEMENTED / DEPLOYED / LIVE VERIFIED**
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

Alla sidor, assets och API ligger bakom katalogens demoautentisering. P7:s interna förhandsvisning (`assortment-preview.html`) är publicerad i staging. Produktdata kräver dessutom separat adminnyckel; sidan gör inga produkter orderbara. Dela lösenordet via privat kanal, aldrig i repo eller Drive-handoff.

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
- `FAILED`, `CANCELLED`, `PARTIALLY_REFUNDED`, `REFUND_PENDING` och `REFUNDED`
- refund går till `REVIEW_REQUIRED` i fulfillment i stället för att automatiskt återkalla redan startad leverans/förmån
- kvitto/refund-outbox med retry/backoff
- payment-mail är avstängt i staging och mottagare tvingas till `example.invalid`

Ingen riktig betalprovider, merchant-onboarding eller providerkostnad är aktiverad.

## Commerce Core
MySQL med separata `bois_`-tabeller för produkter, kunder, order, medlemskap, förmåner, betalning, leverantörer, fulfillment, batcher, outbox och eventlogg.

## 2027-sortiment
P7 föreslår ett litet första sortiment: BoIS 1941 Hoodie, Supporter-T-shirt och BoIS Läktarmössa. Rekommenderade kundpriser är **uppskattningar**, inte godkända skarpa priser. Leverantörskandidat och all ej verifierad inköps-, tryck-, frakt- och SKU-data är tydligt märkt `TBD` i `data/p7-assortment.json` och `docs/P7A-COMMERCIAL-MODEL.md`. Övriga tidigare kandidater är uppskjutna.

P7:s separata `bois_`-tabeller, adminvy och interna preview är live-verifierade i staging. Servern blockerar offentlig katalog och direkta orderanrop före **1 januari 2027**, även om produktflaggor ändras. Efter datumet krävs dessutom uttryckligt godkännande och verifierad kommersiell data. Ingen P7-produkt är godkänd eller orderbar nu. Den publika startsidan visar inga produktnamn från det interna förslaget. Historisk P2–P7 CI och Simply-run `36355678030` verifierade P7; samma produktspärrar har bevarats i senare stagingacceptans.

## Driftägarskap

P6:s payment gate är den gemensamma gränsen för medlemskap, Nordic och matchställ. P8:s Stripe-adapter, produktionsgrindar och cutover-underlag är implementerade, men Stripe är **inte aktiverat** och staging fortsätter med mock. BoIS-kod, CI och deployment ägs enbart av detta repo. Ersatta BoIS-deploy/readiness-workflows i Work Capture är pensionerade; historiska migrations-, purge- och rollbackspår finns kvar.

## P9 – Sales Engine
First-party UTM/referral → pseudonym session → order → verifierad mock-PAID och adminens funnel/kampanj/produktmix är liveverifierade. Serverstyrd rekommendation ger ingen rabatt. `sales_tracking_enabled=true` gäller endast syntetisk staging och innebär inte mätning före serververifierat statistikmedgivande. Produktionens mätning är globalt blockerad. Inga direkta kundidentifierare, IP eller user-agent lagras i sales-tabellerna; sessions-ID är pseudonymt och kan kopplas till order. P4–P8-grindar passerade i samma körning; extern analytics, annonsering, marketing-mail/SMS, extern e-post och riktig Stripe-betalning är avstängda. Se `docs/P9-SALES-ENGINE.md` och `docs/CONSENT-INVENTORY-AND-ACCEPTANCE.md`.

## Kostnadsprincip
Inga nya betaltjänster, abonnemang eller externa kostnader aktiveras utan uttryckligt godkännande.

## Bevarad verifieringshistorik

Historisk P6-hardening: adminens stagingknapp använder signerad mockbetalning; order/session/valuta/belopp kontrolleras. P2–P6 CI på `b5b9a157` och run `36327128975` lyckades. Ursprunglig P9-bas var run `36445454316` med appref `3219dba57fca1eb97b9d50022477131c8db2501b`. C1–C4/browserversionen före inloggningsskydd verifierades i run `36517329717` med appref `e4ac4ce385fcf751460b4af208756d74e562d54b`. Den öppna stagingens åtkomstblockerare är nu löst genom **36623915463**.

Fullständiga tidigare status- och handofftexter har bevarats i `docs/history/`. Historiska instruktioner om att installera demoåtkomst är inte nästa uppdrag. Denna dokumentationscloseout ändrar ingen applikationskod, workflow, hemlighet, databas eller serverinställning.
