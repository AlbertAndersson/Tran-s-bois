# P9 – Sales Engine v1

Datum: 2026-09-29

## Status

**COMPLETE / LIVE VERIFIED IN PROTECTED SYNTHETIC STAGING**

Senaste skyddade stagingverifiering: run `36623915463`, job `109595921802`, success. Workflow/main `4918cef10bcc213ef9c756d407ca88d4990971fa`; publicerad appref `c49ebcd9af9f7081e1764d28419d92dd05b4fd48`. P9, C1–C4 och P4–P8-regressioner passerade bakom Basic Auth. Obehöriga direkta URL:er och POST-anrop nekades. Produktionsmätning är fortsatt globalt blockerad. Se `CONSENT-INVENTORY-AND-ACCEPTANCE.md` och `STAGING-DEMO-ACCESS.md`.

Historisk grundverifiering var `36445454316`; PR #11:s kontrollrättning publicerades i `36463946785`. C1–C4-versionen före åtkomstskydd verifierades i `36517329717` med appref `e4ac4ce385fcf751460b4af208756d74e562d54b`. Explicita samtyckessteg ersätter den tidiga automatiska P9-spårningen. Dessa äldre körningar bevaras som historik, inte som nuvarande åtkomststatus.

P9 bygger first-party mätning ovanpå Commerce Core för kampanjer, referrals och produktvägar till syntetisk order och verifierad mock-PAID. Ingen extern analytics, annonsering, e-posttjänst eller ny kostnad används. P4–P8:s ekonomiska och juridiska gränser ändras inte.

## P9A – First-party attribution

Tabeller: `bois_sales_sessions`, `bois_sales_events`, `bois_sales_order_links`.

Attribution: `utm_source`, `utm_medium`, `utm_campaign`, `ref` och landningssökväg. Sessions-ID skapas i sessionStorage först när statistik är tillåten. Det är pseudonymt och kan kopplas till order internt. Inga särskilda sales-fält för namn, e-post, telefon, personnummer, IP eller user-agent. Event har idempotent `event_key`.

`sales_tracking_enabled=false` är production-default och produktionsmätning är globalt blockerad. Att flaggan är true i syntetisk staging räcker inte: giltigt serververifierat statistikmedgivande krävs dessutom. No-choice/nej/återkallelse ska fortfarande ge vanlig beställning utan mätning.

## P9B – Funnel

Stödda events: `page_view`, `product_view`, `checkout_view`, `checkout_started`, `order_created`. Betald konvertering räknas från order-/betalstatus, inte klientens påstående om betalning.

Admin visar stegen besök, produktvisning, betalningssida, betalning startad, order skapad och PAID.

## P9C – Kampanj-/referralöversikt

Admin visar sessioner, sessioner med order, betalda sessioner, session → PAID %, betalda order, brutto/netto testvärde efter refund, genomsnittligt ordervärde, kampanj/referral per source/medium/campaign/ref samt attribuerad produktmix.

Kampanjlänksbyggaren skapar endast spårbara länkar. Den skickar inget, köper inget och ger ingen rabatt.

## P9D – Rekommendationer i shoppen

Medlemskap utan gym ger information om Nordic Wellness-förmånen. Gym utan medlemskap och utan bekräftad befintlig medlem ger information om medlemskravet. Rekommendationerna ändrar inte pris, ger ingen rabatt, lägger aldrig automatiskt till en produkt och påverkar inte payment gate; `discount_ore=0`.

## Integritet och kostnadsgräns

Sales-data är separerade från kundtabellerna men kan kopplas till order via `bois_sales_order_links.order_id`; de är därför pseudonyma, inte anonyma. Översikten är aggregerad. Produktionsinformation, rättslig bedömning och lagringstider återstår till separat beslut.

Inga annonser, externa analystjänster, marknadsföringsmejl/SMS eller CRM-abonnemang aktiveras. Ingen riktig Stripe-betalning/refund, ingen öppnad P7-merch och inga nya rabatter utan separat beslut. Staging innehåller syntetiska testuppgifter och `payment_provider=mock`; mejltransport är disabled. Ny extern kostnad: 0 kr. P8 är **TECHNICALLY COMPLETE / NOT ACTIVATED**.

## Senaste acceptans och bevis

Run `36623915463` verifierade health P9, mock/testmode, first-party sales engine, teknisk stagingflagga true, extern analytics false och Stripe-/produktionsberedskap false. Inget val gav blockerat direkt sales-event; aktivt statistikval gav attribution → order → signerad mock-PAID och P4 ACTIVE/Nordic ELIGIBLE. Återkallelse spärrade även den gamla samtyckeskakan. Dubblettmock gav ingen ny effekt. P5 8/168h, P7 admin/preview och dold merch samt avstängd payment-mail passerade. Listan över främmande tabeller hade oförändrad hash/antal, antal 0.

Chromium headless på Linux kördes vid 375/390/1280 px bakom Basic Auth. Kundens inget val/nej/ja/återkallelse, befintlig medlem, nekad/avbruten betalning och nytt försök samt adminmedlemskontroll, matchställ/batch och mockrefund passerade. Artifact `bois-p9-synthetic-browser-36623915463`, ID `11059388459`, innehåller 10 PNG till 2026-10-06 20:08:02 UTC. Detta är inte fysisk iPhone/Safari eller Eriks verksamhetsgodkännande.

## Historisk P9-baslinje före C1–C4

Run `36445454316`, job `109006483514`, success. Workflow-main `6aabb41c7f0025cf99749919693d84c391f9ce24`; appref `3219dba57fca1eb97b9d50022477131c8db2501b`. P2–P9 CI var grön. Syntetisk attribution, mock-PAID, medlemskap/Nordic, replay, rekommendation utan rabatt, P5 och P7 passerade med 0 främmande tabeller och ingen ny kostnad. Denna tidiga baslinje föregår samtyckes- och Basic Auth-versionen.

## Exit criteria och nästa steg

P9:s tekniska kriterier omfattar P3–P8-regression, MySQL-smoke, event-dedupe, attribution till verifierad PAID, aggregerad översikt, rekommendation utan rabatt, inga direkta identifierarfält i sales-tabeller, ingen extern analytics/mail och grön BoIS-staging utan nya kostnader. Nuvarande version kräver dessutom fungerande samtycke och bevarat demoåtkomstskydd.

Nästa steg är faktisk Erik/BoIS-demo enligt `SYNTHETIC-DEMO.md` och prioriterad återkoppling. Starta inte generell ny utveckling före konkret behov och beslut. Denna closeout ändrar endast dokumentation och kräver ingen ny appdeploy.
