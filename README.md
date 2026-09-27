# Tranås BoIS – webshop

Fristående BoIS-webshop med medlemskap, gymförmån, matchställ och förberett supporter-/merchsortiment.

## Status
- P1 ordermotor: **COMPLETE**
- P2 produktionsförberedelse: **COMPLETE**
- P3 Commerce Core: **COMPLETE**
- P5 Match kit batching: **COMPLETE / LIVE STAGING VERIFIED**
- Betalning: **AVSTÄNGD**
- Extern mejlsändning: **AVSTÄNGD I STAGING**
- Ny extern driftkostnad: **0 kr**

## Aktiv staging
- Shop: https://alberiq.se/bois-shop-p3/
- Medlemskap + gym: https://alberiq.se/bois-shop-p3/membership.html
- Matchställ: https://alberiq.se/bois-shop-p3/match-kit.html
- Shopadmin / batchmotor: https://alberiq.se/bois-shop-p3/admin.html

Staging använder endast testuppgifter.

## Säljbara kategorier före 1 januari 2027
- medlemskap
- Nordic Wellness gymkort för medlem
- matchställ

Medlemspriser:
- ungdom 200 kr
- vuxen 350 kr
- pensionär 300 kr

Gymkort:
- 2 650 kr för aktiv BoIS-medlem

Matchställ:
- 998 kr är fortfarande **endast staging/testpris**
- verkligt pris/leverantörsdata krävs före skarp handel

## P5 batchregel
Matchställ kan endast batchas när ordern är markerad betald.

Startregel:
- **8 betalda matchställ** → automatisk batch
- **7 dagar** max väntetid → automatisk batch
- admin kan välja **Skicka batch nu**

P5 skapar:
- unikt batch-ID
- låsta batchrader/idempotens
- leverantörs-CSV
- SHA-256 av exakt CSV
- e-post-outbox
- retry med exponentiell väntetid
- historik/status i admin

I staging:
- mottagare tvingas till `example.invalid`
- `mail_transport = disabled`
- inget verkligt mejl skickas

## Commerce Core
MySQL med separata `bois_`-tabeller för produkter, order, medlemskap, betalning, leverantörer, fulfillment, batcher, outbox och eventlogg.

## 2027-sortiment
Dolda/ej beställningsbara fram till 1 januari 2027:
- BoIS 1941 Hoodie
- Supporter-T-shirt
- Bandyförälder Hoodie
- Mössa + halsduk
- BoIS Gym Pack
- Knatte Pack
- Presentkort

## Nästa utvecklingsordning
1. **P4 – Membership & Gym**: riktig medlemsstatus, eligibility och Nordic-aktivering.
2. **P6 – Payment**: Swish/kort + webhook; P5 triggas då av riktiga PAID-events.
3. **P7 – 2027 assortment**.
4. **P8 – production launch**.
5. **P9 – sales engine**.

## Kostnadsprincip
Inga nya betaltjänster, abonnemang eller externa kostnader aktiveras utan uttryckligt godkännande.
