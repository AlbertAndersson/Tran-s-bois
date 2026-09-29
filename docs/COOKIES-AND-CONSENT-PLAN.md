# Kakor, webbläsarlagring och samtycke – plan inför lansering

Datum: 2026-09-29
Status: **IMPLEMENTED / CI VERIFIED / PROTECTED STAGING VERIFIED / PRODUCTION ANALYTICS BLOCKED**.

C1–C4 åter verifierades bakom Basic Auth i run **36623915463**, job `109595921802`, success. Publicerad appref `c49ebcd9af9f7081e1764d28419d92dd05b4fd48`; workflow/main `4918cef10bcc213ef9c756d407ca88d4990971fa`. Obehörig direktåtkomst nekades, medan behöriga samtyckes-, kund- och adminflöden passerade i API och Chromium 375/390/1280 px. 10 PNG finns i `bois-p9-synthetic-browser-36623915463`, artifact ID `11059388459`, till 2026-10-06 20:08:02 UTC. Tidigare öppna C1–C4-run `36517329717` och dess åtkomstblockerare är historik; katalogskyddet är nu verifierat. Se `CONSENT-INVENTORY-AND-ACCEPTANCE.md` för omfattning, kvarvarande produktionsinventeringar och tidigare CI-bevis.

P8 är **TECHNICALLY COMPLETE / NOT ACTIVATED**. Ingen Stripe, riktig betalning, extern mejlsändning/analytics eller produktion aktiverades; ny extern kostnad 0 kr. Den ursprungliga kravbilden nedan bevaras. Faktisk Erik/BoIS-demo och återkoppling är nästa steg; fysisk iPhone/Safari och skarp rättslig/informationsmässig bedömning är inte verifierade av Chromiumtestet.

## Beslut och avgränsning

Albert bad att identifierade säkerhetskontroller rättas före samtyckeshanteringen. Säkerhetsrättningen, C1–C4 och de syntetiska kvalitetsflödena är nu genomförda och senare verifierade i skyddad staging. Inget CMP-abonnemang, annonsering eller extern analystjänst köps.

Genomförd ordning och nästa beslut:
1. Produktionsspärr för nya Stripe-checkouts och spårningsstopp rättades och testades. Se `SECURITY-CONTROL-POINTS.md`.
2. Rättad kod publicerades i kostnadsfri BoIS-staging med mock och syntetiska data.
3. C1–C4 implementerades och testades; produktionsmätning hålls fortfarande stängd.
4. Kundresa, admin och syntetiskt demopaket har automatiserad Chromiumacceptans. Åtkomstskydd är också verifierat. Genomför nu faktisk demo med Erik/BoIS och samla konkret återkoppling.
5. Separat produktionsverifiering återstår först när merchant, bank, domän, juridiskt underlag, drift och lanseringsbeslut är klara.

## Ursprungliga principer och krav

Kakor som är strikt nödvändiga för en tjänst användaren begär kan omfattas av undantag från samtycke. Statistik blir inte nödvändig bara för att den är nyttig för BoIS. Information om användningen krävs även när bara nödvändig lagring används. För valfria ändamål ska användaren kunna acceptera och avvisa lika enkelt, i samma första vy, och kunna återkalla valet. Ursprunglig källa: PTS vägledning [1].

Inventeringen omfattar kakor OCH liknande lagring/åtkomst, inklusive sessionStorage och localStorage. Teknikbyte är inte en genväg runt bedömningen; EDPB:s riktlinjer behandlar den tekniska räckvidden för artikel 5.3 [2]. Vid personuppgiftsbehandling ska rättslig grund, information och lagringstid bedömas separat enligt GDPR; samtycke ska vara frivilligt och kunna återkallas [3].

BoIS arbetsbedömning: P9:s besöksmätning och UTM/referral-attribution är valfri statistik. Den ska inte köras i produktion före fungerande samtyckeshantering och godkänd information. Själva beställningen, betalningssäkerheten och medlemsadministrationen ska fungera utan statistikmedgivande. Beställningsgodkännande är INTE samtycke till spårning.

## C1 – inventering av lagring och externa anrop

- `boisSalesSession`: pseudonymt sessions-ID i sessionStorage för P9-mätning; valfri statistik.
- `boisSalesAttribution`: UTM/referral/landningssida i sessionStorage; valfri statistik.
- Samtyckeskakan `boisConsent`, adminlagring, observerad `sc_clearance` och externa resurser redovisas i acceptansprotokollet.

Stripe Checkout och leverantörers faktiska lagring kvarstår före produktionsaktivering. Anta inte att alla Stripe- eller tredjepartskakor är nödvändiga. Dokumentera namn, teknik, domän/ansvarig, exakt ändamål, data, livslängd, åtkomst, kategori och bedömning. Inspektera Set-Cookie och lagring före val, efter ja, efter nej och efter återkallelse i relevanta webbläsare.

Sessionsdata är pseudonym och kan internt länkas till order; beskriv den inte som anonym. Separera webbläsarens lagringstid från retention i sales-tabeller och från webbserverns driftloggar.

## C2 – egen kostnadsfri samtyckeskomponent

