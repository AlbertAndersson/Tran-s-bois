# Tranås BoIS – beställningsportal

Fristående, kostnadssnål beställningsportal för Tranås BoIS matchställ.

## Status
- P1 ordermotor: COMPLETE / STAGING VERIFIED
- P2 produktionsförberedelse: under utveckling
- Betalning: AVSTÄNGD
- Ny extern driftkostnad: 0 kr

## Mål
- Mobilvänlig beställning för föräldrar.
- Lag, storlek, spelarnamn och nummer i samma flöde.
- Automatisk orderlista per lag.
- Statusflöde och leverantörsexport.
- Automatisk sammanställning per lag/storlek.
- Ingen lagerhantering.
- Ingen koppling till Intersport.
- Swish/kort först efter separat godkännande.
- Så låg fast driftkostnad som möjligt.

## Struktur
- site/ – kundflöde, orderstatus, ledarvy och P2-villkorsutkast.
- site/catalog.js – publik presentationskonfiguration för lag/sortiment.
- server/ – PHP-API och filbaserad ordermotor.
- tests/ – P1/P2 smoke-test.
- docs/ – status och teknisk dokumentation.

## Demo
GitHub Pages publicerar endast site/. Pages-läget sparar inga orderuppgifter.

## Staging
P2-staging ligger under AlberIQ:s befintliga Simply-hosting. Runtime-konfiguration och orderdata ska ligga utanför webbroot. Staging ska endast användas med testuppgifter.

## Kostnadsprincip
Inga betaltjänster, abonnemang eller externa kostnader får aktiveras utan uttryckligt godkännande.
