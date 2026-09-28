# P8 – Production readiness

Datum: 2026-09-28

## Status

**TECHNICALLY READY / NOT ACTIVATED**

P8 ska göra webshoppen produktionsredo utan att aktivera någon extern kostnad eller skarp betalning innan Albert/Erik har lämnat de uppgifter och godkännanden som saknas.

Detta dokument är source of truth för P8:s produktionsgrind. Maskinläsbar spegling finns i `data/p8-readiness.json`.

## P8A – Stripe foundation

Implementerat:
- Stripe som provider bakom befintlig P6 payment gate
- hosted Stripe Checkout
- servern sätter belopp och valuta från ordern
- kundens kort-/Swishuppgifter går inte genom BoIS-servern
- Checkout Session länkas till BoIS-order via servergenererad metadata
- Stripe Checkout-webhook översätts till P6:s interna eventmodell
- rå webhook-body + `Stripe-Signature` verifieras innan event accepteras
- event-idempotens återanvänder P6:s `(provider,event_id)`
- Stripe PaymentIntent lagras separat från Checkout Session
- refunds går till `REFUND_PENDING` och `REVIEW_REQUIRED`
- slutlig refundstatus drivs av signerad Stripe-webhook

Ingen Stripe-kod kör externa API-anrop när `payment_provider=mock`.

## P8B – Production isolation

Implementerat i kod:
- Stripe-runtime fail-closed
- testnyckel accepteras endast när `stripe_mode=test`
- live-nyckel accepteras endast när `stripe_mode=live`
- Stripe webhook secret måste vara separat `whsec_...`
- production launch kräver explicit `production_launch_enabled=true`
- stagingexemplet behåller `payment_provider=mock`
- staging behåller extern e-post avstängd
- Swish är en separat explicit feature flag
- P8 lägger endast till Stripe-specifika kolumner i `bois_payments`
- P8-migrationen är idempotent

Produktionsdatabasen ska vara en separat BoIS-produktionsdatabas. Den nu verifierade dedikerade BoIS-databasen är staging och får inte automatiskt återanvändas som skarp produktionsdatabas.

## P8C – Production readiness

Följande måste vara verifierat före skarp cutover:

### Merchant och Stripe
- juridisk betalningsmottagare
- ägare/admin för Stripe-kontot
- KYC/onboarding
- bankkonto för utbetalning
- aktuell Stripe-prisbild godkänd
- live credentials i privat runtime
- webhook endpoint + secret i privat runtime
- Swish-access verifierad för kontot innan `stripe_swish_enabled=true`

### Juridik och kundkommunikation
- säljarens juridiska namn
- organisationsnummer
- supportadress
- köpvillkor
- integritetspolicy
- slutlig refundpolicy
- vilken medlemsperiod som gäller
- hur Nordic-förmånen ska beskrivas
- verkligt matchställspris och leverantörsdata

### Drift
- separat produktionsdatabas
- separat produktionskonfiguration/secrets
- produktionsdomän
- TLS/HTTPS
- backup före cutover
- verifierat restore-förfarande
- skarp mailtransport
- övervakning av payment events/outbox
- reconciliation mellan BoIS-order och Stripe

## P8D – Cutover package

Cutover får inte ske från stagingens vanliga deployworkflow.

Ordning:
1. frys produktionssource-ref
2. verifiera alla readiness-fält
3. ta backup/snapshot
4. skapa/migrera separat produktionsdatabas
5. deploya med `mode=production`, `payment_provider=stripe`, `stripe_mode=live`
6. verifiera health/readiness innan trafik öppnas
7. skapa Stripe webhook endpoint
8. verifiera test/order med godkänd metod enligt cutoverplan
9. öppna skarp trafik först efter uttrycklig launch-approval
10. följ payment event/outbox/reconciliation direkt efter launch

Rollback:
- stäng production launch gate
- stoppa nya checkouts
- behåll order/payment-eventdata
- återställ applikationsrelease
- återställ databas från pre-cutover backup endast efter separat databeslut
- radera aldrig payment events som del av rollback

## Zero-cost state nu

Följande är avsiktligt **inte** gjort:
- inget Stripe-konto har skapats av denna implementation
- inga Stripe live/test credentials har lagts in
- ingen KYC har startats
- ingen riktig Stripe Checkout har skapats
- ingen Swish-access har begärts
- ingen riktig betalning/refund har skickats
- ingen extern mejltransport har slagits på
- ingen produktionsdatabas har skapats
- ingen produktionsdomän har aktiverats
- ingen ny extern kostnad har uppstått

## Tekniska verifieringar

CI ska bevisa:
- P3–P7 regression fortsatt grön
- Stripe Checkout-kontrakt med fake transport
- felaktig Stripe-signatur nekas
- fel belopp nekas av P6
- Stripe-event replay är idempotent
- Swish är blockerad tills explicit flagga är satt
- refund går via manual review
- live key kan inte användas i testmode
- production launch är false utan samtliga godkännanden
- inga riktiga Stripe-anrop sker i testsviten

## Externa källor kontrollerade 2026-09-28

- Stripe Checkout Session API: https://docs.stripe.com/api/checkout/sessions/create
- Stripe webhook signature verification: https://docs.stripe.com/webhooks/signature
- Stripe Refund API: https://docs.stripe.com/api/refunds/create
- Stripe Swish: https://docs.stripe.com/payments/swish
