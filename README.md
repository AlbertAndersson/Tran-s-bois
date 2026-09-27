# Tranås BoIS – beställningsportal

Fristående, kostnadssnål beställningsportal för Tranås BoIS matchställ.

## Mål
- Mobilvänlig beställning för föräldrar.
- Lag, storlek, spelarnamn och nummer i samma flöde.
- Automatisk orderlista per lag.
- Statusflöde och CSV-export till leverantör.
- Ingen lagerhantering.
- Ingen koppling till Intersport.
- Betalning med Swish/kort kopplas först efter separat godkännande.
- Så låg fast driftkostnad som möjligt.

## Struktur
- `site/` – kundflöde och ledarvy.
- `server/` – P1 PHP-API och filbaserad ordermotor.
- `tests/` – P1 smoke-test.
- `docs/` – status och teknisk dokumentation.

## Demo
GitHub Pages publicerar **endast `site/`**. I Pages-läget sparas inga orderuppgifter.

P1-staging använder samma frontend men konfigureras vid deploy till ett riktigt API. Staging ska endast användas med testuppgifter.

## Kostnadsprincip
Inga betaltjänster, abonnemang eller externa kostnader får aktiveras utan uttryckligt godkännande. P1 är byggd utan externa paket eller betalda tjänster.
