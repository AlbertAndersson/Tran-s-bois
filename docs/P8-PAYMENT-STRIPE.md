# P8 – Payment provider: Stripe

Datum: 2026-09-28

## Status

**TECHNICALLY IMPLEMENTED / NOT ACTIVATED**

Stripe är vald som målprovider för skarp betalning. P8A–P8D bygger integrationskod, produktionsgrindar och cutover-underlag, men ingen extern betaltjänst, KYC, credential, riktig transaktion eller ny kostnad aktiveras av implementationen.

Se även:
- `docs/P8-PRODUCTION-READINESS.md`
- `docs/P8-CUTOVER-RUNBOOK.md`
- `data/p8-readiness.json`

## Arkitektur

P6 är fortsatt den gemensamma payment gate som får driva P4/P5.

Stripe kopplas bakom P6:
1. BoIS skapar ordern och fastställer beloppet
2. servern skapar Stripe Checkout Session
3. kunden lämnar BoIS-sidan och betalar hos Stripe Checkout
4. Stripe skickar signerad webhook
5. Stripe-event översätts till P6:s interna eventmodell
6. P6 verifierar order, session, belopp och valuta
7. endast verifierad `PAID` får driva medlemskap/Nordic/matchställ
8. Stripe refund går via P6 `REFUND_PENDING` och manuell fulfillment review

P4/P5 byggs alltså inte om.

## P8A – Stripe foundation

Implementerat:
- hosted Stripe Checkout
- kortstöd
- Swish-stöd bakom explicit `stripe_swish_enabled`
- separat Stripe PaymentIntent-referens
- serverstyrd total i Checkout
- idempotency key för Stripe API-anrop
- `Stripe-Signature` verifiering mot exakt rå webhook-body
- Checkout-event → P6
- refund.updated → P6 refund state machine
- irrelevanta Stripe-event ignoreras säkert
- felaktiga signaturer, order, session, belopp och valuta nekas

## P8B – Production isolation

P8-runtime är fail-closed:
- staging behåller `payment_provider=mock`
- Stripe kräver komplett privat runtime
- `stripe_mode=test` accepterar endast test-secret
- `stripe_mode=live` accepterar endast live-secret
- webhook secret måste vara separat
- Swish är av tills access uttryckligen verifierats
- `production_launch_enabled=false` är default
- extern payment-mail är fortsatt av i staging
- inga credentials finns i repositoryt

Den verifierade BoIS-databasen är staging. Skarp produktion ska använda en separat produktionsdatabas.

## P8C – Readiness gate

Kodens readiness-grind kräver före launch:
- Stripe provider + live mode
- live secret + webhook secret
- HTTPS production base URL
- juridiskt säljar-/merchantunderlag
- supportmejl
- köpvillkor
- integritetspolicy
- merchant verified
- avgifter godkända
- refundpolicy godkänd
- extern mailtransport aktiverad
- explicit production launch approval

Aktuella blockerare ligger i `data/p8-readiness.json`.

## P8D – Cutover

Cutover är dokumenterad men blockerad tills externa uppgifter finns.

Produktionspreflight finns i:
- `ops/p8-production-preflight.php`

Den ska köras efter P3–P8-migration i den framtida separata produktionsdatabasen. Den failar om readiness inte är komplett, om staging/example-URL används, om främmande tabeller finns, om P8-ledgern saknas eller om launch-grinden inte uttryckligen är öppnad.

## Stripe Checkout

Hosted Checkout valdes för att:
- BoIS inte behöver hantera kort-/Swishuppgifter
- servern behåller kontrollen över order och belopp
- Stripe kan hantera betalmetodens UI
- P6 behöver bara lita på signerade server-events

Stripe dokumenterar Checkout Sessions som server-skapade sessioner med en hosted Checkout-URL.

## Webhook

Stripe-webhook använder Stripes standard:
- rå request body
- `Stripe-Signature`
- endpoint signing secret

P8 accepterar inte frontend-status som betalningsbevis.

## Swish

Stripe dokumenterar Swish för svenska kunder i SEK och stöd i Checkout. Stripe-sidan anger samtidigt att access behöver begäras/aktiveras för kontot.

Därför gäller:
- kodstöd: ja
- aktiverad i staging: nej
- skarp access verifierad: nej
- `stripe_swish_enabled`: false tills kontot visar stöd

Om Swish inte är tillgängligt vid första produktionscutover kan kort gå live utan att P4/P5/P6 byggs om.

## Refunds

Stripe Refund API kan skapa hel eller partiell refund mot PaymentIntent.

BoIS-flödet:
1. admin begär refund
2. Stripe API-anrop använder PaymentIntent
3. BoIS går omedelbart till `REFUND_PENDING` + `REVIEW_REQUIRED`
4. signerad Stripe refund-webhook avgör slutlig finansiell status
5. redan startat medlems-/partner-/leverantörsflöde återkallas inte automatiskt

## Aktuell prisbild

Prisbild ska verifieras igen precis före aktivering och kräver uttryckligt godkännande.

Ingen prisuppgift i dokumentationen räknas som godkännande av kostnad.

## Kostnadsgräns

Följande är fortsatt förbjudet utan nytt uttryckligt beslut:
- starta betald extern tjänst
- genomföra riktig Stripe-transaktion
- godkänna Stripe-avgifter
- skapa/aktivera live credentials
- slå på skarp e-post
- slå på production launch gate

## Officiella Stripe-källor kontrollerade 2026-09-28

- https://docs.stripe.com/api/checkout/sessions/create
- https://docs.stripe.com/webhooks/signature
- https://docs.stripe.com/api/refunds/create
- https://docs.stripe.com/payments/swish
- https://stripe.com/se/pricing
