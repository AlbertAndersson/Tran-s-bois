# Stripe sandbox – Tranås BoIS

Datum: 2026-10-03

## Status

**ACCOUNT CONNECTED / SANDBOX CHECKOUT VERIFIED / CODE MERGED / GITHUB SECRETS + SIMPLY DEPLOY PENDING**

Stripe-konto: Alberiq, Sverige, SEK, sandbox/test mode.

Verifierat via Stripe API:
- hosted Checkout Session kan skapas i sandbox,
- card är tillgängligt,
- Swish är för närvarande `available=false`,
- inga livebetalningar har genomförts,
- inga befintliga webhook-endpoints, produkter eller Checkout Sessions fanns före testet,
- en separat sandbox Checkout Session skapades endast som capability check,
- kontot är ännu inte färdig-onboardat för live (`charges_enabled=false`, KYC/bank/representative-data återstår).

Stripe webhook endpoint är skapad i sandbox för:
- `checkout.session.completed`
- `checkout.session.async_payment_succeeded`
- `checkout.session.async_payment_failed`
- `checkout.session.expired`
- `refund.updated`

Webhook URL:
`https://alberiq.se/bois-stripe-sandbox-webhook.php`

Webhook endpoint ID:
`we_1UMKIsER3xTb2NvX3p0BquLo`

Signing secret får aldrig skrivas i repo, Drive eller chatten.

## Separat miljö

Eriks vanliga demo på `/bois-shop-p3/` ska fortsatt använda mockbetalning.

Stripe testas separat på:
`https://alberiq.se/bois-shop-stripe-sandbox/`

Syftet är att kunna verifiera riktiga Stripe sandbox-API-anrop och signerade webhooks utan att ändra den verifierade mockdemonstrationen.

## GitHub Secrets som krävs före deploy

`BOIS_STRIPE_TEST_API_KEY`
- använd helst en restricted sandbox key (`rk_test_...`),
- aldrig live key,
- rekommenderade minimibehörigheter för nuvarande implementation:
  - Checkout Sessions: Write
  - Prices: Write
  - Products: Write
  - Charges and Refunds: Write
- orsaken är att BoIS skapar line items med `price_data.product_data` inline från serverns orderdata och dessutom behöver kunna begära refund i sandbox.
- utöka inte med andra rättigheter om inte Stripe-loggen visar att något konkret saknas.

`BOIS_STRIPE_TEST_WEBHOOK_SECRET`
- signing secret för endpointen ovan,
- hämtas/revealas i Stripe Dashboard och kopieras direkt till GitHub Secret,
- får inte skickas i mejl eller chatt.

Befintliga Secrets för Simply, BoIS DB, demoauth och staging admin återanvänds.

## Säkerhetsgränser

- `stripe_mode=test`
- `production_launch_enabled=false`
- `stripe_swish_enabled=false`
- extern mail disabled
- sales tracking disabled i Stripe-sandboxen
- UI bakom Basic Auth
- webhook är publik men accepterar endast POST med korrekt `Stripe-Signature`
- webhookfel visar inte interna felmeddelanden
- restricted API keys stöds av applikationen
- live keys nekas i sandbox
- vanlig mock-staging ändras inte

## Nästa steg

1. Skapa restricted sandbox key i Stripe Dashboard.
2. Lägg den i GitHub som `BOIS_STRIPE_TEST_API_KEY`.
3. Öppna sandbox-webhooken i Stripe Dashboard, reveal signing secret och lägg det i GitHub som `BOIS_STRIPE_TEST_WEBHOOK_SECRET`.
4. Mergea sandboxkoden först när P2–P9/Security CI är grön.
5. Pinna sandboxworkflowen till mergead source ref.
6. Kör `Simply - deploy Tranås BoIS Stripe sandbox` med `DEPLOY_BOIS_STRIPE_SANDBOX_READY`.
7. Verifiera Stripe sandbox API call från Simply.
8. Genomför därefter ett kontrollerat testköp med Stripe testkort och verifiera webhook → P6 PAID → P4/P5.
9. Testa decline, 3DS och refund.
10. Swish väntar tills Stripe-kontot visar `available=true`.

Ingen del av detta öppnar livebetalningar.


## Merge- och workflowstatus

Stripe-sandboxkoden mergeades via PR #27 som `571f78acb29e3e8515853f7afd7818cebd8833f5`.
Den manuella sandboxworkflowen är pinnad till denna source ref i commit `40dfb65711cd2ef87f8d93b2ed8c266a840ce89c`.

Före första deploy återstår endast att lägga in de två privata GitHub Secrets som beskrivs ovan. Den vanliga mock-stagingen påverkas inte.
