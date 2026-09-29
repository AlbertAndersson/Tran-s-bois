# Kontrollpunkter efter P9 – betalningsstart och spårningsstopp

Datum: 2026-09-29
Status: **MERGED / CI VERIFIED / PROTECTED STAGING VERIFIED**.

Skyddad staging är nu verifierad i run **36623915463**, job `109595921802`, success, med publicerad appref `c49ebcd9af9f7081e1764d28419d92dd05b4fd48` och workflow/main `4918cef10bcc213ef9c756d407ca88d4990971fa`. Det katalogomfattande Basic Auth-skyddet från PR #22 är installerat och obehöriga direkta URL:er/POST-anrop samt fel demoinloggning nekades med 401. Behöriga P4–P9- och Chromiumflöden passerade. Se `STAGING-DEMO-ACCESS.md` för faktiskt verifierad omfattning och hemlighetshantering.

PR #11:s kontrollrättningar publicerades först i run `36463946785` med `fdb1053c69e929fa7430c74b9ea5a4e47cac31eb`. C1–C4/browserversionen före åtkomstskydd verifierades i `36517329717`, appref `e4ac4ce385fcf751460b4af208756d74e562d54b`. Dessa är historiska referenser; tidigare instruktioner om att PR #11 eller Basic Auth ännu inte publicerats är avslutade.

P8 förblir **TECHNICALLY COMPLETE / NOT ACTIVATED**. Ingen riktig Stripe, betalning, extern mejlsändning/analytics eller produktion har aktiverats. Ny extern kostnad i leveransen är 0 kr. Dokumentationscloseouten ändrar inte kod, runtime, databas eller hemligheter.

## Ursprunglig kontrollleverans och CI-bevis

Utgångspunkt var P9-closeout `3983c5e65726390cd59fcf65fe3d69000376030f`; äldre P9-run `36445454316` verifierade appref `3219dba57fca1eb97b9d50022477131c8db2501b`, inte automatiskt senare rättningar.

- PR #11 merge: `fdb1053c69e929fa7430c74b9ea5a4e47cac31eb`.
- Slutlig PR-head: `8f15c2c4fd21fe392b33de15a82edf9c1198a02b`; P2–P9 och Security controls CI success.
- Security controls CI: `36460272204`, job `109056802128`, success. 16 isolerade JavaScript-/lagringstester, PHP-kontroller och loopback-HTTP/MySQL. Dessa var inte visuella fysiska mobiltester.
- P9: `36460272250`. Övriga gröna körningar: P2 `36460272332`, P3 `36460272430`, P4 `36460272248`, P5 `36460272195`, P6 `36460272189`, P7 `36460272188`, P8 `36460272236`.
- Det ursprungliga kodrättningsuppdraget ändrade inte Simply-runtime; publicering och senare samtyckes-/åtkomstverifieringar gjordes därefter i de separat dokumenterade körningarna.

## Kontrollpunkt 1 – faktiskt stopp för nya Stripe-checkouts

Tidigare kontrollerade betalningsvägen konfigurerade Stripe-nycklar men inte det slutliga lanseringsgodkännandet.

Rättning:
- `bois_p8_stripe_checkout_allowed` skiljer behörighet att STARTA en betalning från providerberedskap att ta emot redan påbörjade betalningar.
- Produktion kräver `stripe_mode=live`, strikt boolean `production_launch_enabled=true`, giltig runtime och samtliga befintliga `ready_for_production_launch`-kontroller.
- Staging/test kan endast skapa Stripe TEST-checkouts med testnycklar; okänd miljö nekas. Simply-staging använder fortfarande mock och tomma Stripe-nycklar.
- Spärren körs i `bois_p8_stripe_checkout` före databasåtkomst samt i transportfunktionen före skapande av en Checkout Session.
- `checkout_enabled` i health visar om nya checkouts får startas. `payment_enabled` behåller sin providerbetydelse för att inte stoppa försenad betalningsbehandling.
- Signaturkontroll och behandling av redan påbörjade betalningar/återbetalningar kopplas inte till spärren för NYA köp. Stängning för nya köp får inte tappa giltiga försenade betalhändelser.

