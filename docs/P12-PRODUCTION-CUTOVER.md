# P12 – stängd produktionsmiljö och cutover-readiness

Hostingbeslut 2026-10-03: production ska ligga på den dedikerade Simply-produkten
`socen.se`, skild från stagingprodukten `alberiq.se`. Se `P12-SOCEN-HOSTING.md` för
backupkrav, credential boundaries och aktuell blockerare. Nedanstående historiska
P12-bevis kompletteras av faktisk privat SOCen-bootstrap/readiness i hostingrapporten.
Begränsade DB-konton och offentlig appverifiering återstår; P12 är fortsatt BLOCKED.

## Status

**BLOCKED – kod klar och verifierad; faktisk produktions-DB/credentials saknas.**

Baslinje main: `9d866c436543c0874061ca244915a3327b40041b`.
Produktionsverktygen är verifierade på isolerad MySQL i CI. Faktisk Simply-miljö kan
ännu inte verifieras: repository secrets för separat produktionsdatabas saknas.

GitHub Settings inventerades utan att läsa hemliga värden. `BOIS_DB_*`,
stagingadmin/demo och Stripe testsecrets finns. `BOIS_PROD_DB_HOST`,
`BOIS_PROD_DB_NAME`, `BOIS_PROD_DB_USER`, `BOIS_PROD_DB_PASSWORD` och
`BOIS_PROD_ADMIN_TOKEN` saknas. Inga environment secrets finns.
Ingen kostnadsfri andra databas eller nya credentials har provisionerats;
befintlig Simply-plans tillgängliga databasplatser/kostnad är inte verifierad.
Detta är en driftblockerare, inte en anledning att återanvända staging.

## Verifieringsreferenser

