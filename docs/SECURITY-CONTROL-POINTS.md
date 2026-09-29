# Kontrollpunkter efter P9 – betalningsstart och spårningsstopp

Datum: 2026-09-28
Status: **MERGED / CI VERIFIED / STAGING VERIFIED**. PR #11 publicerades först i run `36463946785`, pinnad `fdb1053c69e929fa7430c74b9ea5a4e47cac31eb`. Slutlig kodref `b83cd40926c511b5b873a6fb652c43d5bb221053` publicerades och liveverifierades i Simply-run `36516255044` (success; job `109239084358`). Workflow på `main` `26910660686402251f542caa9cc7263218ea6303` var manuellt skyddad och pinnad till denna kod. P2–P9 + Security controls CI passerade för mobilrättningen (PR #17); de fem berörda workflowarna passerade även för sista browser-testjusteringen (PR #18, Security `36516093807`, P9 `36516093893`). Chromium headless testade 375, 390 och 1280 px, 10 skärmbilder i Actions-artifact `bois-p9-synthetic-browser-36516255044` (7 dagars retention). P8 är TECHNICALLY COMPLETE / NOT ACTIVATED. Ingen Stripe, riktig betalning, extern mejlsändning, extern analytics eller produktionsaktivering; ny extern kostnad 0 kr. Staging är publikt nåbar utan verifierat inloggningsskydd och får inte delas brett.

Utgångspunkt: `main` vid P9-closeout `3983c5e65726390cd59fcf65fe3d69000376030f`. Tidigare P9-stagingverifiering är run `36445454316`, med kodref `3219dba57fca1eb97b9d50022477131c8db2501b`. Den körningen verifierar inte automatiskt senare rättningar.

## Verifierad leverans

- PR #11 är mergead som `fdb1053c69e929fa7430c74b9ea5a4e47cac31eb`.
- Slutlig PR-head var `8f15c2c4fd21fe392b33de15a82edf9c1198a02b`; samtliga P2–P9-körningar samt den nya Security controls CI lyckades.
- Security controls CI: run `36460272204`, job `109056802128`, **success**. Jobbsteg och logg är lästa.
- Webbläsarkodens isolerade tester: **16 godkända, 0 underkända**. Detta är automatiserade JavaScript-/lagringstester, inte den ännu väntande visuella mobilgranskningen.
- P9 Sales Engine CI: `36460272250`, success.
- Övriga gröna körningar: P2 `36460272332`, P3 `36460272430`, P4 `36460272248`, P5 `36460272195`, P6 `36460272189`, P7 `36460272188`, P8 `36460272236`.
- Ingen Simply-deploy eller ändring av serverns runtime/secrets genomfördes i rättningsuppdraget. Ingen riktig betalning, extern mejlsändning eller ny betaltjänst aktiverades.

## Kontrollpunkt 1 – faktiskt stopp för nya Stripe-checkouts

Tidigare kontrollerade betalningsvägen konfigurerade Stripe-nycklar men inte det slutliga lanseringsgodkännandet.

Rättning:
- `bois_p8_stripe_checkout_allowed` skiljer behörighet att STARTA en betalning från providerberedskap att ta emot redan påbörjade betalningar.
- Produktion kräver `stripe_mode=live`, strikt boolean `production_launch_enabled=true`, giltig runtime och samtliga befintliga `ready_for_production_launch`-kontroller.
- Staging/test kan endast skapa Stripe TEST-checkouts med testnycklar; okänd miljö nekas. Den befintliga Simply-stagingen använder fortfarande mock och tomma Stripe-nycklar.
- Spärren körs i `bois_p8_stripe_checkout` före databasåtkomst samt i transportfunktionen före skapande av en Checkout Session.
- `checkout_enabled` i health visar om nya checkouts får startas. `payment_enabled` behåller sin providerbetydelse för att inte stoppa försenad betalningsbehandling.
- Signaturkontroll och behandling av redan påbörjade betalningar/återbetalningar kopplas inte till spärren för NYA köp. Stängning av shoppen får inte tappa giltiga försenade betalhändelser.

## Kontrollpunkt 2 – spårning av betyder inga nya sales-skrivningar

Tidigare var event-endpointen spärrad men orderkopplingen och webbläsarens sessionStorage kunde fortfarande användas.

