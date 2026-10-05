## Aktuell styrning 2026-10-05

## Aktuell lokal hoststatus 2026-10-06

Stängd production på dedikerad socen.se-produkt är uppgraderad till main
`cd76ea8ffedd6ca63eaf3905eda40031327421dc` efter PR42. Verifierad privat
backup, isolerad restore, rollback, readiness och syntetiska hostprov finns.
Verifierad krypterad offsitekopia finns på Besovida. Daglig Simply-backup är
installerad. Albert har accepterat driftansvar, RPO 24 h och RTO 4 h som mål.
P18A är DONE; P18B/P18 är fortsatt BLOCKED vid SELECT-only, automatisk
offsiteöverföring, övervakning/key escrow och full hostbrowseracceptans. Se [nya hostbevis och begränsningar](P18B-LOCAL-HOST-EVIDENCE.md).
Den äldre molnkörningens credentialblockerare nedan är historik och gäller
inte som aktuell lokal status. Staging är separat och har inte ändrats.

## Tidigare dokumentation (historik)

P18A är DONE och mergad via PR40. P18B är beställd men **BLOCKED**:
run37241703902 på main `4e4e2349631d434160b77bd9b2b2d3e7be7db349`
saknar produktionscredentials i GitHub och stannade före DB-anslutning.
Befintlig privat produktionsåtkomst är dokumenterad på Alberts lokala dator.
Ingen ny hostdeployment, migration eller aktivering utfördes.
P12 är accepterad av Albert; återstående hostbevis ligger i P18B.
P13–P17 är klara i kod/isolering och får inte beskrivas som fullt installerade.
P18 och P20 förblir BLOCKED vid hostacceptans; P19:s verksamhetssvar är öppna.
Se [aktuell hostacceptans och lokal fortsättning](P18B-HOST-ACCEPTANCE.md).
Äldre nästa-etapp-rader nedan är historik och gäller inte som arbetsinstruktion.

---

# P16 – privacy, retention och dataminimering

Baslinje main: `df6c80a68945a9b6fe7b103a28a66399cf25ea42`. PR #34.
Etappen förbereder tekniken för senare verksamhetsbeslut. Juridiska tider,
ansvarig, ändamålsbedömning och undantag är **TBD**. Inga tider här anges som
lagkrav. Ingen gallring körs mot production/staging, ingen Simply-deploy,
productionaktivering, Stripe live, DNS-cutover, extern mejl eller ny tjänst/kostnad.
P12-verifiering är fortsatt uppskjuten enligt användarens beslut. P17 ingår inte.
BoIS production är dedikerad socen.se; staging/sandbox är separat alberiq.se,
med separata credential boundaries. Lamport/andra system ingår inte i SOCen.

## Fullständig DB-inventering

[P16-DATA-FIELDS.json](P16-DATA-FIELDS.json) listar samtliga 24 tabeller och
281 fält i nuvarande schema. Varje fält har teknisk källfil, ändamål och
dataklassificering. Varje tabell har retention TBD och vilken teknisk åtgärd
som är tillåten. CI jämför den med faktiskt MySQL-schema efter åtta migrationer;
nya/ändrade fält utan inventering stoppar P16-kontrollen. Inventeringen innehåller
endast fältnamn/metadata, aldrig datautdrag. FK-, belopps-, status- och tidsfält
kan också vara persondata genom order-/medlemskoppling och behandlas därför
inte som anonyma. Katalogfält avser normalt produkter, men fria JSON-/textfält
är potentiella persondata även när ingen personkolumn har avsetts.

