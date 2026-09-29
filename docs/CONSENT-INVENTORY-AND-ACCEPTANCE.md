# C1–C4 inventering och acceptans – arbetsprotokoll

Datum 2026-09-29. **LIVE VERIFIED IN PROTECTED SYNTHETIC STAGING**. Senaste verifiering är run `36623915463`, job `109595921802`, success, med applikationsref `c49ebcd9af9f7081e1764d28419d92dd05b4fd48` och workflow/main `4918cef10bcc213ef9c756d407ca88d4990971fa`. Körningslogg, steg och artifactmetadata är efterkontrollerade. Slutlig produktionsinformation och gallring är fortfarande blockerande lanseringsbeslut.

| Namn/resurs | Teknik/domän | Ändamål och kategori | Varaktighet |
| --- | --- | --- | --- |
| `boisConsent` | HttpOnly, Secure, SameSite=Lax-kaka, butikens domän; slumpmässigt värde, endast SHA-256-hash i BoIS-databasen | Nödvändigt underlag för valt ja/nej, policyversion och återkallelse | Konfigurerat 180 dagar i staging, kan sättas 1–365; rad spärras vid återkallelse eller policybyte |
| `boisSalesSession` | sessionStorage, butikens origin | Valfri P9-statistik; pseudonymt UUID som kan kopplas till order | Flikens session; rensas vid återkallelse |
| `boisSalesAttribution` | sessionStorage, butikens origin | Valfri P9-kampanj/referral och landningssida | Flikens session; rensas vid återkallelse |
| `sc_clearance` | Kaka från webbhotell/skyddslager på stagingens domän; även i senaste run observerad vid 375 px före val, uteblev vid 390/1280 i rena kontexter | Teknikdrift/åtkomst; exakt funktion och ansvarig bekräftas med webbhotellet före produktion | Livslängd och inställningar ej verifierade; inventeras före produktion |
| `boisP3Admin` | sessionStorage, butikens origin, endast admin/intern preview | Tillfällig administrativ åtkomst i staging | Flikens session eller utloggning |
| Basic Auth | HTTP-autentisering över HTTPS för hela stagingkatalogen; separat från samtyckeskakan | Tillträde för behöriga demogranskare, inte statistikmedgivande eller personlig admininloggning | Credentials hanteras av webbläsaren; åtkomst dras tillbaka genom secretrotation och ny godkänd deploy |
| Föreningsmärke | Bild från `cdn06.svenskalag.se` | Extern resurs; leverantörens loggar/ev. kakor ännu inte verifierade | Utred före produktion |
| Webbserver | Simply/driftloggar | Separat driftbehandling, inte webbläsarens valfria lagring | Utred avtals- och gallringstid före produktion |
| Stripe | Ej aktiverat | Framtida Checkout-inventering, inklusive eventuell tredjepartslagring | Kvarstår före aktivering |

Ingen localStorage-nyckel eller P9-sessionStorage-nyckel observerades före val i senaste körningens rena kundkontexter. Stagingens kundflöde behöver inte lokal demo-persistens.

P9-databasen har `bois_sales_sessions`, `bois_sales_events` och `bois_sales_order_links`. Den sista gör sessions-ID:t orderkopplingsbart. Det är pseudonymt, inte anonymt. Databasens retention är ännu inte samma sak som sessionStorage-varaktigheten. Order- och betalningshistorik raderas inte vid återkallelse. Den slutliga gallringsplanen och ansvarig säljare måste beslutas före produktion. Stagingens 180 dagar valdes för att undvika täta omfrågningar under test, inte som ett generellt lagkrav.

## Teknisk acceptans

- Beslutstoken är 32 slumpbyte, endast hash i den additiva `bois_consent_choices`-tabellen. Servern kontrollerar version, utgång och revokering. JSON-fältet `consent` i order avser testregistrering och styr inte statistik.
- Ingen valfri P9-lagring läses eller skrivs förrän servern bekräftat både statistikval och teknisk stagingflagga. Nytt ja spelar inte upp tidigare sidor. Ett nej eller återkallelse aborterar köade fetch-anrop och rensar P9-nycklar. Direkta API-anrop kräver samma serververifierade cookie.
- Produktionsmätning är fortsatt globalt blockerad. Checkout och orderstatus är oberoende av statistikval; tekniska samtyckesfel ger statistik av.
- Isolerade tester omfattar inget val, avvisning, ja, återkallelse, gammal token, manipulerad token, policybyte, teknisk avstängning, blockerad webbläsarlagring och direkt API. De tidigare CI-bevisen redovisas nedan; livekörningen ska inte påstås testa varje isolerat negativt fall på Simply.

## Senaste verifiering: skyddad staging

