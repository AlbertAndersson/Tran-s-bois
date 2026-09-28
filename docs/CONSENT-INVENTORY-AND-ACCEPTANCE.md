# C1–C4 inventering och acceptans – arbetsprotokoll

Datum 2026-09-28. Kodgranskning och webbläsarobservation gjord på staging. Slutlig produktionsinformation och gallring är blockerande lanseringsbeslut.

| Namn/resurs | Teknik/domän | Ändamål och kategori | Varaktighet |
| --- | --- | --- | --- |
| `boisConsent` | HttpOnly, Secure, SameSite=Lax-kaka, butikens domän; slumpmässigt värde, endast SHA-256-hash i BoIS-databasen | Nödvändigt underlag för valt ja/nej, policyversion och återkallelse | Konfigurerat 180 dagar i staging, kan sättas 1–365; rad spärras vid återkallelse eller policybyte |
| `boisSalesSession` | sessionStorage, butikens origin | Valfri P9-statistik; pseudonymt UUID som kan kopplas till order | Flikens session; rensas vid återkallelse |
| `boisSalesAttribution` | sessionStorage, butikens origin | Valfri P9-kampanj/referral och landningssida | Flikens session; rensas vid återkallelse |
| `boisP3Admin` | sessionStorage, butikens origin, endast admin/intern preview | Tillfällig administrativ åtkomst i staging | Flikens session eller utloggning |
| Föreningsmärke | Bild från `cdn06.svenskalag.se` | Extern resurs; leverantörens loggar/ev. kakor ännu inte verifierade | Utred före produktion |
| Webbserver | Simply/driftloggar | Separat driftbehandling, inte webbläsarens valfria lagring | Utred avtals- och gallringstid före produktion |
| Stripe | Ej aktiverat | Framtida Checkout-inventering, inklusive eventuell tredjepartslagring | Kvarstår före aktivering |

P9-databasen har `bois_sales_sessions`, `bois_sales_events` och `bois_sales_order_links`. Den sista gör sessions-ID:t orderkopplingsbart. Det är pseudonymt, inte anonymt. Databasens retention är ännu inte samma sak som sessionStorage-varaktigheten. Order- och betalningshistorik raderas inte vid återkallelse. Den slutliga gallringsplanen och ansvarig säljare måste beslutas före produktion. Stagingens 180 dagar valdes för att undvika täta omfrågningar under test, inte som ett generellt lagkrav.

## Teknisk acceptans

- Beslutstoken är 32 slumpbyte, endast hash i den additiva `bois_consent_choices`-tabellen. Servern kontrollerar version, utgång och revokering. JSON-fältet `consent` i order avser testregistrering och styr inte statistik.
- Ingen valfri P9-lagring läses eller skrivs förrän servern bekräftat både statistikval och teknisk stagingflagga. Nytt ja spelar inte upp tidigare sidor. Ett nej eller återkallelse aborterar köade fetch-anrop och rensar P9-nycklar. Direkta API-anrop kräver samma serververifierade cookie.
- Produktionsmätning är fortsatt globalt blockerad. Checkout och orderstatus är oberoende av statistikval; tekniska samtyckesfel ger statistik av.
- Isolerade tester omfattar inget val, avvisning, ja, återkallelse, gammal token, manipulerad token, policybyte, teknisk avstängning, blockerad webbläsarlagring och direkt API. CI och stagingresultat måste fyllas i efter körning; detta dokument påstår inte att de redan passerat.

## Verifieringsstatus

Rättningsdeploy före C1–C4: run `36463946785`, pinnad `fdb1053c69e929fa7430c74b9ea5a4e47cac31eb`, success. Kod- och Actions-logg verifierad. Direkt filhash/webbläsarkontroll av `common.js` återstår på grund av klientblockerad direkt resursnavigering. C1–C4-kod är ännu inte publicerad när detta protokoll skrevs. Den publikt nåbara stagingingången visade endast `noindex`; faktisk inloggningsspärr är inte bekräftad. Bredare demo är blockerad tills åtkomstskydd verifierats.