Särskilt viktiga inbäddade fält, utöver SQL-kolumnerna: order.metadata innehåller
existing_member. Orderrad.metadata innehåller member_name eller matchställets
team/player_name/shirt_size/shorts_size/number/name_print/number_print samt
tekniska eligibility/price_note. P3 validerar dessa nycklar, inte godtycklig
orderinput. P5 outbox.payload innehåller person-/spelaruppgifter i rows
(order/team/player/shirt_size/shorts_size/number/quantity) och samma CSV som
csv_base64, plus batch/supplier/count/hash/filename. Base64 är inte anonymisering.
P6 payment_outbox.payload innehåller kind/order/amount_ore/currency/payment_status/
provider/method/safe_staging_recipient; mottagaren finns i separat mailkolumn.
Affärsevents innehåller order-/payment-/member-/entitlement-/partnerrefs och status;
supplier_batches.config_json och leverantörs-/produkt-JSON är operativa källor.
Nordic CSV innehåller entitlement/order-ID, kundnamn/mail/telefon, medlemsnamn,
medlemstyp/giltighet/status. Sådana exporter, mailkopior och JSON/CSV-underlag
är skyddade persondata och ingår inte i den valfria statistikgallringen.

| Grupp/tabeller | Data och syfte | Retention/åtgärd |
| --- | --- | --- |
| customers | namn, e-post, telefon, UUID för kontakt/order/kvitto | TBD; skyddad |
| orders/order_items | order-ID/token, kundrelation, belopp, status, köp och metadata | TBD; skyddad ekonomisk/leveranshistorik |
| memberships/members | medlemsnamn, kundrelation, giltighet, extern ref, verifierare, anteckningar | TBD; skyddad |
| benefit_entitlements | medlems-/orderrelation, partnerref, status/tider/anteckningar | TBD; skyddad |
| payments/payment_events | provider/session/payment/event-ref, token-/payloadhash, belopp, refund, signerad status, felkod | TBD; skyddad avstämning/idempotens |
| payment_outbox/email_outbox | mottagare, ämne, JSON-kvitto/leveransunderlag, retry och skickad status | TBD; skyddad även efter SENT |
| supplier_batches/batch_items/events | orderrelation, batchunderlag/hash, affärshändelser och JSON | TBD; skyddad leverans-/affärshistorik |
| sales_sessions | pseudonymt UUID, kampanj/referral/landningspath och aktivitet | konfigurerbar; TBD/null tills beslut |
| sales_events | eventnyckel, session, sida/produkt/händelse och tid | konfigurerbar; TBD/null tills beslut |
| sales_order_links | session–orderkoppling, inte anonym | gallras endast med vald gammal session; ordern bevaras |
| consent_choices | tokenhash, policy, val, beslut/expiry/revoke | inaktiva token konfigurerbara; TBD/null tills beslut |
| suppliers | namn och kontaktmail, CC, JSON | TBD; skyddad leverantörskontakt |
| products/variants/fulfillment_rules/p7_assortment/p7_variants/schema_migrations | katalog, sortimentskällor, leveransregler, ledger | inventerade; skyddade |

P9-attribution är fritext med begränsat format och paths med borttagen query;
en avsändare kan ändå lägga personuppgifter i kampanj-/ref-/pathvärden. Använd
opersonliga kampanjkoder och undvik individuppgifter i UTM/URL. SessionUUID och
orderlänk är pseudonyma. Radering av events gör inte kvarvarande sessiondata
anonyma, och sessionens event_count är en kvarvarande kapacitetsräknare.
Radera inte betalnings-/orderunderlag som bieffekt av återkallat statistikval.

## Data utanför DB

