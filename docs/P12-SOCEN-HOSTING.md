## P13 DONE – P12-verifiering uppskjuten enligt beslut

Användaren har uttryckligen valt att fortsätta med P13 eftersom återstående
P12-verifiering inte kan genomföras nu. P12 är inte DONE; dess kvarvarande
flödes-/hostverifiering är uppskjuten (SKIPPED BY DECISION för dessa kontroller).
BoIS production ligger på den dedikerade Simply-produkten socen.se, staging och
Stripe-sandbox ligger kvar på separat alberiq.se-produkt med separata credentials.
Den skyddade production-kandidaten behåller launch/checkout/betalning/mail av.
P13 gäller backup/restore: isolerad syntetisk MySQL- och filrestore, privat
read-only integritetskontroll samt runbook. Se docs/P13-BACKUP-RESTORE.md och
PR #31; restore-CI 37154604007 är success på kod-SHA 02ac9557c42017e3d166d54a012f347d9d4a2421. P14 är nästa etapp och har inte startats. Ingen ny productiondeploy, DNS-cutover eller Stripe-live-aktivering.
Äldre P12-status nedan är historisk och ersätter inte detta beslut.

# P12 – dedikerad BoIS production på socen.se

## Uppdatering 2026-10-03 – skyddad BoIS-app installerad över HTTPS

Fortsatt arbete efter användarens fråga om utvecklingen kan gå vidare.
App/release-SHA: `020446867dcf29b7ebe63a060f8594e8f8da8aff`, då aktuell main.
Skyddad kandidat: `https://socen.se/bois-shop-production/`.
Privat runtime är fortsatt `.bois-production/config.php`; wrappern väljer denna
explicit och implementationen ligger helt utanför webbroten i privat release.
Ingen produktionskonfiguration eller Stripe-/launchflagga har öppnats.

Gamla public_html flyttades till
`/var/www/socen.se/.socen-backup-20261003/legacy-public-html-original/`.
Ingen äldre kod har återinstallerats och inga originalfiler har raderats.
Rooten är stängd; BoIS-kandidaten har separat HTTP Basic Auth. Slumpmässigt
åtkomstlösenord ligger endast privat på hosten och lokalt, inte i repo/loggar.
Apache läser en bcrypt-hash i en separat authkatalog utanför webbroten;
konfigurationen och dess katalog behåller 0600 respektive 0700.

Faktisk HTTPS-verifiering:
- Obehörig index/API/admin/membership/config/assets: 401.
- Behörig index/admin/membership/match-kit/payment/config/assets: 200.
- Behörig health/catalog/admin_orders: 503.
- Behörig orders/checkout/consent/sales_event POST: 503.
- TLS-certifikat verifierat utan bypass; HTTP omdirigeras till HTTPS med 301.
- CSP, nosniff, frame-deny, referrer-policy och no-store finns i API-svaret.
- .htaccess nekas (403); server/ops saknas publikt (404); gammal wp-config-url
  stoppas av Simply WAF (455). WAF-blocket är inte en exponerad fil.
- Privat PHP-loggning verifierad med en tillfällig konstant markör via faktiskt
  HTTPS-anrop. Originalwrappern återställdes byte-identiskt efter loggprovet.
- DB-schema och alla rader är oförändrade efter HTTPS-kontrollerna; stängd
  P12-readiness passerar fortfarande och ready_for_launch är false.

