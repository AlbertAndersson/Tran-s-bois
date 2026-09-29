# Kakor, webbläsarlagring och samtycke – plan inför lansering

Datum: 2026-09-28
Status: **IMPLEMENTED / CI VERIFIED / LIVE STAGING VERIFIED / PRODUCTION ANALYTICS BLOCKED**. Slutlig kodref `b83cd40926c511b5b873a6fb652c43d5bb221053` publicerades och liveverifierades i Simply-run `36516255044` (success; job `109239084358`). Workflow på `main` `26910660686402251f542caa9cc7263218ea6303` var manuellt skyddad och pinnad till denna kod. P2–P9 + Security controls CI passerade för mobilrättningen (PR #17); de fem berörda workflowarna passerade även för sista browser-testjusteringen (PR #18, Security `36516093807`, P9 `36516093893`). Chromium headless testade 375, 390 och 1280 px, 10 skärmbilder i Actions-artifact `bois-p9-synthetic-browser-36516255044` (7 dagars retention). P8 är TECHNICALLY COMPLETE / NOT ACTIVATED. Ingen Stripe, riktig betalning, extern mejlsändning, extern analytics eller produktionsaktivering; ny extern kostnad 0 kr. Staging är publikt nåbar utan verifierat inloggningsskydd och får inte delas brett. Ursprunglig plan nedan bevaras som historisk kravbild; aktuellt genomförande och inventering finns i `docs/CONSENT-INVENTORY-AND-ACCEPTANCE.md`.

## Beslut och avgränsning

Albert har bett att kakhantering läggs in i planen, men att identifierade säkerhetskontroller rättas först. Den ursprungliga säkerhetsrättningen föregick C1–C4. Samtyckeskomponenten är nu publicerad i syntetisk staging; produktionsspårning är inte aktiverad. Inget CMP-abonnemang, annonsering eller extern analystjänst köps.

Ordningen är:
1. Rätta och testa produktionsspärren för nya Stripe-checkouts samt spårningsspärren i server och webbläsare. Se `docs/SECURITY-CONTROL-POINTS.md`.
2. Verifiera den rättade koden i kostnadsfri BoIS-staging med mockbetalning och syntetiska data.
3. Implementera och testa nedanstående samtyckeshantering innan valfri mätning används för riktiga besökare.
4. Genomför de redan godkända kvalitetsrundorna: kundresan på mobil, Eriks administrativa arbetsflöden och ett sammanhållet syntetiskt demo-/acceptanspaket. Dessa ska inte rapporteras som genomförda innan de faktiskt verifierats.
5. Gör separat produktionsverifiering först när merchant, bank, domän, juridiskt underlag, drift och lanseringsbeslut är klara.

## Princip

Kakor som är strikt nödvändiga för en tjänst användaren begär kan omfattas av undantag från samtycke. Statistik blir inte nödvändig bara för att den är nyttig för BoIS. Information om användningen krävs även när bara nödvändig lagring används. För valfria ändamål ska användaren kunna acceptera och avvisa lika enkelt, i samma första vy, och kunna återkalla valet. Dessa krav följer PTS vägledning [1].

Inventeringen ska omfatta kakor OCH liknande lagring/åtkomst, inklusive sessionStorage och localStorage. Teknikbyte är inte en genväg runt bedömningen; EDPB:s riktlinjer behandlar den tekniska räckvidden för artikel 5.3 [2]. Vid personuppgiftsbehandling ska rättslig grund, information och lagringstid bedömas separat enligt GDPR; samtycke måste vara frivilligt och kunna återkallas [3].

BoIS arbetsbedömning: P9:s besöksmätning och UTM/referral-attribution är valfri statistik. Den ska inte köras i produktion före fungerande samtyckeshantering och godkänd information. Själva beställningen, betalningssäkerheten och medlemsadministrationen ska kunna fungera utan statistikmedgivande. Beställningsgodkännande är INTE ett samtycke till spårning.

## C1 – inventera faktisk lagring och externa anrop

Bekräftat i `site/commerce/assets/common.js`:
- `boisSalesSession`: pseudonymt sessions-ID i sessionStorage för P9-mätning; valfri statistik.
- `boisSalesAttribution`: UTM/referral/landningssida i sessionStorage; valfri statistik.

Inventeringen finns i acceptansprotokollet. Stripe Checkout och leverantörers faktiska lagring kvarstår före produktionsaktivering. Anta inte att alla Stripe- eller tredjepartskakor är nödvändiga. Dokumentera för varje post: namn, teknik, domän/ansvarig, exakt ändamål, data, livslängd, åtkomst, kategori och bedömning. Inspektera Set-Cookie och lagring före val, efter ja, efter nej och efter återkallelse i riktiga webbläsare.

Sessionsdata är pseudonym och kan internt länkas till order; beskriv den inte som anonym. Separera webbläsarens lagringstid från retention i sales-tabeller och från webbserverns driftloggar.

## C2 – egen kostnadsfri samtyckeskomponent

- Första vyn erbjuder likvärdiga knappar för att acceptera respektive avvisa valfri lagring. Det ska också gå att anpassa valet per faktiskt ändamål.
- Statistik är av som standard. Inga förkryssade val, tyst accept vid fortsatt surf eller koppling till köp-/medlemsvillkor.
- Köp, mockbetalning, senare riktig betalning och orderstatus ska fungera vid nej eller inget val.
- Permanent länk `Kakinställningar` på alla kundsidor öppnar samma val igen.
- Tillgänglig hantering med tangentbord, skärmläsare, tydlig fokusförflyttning och fungerande mobilvy.
- Spara endast nödvändigt underlag för valet: version, kategorier, tidpunkt och beslutad giltighetstid. Spara inte IP-adress eller extra kundidentifierare enbart för detta.
- En dokumenterad vald giltighetstid ska beslutas; sätt inte ett godtyckligt antal månader och påstå att det är ett generellt lagkrav.
- En separat kak-/lagringssida beskriver inventeringen, leverantörer, ändamål, varaktighet, återkallelse och ansvarig.
- Om lanseringen endast använder strikt nödvändig lagring behövs inte en banner som ber om samtycke till denna; lämna ändå korrekt information. Detta måste grundas på faktisk inventering.

## C3 – verkställ valet i både klient och server

Framtida villkor för produktionsmätning ska vara: uttrycklig teknisk aktivering OCH giltigt, aktuellt statistikmedgivande. Saknat, avvisat, utgånget, återkallat eller ogiltigt medgivande betyder av.

Före ja: inga läsningar/skrivningar av P9-nycklar, inga statistik-ID:n, inga sales-event och ingen attribution på order. Ingen efterhandsåterspelning av surfning som inträffade innan samtycket.

Servern måste kontrollera sitt eget samtyckesunderlag även på direkta API-anrop. Ett fritt JSON-fält `consent=true` eller en manipulerad frontendflagga får inte öppna spårning. Utforma ett versionsbundet, tidsbegränsat och verifierbart samtyckesunderlag; minimera datamängd och dokumentera verifieringen. Den tekniska lösningen får inte marknadsföras som bevis för frivilligt mänskligt samtycke i sig.

Vid återkallelse: stoppa nya events, avbryt eventuell väntande statistik, rensa valfria P9-nycklar och stoppa nya orderkopplingar. Hantering av redan insamlade statistikdata och retention ska vara dokumenterad. Radera inte order-/betalningshistorik automatiskt bara för att statistikmedgivandet återkallas; den behandlingen har ett annat ändamål.

Under tiden är produktionsspårning hårt spärrad i koden, även om `sales_tracking_enabled=true` skulle sättas av misstag. Syntetisk staging/test kan använda P9 med explicit flagga. Samtyckeshanteringen är nu tekniskt färdig i syntetisk staging, men en öppet åtkomlig stagingmiljö är inte lämplig för riktiga kunder. Begränsa användningen till behöriga testare och syntetiska uppgifter; inför och verifiera faktiskt åtkomstskydd inför bredare demo.

## C4 – acceptanskrav före aktivering

Verifiera automatiskt och i webbläsare:
- Ny besökare utan val: noll valfri lagring, noll sales-events och noll orderattribution.
- Avvisa: motsvarande nolläge, men hela köpresan fungerar.
- Acceptera statistik: endast tillåtna statistikfunktioner startar efter valet.
- Återkalla: nya events stoppas, nycklar rensas och ny orderattribution uteblir.
- Manipulerade/utgångna samtyckesunderlag och direkta API-anrop kan inte kringgå serverkontrollen.
- Byte av policyversion eller utgången giltighet leder till avstängd statistik tills nytt giltigt val finns.
- Blockerad sessionStorage/localStorage eller ett tekniskt fel stoppar inte beställningen.
- Administrationssidor initierar inte kundens besöksmätning.
- Inga externa pixlar, marketing-mail/SMS eller betalda tjänster aktiveras.
- P4–P9-regressioner, betalningsspärrar och P7:s produktspärrar förblir gröna.

## Övriga lanseringskrav som ligger kvar

Personliga adminkonton, behörigheter/MFA, allmän missbruksspärr, backup/återställningsprov och slutlig säkerhetsgranskning är separata krav. De är inte levererade genom den här cookieplanen eller de två kontrollrättningarna. P8 förblir `TECHNICALLY COMPLETE / NOT ACTIVATED`.

## Källor – kontrollerade 2026-09-28

[1] PTS, Kakor (cookies): https://www.pts.se/internet-och-telefoni/kakor-cookies/

[2] EDPB, Guidelines 2/2023 on Technical Scope of Art. 5(3) of ePrivacy Directive: https://www.edpb.europa.eu/documents/guideline/guidelines-22023-on-technical-scope-of-art-53-of-eprivacy-directive_en

[3] IMY, Samtycke som rättslig grund: https://www.imy.se/verksamhet/dataskydd/det-har-galler-enligt-gdpr/rattslig-grund/samtycke/

Detta är en teknisk genomförandeplan, inte en slutlig juridisk bedömning av den ännu inte aktiverade produktionsmiljön. Kontrollera faktisk konfiguration och aktuella krav igen före lansering.
