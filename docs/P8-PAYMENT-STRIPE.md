# P8 – Payment provider decision: Stripe

Datum: 2026-09-27

## Status

**PROVIDER SELECTED FOR P8 – NOT ACTIVATED**

Tranås BoIS har valt **Stripe** som målprovider för skarp betalning i P8 / production launch.

Detta beslut ändrar **inte** P7. P7 ska fortsätta med 2027-sortiment, pris, SKU, produktdata, fulfillment och launch gate. Ingen Stripe-kod, merchant-onboarding eller betaltjänst ska aktiveras i P7.

## Varför beslutet passar nuvarande arkitektur

P6 har redan:
- provider-adapter
- serverstyrd checkout
- signerad webhook
- event-idempotens
- payment state machine
- verifierad PAID-handler till P4/P5
- refund-flöde
- payment outbox

Stripe ska därför implementeras som en ny provider bakom P6-kontraktet i P8. P4/P5 ska inte byggas om.

## Mål för P8

P8 ska utreda och därefter, efter uttryckligt godkännande av kostnad och merchant-upplägg, implementera:
- Stripe merchant/account för rätt juridisk betalningsmottagare
- kort
- Swish om det är produktionsmässigt tillgängligt för kontot
- Stripe webhook → befintlig P6 eventmodell
- checkout-integration bakom befintlig provider-adapter
- refunds via Stripe → befintlig P6 state machine
- produktionscredentials/secrets
- testmode → production cutover
- kvitto/orderbekräftelse i skarp mailtransport
- observability och reconciliation

## Viktigt om Swish

Stripe visar 2026-09-27 stöd för Swish i Sverige men märker Swish som **Beta**.

P8 får därför inte anta att Swish är skarpt tillgängligt för BoIS-kontot. Innan produktionsaktivering ska Work verifiera:
- att BoIS/merchant-kontot är berättigat till Swish
- att Swish kan aktiveras i production
- eventuella onboardingkrav
- faktisk prislista vid aktivering

Om Swish inte kan aktiveras ska kort kunna gå live via Stripe utan att P6/P4/P5 behöver ändras.

## Prisbild – endast planeringssnapshot

Stripe visade 2026-09-27 följande svenska standardpriser:
- standardkort från EES: **1,5 % + 1,80 kr**
- Swish: **1 % + 3,00 kr**, max **7,00 kr**
- standardupplägget anges utan start- eller månadsavgift

Detta är inte ett budgetgodkännande och får inte användas som fast framtida pris. P8 ska verifiera aktuell Stripe-prissättning före aktivering.

## Fortsatta beslut före skarp betalning

Stripe är valt som provider, men följande återstår:
- juridisk betalningsmottagare / merchant
- vem som äger och administrerar Stripe-kontot
- bankkonto för utbetalningar
- slutlig refund-policy för medlemskap, Nordic och matchställ
- produktionsdatabas/credentials
- skarpa villkor, integritet och säljaruppgifter
- skarp mailtransport
- om Stripe Checkout eller Payment Element bäst passar den befintliga P6-arkitekturen
- verifierad Swish-tillgänglighet för merchant-kontot

## Kostnadsgräns

Ingen riktig Stripe-tjänst, transaktion, merchant-onboarding med kostnad eller annan extern kostnad får aktiveras förrän Albert/Erik uttryckligen godkänt det.

## Källor för pris/tillgänglighet

Kontrollerade 2026-09-27:
- https://stripe.com/se/pricing
- https://stripe.com/se/pricing/local-payment-methods
- https://stripe.com/se/payment-method/swish
