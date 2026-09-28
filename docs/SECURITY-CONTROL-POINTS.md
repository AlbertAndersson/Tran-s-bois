# Kontrollpunkter efter P9 – betalningsstart och spårningsstopp

Datum: 2026-09-28
Status: **IMPLEMENTED / CI PENDING / STAGING NOT UPDATED**

Utgångspunkt: `main` vid P9-closeout `3983c5e65726390cd59fcf65fe3d69000376030f`. Tidigare P9-stagingverifiering är run `36445454316`, med kodref `3219dba57fca1eb97b9d50022477131c8db2501b`. Den körningen verifierar inte automatiskt senare rättningar.

## Kontrollpunkt 1 – faktiskt stopp för nya Stripe-checkouts

Tidigare kontrollerade betalningsvägen konfigurerade Stripe-nycklar men inte det slutliga lanseringsgodkännandet.

Rättning:
- `bois_p8_stripe_checkout_allowed` skiljer behörighet att STARTA en betalning från providerberedskap att ta emot redan påbörjade betalningar.
- Produktion kräver `stripe_mode=live`, strikt boolean `production_launch_enabled=true`, giltig runtime och samtliga befintliga `ready_for_production_launch`-kontroller.
- Staging/test kan endast skapa Stripe TEST-checkouts med testnycklar; okänd miljö nekas.
- Spärren körs i `bois_p8_stripe_checkout` före databasåtkomst samt i transportfunktionen före skapande av en Checkout Session.
- `checkout_enabled` i health visar om nya checkouts får startas. `payment_enabled` behåller sin providerbetydelse för att inte stoppa försenad settlement.
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
- Produktion är tills vidare hårt blockerad för spårning, även med flaggan true. Explicit syntetisk staging/test fungerar fortsatt. Framtida produktionssamtycke är separat, ännu inte implementerat.

## Testning

Ny `Security controls CI` kör:
- nekad checkout före databas och providertransport,
- saknat/icke-boolean lanseringsgodkännande och fel miljö,
- bevarad signaturverifiering för befintliga betalningar,
- webbläsartester av avstängd lagring, events och orderattribution,
- nät-/JSON-/HTTP-/timeout-fel samt blockerad lagring,
- MySQL-regression för P9 inklusive vanlig mockbetalning utan spårning,
- verkliga loopback-HTTP-anrop till API:t med avstängd, saknad och felaktig spårningskonfiguration,
- oförändrade betalposter vid blockerad HTTP-checkout.

Testerna använder syntetiska fixtures. Externa cURL-anrop och mail är avstängda i säkerhetstestprocesserna. CI använder isolerad MySQL, inte Simply-databasen. Ingen schemaändring eller ny extern tjänst behövs.

## Separat stagingverifiering

Efter grön CI och merge ska en godkänd BoIS-ägd stagingdeploy peka på rättad kodref. Bevara den manuella deploygrinden; bygg inte om den till automatisk push-deploy för att kringgå åtkomsten till workflow_dispatch. Behåll mock, tomma Stripe-secrets, avstängd extern e-post och stängd production launch.

Följ P4–P9-stagingacceptansen och dokumentera nytt run-ID/kodref. Tills dess står den tidigare P9-baselinen kvar som senast liveverifierad version.

## Planen efter rättningen

Se `docs/COOKIES-AND-CONSENT-PLAN.md`. Kakor, sessionStorage/localStorage, återkallelse, information och kontroll på serversidan ska implementeras innan produktionsmätning aktiveras. De tre godkända kvalitetsrundorna (mobil kundresa, Eriks adminarbete och syntetiskt demoflöde) ligger kvar och är inte automatiskt slutförda av dessa kontrolltester.

P8 förblir **TECHNICALLY COMPLETE / NOT ACTIVATED**. Personliga adminkonton/MFA, slutlig drift-/säkerhetsgranskning och juridiskt underlag är kvarvarande lanseringskrav, inte levererade av denna avgränsade rättning.