Kravbild som ligger till grund för implementationen:
- Likvärdiga möjligheter att acceptera eller avvisa valfri lagring i första vyn och anpassning per faktiskt ändamål.
- Statistik av som standard. Inga förkryssade val, tyst accept vid fortsatt surf eller koppling till köp-/medlemsvillkor.
- Köp, mockbetalning, framtida riktig betalning och orderstatus ska fungera vid nej eller inget val.
- Permanent länk `Kakinställningar` på kundsidorna för nytt val/återkallelse.
- Tillgänglig tangentbords-/fokushantering, skärmläsarinformation och mobilvy. Chromiumacceptans är inte en fullständig tillgänglighetscertifiering.
- Minimalt underlag för valet: version, kategorier, tidpunkt och giltighet. Inga extra IP-/kundidentifierare enbart för detta.
- Konfigurerad giltighet och motivering; 180 dagar är stagingval, inte ett generellt lagkrav. Slutligt produktionsbeslut återstår.
- Kak-/lagringssida med inventering, leverantörer, ändamål, varaktighet, återkallelse och ansvarig.
- Om lansering endast använder strikt nödvändig lagring behövs inte en banner som ber om samtycke till den; korrekt information ska ändå lämnas utifrån faktisk inventering.

## C3 – valet verkställs i klient och server

Framtida produktionsmätning kräver både ett separat tekniskt aktiveringsbeslut OCH giltigt, aktuellt statistikmedgivande. Saknat, avvisat, utgånget, återkallat eller ogiltigt medgivande betyder av. Produktion är fortfarande globalt blockerad trots färdig komponent.

Före ja: inga P9-nycklar, statistik-ID:n, sales-event eller orderattribution. Ingen efterhandsåterspelning av surfning före samtycket.

Servern kontrollerar version, giltighet och revokering även vid direkta API-anrop. Ett fritt JSON-fält `consent=true` eller manipulerad frontendflagga räcker inte. Den nuvarande lösningen använder slumpmässig HttpOnly-kaka och serverlagrad tokenhash. Den tekniska kontrollen ska inte beskrivas som bevis för ett frivilligt mänskligt val i sig.

Återkallelse stoppar nya events och orderkopplingar, avbryter köad statistik och rensar valfria P9-nycklar. Hanteringen av redan insamlade statistikdata och retention ska vara dokumenterad före produktion. Radera inte order-/betalningshistorik som automatisk bieffekt; den behandlingen har annat ändamål.

Syntetisk staging kan använda P9 med explicit teknisk flagga och giltigt statistikval. Den är nu skyddad med Basic Auth enligt `STAGING-DEMO-ACCESS.md`, men är fortfarande inte en miljö för riktiga kunder eller verkliga medlemsuppgifter. Begränsa åtkomsten till behöriga granskare.

## C4 – acceptanskrav och verifieringsgräns

Isolerad CI och dokumenterad stagingacceptans täcker:
- inget val/nej: ingen valfri lagring eller attribution; köpresan fungerar,
- ja: endast godkänd statistik efter valet,
- återkallelse: nya events stoppas, P9-nycklar rensas och ny attribution uteblir,
- manipulerat/utgånget/återkallat underlag, policybyte och direkt API kontrolleras i isolerade tester,
- blockerad lagring eller tekniskt fel stoppar inte vanlig order,
- admin initierar inte kundens besöksmätning,
- inga externa pixlar, marketing-mail/SMS eller betalda tjänster,
- bevarade P4–P9-, checkout- och P7-grindar.

Exakt fördelning mellan isolerad CI och livebrowserbevis, vilka motorer som testats samt artifacter finns i `CONSENT-INVENTORY-AND-ACCEPTANCE.md`. Testresultat ska inte utvidgas till osedda enheter eller betraktas som slutligt juridiskt godkännande.

## Övriga lanseringskrav

Personliga adminkonton, roller/MFA, missbruksskydd, backup/återställningsprov och slutlig säkerhetsgranskning är separata krav. De är inte levererade genom cookiekomponenten eller demots Basic Auth. Produktionsinformation, gallring och ansvarig säljare återstår. P8 förblir `TECHNICALLY COMPLETE / NOT ACTIVATED`.

## Bevarade källor från ursprungsplanen

Källorna nedan angavs som kontrollerade 2026-09-28 i den ursprungliga planen. Denna dokumentationscloseout gör ingen ny juridisk bedömning.

[1] PTS, Kakor (cookies): https://www.pts.se/internet-och-telefoni/kakor-cookies/

[2] EDPB, Guidelines 2/2023 on Technical Scope of Art. 5(3) of ePrivacy Directive: https://www.edpb.europa.eu/documents/guideline/guidelines-22023-on-technical-scope-of-art-53-of-eprivacy-directive_en

[3] IMY, Samtycke som rättslig grund: https://www.imy.se/verksamhet/dataskydd/det-har-galler-enligt-gdpr/rattslig-grund/samtycke/

Kontrollera faktisk produktionskonfiguration och aktuella krav igen före en separat lansering. Ingen produktionsaktivering görs i denna closeout.