## Kontrollpunkt 2 – spårning av betyder inga nya sales-skrivningar

Tidigare var event-endpointen spärrad men orderkopplingen och webbläsarens sessionStorage kunde fortfarande användas.

Rättning:
- `bois_p9_capture_event` och `bois_p9_link_order` kräver separat, betrodd serverkonfiguration. Utelämnad konfiguration betyder av.
- Order-API:t skickar serverkonfigurationen och hoppar över attribution när den är avstängd. Klientfält kan inte öppna spärren.
- Alla tre sales-tabeller lämnas oförändrade när spårning är av; befintliga länkar omattribueras inte heller då.
- Saknat/ogiltigt svar eller timeout ger avstängd statistik. Blockerad webbläsarlagring får inte förhindra en vanlig order.
- Produktionsspårning är fortsatt hårt blockerad. Efter C1–C4 kräver även syntetisk staging både teknisk tillåtelse och giltigt serververifierat statistikval. Webbläsaren kontrollerar detta innan P9-lagring, events eller orderattribution. Se `CONSENT-INVENTORY-AND-ACCEPTANCE.md` för nuvarande samtyckesflöde, giltighet och revokering.

## Testning

Security controls CI verifierade nekad checkout före databas/providertransport, saknat/icke-boolean lanseringsgodkännande och fel miljö, bevarad signaturverifiering för befintliga betalningar, avstängd lagring/events/orderattribution, nät-/JSON-/HTTP-/timeout-fel och blockerad lagring samt P9/MySQL med vanlig mockbetalning utan statistik. Loopback-HTTP verifierade avstängd/saknad/felkonfigurerad spårning och oförändrade betalposter vid blockerad checkout.

Tester använder syntetiska fixtures och isolerad MySQL, inte Simply-databasen. Externa cURL-anrop och mail var avstängda i säkerhetstestprocesserna. Kontrollrättningen krävde ingen schemaändring; den senare samtyckesfunktionen lade separat till `bois_consent_choices`.

Sales-kolumnkontrollen rättades till skiftlägesoberoende läsning med PDO::FETCH_COLUMN. Tomt/ofullständigt metadataresultat underkänner testet. Den slutliga ursprungliga säkerhetsloggen passerade utan tidigare PHP-varningar.

## Genomförd staging- och browserverifiering

Den manuellt skyddade BoIS-workflowen har senare åter verifierat rättningarna, C1–C4 och P4–P9. Senaste skyddade run **36623915463** verifierade relevanta publicerade filhashar, direktåtkomstskydd och syntetisk API-/browseracceptans. Negativa produktions-/Stripefall har inte testats genom att öppna produktion eller installera riktiga nycklar på Simply; de behåller sina isolerade CI-bevis.

Chromium headless på Linux testade **375/390/1280 px**. 10 PNG i `bois-p9-synthetic-browser-36623915463`, artifact ID `11059388459`, till 2026-10-06 20:08:02 UTC. Detta är inte fysisk iPhone-/Safari-verifiering eller ett faktiskt godkännande av Erik/BoIS.

Spara manualgrinden och korrekt appref vid eventuella framtida ändringar. En dokumentationscommit kräver ingen ny stagingdeploy. Kör inte om äldre pin på `fdb1053c...` som om den innehöll den senare samtyckes-/Basic Auth-versionen.

## Nästa steg och kvarvarande lanseringskrav

Kontrollrättningar, samtyckesimplementation och skyddad syntetisk demo är tekniskt verifierade. Nästa steg är Eriks/BoIS faktiska genomgång enligt `SYNTHETIC-DEMO.md` och en prioritering utifrån konkret feedback, inte ny generell utveckling.

Produktionens godkännande och aktivering återstår. Personliga adminkonton/roller/MFA, missbruksskydd, backup/återställningsprov, slutlig säkerhetsgranskning och juridiskt/driftmässigt underlag är separata lanseringskrav. Basic Auth för demo ersätter dem inte. P8 förblir **TECHNICALLY COMPLETE / NOT ACTIVATED**.