Run [36623915463](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/36623915463), job `109595921802`, **success**. Appref `c49ebcd9af9f7081e1764d28419d92dd05b4fd48`; workflow/main `4918cef10bcc213ef9c756d407ca88d4990971fa`.

- Publicerade `.htaccess`, `common.js`, `commerce-api.php`, `consent.php` och `cookies.html` matchade payloadens filhashar. Antal och hash för listan över främmande tabeller var oförändrade, antal 0; ingen fullständig raddatarevision gjordes i denna kontroll.
- Obehöriga direkta kund-, admin-, payment-, order-, consent-, asset- och API-URL:er samt otillåtna POST-anrop gav 401. Fel Basic Auth-hemlighet nekades. Behöriga API-/Chromiumtester kördes bakom inloggningen och med separat privat adminnyckel där den krävdes.
- API: inget statistikmedgivande gav disabled; aktivt ja gav syntetisk attribution och order → signerad mock-PAID; återkallelse nekade statistik även med kopia av den tidigare kakans token.
- Chromium headless på Linux testade **375, 390 och 1280 px**. Loggen rapporterade pass för köp utan val/efter nej till mock-PAID, befintlig medlem med misslyckad/avbruten betalning och nytt försök, ja/attribution/återkallelse, adminsektioner, medlemsverifiering, matchställskö, syntetisk batch och simulerad refund. Ingen fysisk iPhone, Safari eller annan motor testades i körningen.
- Före val: inga P9-session-/localStorage-nycklar. `sc_clearance` sågs i 375-kontexten; `cdn06.svenskalag.se` var extern bildresurs i alla tre. Dessa kvarvarande produktionsinventeringar döljs inte av ett grönt testresultat.
- **10 PNG** i [artifact bois-p9-synthetic-browser-36623915463](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/36623915463/artifacts/11059388459), ID `11059388459`, till **2026-10-06 20:08:02 UTC**. ZIP-digest `5199fe10e718cee4de4823ef003fbb76a236ad4319857fe7252143a8bc553f65`. Ingen permanent bildarkivering eller ny manuell bildgranskning gjordes av dokumentationscloseouten.

Det tidigare hindret att staging saknade inloggningsskydd är löst. Begränsad demo med behöriga granskare och syntetiska uppgifter kan nu genomföras enligt `SYNTHETIC-DEMO.md`. Eriks/BoIS faktiska verksamhetsacceptans och återkoppling har inte genomförts genom det automatiserade testet.

## Historiska verifieringar – bevarade

- Första säkerhetsrättningsdeployen: run `36463946785`, source `fdb1053c69e929fa7430c74b9ea5a4e47cac31eb`, success.
- Run `36516255044` verifierade C1–C4 före den sista visuella PAID-rättningen.
- Tidigare öppna staging: `36517329717`, job `109242442693`, workflow-main `0933351896cf608a121d4cdeda7ce7e0c13b0052`, appref `e4ac4ce385fcf751460b4af208756d74e562d54b`, success. Filhashar, API och Chromium passerade innan Basic Auth infördes.
- PR #17: P2–P9 och Security controls success; Security `36471214350`, P9 `36471214438`. PR #20: P2–P9 och Security controls success; Security `36517184531`, P9 `36517184682`. PR #18: berörda P2/P7/P8/P9/Security success; Security `36516093807`, P9 `36516093893`. Isolerade tester omfattade 21 JS-fall och PHP loopback/MySQL för ogiltigt, utgånget, policybyte, återkallat och direkt API.
- Tidigare 375 px: köp utan val till mock-PAID, ja till kampanjattribution/matchställ/PAID och återkallelse. 390 px: nej, befintlig medlem + gym, nekad/avbruten betalning och nytt försök. 1280 px: layout/admin med medlemsverifiering, batch och mockrefund. PAID döljer nytt betalningsförsök. Ett separat tidigare molnwebbläsarprov verifierade ungdomsmedlemskap utan gym, obligatoriska fält, 200 kr, mock-PAID/ACTIVE utan statistikval. Det provet är inte en ny manuell kontroll av Basic Auth-versionen.
- Tidigare bilder: `bois-p9-synthetic-browser-36517329717`, 10 PNG, sju dagars retention. Den senaste körningens bilder ovan ersätter inte kravet att skilja olika refars testbevis.

## Kvar före produktion

Slutlig produktionsinformation, retention och säljare; personliga adminkonton/roller/MFA, missbruksskydd, backup/återställningsprov och slutlig säkerhetsgranskning; fysisk enhets-/Safari-kontroll om den ingår i BoIS acceptans; webbhotells- och bildresursinventering. Stripe inventeras först inför separat godkänd aktivering. Ingen produktionsaktivering, extern analytics, faktisk betalning eller ny kostnad har gjorts. P8 förblir **TECHNICALLY COMPLETE / NOT ACTIVATED**.
