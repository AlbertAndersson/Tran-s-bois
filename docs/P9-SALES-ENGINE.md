# P9 – Sales Engine v1

Datum: 2026-09-28

## Status

**IMPLEMENTED / COST-FREE STAGING VERIFY PENDING**

P9 bygger ett eget first-party sales engine ovanpå befintlig Commerce Core. Syftet är att förstå vilka kampanjer, referrals och produktvägar som faktiskt leder till testorder och verifierad mock-PAID utan att köpa analytics, annonsering, e-postverktyg eller andra externa tjänster.

P9 ändrar inte P4–P8:s ekonomiska eller juridiska gränser.

## P9A – First-party attribution

Nya tabeller:
- `bois_sales_sessions`
- `bois_sales_events`
- `bois_sales_order_links`

Attribution:
- `utm_source`
- `utm_medium`
- `utm_campaign`
- `ref`
- landing path

Principer:
- session-id skapas i browserns `sessionStorage`
- sessionspåret är pseudonymt och kan internt kopplas till en order
- inga tredjepartscookies
- ingen extern analytics
- ingen IP-adress lagras
- ingen user-agent lagras
- inga direkta kundidentifierare som namn/e-post/telefon lagras i sales-tabellerna
- events har eget idempotent `event_key`
- `sales_tracking_enabled=false` är production-default; P9-staging sätter flaggan explicit till true för syntetisk testdata

## P9B – Funnel

Stödda events:
- `page_view`
- `product_view`
- `checkout_view`
- `checkout_started`
- `order_created`

Betald konvertering räknas från den verkliga order-/payment-state-maskinen och inte från frontend-event.

Funnel i admin:
- besök
- produktvisning
- betalningssida
- betalning startad
- order skapad
- PAID

## P9C – Campaign/referral dashboard

Admin visar:
- sessioner
- sessioner med order
- betalda sessioner
- session → PAID %
- betalda order
- brutto testvärde
- netto testvärde efter refund
- genomsnittligt betalt ordervärde
- kampanj/referral per source/medium/campaign/ref
- produktmix för attribuerade betalda order

Kampanjlänksbyggaren skapar endast spårbara länkar. Den skickar inget, köper inget och ger ingen rabatt.

## P9D – On-site sales guidance

Första rekommendationsregler:
- medlemskap utan gym → visa Nordic Wellness som relevant medlemsförmån
- gym utan medlemskap och inte befintlig medlem → rekommendera medlemskap eftersom det krävs

Reglerna:
- ändrar inte pris
- ger ingen rabatt
- lägger aldrig automatiskt till en produkt
- har `discount_ore=0`
- påverkar inte payment gate

## Kostnadsgräns

P9 v1 får inte:
- köpa annonser
- aktivera extern analytics
- skicka marknadsföringsmejl/SMS
- skapa extern CRM-/marketing automation-kostnad
- aktivera Stripe eller riktig betalning
- öppna P7-merch före servergaten
- skapa rabatt utan separat affärsbeslut

## Integritet

Sales-tabellerna är uttryckligen separerade från kundtabellerna. Koppling till order sker endast genom `bois_sales_order_links.order_id`; det gör sessionen indirekt kopplingsbar och därför behandlas den som pseudonym data. Attribution-dashboarden är aggregerad.

Före eventuell produktionsaktivering ska integritetsinformationen och rättslig grund/consent-bedömning bekräftas. Tracking är därför fail-closed i produktionskonfigurationen.

Förbjudna direkta sales-fält:
- namn
- e-post
- telefon
- personnummer
- IP
- user agent

## Staging

Staging fortsätter med:
- syntetiska testuppgifter
- `payment_provider=mock`
- extern e-post disabled
- Stripe ej aktiverat
- P7 merch blockerad
- ingen extern analytics
- ny extern kostnad 0 kr

## Exit criteria för P9 v1

P9 kan markeras `COMPLETE / LIVE STAGING VERIFIED` när:
1. P3–P8 regressioner är gröna
2. P9 MySQL-smoke är grön
3. funnel-event dedupe verifieras
4. campaign/ref attribution följer order till PAID
5. dashboard visar funnel/kampanj/produktmix
6. recommendations är zero-discount och serverstyrda
7. inga sales-PII-fält finns
8. ingen extern analytics eller mail används
9. BoIS-ägd stagingdeploy är grön
10. `NEW_EXTERNAL_COST=0`
