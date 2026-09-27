# P1 – Ordermotor och ledarvy

## Syfte
P1 gör konceptdemon till en tekniskt fungerande beställningsportal utan att aktivera betalning eller nya löpande kostnader.

## Arkitektur
- `site/`: statisk mobilanpassad frontend.
- `server/api.php`: minimalt JSON-API i PHP utan externa ramverk eller paket.
- `server/bootstrap.php`: validering, orderlagring, statusflöde, rate limiting och audit-logg.
- Orderdata lagras som **en JSON-fil per order** i en privat katalog utanför `public_html`.
- Adminnyckeln lagras endast i privat runtime-konfiguration på servern.
- GitHub Pages publicerar bara `site/`; serverkoden och runtime-hemligheter publiceras inte där.

## Varför filbaserad lagring?
Volymen för ett knatte-/ungdomslags matchställ är låg. En fil per order ger:
- 0 kr i databaskostnad,
- inga DB-konton eller migrationsberoenden,
- atomiska skrivningar och enkel backup/export,
- möjlighet att migrera till databas senare utan att ändra frontendflödet.

Detta är avsiktligt dimensionerat för föreningens användningsfall och ska inte användas som generell högvolym-e-handel.

## Orderstatus
- `received` – Ny
- `checked` – Kontrollerad
- `ready_for_supplier` – Klar för leverantör
- `ordered` – Beställd hos leverantör
- `cancelled` – Avbruten

Betalningsstatus är i P1 alltid `not_enabled`.

## Säkerhet P1
- runtime-konfiguration och orderdata utanför webbrot,
- admin-API kräver Bearer-token,
- token sparas bara i `sessionStorage` i ledarvyn,
- servervalidering av all orderdata,
- pris beräknas server-side,
- enkel rate limiting,
- honeypot mot botar,
- origin-kontroll,
- no-store på API-svar,
- audit-logg för order- och statusändringar,
- staging ska endast innehålla testuppgifter.

## P1 acceptance
- skapa order och få unikt ordernummer,
- idempotent orderregistrering,
- lista order i ledarvyn,
- filtrera/söka,
- ändra status,
- CSV-export,
- mobilvänlig frontend,
- inga betalningar,
- inga nya externa abonnemang.
