# CURRENT STATUS

Datum: 2026-09-27

## Status
**P1: COMPLETE**  
**P2 – produktionsförberedelse: TECH COMPLETE / BOIS DATA PENDING**  
**P2 staging: VERIFIED**

## Adresser
- Kund/staging: https://alberiq.se/bois-bestallning-p1/
- Ledarvy: https://alberiq.se/bois-bestallning-p1/admin.html
- Orderstatus: https://alberiq.se/bois-bestallning-p1/order.html
- Personuppgiftsutkast: https://alberiq.se/bois-bestallning-p1/privacy.html
- Villkorsutkast: https://alberiq.se/bois-bestallning-p1/terms.html
- Publik no-data-demo: https://albertandersson.github.io/Tran-s-bois/

## P2 levererat
- Konfigurerbar laglista, beställningsperiod, produkt, storlekar och priser.
- Order schema version 2 med produkt-, period- och leverantörsmetadata.
- Servervalidering av lag, storlek, produkt, period och tröjnummer 1–99.
- Unika ordernummer och idempotent registrering.
- Säker publik orderstatus via ordernummer + slumpad token.
- Digital avbeställning medan ordern är Ny/Kontrollerad.
- Statusflöde: Ny → Kontrollerad → Klar för leverantör → Beställd / Avbruten.
- Ledarvy med sök, lagfilter och statusfilter.
- Automatisk leverantörssammanställning per lag/produkt/storlek.
- Detaljerad CSV-export.
- Aggregerad leverantörs-CSV.
- Produktionsutkast för GDPR/personuppgifter och beställningsvillkor.
- Betalning fortsatt avstängd.

## Verifiering
GitHub CI:
- PHP syntax: pass
- P1 smoke: pass
- P2 smoke: pass
- JavaScript syntax: pass
- P2 artifact/security checks: pass
- GitHub Pages deploy: pass

AlberIQ/Simply end-to-end:
- schema version 2: pass
- health + skrivbar privat lagring: pass
- kundsida/admin/orderstatus/villkorssidor: pass
- skapa testorder: pass
- publik orderstatus: pass
- digital avbeställning: pass
- admin list orders: pass
- statusändring: pass
- detaljerad CSV: pass
- sammanställnings-CSV: pass
- runtime-konfiguration/orderdata utanför webbroot: pass

## Säkerhet
- Runtime-konfiguration och orderdata ligger utanför webbroot.
- Adminnyckel genereras vid deploy och finns inte i publikt repo.
- Servern räknar pris och validerar beställningen.
- Rate limiting, honeypot och auditlogg.
- Publik orderstatus kräver en 128-bitars slumpad token.
- GitHub Pages sparar inga personuppgifter.
- Staging är endast avsedd för testuppgifter.

## Kostnad
**Ny extern kostnad: 0 kr.**

Inga betaltjänster eller nya abonnemang har aktiverats. P2 använder befintlig AlberIQ/Simply-hosting och GitHub.

## Kvar före skarp lansering
1. Erik/BoIS bekräftar verkliga produkter, priser och storlekar.
2. BoIS/leverantören bekräftar artikel-/leverantörskoder och önskat orderformat.
3. BoIS bekräftar vilka lag som ska vara öppna och sista beställningsdag.
4. Föreningens fullständiga säljar-/kontaktuppgifter, rättslig grund, lagringstid och personuppgiftsupplägg fylls i.
5. Leverans/utlämning, reklamation och slutlig regel för personligt tryck/ångerrätt fastställs.
6. Slutlig domän/subdomän beslutas.
7. Först efter uttryckligt godkännande kopplas Swish/kort och betalstatus.

## Drift
Den gamla P1-deployen är pensionerad efter verifierad P2-deploy. DMA Motor innehåller inte längre BoIS-sidan eller dess deployflöde.
