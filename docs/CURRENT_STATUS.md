# CURRENT STATUS

Datum: 2026-09-27

## Status
**P1: COMPLETE**  
**P2: COMPLETE**  
**P3 – Commerce Core: COMPLETE / MYSQL STAGING VERIFIED**

Betalning: **AVSTÄNGD**  
Leverantörsutskick: **AVSTÄNGT**  
Ny extern kostnad: **0 kr**

## Aktiva stagingadresser
- P3 shop: https://alberiq.se/bois-shop-p3/
- Medlemskap + gym: https://alberiq.se/bois-shop-p3/membership.html
- Matchställ: https://alberiq.se/bois-shop-p3/match-kit.html
- Orderstatus: https://alberiq.se/bois-shop-p3/order.html
- Shopadmin: https://alberiq.se/bois-shop-p3/admin.html
- P3 health: https://alberiq.se/bois-shop-p3/commerce-api.php?action=health

P2-staging finns kvar på:
- https://alberiq.se/bois-bestallning-p1/

## Affärsbeslut 2026-09-27
Erik och Albert har bekräftat att följande får säljas redan nu:
- medlemskap
- Nordic Wellness gymkort för medlem
- matchställ

Övrigt supporter-/merchsortiment hålls dolt till 1 januari 2027.

Bekräftade medlemspriser:
- ungdom 200 kr
- vuxen 350 kr
- pensionär 300 kr

Nordic Wellness gymkort:
- 2 650 kr för aktiv BoIS-medlem

Matchställ:
- batch-/ordermodell är byggd
- 998 kr är fortsatt endast staging/testpris
- verkligt pris och leverantörsdata krävs före skarp försäljning

## P3 levererat

### MySQL Commerce Core
P3 har en riktig MySQL-datamodell med separata `bois_`-tabeller:
- `bois_schema_migrations`
- `bois_products`
- `bois_variants`
- `bois_customers`
- `bois_orders`
- `bois_order_items`
- `bois_memberships`
- `bois_payments`
- `bois_suppliers`
- `bois_fulfillment_rules`
- `bois_supplier_batches`
- `bois_batch_items`
- `bois_email_outbox`
- `bois_events`

### Publik katalog i P3
Endast tre produktgrupper är publika/orderbara:
1. Medlemskap
   - MEM-YOUTH – 200 kr
   - MEM-ADULT – 350 kr
   - MEM-SENIOR – 300 kr
2. Nordic Wellness
   - NW-GYM-ANNUAL – 2 650 kr
3. Matchställ
   - MATCHKIT-STAGING – 998 kr testpris

### Dolda 2027-produkter
- BoIS 1941 Hoodie
- Supporter-T-shirt
- Bandyförälder Hoodie
- Mössa + halsduk
- BoIS Gym Pack
- Knatte Pack
- Presentkort

Dessa är `is_public = false` och `is_orderable = false`.

### Fulfillment-modell
- `DIGITAL_MEMBERSHIP`
- `MEMBER_BENEFIT`
- `BATCH_SUPPLIER`
- `DIRECT_SUPPLIER`
- `DIGITAL_GIFT`

Fulfillment-reglerna är ännu inte aktiverade för utskick.

### Medlemskap + gym
- medlemskap och gym kan testbeställas i samma order
- vuxen + gym ger 3 000 kr i Commerce Core
- gym utan medlemskap nekas om inte testflaggan "befintlig medlem" anges
- medlemskap skapas i databasen som `PENDING_PAYMENT`
- riktig medlemsverifiering byggs i P4

### Matchställ
Orderraden sparar:
- lag
- spelarnamn
- tröjnummer
- tröjstorlek
- byxstorlek
- namntryck
- nummertryck

Fulfillment är `BATCH_SUPPLIER`, men ordern ligger i `ON_HOLD` eftersom betalning inte är aktiverad.

## Betalningsspärr
Varje P3-order skapas som:
- orderstatus: `PENDING_PAYMENT`
- payment_status: `NOT_ENABLED`
- fulfillment_status: `ON_HOLD`

P3 skapar därför inga riktiga leverantörsorder och aktiverar inga medlemskap.

## Verifiering

### GitHub CI
- MySQL 8.4 container: pass
- PHP syntax: pass
- PDO MySQL: pass
- P3 MySQL smoke: pass
- membership + gym order: pass
- gym membership guard: pass
- match kit metadata: pass
- JavaScript syntax: pass
- secret/payment safety checks: pass

### AlberIQ / Simply end-to-end
- Commerce API health: pass
- storage_driver=mysql: pass
- betalning avstängd: pass
- tre publika produkter: pass
- membership + gym testorder: pass
- total vuxen + gym = 3 000 kr: pass
- offentlig orderstatus: pass
- matchställ med BATCH_SUPPLIER: pass
- lagmetadata P13: pass
- admin läser MySQL-order: pass
- 10 katalogprodukter totalt inkl. dolda 2027-produkter: pass
- supplier_batches = 0: pass
- email_outbox = 0: pass
- p3_db.php direktåtkomst blockerad: pass
- p3-migrate.php direktåtkomst blockerad: pass
- icke-BoIS-tabeller före/efter migration: oförändrade

## Databassäkerhet
P3 staging använder befintlig Simply/MySQL-infrastruktur med strikt `bois_`-prefix.

Detta är endast en kostnadsfri staginglösning:
- inga riktiga kund-/medlemsuppgifter ska användas
- inga befintliga icke-BoIS-tabeller får ändras
- produktion ska ha dedikerad BoIS-databas/credential

## Kostnad
**Ny extern kostnad: 0 kr.**

Ingen ny hosting, betaltjänst, mailtjänst eller betalprovider har aktiverats.

## Nästa fas
Rekommenderad nästa operativa fas:

### P5 – Match kit batching
Bygg:
- konfigurerbar tröskel X, startförslag 8–10 order
- max väntetid, startförslag 7 dagar
- batch-ID
- låsning/idempotens så samma order aldrig skickas två gånger
- leverantörs-CSV
- mail till leverantör + kopia Erik
- email outbox + retry
- "Skicka batch nu" i admin
- full batchhistorik

### P4 – Membership & Gym
Bygg parallellt när Nordic Wellness-flödet är bekräftat:
- riktig medlemsstatus
- medlemsperiod
- eligibility
- gymaktivering
- befintlig medlemskontroll
- förnyelsemodell

P6 betalning ska fortfarande komma efter att merchant/betalningsmottagare är beslutad.
