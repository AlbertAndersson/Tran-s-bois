# C1–C4 inventering och acceptans – arbetsprotokoll

Datum 2026-09-29. Kodgranskning, isolerad CI och syntetisk Chromiumkontroll gjord på staging. Slutlig produktionsinformation och gallring är blockerande lanseringsbeslut.

| Namn/resurs | Teknik/domän | Ändamål och kategori | Varaktighet |
| --- | --- | --- | --- |
| `boisConsent` | HttpOnly, Secure, SameSite=Lax-kaka, butikens domän; slumpmässigt värde, endast SHA-256-hash i BoIS-databasen | Nödvändigt underlag för valt ja/nej, policyversion och återkallelse | Konfigurerat 180 dagar i staging, kan sättas 1–365; rad spärras vid återkallelse eller policybyte |
| `boisSalesSession` | sessionStorage, butikens origin | Valfri P9-statistik; pseudonymt UUID som kan kopplas till order | Flikens session; rensas vid återkallelse |
| `boisSalesAttribution` | sessionStorage, butikens origin | Valfri P9-kampanj/referral och landningssida | Flikens session; rensas vid återkallelse |
| `sc_clearance` | Kaka från webbhotell/skyddslager på stagingens domän, observerad i Chromium 375 px före val; uteblev i två andra rena kontexter | Teknikdrift/åtkomst; exakt funktion och ansvarig bekräftas med webbhotellet före produktion | Livslängd och inställningar ej verifierade; inventeras före produktion |
| `boisP3Admin` | sessionStorage, butikens origin, endast admin/intern preview | Tillfällig administrativ åtkomst i staging | Flikens session eller utloggning |
| Föreningsmärke | Bild från `cdn06.svenskalag.se` | Extern resurs; leverantörens loggar/ev. kakor ännu inte verifierade | Utred före produktion |
| Webbserver | Simply/driftloggar | Separat driftbehandling, inte webbläsarens valfria lagring | Utred avtals- och gallringstid före produktion |
| Stripe | Ej aktiverat | Framtida Checkout-inventering, inklusive eventuell tredjepartslagring | Kvarstår före aktivering |

Ingen annan localStorage-nyckel eller demolagring observerades i de rena kundkontexterna före val. Stagingens kundflöde behöver inte lokal demo-persistens.

P9-databasen har `bois_sales_sessions`, `bois_sales_events` och `bois_sales_order_links`. Den sista gör sessions-ID:t orderkopplingsbart. Det är pseudonymt, inte anonymt. Databasens retention är ännu inte samma sak som sessionStorage-varaktigheten. Order- och betalningshistorik raderas inte vid återkallelse. Den slutliga gallringsplanen och ansvarig säljare måste beslutas före produktion. Stagingens 180 dagar valdes för att undvika täta omfrågningar under test, inte som ett generellt lagkrav.

## Teknisk acceptans

- Beslutstoken är 32 slumpbyte, endast hash i den additiva `bois_consent_choices`-tabellen. Servern kontrollerar version, utgång och revokering. JSON-fältet `consent` i order avser testregistrering och styr inte statistik.
- Ingen valfri P9-lagring läses eller skrivs förrän servern bekräftat både statistikval och teknisk stagingflagga. Nytt ja spelar inte upp tidigare sidor. Ett nej eller återkallelse aborterar köade fetch-anrop och rensar P9-nycklar. Direkta API-anrop kräver samma serververifierade cookie.
- Produktionsmätning är fortsatt globalt blockerad. Checkout och orderstatus är oberoende av statistikval; tekniska samtyckesfel ger statistik av.
- Isolerade tester omfattar inget val, avvisning, ja, återkallelse, gammal token, manipulerad token, policybyte, teknisk avstängning, blockerad webbläsarlagring och direkt API. Isolerade Security controls CI och P9 CI passerade på PR #17 och #18. Slutlig stagingkörning `36516255044` passerade filhashjämförelse, API-acceptans och Chromium med 10 skärmbilder.

## Verifieringsstatus

- Första säkerhetsrättningsdeployen: run `36463946785`, source `fdb1053c69e929fa7430c74b9ea5a4e47cac31eb`, success.
- Slutlig staging: run `36516255044`, job `109239084358`, workflow-main `26910660686402251f542caa9cc7263218ea6303`, pinnad publicerad source `b83cd40926c511b5b873a6fb652c43d5bb221053`, success. SSH-hash för `common.js`, `commerce-api.php`, `consent.php` och `cookies.html` matchade payloaden. BoIS-databasen hade 0 främmande tabeller före/efter.
- PR #17: P2–P9 och Security controls success; Security `36471214350`, P9 `36471214438`. PR #18: berörda P2/P7/P8/P9/Security success; Security `36516093807`, P9 `36516093893`. Isolerade tester omfattar 21 JS-fall och PHP loopback/MySQL för ogiltigt, utgånget, policybyte, återkallat och direkt API.
- Chromium headless på Linux testade viewport 375, 390 och 1280 px. Före val: inga P9-nycklar i sessionStorage/localStorage; `sc_clearance` från webbhotellet observerades i ett av tre kontexter, extern bild från `cdn06.svenskalag.se`. Ingen fysisk iPhone, Safari eller annan motor testades.
- 375 px: köp utan val → signerad mock-PAID; därefter ja → syntetisk kampanjattribution → matchställ/PAID → återkallelse och rensade P9-nycklar. 390 px: nej, befintlig medlem + gym, nekad/avbruten mockbetalning, nytt försök → PAID och väntande medlemskontroll. 1280 px: layout och admin. Admin verifierade medlem, såg Nordic/matchställskö och kampanj, skapade syntetisk batch och registrerade simulerad refund.
- Skärmbilder: GitHub Actions-artifact `bois-p9-synthetic-browser-36516255044` (10 PNG, 7 dagars retention). Logg och manuellt prov på den publika sidan verifierade samtyckesvyn; orderstatus och mockbetalning fungerade också separat i molnwebbläsare.
- Kvarstående: slutlig produktionsinformation/retention och säljare, personliga adminkonton/roller/MFA, fysisk enhets-/Safari-kontroll om BoIS kräver den, samt faktisk åtkomstspärr för bredare stagingdemo. Staging kunde nås av oinloggad Actions-run; `noindex` är inte inloggningsskydd. Stripe-inventering görs först inför separat framtida aktivering. Ingen produktionsaktivering, extern analytics eller ny kostnad.
