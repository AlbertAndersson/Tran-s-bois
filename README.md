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
- `tests/` – smoke-test för ordermotorn.
- `docs/` – status och teknisk dokumentation.

## Miljöer
**GitHub Pages** publicerar endast `site/`. Där sparas inga orderuppgifter och sidan fungerar som live-demo.

**P1 staging** använder samma frontend tillsammans med PHP-API:t. Staging ska endast användas med testuppgifter och har ingen betalning.

## Kostnadsprincip
Inga betaltjänster, abonnemang eller externa kostnader får aktiveras utan uttryckligt godkännande. P1 är byggd utan externa paket eller betalda tjänster.
