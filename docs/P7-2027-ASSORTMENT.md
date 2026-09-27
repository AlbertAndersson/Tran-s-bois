# P7 – 2027 assortment

Datum: 2026-09-27

## Status

**NEXT / NOT STARTED**

P7 ska göra Tranås BoIS supporter-/merchsortiment kommersiellt och tekniskt lanseringsklart för 2027, utan att exponera eller sälja sortimentet före avtalsgränsen.

## Hård launch gate

Supporter-/merchprodukter får **inte** bli publika eller orderbara före **1 januari 2027**.

Det ska skyddas både:
- i datamodellen
- i katalog/API
- i frontend
- i tester/CI

Att ändra lokal klocka, direkt anropa API eller manipulera frontend får inte kunna kringgå serverns gate.

## P7 påverkar inte Payment

P6 är stängd och verifierad.

Stripe är vald som målprovider för **P8 production launch**, se `docs/P8-PAYMENT-STRIPE.md`.

P7 får inte:
- skapa eller aktivera Stripe-konto
- lägga in Stripe-credentials
- implementera Stripe-provider
- ändra P6 state machine utan regressionfix
- aktivera riktiga betalningar
- aktivera extern e-post
- skapa ny extern kostnad

## Befintliga 2027-kandidater

Commerce Core innehåller redan dolda kandidater:
- BoIS 1941 Hoodie
- Supporter-T-shirt
- Bandyförälder Hoodie
- Mössa + halsduk
- BoIS Gym Pack
- Knatte Pack
- Presentkort

Dessa är kandidater, inte automatiskt godkänt slutligt sortiment.

P7 får:
- behålla
- slå ihop
- ompositionera
- komplettera

men ska dokumentera varför.

## Affärsmål

Sortimentet ska:
- generera föreningsmarginal
- vara enkelt att driva operativt
- minimera lager- och kapitalrisk
- passa en lokal föreningsshop
- ha tydlig Tranås BoIS-identitet
- undvika onödigt många varianter/SKU
- fungera med framtida Stripe/P6 utan specialfall

## P7A – Assortment research & commercial model

Kartlägg och föreslå ett fokuserat lanseringssortiment.

För varje produkt ska underlaget skilja tydligt på:
- verifierad leverantörsdata
- verifierat inköpspris
- verifierad MOQ
- verifierad ledtid
- verifierad tryck/brodyrkostnad
- rekommenderat försäljningspris
- beräknad bruttomarginal
- frakt/hanteringsantagande
- retur-/reklamationsrisk
- lager kontra direct supplier
- status: VERIFIED / ESTIMATE / TBD

Hitta inte på leverantör, pris eller SKU.

Om uppgift inte kan verifieras ska den ligga som `TBD` eller `ESTIMATE`.

Målet är ett litet starkt sortiment snarare än många halvfärdiga produkter.

## P7B – Commerce model

Utöka Commerce Core så 2027-produkter har strukturerad data för:
- product key
- SKU
- variant
- storlek/färg
- inköpspris
- försäljningspris
- moms-/prisdata där relevant
- leverantör
- fulfillment type
- launch date
- public/orderable gate
- lagerstrategi
- supplier/direct fulfillment
- returklass
- marginaldata
- metadata/källstatus

Ingen P7-produkt får orderbart läge före launch gate.

## P7C – Admin preview & shop presentation

Admin ska kunna granska 2027-sortimentet före lansering:
- produkt
- variant/SKU
- leverantör
- verifieringsstatus
- inköpspris
- försäljningspris
- marginal kr
- marginal %
- fulfillment
- launch status
- blockers/TBD

Skapa en intern/staging preview av butikspresentationen så Erik/Albert kan granska:
- produktnamn
- bild/placeholder
- produktcopy
- pris
- varianter
- sortimentsgruppering

Preview får inte göra produkterna publikt orderbara.

## P7D – Launch gate & regression

CI ska bevisa:
- före 2027-01-01 är 2027-produkter inte publika/orderbara
- efter simulerad launch-tid kan godkända produkter bli tillgängliga enligt konfiguration
- P4 medlemskap/Nordic är oförändrat
- P5 matchställsbatchning är oförändrad
- P6 payment gate är oförändrad
- inga externa mejl aktiveras
- inga riktiga betalningar aktiveras
- inga icke-BoIS-tabeller ändras

## Rekommenderad arbetsordning

Genomför P7 i fyra verifierbara delsteg och committa efter varje del:

1. **P7A – research/commercial model**
2. **P7B – commerce schema/catalog**
3. **P7C – admin + preview**
4. **P7D – launch gate + regression + docs**

Undvik en enda stor implementation-commit.

## Definition of Done

P7 är klar när:
- ett fokuserat lanseringssortiment finns dokumenterat
- leverantör/pris/SKU-status är explicit per produkt
- verifierad data inte blandas ihop med uppskattningar
- priser och marginaler kan granskas i admin
- SKU/varianter är strukturerade i Commerce Core
- fulfillment är definierat per produkt
- preview kan granskas i staging
- serverstyrd launch gate är testad
- inga 2027-produkter kan säljas före 1 januari 2027
- P4–P6 regressioner är gröna
- inga externa kostnader har aktiverats
- handoff/current status är uppdaterade

## Bra stopp efter P7

Efter P7 ska projektet gå vidare till P8 production launch.

P8 äger:
- Stripe-integration
- merchant/onboarding
- produktionsdatabas
- domän
- skarpa villkor/integritet/säljaruppgifter
- skarp e-post
- production cutover

P7 ska inte påbörja dessa delar.