| Källa/fält | Ändamål och teknik | Varaktighet/retention |
| --- | --- | --- |
| boisConsent-kaka | opak capability; DB lagrar endast SHA-256-hash; Secure/HttpOnly/SameSite=Lax | giltighet konfigurerbar; 180 dagar är stagingfallback, production kräver beslutad explicit inställning |
| boisSalesSession/boisSalesAttribution | sessionStorage efter statistik-ja; UUID och kampanj/path | flikens session, rensas vid återkallelse; DB-retention separat TBD |
| boisP3Admin | delad adminnyckel i sessionStorage i legacy staging/preview | flik/logout; production accepterar inte den |
| __Host-BoISAdmin | opak Secure/HttpOnly/SameSite=Strict-sessionkaka | P14 15 min idle/8 h absolute är tekniska säkerhetsgränser, inte juridiska gallringstider |
| P14 users.json | principal-ID, roll, enabled/epoch, passwordhash, TOTP-secret | privat 0600; credentialrotation separat; retention TBD |
| P14 state.json | sessionshash, CSRF, principal, tider/epoch, OTP-replay och failure/rate-status | privat, opportunistisk expiry i P14; inga MFA-/replayskydd gallras av P16 |
| P14 audit.jsonl | tid, principal/roll/action/outcome, request-ID och eventuellt revoke-target | privat, retention TBD; förblir utanför gallringsjobbet |
| P15 operations JSONL | tid/request-ID/komponent/fasta event/status/latens | privat 0600, storlekskontroller; retention TBD, P15-arkivering separat |
| Security-rate buckets | HMAC av remote-IP/grupp, count/until | privat; kort teknisk expiry, ingen rå IP lagras i appens bucket |
| PHP-/Simply-access-/errorlogg | hostens HTTP-metadata och tekniska fel; query kan innehålla ordertoken | privat åtkomst, exakt hostretention och querymaskning TBD |
| Basic Auth/SSH/DB/Stripe secrets | teknisk autentisering, separat staging/production | privat secretrotation; ingen automatisk gallring |
| P13/SOCen-backuper, privata snapshots | kopior av DB, config, users/audit och legacy-material | privat; TBD inklusive kopior/restore; verifierad backup raderas inte av P16 |
| CSV/Nordic/export/mail/provider | delade order-/medlems- och leverantörsunderlag | separat ansvarig, destination/kopia/retention TBD |
| CI-fixtures/artifacts | syntetiska data, tidigare demoscreenshots enligt respektive workflow | P16 publicerar inga data/config/log-artifacts |

Orderstatus-/return-URL:er innehåller en ordercapability. `Referrer-Policy:
no-referrer` begränsar referrerdelning, men URL:en finns fortfarande i browser-
historik, hostloggar och Stripe return-parametrar. P16 ändrar inte checkoutens
åtkomstmodell; detta är ett dokumenterat framtida säkerhets-/maskningsarbete.
Ingen lokal CLI-rapport innehåller tokens, principal, mottagare, rader eller ID-listor.

## Konfiguration och säkra standarder

Productionexemplet innehåller `retention.rules` med null för
`sales_events_days`, `sales_sessions_days`, `consent_inactive_days`.
Null betyder **TBD, ingen kandidat i den regeln**. En explicit integer 1–36500
är ett tekniskt intervall, ingen rekommenderad juridisk tid. Okända regler,
strängar, negativa/0-värden och för stor batch nekas. `batch_size` är 1–1000,
standard 100 per event/session/consent-grupp; analyslänkar har separat tak 1000.
Ingen rule finns för orders/payments/members/outboxes/etc.
`apply_enabled=false`, `legal_hold=true`, policy-/backupreferens tom och
`approved_target=[]` gör faktisk gallring avstängd.

Production kräver också explicit `consent_validity_days` (stödd teknik 1–365)
och `consent_cookie_path`. De är null/TBD i exemplet. Ingen 180-dagars staging-
fallback är tillåten i production. Cookiepath måste vara absolut säker scope,
exempelvis `/bois-shop-production/`; inget antagande om stagingens path.
Validering sker före en consent-DB-skrivning. Cookiegiltighet och retention
för återkallade/utgångna DB-token är olika inställningar/ändamål.

## Dry-run och tekniskt gallringsstöd

Kör endast från privat CLI mot den avsedda miljön, med privat 0600-config i
0700-katalog utanför en existerande `BOIS_PUBLIC_ROOT`. Ingen HTTP-adminroute
eller cron för retention skapas. Standardläget är dry-run:

```text
BOIS_PUBLIC_ROOT=/absolut/public_html php ops/p16-retention.php /absolut/privat/config.php
```

Rapporten visar UTC-klocka/cutoffs, konfigurerade/TBD-regler, batchstorlek,
antal kandidater för events/länkar/sessioner/consent och plan_hash/targetfingerprint.
Den visar hypotetiska kandidater även när legal_hold blockerar apply. Rapporten
är ett granskningsunderlag, inte ett godkännande. READ ONLY-transaktion fungerar
med enbart SELECT; inga migrations-/affärs-/audit-DB-skrivningar sker.
P15 skriver privata fasta operationssignaler vid CLI-körning. Känsliga detaljer
och data-ID-listor finns bara tillfälligt i minnet; utdata är aggregerade.

