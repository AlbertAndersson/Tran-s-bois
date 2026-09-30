# CURRENT STATUS

Datum: 2026-09-29

## Senaste verifierade läge

**PROTECTED STAGING / LIVE VERIFIED / READY FOR LIMITED SYNTHETIC DEMO**

Stagingens tidigare åtkomstblockerare är löst. BoIS-ägda run **36623915463** avslutades med **success** och samtliga jobbsteg passerade. Jobbsteg, faktisk logg och artifactmetadata har efterkontrollerats. Detta är verifierad testdrift, inte produktionslansering eller Eriks verksamhetsgodkännande.

| Referens | Värde |
| --- | --- |
| Repository för kod, CI, secrets och deployment | `AlbertAndersson/Tran-s-bois` |
| Workflow | `.github/workflows/simply-deploy-bois-p9-staging.yml` |
| Körning | [36623915463](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/36623915463) |
| Jobb | `109595921802` – `deploy`, success |
| Workflow/main vid körningen | `4918cef10bcc213ef9c756d407ca88d4990971fa` |
| Faktiskt utcheckad och publicerad applikationsref | `c49ebcd9af9f7081e1764d28419d92dd05b4fd48` |
| Avslutad körning | 2026-09-29, 20:08 UTC |

Senare dokumentationscommits ändrar inte den publicerade applikationsrefen. Verifiera alltid aktuell `main` före nästa ändring; återställ inte till ett historiskt SHA.

## Verifierat i den skyddade miljön

- HTTP Basic Auth installerat för hela `/bois-shop-p3/` över HTTPS. Det privata demo-secretet accepterades; lösenordet ska inte förekomma i dokumentationen.
- Obehöriga direkta HTML-, JS- och API-URL:er, inklusive shop, admin, order, payment, consent och query strings, gav 401. Obehöriga POST-anrop till orders, checkout, mockbetalning, consent och sales-event samt fel demoinloggning nekades.
- Behöriga P4–P9-kontroller passerade bakom inloggningen. SHA-256-jämförelse av `.htaccess`, `common.js`, `commerce-api.php`, `consent.php` och `cookies.html` passerade.
- Health verifierade P9, mock/testmode, first-party sales engine, `checkout_enabled=true` för mock, `sales_tracking_enabled=true` för syntetisk staging, extern analytics false och Stripe-/produktionsberedskap false. Statistik kräver dessutom besökarens serververifierade samtycke.
- Ingen statistik utan medgivande; ja → syntetisk attribution → order → signerad mock-PAID; återkallelse blockerade även återanvändning av tidigare samtyckeskaka.
- Medlemskap ACTIVE, Nordic ELIGIBLE/manuell medlemskontroll, matchställskö, syntetisk batch, betalningsfel/avbrott/nytt försök och simulerad refund passerade. P5:s 8/168h-regel och P7:s dolda/blockerade merch bevarades.
- Extern mejltransport var disabled. Antal och hash för listan över icke-BoIS-tabeller var oförändrade före/efter, antal 0. Det är inte en ny fullständig radbackup eller återställningsrevision.

## Webbläsaracceptans och bevis

Chromium headless på Linux testade **375, 390 och 1280 px**. Loggen rapporterade pass för köp utan val/efter nej, befintlig medlem med misslyckad/avbruten betalning och nytt försök, samtycke/attribution/återkallelse samt adminsektioner, medlemsverifiering, matchställ och mockrefund. Testerna kördes med Basic Auth och separat privat adminnyckel.

**10 PNG** sparades i artifact `bois-p9-synthetic-browser-36623915463`, ID `11059388459`, 1 229 695 byte. [Öppna artifacten](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/36623915463/artifacts/11059388459). Den uppgivna giltighetstiden är till **2026-10-06 20:08:02 UTC**. SHA-256 för ZIP: `5199fe10e718cee4de4823ef003fbb76a236ad4319857fe7252143a8bc553f65`. Någon permanent kopia av bilderna har inte skapats i dokumentationscloseouten.

Detta är automatiserad browseracceptans, inte ett test på fysisk iPhone/Safari eller ett redan genomfört godkännande av Erik/BoIS. Se `CONSENT-INVENTORY-AND-ACCEPTANCE.md` och `SYNTHETIC-DEMO.md`.

## Fasstatus och oförändrade gränser

- P1–P3: tekniskt levererade.
- P4–P7: COMPLETE / LIVE STAGING VERIFIED; P7 är inte kommersiellt lanserat.
- P8: **TECHNICALLY COMPLETE / NOT ACTIVATED**.
- P9 och C1–C4 samtycke: implementerat, CI-verifierat och åter verifierat i skyddad syntetisk staging.
- Demoåtkomst: **LIVE VERIFIED** för begränsat antal behöriga granskare. Basic Auth ersätter inte framtida personliga adminkonton, roller eller MFA.
- Stripe/KYC, riktiga betalningar/återbetalningar, extern analytics, annonsering, externa mejl/SMS och produktion: **INTE AKTIVERADE**.
- Ny extern kostnad i leveransen: **0 kr**. Den här closeouten ändrar endast dokumentation.

## Aktiv staging och driftansvar

Shop: https://alberiq.se/bois-shop-p3/

Medlemskap/Nordic: `membership.html`; matchställ: `match-kit.html`; orderstatus: `order.html`; mockbetalning: `payment.html`; admin: `admin.html`; samtyckesinformation: `cookies.html`; intern sortimentsvy: `assortment-preview.html`. Samtliga ligger bakom samma katalogskydd. API: `commerce-api.php`.