Rättning:
- `bois_p9_capture_event` och `bois_p9_link_order` kräver separat, betrodd serverkonfiguration. Utelämnad konfiguration betyder av.
- Order-API:t skickar serverkonfigurationen och hoppar över attribution när den är avstängd. Klientfält kan inte öppna spärren.
- Alla tre sales-tabeller lämnas oförändrade när spårning är av; redan existerande länkar omattribueras inte heller i det läget.
- Webbläsaren kontrollerar health innan P9:s lagring läses/skrivs, events skickas eller attribution bifogas en order.
- Endast en pågående health-kontroll delas; godkännandet cachas inte mellan senare interaktioner.
- Saknat/ogiltigt svar eller timeout ger avstängd statistik. Blockerad webbläsarlagring får inte förhindra en vanlig order.
- Produktion är tills vidare hårt blockerad för spårning, även med flaggan true. Explicit syntetisk staging/test fungerar fortsatt. C1–C4 är nu implementerat i staging; produktionsmätning är fortsatt globalt blockerad till separat beslut.

## Testning

`Security controls CI` verifierade:
- nekad checkout före databas och providertransport,
- saknat/icke-boolean lanseringsgodkännande och fel miljö,
- bevarad signaturverifiering för befintliga betalningar,
- isolerade webbläsarkodtester av avstängd lagring, events och orderattribution,
- nät-/JSON-/HTTP-/timeout-fel samt blockerad lagring,
- MySQL-regression för P9 inklusive vanlig mockbetalning utan spårning,
- verkliga loopback-HTTP-anrop till API:t med avstängd, saknad och felaktig spårningskonfiguration,
- oförändrade betalposter vid blockerad HTTP-checkout.

Testerna använder syntetiska fixtures. Externa cURL-anrop och mail är avstängda i säkerhetstestprocesserna. CI använder isolerad MySQL, inte Simply-databasen. Ingen schemaändring eller ny extern tjänst behövs.

Kontrollen av sales-tabellernas kolumnnamn rättades även till en skiftlägesoberoende läsning med PDO::FETCH_COLUMN. Ett tomt eller ofullständigt metadataresultat underkänner testet i stället för att ge en falskt godkänd kolumnkontroll. Den slutliga säkerhetsloggen passerade utan de tidigare PHP-varningarna.

## Separat stagingverifiering – återstår

Den befintliga manuella workflowen `.github/workflows/simply-deploy-bois-p9-staging.yml` är förberedd i commit `6a3807cac566161e78052f8f82ce95a310dde438` och pinnad till rättningskoden `fdb1053c69e929fa7430c74b9ea5a4e47cac31eb`. Den har INTE startats i detta uppdrag.

Kör från `AlbertAndersson/Tran-s-bois`, branch `main`, med confirmation `DEPLOY_BOIS_P9_READY`. Kontrollera först att aktuell workflow fortfarande pekar på rättad kod. Bevara den manuella deploygrinden; bygg inte om den till automatisk push-deploy för att kringgå åtkomsten till workflow_dispatch.

Behåll mock, tomma Stripe-secrets, avstängd extern e-post och stängd production launch. Syntetisk staging-spårning ska fortsatt kunna användas. Workflowen kontrollerar även `checkout_enabled=true` för MOCK och strikta nuvarande privacy-fält i sales-svaret; frånvaro av ett fält ska inte ge falskt godkänt resultat.

Följ P4–P9-stagingacceptansen och dokumentera nytt run-ID/kodref först efter success. Kontrollera att den publicerade `common.js` innehåller spårningsspärren. De negativa produktionsfallen är verifierade i isolerad CI; lägg inte in riktiga Stripe-nycklar eller öppna produktion för att testa dem på Simply. Run `36516255044` verifierar den nya samtyckesversionen och publicerade filhashar; `36445454316` är historisk äldre P9-kod.

## Planen efter rättningen

Se `docs/COOKIES-AND-CONSENT-PLAN.md`. Kakor, sessionStorage/localStorage, återkallelse, information och kontroll på serversidan ska implementeras innan valfri mätning används för riktiga besökare. Kak-/samtyckeskomponenten är **IMPLEMENTED / CI VERIFIED / LIVE STAGING VERIFIED**. Produktionens godkännande och aktivering återstår.

De tre kvalitetsrundorna har nu separat syntetisk Chromiumacceptans i run `36516255044`; 375/390/1280 px och adminflöden passerade. Detta är inte fysisk iPhone- eller Safari-verifiering.

P8 förblir **TECHNICALLY COMPLETE / NOT ACTIVATED**. Personliga adminkonton/MFA, slutlig drift-/säkerhetsgranskning och juridiskt underlag är kvarvarande lanseringskrav, inte levererade av denna avgränsade rättning.
