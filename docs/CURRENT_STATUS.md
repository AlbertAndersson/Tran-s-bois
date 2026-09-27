# CURRENT STATUS

Datum: 2026-09-27

## Övergripande status
- **P1 – ordermotor: COMPLETE**
- **P2 – produktionsförberedelse: COMPLETE**
- **P3 – Commerce Core / MySQL: COMPLETE**
- **P5 – Match kit batching: COMPLETE / LIVE STAGING VERIFIED**
- **P4 – Membership & Gym: NEXT**
- **P6 – Payment: NOT STARTED**

Betalning: **AVSTÄNGD**  
Extern mejlsändning: **AVSTÄNGD I STAGING**  
Ny extern kostnad: **0 kr**

## Aktiv staging
- Shop: https://alberiq.se/bois-shop-p3/
- Medlemskap + gym: https://alberiq.se/bois-shop-p3/membership.html
- Matchställ: https://alberiq.se/bois-shop-p3/match-kit.html
- Orderstatus: https://alberiq.se/bois-shop-p3/order.html
- Shopadmin / batchmotor: https://alberiq.se/bois-shop-p3/admin.html
- API health: https://alberiq.se/bois-shop-p3/commerce-api.php?action=health

Staging använder endast testuppgifter.

## Affärsbeslut 2026-09-27
Följande får säljas före 31 december 2026:
- medlemskap
- Nordic Wellness gymkort för medlem
- matchställ

Övrigt supporter-/merchsortiment hålls dolt till 1 januari 2027.

Bekräftade priser:
- ungdomsmedlemskap: 200 kr
- vuxenmedlemskap: 350 kr
- pensionärsmedlemskap: 300 kr
- Nordic Wellness gymkort: 2 650 kr för aktiv BoIS-medlem

Matchställ:
- Commerce Core och batchflöde är byggda
- 998 kr är fortsatt endast staging/testpris
- verkligt pris och leverantörsdata krävs före skarp försäljning

## P3 – Commerce Core
MySQL-datamodell med separata `bois_`-tabeller för:
- schema/migrations
- produkter och varianter
- kunder
- order och orderrader
- medlemskap
- betalningar
- leverantörer
- fulfillment-regler
- leverantörsbatcher
- batchrader
- e-post-outbox
- eventlogg

Publika/orderbara produktgrupper:
1. medlemskap
2. Nordic Wellness gymkort
3. matchställ

Dolda 2027-produkter:
- BoIS 1941 Hoodie
- Supporter-T-shirt
- Bandyförälder Hoodie
- Mössa + halsduk
- BoIS Gym Pack
- Knatte Pack
- Presentkort

## P5 – Match kit batching

### Regel
- tröskel: **8 betalda matchställ**
- max väntetid: **168 timmar / 7 dagar**
- admin kan alltid välja **Skicka batch nu**

Regeln är konfigurerbar i databasen.

### Kandidatkrav
En matchställsrad får endast batchas om:
- orderns `payment_status = PAID`
- `fulfillment_type = BATCH_SUPPLIER`
- `fulfillment_status = WAITING_BATCH`
- raden inte redan finns i `bois_batch_items`

Det gör att:
- obetalda order aldrig batchas
- medlemskap/gym aldrig hamnar i matchställsbatch
- samma orderrad aldrig kan ingå i två batcher

### Batchresultat
När batch skapas:
1. unikt batch-ID skapas
2. orderrader låses i MySQL-transaktion
3. trigger sparas: `THRESHOLD`, `MAX_WAIT` eller `MANUAL`
4. leverantörs-CSV skapas
5. SHA-256 av exakt CSV sparas
6. e-postmeddelande skapas i `bois_email_outbox`
7. orderrader blir `BATCHED`

Efter lyckad framtida mailtransport:
- outbox → `SENT`
- batch → `SENT`
- orderrader → `SENT_TO_SUPPLIER`

### Leverantörs-CSV
Innehåller:
- ordernummer
- lag
- spelare
- tröjstorlek
- byxstorlek
- nummer
- namntryck
- nummertryck
- antal
- SKU

Kolumnordningen justeras när verklig leverantör lämnat sitt slutliga format.

### Outbox och retry
P5 stödjer:
- message_key/idempotens
- till-adress + cc
- exakt batchpayload
- CSV snapshot som base64
- CSV SHA-256
- attempts
- not_before
- last_error
- sent_at
- exponentiell retry
- `FAILED` efter fem misslyckade försök
- manuell retry från admin

## Staging-säkerhet
I staging:
- mottagare tvingas till `supplier@example.invalid`
- kopia tvingas till `erik@example.invalid`
- `mail_transport = disabled`
- inget externt mejl kan skickas

Admin kan simulera `PAID` för testorder. Funktionen är blockerad i production mode.

## P5 shopadmin
Admin visar:
- väntande betalda matchställ
- antal order/ställ
- kvar till tröskel
- äldsta väntetid
- batchhistorik
- triggerorsak
- outboxstatus och attempts
- CSV-download
- Skicka batch nu
- Kontrollera automatik
- staging: Simulera betald

## Verifiering

### GitHub CI / MySQL 8.4
- PHP syntax: pass
- 8 betalda testställ → exakt 1 THRESHOLD-batch: pass
- dubblettskydd: pass
- deterministisk CSV + SHA-256: pass
- failed transport → RETRY: pass
- retry → SENT: pass
- manuell batch under tröskel: pass
- 7-dagarsregel → MAX_WAIT: pass
- JavaScript: pass
- safety checks: pass
- verkligt mejl skickat: **no**

### AlberIQ / Simply live-staging
- API phase=P5: pass
- MySQL: pass
- threshold=8: pass
- max_wait_hours=168: pass
- 8 nya live-testorder skapade: pass
- samtliga simulerade PAID: pass
- THRESHOLD-batch skapad: pass
- CSV download/verifiering: pass
- stagingmottagare `supplier@example.invalid`: pass
- mail transport disabled: pass
- worker processed external mail: 0
- icke-BoIS-tabeller före/efter migration: oförändrade

## Drift
Aktiv deployment:
- `work-capture/.github/workflows/simply-deploy-bois-p5.yml`

P3-deployworkflowen är pensionerad så den inte kan skriva över P5.

För 7-dagarskontroll finns `server/p5-worker.php`.
Schemalagd körning aktiveras först när riktiga betalningar är live, så utvecklingsfasen inte skapar onödiga återkommande körningar.

## Kostnad
**Ny extern kostnad: 0 kr.**

Ingen ny:
- hosting
- mailtjänst
- betalprovider
- schemalagd molntjänst

har aktiverats.

## Nästa utvecklingssteg

### P4 – Membership & Gym
När Nordic Wellness-flödet är bekräftat:
- riktig medlemsstatus
- medlemsperiod
- befintlig medlemskontroll
- eligibility
- gymaktivering
- förnyelsemodell

### P6 – Payment
Därefter:
- Swish/kort
- webhook
- refunds
- kvitto/orderbekräftelse
- riktig `PAID`-händelse

P6 ska anropa samma P5-logik som stagingens simulerade betalning. Matchställsbatchningen behöver därför inte byggas om när betalning kopplas på.