Urval använder **strikt äldre än** UTC-cutoff; exakt gräns behålls:

- Events väljs efter created_at enligt eventsregeln.
- Sessioner väljs efter last_seen_at. De får inte ha kvarvarande events efter
  den begränsade eventbatchen eller en orderlänk vid/efter session-cutoff.
  Sessionens gamla analyslänkar tas bort före sessionen; själva ordern ändras inte.
  Om eventsregeln är TBD kan sessioner med events inte tas bort. En stor linkbatch
  (>1000) nekas och kräver mindre sessionbatch. Fler äldre events kräver nästa dry-run.
- Consent väljs efter revoked_at om revokerad, annars expires_at. Aktiva token
  behålls. Borttaget gammalt token ger inget statistikmedgivande vid replay.

Efter ett senare uttryckligt verksamhets-/driftbeslut kan samma arkitektur användas
för apply: konfigurera tider, `apply_enabled=true`, `legal_hold=false`, policy-
och verifierad backupreferens samt exakt approved_target host/database/user.
Production kräver dessutom `production_decisions.privacy_retention` godkänd
med samma policyreferens, separat P12-DB-mål och P15:s privata logglagring.
En referens är ett operatörsintyg, inte automatisk verifiering av ett beslut eller
att en backup går att återställa; kontrollera dessa privat enligt P13 före ändring.
Ta nytt dry-run efter configändring och använd exakt rapportens as_of och hash:

```text
BOIS_PUBLIC_ROOT=/absolut/public_html php ops/p16-retention.php /absolut/privat/config.php --dry-run YYYY-MM-DDTHH:MM:SSZ
BOIS_PUBLIC_ROOT=/absolut/public_html php ops/p16-retention.php /absolut/privat/config.php --apply=GRANSKAD_64_HEX_HASH YYYY-MM-DDTHH:MM:SSZ
```

Apply binds UTC-klocka, regler, godkännande-/backupreferenser via fingerprint,
DB-identitet och exakt kandidatset med tider. Okänd miljötyp nekas.
Ny aktivitet/ändrat urval/hash/target blockerar före DELETE och kräver ny granskning.
Ett retention-jobb i taget via advisory lock; rader låses och varje batch körs i
en atomär transaktion med FK-kontroll kvar. Fel/race ger rollback av hela batchen.
Rapporten visar bara raderade antal. Återanvänd gammal hash nekas när urvalet
ändrats; ett nytt dry-run visar 0 efter avslutad gallring. Kör inte dessa apply-
kommandon mot framtida productiondata som del av P16. Ingen sådan körning görs här.

## Externa resurser, sc_clearance och Stripe

Teknisk kod-/HTTP-granskning i P16: externa klubbmärket används i HTML från
cdn06.svenskalag.se. Ett aktuellt GET gav image/png och 200 utan Set-Cookie i
just detta svar. IP/HTTP-metadata når ändå leverantören; ett enda svar bevisar
inte frånvaro av cookies eller leverantörsloggar i andra sammanhang. CSP tillåter
den bilden, inga externa analytics/scripts och connect-src self.

`sc_clearance` skapas inte av BoIS-kod. Den har observerats i tidigare rena
stagingbrowser-körningar vid 375 px, inte alltid i andra kontexter; se
[samtyckesinventeringen](CONSENT-INVENTORY-AND-ACCEPTANCE.md). Aktuella
oautentiserade GET till staging och productionkandidat gav 401 utan Set-Cookie
i dessa svar. Detta ersätter inte browser-/leverantörsverifiering. Exakt domän,
expiry, HttpOnly/Secure/SameSite, ändamål, ansvarig och möjlighet att begränsa
skyddskakan är fortfarande TBD och ska verifieras med Simply före publik drift.
Ingen kontakt skickas till leverantören i denna etapp.