Verifieringsresultat ligger privat under
`C:\Users\AlbertAndersson\.codex\private\bois-production\` som
`https-verification-20261003.txt` och `post-https-readiness-20261003.txt`.

P12 har kommit vidare till skyddad hostverifiering men är inte DONE. Fullständiga
syntetiska köp-/medlems-/admin-E2E och separat SELECT-only hostkonto återstår.
De gamla supportuppmaningarna blockerar inte denna appinstallation. Inget liveköp,
extern mail/analytics eller slutlig DNS-cutover har gjorts. Staging/sandbox på
alberiq.se är kvar och har inte deployats om. P13 har inte startats.

## Historik före skyddad appinstallation

## Uppdatering 2026-10-03 – legacy-databasen raderad

Efter användarens uttryckliga godkännande har hela `socen_se_db` raderats med
DROP DATABASE. Ny full backup före radering finns privat som
`socen-final-before-delete.sql`; lokal/server SHA-256:
`d06637fd6c0cf6a074ec80da5776f171a18679182698065423565d4344b14aed`.
Dumpen innehåller 25 CREATE TABLE och avslutningsmarkör; båda kopiorna är verifierade.

Efterkontroll: legacy-schemat saknas, BoIS production-schema och samtliga rader
är oförändrade jämfört med tidigare baseline, och stängd readiness passerar.
Den gamla WordPress-webben är spärrad via `.htaccess` och svarar 403 över HTTPS.
Originalets `.htaccess` och samtliga webb-filer finns kvar i backup; webb-filerna
har inte raderats. BoIS production är fortfarande privat/stängd. Ingen staging,
DNS, Stripe live eller e-post ändrades.

Raderingen är klar och kräver ingen ytterligare manuell åtgärd. Den tidigare
blockeraren att produktionskontot når legacy-data är undanröjd. Separat SELECT-only
hostkonto är fortfarande inte tillgängligt/verifierat enligt ursprunglig P12-runbook;
detta är en separat verifieringsbegränsning. Publik BoIS-app och full E2E återstår,
så P12 är inte DONE. Tidigare supportuppmaning ska inte tolkas som att användaren
behöver kontakta Simply för legacy-raderingen.

## Historisk rapport före godkänd legacy-radering

Beslut 2026-10-03: Simply-produkten `socen.se` ska återanvändas uteslutande för
Tranås BoIS production. Lamport, Work Capture och andra system får inte
installeras där. Slutlig publik BoIS-domän och DNS-cutover är inte godkända.

## Status: BLOCKED – begränsade DB-konton saknas

Aktuell main vid arbetets start och privat deployment:
`1906b1f055b2709c1c9cbaab4d8d600f2caa8e5c`. README, CURRENT_STATUS,
P12-runbook, config, bootstrap/readiness och deployment-workflows är granskade.
Ingen äldre version har återställts.

Simply-inloggningen är genomförd. SOCen är inventerat och säkerhetskopierat.
Ny tom production-DB är skapad via kontrollpanelen inom befintlig Basic Suite,
utan köp eller uppgradering. Privat release, config och bootstrap är installerade.
Readiness är grön för stängd verifiering, inte för launch.

**Faktisk blockerare:** panelen använder samma produktanvändare för SOCens båda
MySQL-databaser. SHOW GRANTS visar två databasscope och inga globala privilegier
utöver USAGE. Användaren når legacy-databasen och saknar CREATE USER. Detta
uppfyller separationen mot staging, men inte P12-runbookens krav på konto
begränsat till endast produktionsdatabasen och separat SELECT-only verifiering.
Inget försök har gjorts att kringgå Simplys rättighetsmodell eller öppna DB för
Internet. Separat SELECT-only konto har inte verifierats på faktisk host.

Arbetet stoppades vid denna blockerare. Offentlig BoIS-appinstallation,
nedtagning/rensning av SOCen-webben och syntetiska köp-/medlems-/admin-E2E på
production-hosten återstår. Befintliga webb-filer, legacy-DB, alias och e-post är
bevarade. Ingen DNS, Stripe live, extern mail/SMS eller analytics har aktiverats.
P12 får inte markeras DONE utifrån det gröna readiness-resultatet ensamt.

## Hosting och credential boundaries

| Miljö | Simply-produkt | DB-host | DB-användare | Status |
| --- | --- | --- | --- | --- |
| Staging och Stripe-sandbox | alberiq.se | mysql115.unoeuro.com | alberiq_se | Befintlig drift bevarad |
| Stängd production-kandidat | socen.se | mysql97.unoeuro.com | socen_se | Privat installerad och bootstrappad |

Staging: `https://alberiq.se/bois-shop-p3/`. Stripe-sandbox:
`https://alberiq.se/bois-shop-stripe-sandbox/`. De har inte flyttats eller deployats
om. Production använder separat produkt, SSH-nyckel, MySQL-databas, användare och
privat runtime. Produktionscredentials nekades DB-åtkomst till staging.
Produktanvändaren på SOCen når tills vidare även SOCens legacy-DB.

