# Tranås BoIS – webshop

Fristående BoIS-webshop med medlemskap, gymförmån, matchställ och förberett supporter-/merchsortiment.

## Status
- P1 ordermotor: **COMPLETE**
- P2 produktionsförberedelse: **COMPLETE**
- P3 Commerce Core: **COMPLETE / MYSQL STAGING VERIFIED**
- Betalning: **AVSTÄNGD**
- Leverantörsutskick: **AVSTÄNGT**
- Ny extern driftkostnad: **0 kr**

## P3 staging
- Shop: https://alberiq.se/bois-shop-p3/
- Medlemskap + gym: https://alberiq.se/bois-shop-p3/membership.html
- Matchställ: https://alberiq.se/bois-shop-p3/match-kit.html
- Shopadmin: https://alberiq.se/bois-shop-p3/admin.html

Staging använder endast testuppgifter.

## Säljbara kategorier före 1 januari 2027
Bekräftat i arbetsmötet 2026-09-27:
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
- Commerce Core och batchmodell är byggda
- 998 kr är fortfarande **endast staging/testpris** och ska ersättas med verkligt pris före skarp handel

## Commerce Core
P3 använder MySQL och separata `bois_`-tabeller för:
- produkter och varianter
- kunder
- order och orderrader
- medlemskap
- betalningsposter
- leverantörer och fulfillment-regler
- batcher
- e-post-outbox
- eventlogg

Fulfillment-typer:
- `DIGITAL_MEMBERSHIP`
- `MEMBER_BENEFIT`
- `BATCH_SUPPLIER`
- `DIRECT_SUPPLIER`
- `DIGITAL_GIFT`

## 2027-sortiment
Följande finns förberedda som dolda produkter och är inte publikt säljbara före 1 januari 2027:
- BoIS 1941 Hoodie
- Supporter-T-shirt
- Bandyförälder Hoodie
- Mössa + halsduk
- BoIS Gym Pack
- Knatte Pack
- Presentkort

## Säkerhet och kostnad
P3-staging använder befintlig Simply/MySQL-infrastruktur med egna `bois_`-tabeller. Deployen verifierar att icke-BoIS-tabeller inte ändras.

Produktion ska få dedikerad BoIS-databas/credential innan riktiga kunduppgifter används.

Inga betaltjänster, abonnemang eller leverantörsutskick aktiveras utan uttryckligt godkännande.

## Nästa utvecklingsordning
1. **P5 – Match kit batching**: tröskel X, max väntetid, batch-ID, leverantörsunderlag, mail/outbox/retry.
2. **P4 – Membership & Gym**: riktig medlemsstatus och Nordic Wellness-eligibility/aktivering.
3. **P6 – Payment**: Swish/kort och webhook.
4. **P7 – 2027 assortment**.
5. **P8 – production launch**.
6. **P9 – sales engine**.