Stripeflödet granskas från befintlig `server/p8_stripe.php`: servern skickar
kundmail, order-ID, totalbelopp/valuta, return-URL med ordertoken samt order-ID
och slumpad requestref i Checkout/PaymentIntent-metadata till api.stripe.com.
Kundens browser går till checkout.stripe.com; BoIS hanterar inga kortfält.
Signerade webhooks översätts till fasta normaliserade event; DB behåller refs,
hash, status, belopp och idempotensspår, inte hela webhookbody. Body används
tillfälligt i minnet för signatur/hash. Refundspår förblir skyddade.

Officiell [Stripe metadata-dokumentation](https://docs.stripe.com/metadata/use-cases)
beskriver order-ID som kopplingsmetadata och avråder från person-/kort-/bankdata
där. [Stripe cookiepolicy](https://stripe.com/legal/cookies-policy) beskriver
teknik för drift och fraud, men är inte bevis för exakt cookieuppsättning i BoIS
Checkout. Den kräver separat browserinventering före aktivering. Lokal gallring
anropar ingen Stripe delete-/redaction-API och ändrar därför inga providerobjekt;
extern datahantering/retention och avtalsbedömning är separat TBD. Den befintliga
sandboxen behålls, inga API-nycklar/versions-/betalmetodsändringar görs i P16.

## Verifiering och kvarvarande beslut

CI ska verifiera inventeringens faktiska fält, SELECT-only dry-run utan DB-
förändring, TBD-defaults, åldersgräns, unga events/sessioner/länkar och aktiva
token, batchgräns, hold/approval/target/plan-guard, rollback efter delvis DELETE,
faktisk syntetisk apply med bibehållna 20 skyddade tabeller/ledger/FKs, återkörd
dry-run och privat CLI utan identifierar-/secretutdata. Produktionsconsent kräver
uttrycklig giltighet/scope. Inga data-/log-/config-artifacts laddas upp.

Verksamheten behöver senare besluta tider/ändamål per datagrupp, ansvarig,
undantag/holds, backupkopior, server-/auditloggar, exporter/partners och Stripe.
De är TBD och befintliga launchgrindar hålls stängda. Schema/migrationsledger
förblir 24 tabeller/åtta poster. Ingen produktiongallring eller hostverifiering
behövs för denna tekniska closeout; P12 förblir uppskjuten. P17 startas inte.

## Closeout

P16 DONE via [PR #34](https://github.com/AlbertAndersson/Tran-s-bois/pull/34).
Main före: `df6c80a68945a9b6fe7b103a28a66399cf25ea42`; PR-head:
`d6f2bbbdaffc35adeb96e6f4419c8a779bbf0131`; merge/kod-main efter:
`ad035c6288852f354a7b39d433aa8c25b10d6180`.
P16 [37179059419](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37179059419),
jobb `111367711810`, success inklusive P6-syntetik, inventory/SELECT-only dry-run,
plan-/policy-/target-/hold-/batch-/gränsskydd, rollback, faktisk isolerad apply,
20 oförändrade skyddade tabeller, ledger/FKs och privat CLI.
P14 37179059420 inklusive Chromium, P15 37179059403, P13 37179059416,
P12 37179059415, P9 37179059404, P8 37179059417, P7 37179059411,
P3 37179059412, P2 37179059413 och Security 37179059409 är alla success
på slutlig PR-head. P4–P6 hade inga separata triggade runs på denna diff;
P16 kör deras verkliga syntetiska betalnings-/medlems-/batch-fixture och P13/P9
kör också sina relevanta regressioner. Ingen production-/staginggallring,
Simply-deploy eller aktivering. Inga tekniska P16-blockerare/manuella åtgärder nu;
TBD-beslut/leverantörsverifiering återstår inför drift. P17 har inte startats.

Merge-main passerade också: P16 37179208824, P15 37179208793,
P14 37179208845, P13 37179208836, P12 37179208841, P9 37179208787,
P3 37179208794, P2 37179208813 och Security 37179208858 är success.
Den befintliga statiska GitHub Pages-demoautomaten publicerade uppdaterad
cookieinformation (37179208786 success); den deployar inte PHP/retention till
Simply och aktiverar inte production. Inga Simply-miljöer eller affärsdata ändrades.
