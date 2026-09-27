# P1 – Ordermotor och ledarvy

## Syfte
P1 gör konceptdemon till en tekniskt fungerande beställningsportal utan att aktivera betalning eller nya löpande kostnader.

## Arkitektur
- `site/`: mobilanpassad kundvy och ledarvy.
- `server/api.php`: minimalt JSON-API i PHP utan externa paket.
- `server/bootstrap.php`: validering, orderlagring, statusflöde, rate limiting och auditlogg.
- Orderdata lagras som **en JSON-fil per order** i en privat katalog utanför `public_html`.
- Adminnyckeln finns endast i privat runtime-konfiguration på servern.
- GitHub Pages publicerar endast `site/` och fungerar som säker demo utan orderlagring.

## Varför filbaserad lagring?
Volymen för matchställ till knatte-/ungdomslag är låg. En fil per order ger:
- 0 kr i databaskostnad,
- inga DB-konton eller migrationsberoenden,
- atomiska skrivningar och enkel backup/export,
- möjlighet att migrera till databas senare utan att ändra frontendflödet.

Lösningen är avsiktligt dimensionerad för föreningens användningsfall, inte generell högvolym-e-handel.

## Orderstatus
- `received` – Ny
- `checked` – Kontrollerad
- `ready_for_supplier` – Klar för leverantör
- `ordered` – Beställd hos leverantör
- `cancelled` – Avbruten

Betalningsstatus är i P1 alltid `not_enabled`.

## Säkerhet
- runtime-konfiguration och orderdata utanför webbrot,
- admin-API kräver Bearer-token,
- adminnyckel sparas bara i `sessionStorage` i ledarvyn,
- servervalidering av all orderdata,
- priser beräknas server-side,
- rate limiting,
- honeypot mot enkla botar,
- origin-kontroll,
- `no-store` på API-svar,
- auditlogg för order- och statusändringar,
- staging får endast innehålla testuppgifter.

## P1 acceptance
- skapa order och få unikt ordernummer,
- idempotent orderregistrering,
- lista order i ledarvyn,
- filtrera och söka,
- ändra status,
- CSV-export,
- mobilvänlig frontend,
- ingen betalning aktiverad,
- inga nya externa abonnemang eller löpande kostnader.
