# P8 – Production cutover runbook

Datum: 2026-09-28

## Syfte

Detta är körordningen för den framtida skarpa lanseringen. Den är förberedd nu men ska inte exekveras förrän alla externa uppgifter och godkännanden finns.

## Förkrav

Samtliga måste vara JA:
- merchant/KYC verifierad
- Stripe kontoägare verifierad
- bankkonto verifierat
- Stripe-avgifter uttryckligen godkända
- live API key och webhook secret installerade privat
- Swish-access verifierad om Swish ska aktiveras
- produktionsdomän beslutad
- separat produktions-DB skapad
- köpvillkor och integritet publicerade
- refundpolicy godkänd
- supportmejl fungerar
- skarp mailtransport verifierad
- backup + restore-test klart
- produktionspriser/gates godkända
- `production_launch_enabled` fortsatt false fram till sista beslutet

## Secret inventory

Följande får aldrig sparas i GitHub/Drive:
- Stripe secret key
- Stripe webhook signing secret
- databaslösenord
- admin token
- privata SSH-nycklar

Produktionsruntime behöver minst:
- `BOIS_PROD_DB_HOST`
- `BOIS_PROD_DB_PORT`
- `BOIS_PROD_DB_NAME`
- `BOIS_PROD_DB_USER`
- `BOIS_PROD_DB_PASSWORD`
- `BOIS_STRIPE_SECRET_KEY`
- `BOIS_STRIPE_WEBHOOK_SECRET`
- produktionsdomän/base URL
- seller/support/legal-värden
- separat admin token

## Pre-cutover

1. verifiera aktuell BoIS `main`
2. verifiera full CI
3. skapa immutable source ref/tag
4. kontrollera att staging fortfarande kör mock
5. kontrollera att P7 merch gate fortfarande är stängd
6. verifiera produktionsdatabasen är annan databas än staging
7. ta backup/snapshot
8. kör idempotenta P3–P8 migrationer
9. kör production preflight
10. lämna `production_launch_enabled=false`

## Stripe setup

1. skapa/verifiera Stripe-konto för rätt merchant
2. ställ in payout bankkonto
3. verifiera företag/KYC
4. konfigurera public business details
5. aktivera kort
6. aktivera Swish endast om Stripe visar att kontot har access
7. skapa webhook mot produktions-API:s `?action=payment_webhook`
8. prenumerera endast på event som integrationen använder
9. installera webhook secret i privat runtime

## Cutover

1. deploya frusen release
2. health ska visa `phase=P8`
3. `payment_provider=stripe`
4. `payment_mode=live`
5. `production_launch_ready=true`
6. verifiera katalog/gates utan köp
7. sätt `production_launch_enabled=true` först efter slutgodkännande
8. genomför en kontrollerad första order
9. verifiera Checkout → Stripe webhook → P6 PAID → P4/P5
10. verifiera kvitto/outbox
11. verifiera Stripe↔BoIS reconciliation

## Stop/rollback criteria

Stoppa nya checkouts om:
- webhooksignaturer fallerar
- belopp/valuta inte matchar
- dubbel fulfillment upptäcks
- payment-event backlog växer
- reconciliation inte går ihop
- mail/outbox skapar fel
- produktion pekar mot stagingdatabas
- Stripe-kontot/merchant inte matchar beslutad betalningsmottagare

## Efter launch

Första dygnet:
- kontrollera Stripe Dashboard mot BoIS payments
- kontrollera failed/ignored webhook events
- kontrollera refunds/review required
- kontrollera outbox
- kontrollera P4 medlemskap/Nordic
- kontrollera P5 batchkö
- ta ny backup
- dokumentera faktisk launch-run och source ref

## Nuvarande status

**RUNBOOK READY / CUTOVER BLOCKED**

Blockeringen är avsiktlig tills Albert/Erik har lämnat och godkänt externa produktionsuppgifter.
