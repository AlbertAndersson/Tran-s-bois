# NEXT THREAD PROMPT

Ta över **Tranås BoIS – Webbshop** från `AlbertAndersson/Tran-s-bois`. GitHub är source of truth och BoIS äger nu själv kod, CI och deployment. Börja alltid med att verifiera aktuell `main` HEAD.

## Läs först

1. `README.md`
2. `docs/CURRENT_STATUS.md`
3. `docs/00-WORK-HANDOFF.md`
4. `docs/P6-PAYMENT.md`
5. `docs/P7-2027-ASSORTMENT.md`
6. `docs/P8-PAYMENT-STRIPE.md`
7. `docs/P8-PRODUCTION-READINESS.md`
8. `docs/P8-CUTOVER-RUNBOOK.md`
9. `docs/P9-SALES-ENGINE.md`
10. detta dokument

## Verifierat nuläge

P1–P7 är kompletta. P8A–D är **TECHNICALLY COMPLETE / NOT ACTIVATED**. P9 Sales Engine är **COMPLETE / LIVE STAGING VERIFIED**.

BoIS-ägd P8 readiness staging:
- workflow: `Simply - deploy Tranås BoIS P8 readiness staging`
- run: `36414148818`
- job: `108901126748`
- result: **success**
- workflow source main: `1a29eb5ddb229144f255fe92a837d20953617832`
- pinned P8 code: `818c262af23431be972986b9c79f70f319da90e2`

Verifierat:
- phase P8
- payment provider mock
- payment mode testmode
- Stripe ej aktiverat
- production launch false
- syntetisk checkout → signerad mock-webhook → PAID
- medlem ACTIVE
- Nordic ELIGIBLE
- replay-idempotens
- P5 8/168h
- P7 admin/preview
- P7 merch dold/blockerad
- extern payment-mail disabled
- real Stripe calls no
- production traffic no
- new external cost 0 kr

Work Capture äger inte längre BoIS deployment. Flytta inte tillbaka deployment eller databas dit.

## P8 – vad som fortfarande väntar externt

Vänta på Albert/Erik för:
- juridisk merchant/betalningsmottagare
- Stripe kontoägare/KYC
- payout bankkonto
- pris-/avgiftsgodkännande
- verifierad Swish-access
- produktionsdomän
- separat produktionsdatabas
- köpvillkor/integritet
- refundpolicy
- skarp mailtransport

Aktivera inget av detta utan uttryckligt godkännande.

## P9 – verifierad kostnadsfri staging

BoIS-ägd deployrun `36445454316`, job `109006483514`, **success** från `main` `6aabb41c7f0025cf99749919693d84c391f9ce24`, pinnad kodref `3219dba57fca1eb97b9d50022477131c8db2501b`. P9 är **COMPLETE / LIVE STAGING VERIFIED**. Health visade P9, mock/testmode, first-party sales engine, staging tracking true, extern analytics false och stängd Stripe-/produktionsgrind. Syntetisk UTM/referral-session → order → signerad mock-PAID och kampanjattribution, zero-discount recommendation, pseudonym sessiondata utan direkta identifierare, P4 ACTIVE/Nordic ELIGIBLE, P5 8/168h, P7 dold merch och avstängd payment-mail passerade. Inga riktiga Stripe-anrop, marketing-mail, annonser eller nya kostnader. Production-default för tracking förblir false.

Scope:
- first-party funnel
- campaign/referral attribution via UTM/ref
- session → order → PAID attribution
- admin funnel/KPI dashboard
- campaign link builder
- product mix
- zero-discount server-driven recommendations

P9 får inte:
- använda extern analytics
- köpa annonser
- skicka marketing-mail/SMS
- aktivera Stripe
- skapa riktiga betalningar
- ändra P7 launch gate
- skapa rabatter utan separat affärsbeslut

Staging använder endast syntetiska testuppgifter.

## Befintliga priser

- ungdomsmedlemskap: 200 kr
- vuxenmedlemskap: 350 kr
- pensionär: 300 kr
- Nordic Wellness gymkort: 2 650 kr
- matchställ: 998 kr **endast staging/testpris**

P7:s framtida merchpriser är fortfarande uppskattningar/TBD och får inte göras orderbara utan verifierad kommersiell data och BoIS-godkännande.
