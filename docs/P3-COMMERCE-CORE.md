# P3 – Commerce Core

Datum: 2026-09-27

## Statusmål
P3 ersätter P2:s filbaserade orderlagring för den nya shopen med en riktig MySQL-baserad commerce-modell.

P3 får inte aktivera betalning, leverantörsmail eller riktiga kunddata. Målet är en komplett stagingkedja utan ny extern kostnad.

## Uppdaterat affärsbeslut
Erik och Albert har 2026-09-27 bekräftat att följande får säljas före 31 december 2026:
- medlemskap
- Nordic Wellness-medlemsförmån/gymkort
- matchställ

Övrigt supporter-/merchsortiment får inte säljas före 1 januari 2027 och ligger därför dolt/inaktivt i Commerce Core.

## Priser som är bekräftade av arbetsmötet
- ungdomsmedlemskap: 200 kr
- vuxenmedlemskap: 350 kr
- pensionärsmedlemskap: 300 kr
- Nordic Wellness gymkort för medlem: 2 650 kr

Matchstället använder fortsatt 998 kr som staging/testpris. Det är inte ett skarpt pris och måste bytas mot verklig leverantörskalkyl före produktion.

## P3 datamodell
Alla tabeller har prefix bois_.

Kärna:
- bois_products
- bois_variants
- bois_customers
- bois_orders
- bois_order_items

Medlem/betalning:
- bois_memberships
- bois_payments

Fulfillment:
- bois_suppliers
- bois_fulfillment_rules
- bois_supplier_batches
- bois_batch_items
- bois_email_outbox

Spårbarhet:
- bois_events
- bois_schema_migrations

## Fulfillment-typer
- DIGITAL_MEMBERSHIP – medlemskap.
- MEMBER_BENEFIT – Nordic Wellness-förmånen.
- BATCH_SUPPLIER – matchställ.
- DIRECT_SUPPLIER – framtida supporterprodukter.
- DIGITAL_GIFT – framtida presentkort.

Alla fulfillment-regler är disabled i P3. Inget mail eller leverantörsutskick sker.

## Katalog
Publikt/orderbart i P3 staging:
1. Medlemskap
   - MEM-YOUTH 200 kr
   - MEM-ADULT 350 kr
   - MEM-SENIOR 300 kr
2. Nordic Wellness
   - NW-GYM-ANNUAL 2 650 kr
3. Matchställ
   - MATCHKIT-STAGING 998 kr (testpris)

Dolt till 2027:
- BoIS 1941 Hoodie
- Supporter-T-shirt
- Bandyförälder Hoodie
- Mössa + halsduk
- BoIS Gym Pack
- Knatte Pack
- Presentkort

## Medlems-/gymlogik i P3
- Gymkort får testbeställas tillsammans med ett medlemskap i samma order.
- Gymkort kan även testbeställas som befintlig medlem.
- P3 litar ännu på testflaggan för befintlig medlem.
- P4 ersätter detta med riktig medlemsstatus/eligibility.
- Medlemskap skapas som PENDING_PAYMENT i databasen.
- Inget medlemskap aktiveras före betalning.

## Matchställ i P3
Matchställsordern sparar strukturerad metadata:
- lag
- spelarnamn
- tröjnummer
- tröjstorlek
- byxstorlek
- namntryck
- nummertryck

Fulfillment är BATCH_SUPPLIER, men ordern stannar i ON_HOLD eftersom betalning inte är aktiverad.

## Betalning
P3 skapar en betalningspost med:
- status = NOT_ENABLED
- order status = PENDING_PAYMENT
- fulfillment = ON_HOLD

P6 får senare koppla Swish/kort och webhook.

## Staging-databas
För att hålla ny kostnad på 0 kr används i P3 staging befintlig Simply/MySQL-infrastruktur och endast egna bois_-tabeller.

Detta är uttryckligen en staginglösning:
- endast testdata
- inga riktiga medlems-/kunduppgifter
- inga ändringar i befintliga icke-BoIS-tabeller
- produktion ska få en dedikerad BoIS-databas/credential innan riktiga kunder släpps in

## Definition of Done
P3 är tekniskt klar när:
1. schema + seed migrerar på MySQL 8.4
2. endast tre tillåtna kategorier är publika före 2027
3. medlemskap + gym kan skapa en gemensam testorder
4. gym utan medlemskap nekas om inte befintlig medlem anges
5. matchställ skapar BATCH_SUPPLIER-orderrad med strukturerad metadata
6. betalning är avstängd
7. batch/outbox förblir tomma
8. admin kan läsa MySQL-order och katalog
9. live staging kör utan filbaserad orderlagring
10. ny extern kostnad är 0 kr