Privat host-runtime: `/var/www/socen.se/.bois-production/config.php` (0600).
Release: `/var/www/socen.se/.bois-production/releases/1906b1f055b2709c1c9cbaab4d8d600f2caa8e5c/`.
Privata kataloger är 0700 och filer 0600. Ingen appkod/config har lagts i webbroten.
Dedikerad SSH-nyckel är installerad och SSH fungerar med strikt hostkontroll.
Privat nyckel och lokal configkopia ligger under
`C:\Users\AlbertAndersson\.codex\private\bois-production\`, utanför repo och OneDrive.
Secretvärden har inte skrivits i repo eller loggar. GitHub BOIS_PROD-secrets har
inte installerats; befintliga stagingsecrets och workflows är oförändrade.

## Inventering före ändringar

- Basic Suite linux, konto S591614; produktens utgång 2027-05-12.
- Webbserver linux122.unoeuro.com, 94.231.103.22. Hemkatalog `/var/www/socen.se`.
- WordPress under public_html: cirka 396 MB. Statisk socnordiq.com-katalog: 36 KB.
- Legacy MySQL: 25 tabeller, panelen rapporterade 969 rader. En av två DB-platser
  användes före provisionering; nu två av två.
- PHP 8.5 fast version; faktisk CLI 8.5.11. Inga registrerade SSH-nycklar före
  arbetet. Inga panel-cronjobb; shell crontab gav tomt resultat.
- Domänalias socnordiq.com pekar på `/socnordiq.com`; inga subdomäner.
- SOCen har Let's Encrypt och forced HTTPS. Aliaset saknar HTTPS.
- DNSSEC aktiverat, ingen URL-vidarebefordran eller DNS-mall. DNS-zon exporterad.
- Två e-postkonton finns, varav ett catch-all; de är bevarade. Mailinnehåll har
  inte exporterats. MS SQL är inte tillgängligt på denna Basic Suite-produkt.
- Redis/PostgreSQL och övriga tillägg är inte slutligt inventerade vid stoppet.

## BACKUP – verifierad läsbarhet, ingen restoreövning

Privat lokal plats:
`C:\Users\AlbertAndersson\.codex\private\bois-production\socen-backup-20261003\`.
Serverkopia: `/var/www/socen.se/.socen-backup-20261003/` (0700 katalog/0600 filer).
Lokal katalog har explicit Windows-ACL för AlbertAndersson och SYSTEM.

| Fil | Storlek | SHA-256 |
| --- | --- | --- |
| web-and-config.tar.gz | 113064000 byte | c56a7edbff6fb31a4665dcf47f1a35804a2897784504755a38858f84ba00ef7d |
| socen_se_db.sql | 819225 byte | 2efc9597e909e83236982323d6d72ebf5d375c5c5efbc7ed4a10aa88987b32d8 |
| socen.se.zone | 1420 byte | 69e18b2be722398e6992811358097eb8aa11cffb2a7ddc2463182d924fcb7f5a |

Filarkivet innehåller båda webb-katalogerna inklusive dolda filer, WordPress
uploads/plugins/themes/cache och wp-config.php samt .phpversion och .cl.selector.
Arkivet har lästs på både server och lokalt: 27132 arkivposter. Full SQL-export
med single-transaction/routines/triggers/events lyckades: 25 CREATE TABLE,
16 INSERT-satser och avslutningsmarkör 2026-10-03 22:15:32. Lokala fil-/SQL-hashar
matchar serverkopiorna. Arkivmanifest och hosting-inventory.txt finns privat.
Inga backup- eller datainnehåll har lagts i GitHub-artifacts. Återställningsprov
är inte genomfört; denna läsbarhetskontroll ersätter inte P13 restoreövning.

Ny production-baseline finns separat som privat production-bootstrap.sql på
servern och lokalt. SHA-256:
`8e6877d0cc7f5e796bab493532be696623acdf1080934068f8e100a1dfe8c5f7`.

## VERIFIED

- DB-anslutning till nya production-DB och tom DB före bootstrap.
- Befintlig P12-bootstrap: pass. Readiness: ready_for_closed_verification=true,
  ready_for_launch=false; exakt 24 tabeller/ledger, tomma affärstabeller och
  blockerad katalog. Ingen stagingdata importerad.
- Upprepad bootstrap: SHA-256 över schema och samtliga rader oförändrad.
- Faktisk applikationskod laddas i CLI. Health/catalog/orders/checkout/admin_orders/
  consent/sales_event ger 503 genom production-grinden. Schema/rader är
  oförändrade efter kontrollerna. Detta är CLI-verifiering, inte HTTP/browser-E2E.
- DB-credentialseparation mot staging: åtkomst med production-kontot nekas.
  Stagingpanelen visade baseline 24 tabeller/705 rader före; ingen skrivning eller
  runtime/deploymentändring har gjorts mot staging. Full stagingrad-hash saknas.
- Config 0600 och privat rot 0700 verifierade.
- Lokalt 21 befintliga browser-säkerhetstester och git diff --check passerade.

Ej verifierat i denna körning: production-app över HTTPS, personligt adminflöde,
syntetisk medlems-/köp-E2E, full loggningsacceptans, SELECT-only hostkonto, full
backuprestore eller slutlig offentligt publicerad app. SOCens befintliga TLS är
inventerat; det är inte bevis för en ny BoIS-appdeployment.

Launch, checkout, statistik, analytics och Swish är false. Betalprovider och
mailtransporter är disabled, Stripe-secrets tomma, public_base_url tom och inga
production_decisions fabricerade. Ingen slutlig publik DNS-cutover eller Stripe
live utan separat uttryckligt godkännande.

## MANUAL ACTION REQUIRED

Simply behöver erbjuda ett separat konto begränsat till enbart BoIS production-DB
med bootstrapprivilegier och ett separat SELECT-only konto för readiness, utan
ny kostnad. Operatörskontot kan inte självt skapa dessa användare. Begär besked
från Simply; inget supportmeddelande har skickats av agenten.

Återuppta därefter från då aktuell main: verifiera grants/readiness med reader,
slutför övrig resursinventering, dokumentera säker nedtagning av legacy efter
backup, installera skyddad stängd app och verifiera HTTPS/loggning samt isolerade
syntetiska admin-/medlems-/köpflöden. Ändra inte stängd production-config till
staging/mock för att få flödestester gröna. Registrera faktisk deployment-SHA och
bevis före DONE. DNS/liveaktivering förblir separat beslut.