BoIS äger ensam kod, CI, secrets och deployment. Den dedikerade stagingdatabasen använder `BOIS_DB_*` i privat runtime. Ingen ny ändring av Work Capture eller databasflytt ingår. Skarp drift ska senare använda separat produktionsdatabas, skild även från BoIS staging. Byt inte adress eller driftupplägg utan beslut.

Demoanvändaren och rotation beskrivs i `STAGING-DEMO-ACCESS.md`. Dela lösenord privat, inte i repo eller Drive-handoff. Adminnyckeln är separat; demonstrationslösenordet är inte adminbehörighet.

## Adminåtkomst inför verksamhetsdemo

Den tidigare deploymodellen skapade en ny slumpmässig adminnyckel vid varje stagingdeploy. Det gjorde automatiserad adminacceptans möjlig men var opraktiskt för Erik/BoIS faktisk demo.

Workflow och admintext är nu ändrade för en stabil, separat stagingnyckel via Repository Secret `BOIS_STAGING_ADMIN_TOKEN`. Den är skild från Basic Auth-lösenordet, får inte exponeras i dokumentation och lagras i privat server-runtime. P9 CI bevakar att stagingworkflowen inte återgår till slumpgenererad adminnyckel.

**Viktigt:** run `36623915463` kör fortfarande med den gamla slumpgenererade nyckeln. Innan Erik provar admin ska det nya secretet läggas in och staging deployas/verifieras en gång till. Kundsidorna och Basic Auth i den nuvarande verifierade miljön påverkas inte av denna väntande adminrotation.

## Nästa steg – Erik/BoIS demo och återkoppling

När den stabila adminnyckeln är aktiverad genom en verifierad deploy: använd `SYNTHETIC-DEMO.md` för en begränsad genomgång med syntetiska uppgifter och `example.invalid`-adresser. Samla konkret återkoppling om kundresan, medlems-/Nordic-hanteringen, matchställskön, samtyckesval och begripligheten i admin.

Ingen ny generell utvecklingsfas, ombyggnad eller deploy behövs enbart för att denna dokumentation uppdateras. Prioritera eventuella påvisade fel och önskemål efter genomgången och fatta beslut om ett avgränsat nästa uppdrag. Fysisk iPhone/Safari kan provas manuellt vid demot; redovisa då den faktiska enheten och resultatet.

## Affärsregler och kvarvarande produktionskrav

Medlemskap: ungdom 200 kr, vuxen 350 kr, pensionär 300 kr. Nordic Wellness 2 650 kr för aktiv medlem. Stagingens medlemsperiod är 365 dagar; den skarpa perioden behöver bekräftas. Matchställ **998 kr är endast testpris**; verkliga priser, leverantör, SKU/storlekar och orderformat återstår.

Endast verifierad PAID får driva P4/P5. Befintlig medlem + gym kräver medlemsverifiering. Matchställ batchas vid 8 betalda ställ eller 168 timmar, alternativt via admin. Refund kräver manuell granskning av redan påbörjad fulfillment. Stopp för nya checkouts får inte tappa signerade besked för tidigare betalningar.

P7:s tre förslag – BoIS 1941 Hoodie, Supporter-T-shirt och Läktarmössa – är inte godkända eller orderbara. Priser är ESTIMATE och produktunika kostnader/SKU/marginaler TBD. Printful är endast kandidat. Ingen merch före 1 januari 2027 i Stockholmstid; även därefter krävs verifierad kommersiell data och uttryckligt produktgodkännande. P9-rekommendationer ger ingen rabatt.

Före skarp lansering återstår merchant/kontoägare/KYC, bank, godkända avgifter, Swish-access, domän, separat produktionsdatabas, villkor/integritet/refundpolicy, support och mejltransport, produkt-/partnerunderlag, personliga adminkonton/roller/MFA, missbruksskydd, backup/återställningsprov och slutlig säkerhetsgranskning. Produktionens statistik är fortsatt spärrad. Slutlig samtyckesinformation och gallring samt webbhotellets `sc_clearance`, externa bildresurser och framtida Stripe-konfiguration behöver bedömas före produktion. Ingen av dessa gränser öppnas av demogodkännandet.

## Historik – bevarad, inte nästa arbetsinstruktion

De tidigare fullständiga status- och handofftexterna från main `4918cef10bcc213ef9c756d407ca88d4990971fa` har bevarats oförändrade i `history/CURRENT_STATUS-before-protected-staging-20260929.md` och `history/WORK-HANDOFF-before-protected-staging-20260929.md`. Deras uppgifter om öppen staging och väntande åtkomstdeploy är historiska och ersätts av denna verifiering.

Viktiga revisionsreferenser: legacy-merge `36358028481`, P7-acceptans `36358111506`, cutover-kontroll `36358232965`, purge av exakt 18 äldre BoIS-tabeller `36394705592`, separationsdiagnos `36394944351` och återställd Work Capture-deploy `36403070406`. Privata rollback-backuper och övriga historiska spår har inte ändrats i closeouten. Historiska fasverifieringar: P6 `36327128975`, P7 `36355678030`, P8 `36414148818`, P9 `36445454316`, säkerhetsrättningsdeploy `36463946785` och tidigare öppna C1–C4/browserstaging `36517329717`. Den senaste skyddade stagingverifieringen är **36623915463**.
