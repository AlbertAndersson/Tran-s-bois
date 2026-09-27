# Tranås BoIS – beställningsportal

Fristående, kostnadssnål beställningsportal för Tranås BoIS matchställ.

## Status
- P1 ordermotor: **COMPLETE**
- P2 produktionsförberedelse: **TECH COMPLETE / BOIS DATA PENDING**
- AlberIQ staging: **VERIFIED**
- Betalning: **AVSTÄNGD**
- Ny extern driftkostnad: **0 kr**

## Nu finns
- Mobilvänlig beställning för föräldrar.
- Konfigurerbar laglista, beställningsperiod, produkt, storlekar och priser.
- Spelarnamn, nummer och personligt tryck i samma flöde.
- Unika ordernummer och säker orderstatuslänk.
- Digital avbeställning innan ordern gått vidare i processen.
- Ledarvy med sök, filter och statusflöde.
- Automatisk storlekssammanställning.
- Detaljerad CSV + aggregerad leverantörs-CSV.
- Produktionsutkast för personuppgifter och beställningsvillkor.
- Ingen lagerhantering.
- Ingen koppling till Intersport.
- Swish/kort först efter separat godkännande.

## Struktur
- `site/` – kundflöde, orderstatus, ledarvy och villkorsutkast.
- `site/catalog.js` – publik presentationskonfiguration för lag/sortiment.
- `server/` – PHP-API och filbaserad ordermotor.
- `tests/` – P1/P2 smoke-test.
- `docs/` – status och teknisk dokumentation.

## Demo
GitHub Pages publicerar endast `site/`. Pages-läget sparar inga orderuppgifter.

## Staging
P2-staging: https://alberiq.se/bois-bestallning-p1/

Runtime-konfiguration och orderdata ligger utanför webbroot. Staging ska endast användas med testuppgifter.

## Kvar före produktion
BoIS behöver bekräfta verkligt sortiment, priser, storlekar, leverantörskod/orderformat, beställningsperiod samt förenings-/kontaktuppgifter och slutliga villkor. Därefter kan produktionsdata läggas in utan ombyggnad.

## Kostnadsprincip
Inga betaltjänster, abonnemang eller externa kostnader får aktiveras utan uttryckligt godkännande.