PR [#30](https://github.com/AlbertAndersson/Tran-s-bois/pull/30), slutlig kod-SHA
`7c90987bb75ee4a661ed48aed8fbdf1b081ae1bf`, merge
`b8590e4566f77ed4a2f630c256c2e2626cbe43d3`. Ingen Simply-appdeploy i P12.

| Kontroll på slutlig PR-head | Run | Resultat |
| --- | --- | --- |
| P12, isolerad MySQL/HTTP/P3–P9 | [37136439541](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37136439541), jobb 111241823291 | success |
| Security controls | 37136439556 | success |
| P2 / P3 / P4 | 37136439563 / 37136439555 / 37136439543 | success |
| P5 / P6 / P7 | 37136439542 / 37136439537 / 37136439535 | success |
| P8 / P9 | 37136439613 / 37136439544 | success |
| P12 push | 37136436493 | success |
| P12 merge-main | [37136549149](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37136549149) | success |
| Security merge-main | 37136549167 | success |

Merge-main P2/P3/P4/P5/P6/P8/P9: 37136549156 / 37136549115 /
37136549222 / 37136549132 / 37136549163 / 37136549166 / 37136549210,
alla success. P7 var success på PR-head; ingen ny P7 push-trigger på merge.
Lokalt: diff-check, YAML-parse och 21 browser-säkerhetstester passerade.
PHP/MySQL verifierades på CI-runner, inte i den lokala miljön.

P12-loggen bevisar fail-closed config, idempotent bootstrap med oförändrat schema
och alla radvärden efter andra körningen, SELECT-only readiness utan DB-ändring,
staging-sentinel/schema/rader oförändrade, separata DB-konton, HTTP 503 utan
skrivningar, nekad ensam launch-flagga, saknade beslut/secrets samt vägran vid
främmande tabell, kunddata och schema-drift. Inga transportfunktioner var tillåtna
under testerna. Detta är ingen P13 backup/restoreövning eller faktisk Simply-DB.

Manuell driftinventering på merge-main:
[37136579981](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37136579981),
jobb 111242229807, **failure vid secret-spärren**. Samtliga fem BOIS_PROD-secrets
rapporterades som saknade. Privat configbyggande och DB-readiness hoppades över;
ingen DB-anslutning, bootstrap eller deploy gjordes. Secretvärden exponerades inte
 och live Stripe-secrets lästes inte. Det är en verifierad blockerare, inte grön
produktionsreadiness.

Ett tidigare säkerhetstest förväntade order/health 200 i stängd produktion och
checkout 401. Det uppdaterades till P12:s starkare 503 och verifierar fortfarande
oförändrad order-, betal- och statistikdata. Slutlig security-run ovan passerade.

P12 återupptas när separat kostnadsfri produktionsdatabas och begränsade credentials
finns. Kör då runbookens host-bootstrap/readiness och registrera faktiska bevis innan
status DONE. P13 startas inte automatiskt medan P12 är blockerad.

## Implementerat

- `server/production-config.example.php`: separat privat runtime, mode production,
  versionsmarkör, launch/checkout/statistik/extern analytics/Swish false,
  betalprovider disabled, mailtransporter disabled och inga Stripe credentials.
- Produktionsverktygen kräver DB-identitet och DB-användare som skiljer sig från
  staging. Samma databasnamn nekas även om olika hostalias används. Ett separat
  konto ska hos Simply begränsas till endast produktionsdatabasen; klientkontrollen
  ersätter inte serverns grants.
- `ops/p12-bootstrap.php`: explicit CLI-bootstrap med DB-identitetskontroll,
  rådgivningslås, idempotenta P3–P9-migreringar och consent-schema. Slutmarkör skrivs
  efter migreringar. MySQL-DDL är inte transaktionell: vid avbrott stannar miljön
  stängd och körningen kan återupptas. Ingen down-migration eller radering ingår.
- Endast katalogförslag från repo seedas. Kund-/order-/betal-/consent-/statistikdata
  importeras aldrig. Alla produkter hålls osynliga och ej orderbara, eftersom
  stagingens priser och produktunderlag inte innebär produktionsgodkännande.
  P7-förslagen förblir ogodkända. Upprepad färdig bootstrap är en kontroll utan
  reseed; framtida godkända katalogändringar kräver egen migration.
- `ops/p12-readiness.php`: skrivskyddad MySQL-transaktion, exakt manifest med
  24 tabeller och åtta ledgerposter, kritiska ändrade kolumner, tomma affärstabeller,
  stängda funktioner och blockerad katalog. Inga secrets, radvärden eller
  databasnamn i resultatet. Fungerar med SELECT-only DB-konto.
- Stängd produktion svarar 503 på commerce-trafik före DB-anslutning. Produktions-
  API:t utför inte automatiska migreringar. Befintlig `payment_webhook` undantas
  från stoppet för nya köp för framtida signerade besked om tidigare betalningar.
  P12:s provider disabled gör den inte aktiv. Den separata sandbox-callbacken
  förblir sandbox-only.
- `ops/p12-launch-preflight.php`: kontrollerar en privat, separat föreslagen
  aktiveringskonfiguration utan att installera den, ringa Stripe eller aktivera
  något. Saknade secrets eller explicita beslut ger exit 1 före DB-anslutning.
  Godkännanden måste innehålla approved=true och en referens; verktyget kan inte
  intyga att referensen är ett faktiskt beslut. Operatören verifierar den mot
  P18/P19/P20. Det äldre workflowet fabricerar inte längre true-godkännanden eller
  läser live Stripe-secrets; det kontrollerar endast stängd readiness.

## Privata paths och behörigheter

Föreslagen Simply-miljö: `$HOME/.bois-production/config.php` och privata
releasekataloger `$HOME/.bois-production/releases/<SHA>/`. Kataloger 0700,
config 0600. Inget under public_html i P12. `BOIS_PUBLIC_ROOT` sätts till faktisk
webbrot vid kontroll. Config läses endast från explicit CLI-argument; ingen
fallback till `.bois-p3` eller sandboxconfig i dessa verktyg.

Obligatoriska P12-secrets: de fem `BOIS_PROD_*` ovan. `BOIS_PROD_DB_PORT` är
en variable, standard 3306. Produktionsadminsecret får inte vara stagingnyckeln
och ersätter inte P14:s personliga konton/MFA. Readiness kan lokalt använda en
annan privat config med ett separat SELECT-only konto. Bootstrap-kontot behöver
SELECT/INSERT/UPDATE/CREATE/ALTER och INDEX på endast den separata databasen;
varken global CREATE USER eller åtkomst till Work Capture/staging behövs.

## Stängd bootstrap och verifiering

1. Verifiera i Simply att en separat tom MySQL-databas och separat användare kan
   skapas inom befintlig plan utan extra kostnad. Stanna före beställning eller
   uppgradering. Om inte gratis: dokumentera databasplats, pris och planändring.
2. Skapa/konfigurera credentials privat och lägg dem i de angivna GitHub Secrets.
   Klistra aldrig lösenord i chat, repo, Drive, artifacts eller terminalutskrift.
3. Förbered privat release från aktuell grön main; kopiera server/, ops/ och data/
   dit. Utgå från production-config.example.php och fyll endast privata DB- och
   adminfält samt staging-identitet. Håll samtliga externa funktioner av.
4. Kör på Simply, med rätt privata paths:

   ```sh
   export BOIS_PUBLIC_ROOT="$HOME/public_html"
   php "$HOME/.bois-production/releases/<SHA>/ops/p12-bootstrap.php" "$HOME/.bois-production/config.php" BOOTSTRAP_CLOSED_BOIS_PRODUCTION
   php "$HOME/.bois-production/releases/<SHA>/ops/p12-readiness.php" "$HOME/.bois-production/config.php"
   ```

5. Kör bootstrap igen och verifiera oförändrat schema/ledger/katalog och tomma
   affärstabeller. Kör readiness med SELECT-only credentials. Kontrollera grants
   och att stagingens befintliga schema/data har bevarats. Spara endast booleska
   kontrollresultat och referenser, inga dataexporter i artifacts.
6. Kör det manuella workflowet `simply-validate-bois-p8-production.yml` med
   `VALIDATE_CLOSED_BOIS_PRODUCTION`. Det är en kontroll, ingen bootstrap/deploy.
   Om Simply nekar MySQL från GitHub-runner körs samma CLI på hosten; öppna inte
   databasen för Internet för att få kontrollen grön.

## Domän- och URL-strategi

P12 ändrar ingen DNS, publik domän, certifikat eller stagingadress. Slutlig
produktions-URL och samma origin beslutas av BoIS. `public_base_url` lämnas tom
före beslut. Staging `/bois-shop-p3/` och `/bois-shop-stripe-sandbox/` får aldrig
användas som produktions-URL. Readiness sker privat via CLI, utan kundtrafik.
Vid P20 får en separat publicerad release en wrapper med explicit privat config,
intern implementationsspärr och slutlig CSP/origin/HTTPS-konfiguration. Flytta
inte staging till produktion och återanvänd inte P11:s publika sandboxcallback.

## Cutover – först efter P18/P19 och uttryckligt P20-godkännande

1. Verifiera slutlig release-SHA, godkända affärsbeslut, P13 backup/restore,
   P14 personlig admin/MFA, P17 mail/avsändardomän och slutlig DNS/TLS.
2. Spara föregående release och privat config med behörigheter. Ta en P13-verifierad
   backup av endast BoIS produktions-DB. Registrera privat backup-ID och ledger.
3. Skapa en separat privat föreslagen aktiveringsconfig. Ändra inte den stängda
   kandidatconfigen. Live Stripe-secrets införs först med uttryckligt godkännande.
4. Kontrollera `production_decisions` för go_live, p18_release, backup_restore,
   personal_admin_mfa, seller_merchant_bank, legal_policies, product_partner_prices,
   membership_period, support_mail, domain_dns_tls, privacy_retention och
   final_smoke_rollback. Varje post behöver approved=true samt beslutsreferens.
5. Kör `p12-launch-preflight.php <closed-config> <proposed-config>`. Ett pass är
   ingen aktivering. Kontrollera faktiska beslutsreferenser och externa konton
   separat. Inga godkännanden får genereras av deploymentworkflow.
6. Godkända produkt-/partneruppgifter installeras genom en granskad migration;
   P7-grindar kvarstår. Cutover till publik release och config sker först i P20
   efter en uppdaterad preflight som också verifierar den godkända katalogen.
   P12-preflight avser den tomma stängda kandidaten, inte redan aktiv drift.
7. Verifiera hälsa, kund-/adminflöden, webhook, leverantör/outbox och launchgrind
   enligt P18/P20. Registrera tid, SHA och faktisk publicering. Starta inte cron
   eller externa transporter som en sidoeffekt av bootstrap.

## Rollback

- Före kundtrafik: stanna i stängd privat release. Behåll DB och ledger för
  felsökning. Radera inte DB, staging eller privata backuper. Återställ föregående
  kompatibla kod/config endast om ingen schemaändring kräver annan version.
- Efter launch: stäng nya orders/checkouts och externa workers. Behåll verifierade
  signerade besked för tidigare betalningar. Återställ föregående kompatibla release
  och privat config; återställ inte en gammal DB ovanpå nya betalningar/order.
- Vid schemafel: använd P13:s separata restoreövning och en granskad forward fix.
  MySQL-DDL kan redan vara committad. Avbryt vid okänd ledger/tabell/schema och
  bevara revisionsspår. Ingen automatisk DROP/DELETE eller blind down-migration.
- DNS ändras endast enligt beslutad P20-plan. Efter rollback verifieras DB-bindning,
  launchgrind, orders, payment-event-idempotens och externa transporter.

## Gränser

Live Stripe: nej. Riktiga pengar/refund: nej. Extern mail/SMS/analytics/annonser:
nej. Produktion aktiv: nej. Work Capture och staging orörda. Ny extern kostnad:
0 kr. P13–P20 startas inte av detta steg.
