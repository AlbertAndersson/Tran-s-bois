# P15 – observability och driftberedskap

Baslinje main: `18ef6b03605ae96c5c1b84fa6f683037c0d452c8`. PR #33.
Teknisk implementation och isolerad verifiering; ingen Simply-deploy eller
aktivering. Nuvarande hostrelease är fortsatt den stängda P12-kandidaten.
P12:s återstående host-/flödesverifiering är uppskjuten enligt användarens beslut.
`socen.se` är dedikerad BoIS production; staging/sandbox ligger på separat
Simply-produkt `alberiq.se`, med separata databas- och SSH-credentials.
Inga riktiga konton, livebetalningar, extern mejl, DNS-cutover, monitoringköp
eller nya externa kostnader. P16 ingår inte i denna etapp.

## Signaler och åtkomst

| Kontroll | Betydelse | Svar |
| --- | --- | --- |
| `commerce-api.php?action=liveness` GET | PHP/API-koden svarar; ingen config eller DB behövs | 200 `{"ok":true}` |
| `commerce-api.php?action=readiness` GET | Privat logg/disk, DB, P12-ledgermarkör och operativa köer fungerar | 200 `{"ready":true}` eller 503 `{"ready":false}` |
| `php ops/p15-readiness.php /absolut/privat/config.php` | Samma kontroll med privata booleska delresultat | exit 0/1; database/schema/storage/queues |
| Befintlig `action=health` | Äldre stagingdiagnostik; fortsatt 503 i stängd production | Ingen ändring av lanseringsgrinden |

Alla probes skickar `Cache-Control: no-store`. Request-ID är 24 hextecken,
genereras av servern och returneras i `X-Request-ID`; inkommande ID används aldrig.
Probe-svaren innehåller inga versions-, host-, DB-, konfigurations-, konto- eller
ködetaljer. Readiness får högst 30 anrop/minut per befintlig privat IP-HMAC-bucket.
Metod/origin-kontroller gäller; probes gör inga DDL eller affärsskrivningar.
Liveness öppnar inte butiken. Readiness kan vara grön medan launch är av.
Basic Auth/HTTPS på hosten ska också skydda probe-URL:erna; anslutning utifrån
visar först åtkomstskyddets svar. Dessa två probes är inte installerade på hosten nu.

DB-readiness öppnar en separat READ ONLY-transaktion med SELECT, inte migration.
Anslutningstimeout är 8 s, SELECT max 2 s per fråga. Den kontrollerar P12:s
bootstrapmarkör och operativa tabeller via frågorna. Den är ingen fullständig
schema-/FK-/restorekontroll: använd P12/P13 för det. Den kräver inte tomma
affärstabeller och fungerar därmed även efter framtida beställningar.

## Privat loggning och införande före godkänd drift

1. Deploya granskad main till privat release enligt P12:s hostmodell när
   hostverifiering återupptas. Deploymentpaketen har kompletterats med
   `production.php`, `p14_admin.php` och `p15_observability.php`; inga workflows
   har körts mot Simply i P15. Webbkandidaten och staging behåller åtkomstskydd.
2. Skapa en separat befintlig katalog, exempelvis `.bois-production/logs/operations`,
   med mode 0700, utanför public_html. Sätt privat config:
   `observability.enabled=true`, absoluta `log_dir` och verklig `public_root`.
   Exempelfilen innehåller inga credentials och lämnar observability av.
3. Behåll privat PHP error_log, display_errors=0 och log_errors=1 enligt P12.
   Utan opt-in skrivs endast fasta JSON-fält till PHP:s befintliga error_log;
   logglagring krävs dessutom för en framtida godkänd production-launch.
4. Kör privata CLI-readiness och syntetisk HTTP-smoke. Bekräfta 0700/0600,
   otillgänglig publik logg/config, request-ID-korrelation och read-only DB.
   Ett SELECT-only konto används i CI; motsvarande hostkonto är fortfarande
   del av den uppskjutna P12-verifieringen.
5. Verifiera backup/restore/readiness enligt P13 och besluta vem som äger
   driftkontrollerna. Automatiserade externa larm eller cron är inte installerade.

`operations-YYYY-MM-DD.jsonl` har mode 0600, exklusiv append-lock och max 10 MiB
per UTC-dygn. Katalog-/filsymlänkar, publika paths, grupp/other-behörighet och
hardlinks nekas. Katalogen skapas inte av appen. Loggen är begränsad till tid,
request, component, event, status och duration_ms. Komponenter är fasta:
commerce/admin/payment/webhook/outbox/probe. Händelser är request_started,
request_completed, internal_error, fatal_error, outbox_failed, webhook_failed,
ops_failed. Ingen URL/querystring, IP, User-Agent, body, exceptiontext, cookies,
order-/kund-/betalnings-ID, credentials eller persondata skickas till denna logg.
P14:s privata audit är separat och innehåller principal-ID och kontrollerad action;
dess request-ID korrelerar nu med HTTP-svaret/operationsloggen.

Loggfel före en mutation nekar anropet med generiskt 500. Completion-loggfel
efter commit kan inte återställa en utförd affärsåtgärd; den redan skrivna intent-
raden och P14/DB-status måste stämmas av före retry. En fast emergency-rad
`operational_log_unavailable` + request-ID går till PHP error_log. Fel där också
den loggen är otillgänglig kräver host-/diskkontroll; ingen fjärrlarmgaranti finns.
Fatal-hanteraren loggar en fast kod och ingen PHP-feltext. Fel före att modulen
kan laddas måste upptäckas via liveness/hostens privata PHP-logg.

Outbox- och webhook-exceptions lagras numera även i DB som fasta
`transport_failed`/`webhook_processing_failed`, inte transporterade feltexter.
Gamla befintliga last_error-rader gallras eller migreras inte i P15. Betalnings-
och outbox-fel upptäcks både genom operationsloggen och read-only kökontroll.

## Larmtrösklar och första åtgärd

| Signal | Tröskel | Åtgärd |
| --- | --- | --- |
| Liveness/HTTPS | 2 fel i följd med 1 min mellanrum, eller TLS-certifikat <14 dagar kvar | Kontrollera Simply/PHP, skyddets credentials, giltighet och senaste release |
| Readiness | 2 st 503/500 i följd med 1 min mellanrum | Kör privat CLI, isolera DB/storage/queues; öppna inte butik |
| Disk | <256 MiB eller <5 % ledigt ger readiness false; <50 MiB eller <1 % nekar loggning | Frigör endast verifierat arkiverat material, kontrollera quota/inodes i panelen |
| Logg | 8 MiB varning, 10 MiB stoppar nya loggade requests; någon operational_log_unavailable | Arkivera privat och verifiera checksum/läsbarhet; byt fil kontrollerat under paus |
| PHP/API | något internal_error/fatal_error; >=5 stycken 5xx/5 min eller >2 s i >=5 anrop/5 min | Korrelera request-ID, jämför release; DB/readiness först |
| Outbox | någon FAILED; due PENDING/RETRY äldre än 15 min | Kontrollera transport/worker och status innan begränsad retry |
| Webhook/payment | någon ERROR; PROCESSING eller PAID/PENDING-effekt äldre än 5 min | Kontrollera providerstatus/idempotens och DB-effekter privat innan replay |
| Backup | Senaste verifierade backup >24 h; saknad läsbar dump/filarkiv | Följ P13, dokumentera ålder/restorebevis; stoppa ändring som kräver backup |
| Worker | Ingen lyckad worker-run i senaste planerade intervallet +15 min | Kontrollera att schemat faktiskt finns; request_completed/outboxkomponent är bevis |

Trösklarna för disk, köer och bootstrapmarkör är implementerade i readiness.
Övriga är manuella drifttrösklar, inte en installerad monitoringtjänst.
Köfel kan vara historiska men får inte automatiskt förklaras bort: en ansvarig
måste stämma av och markera/retrya enligt befintligt arbetsflöde.
Vid 429 väntar kontrollen minst Retry-After; probes är inget belastningstest.
Felaktiga credentials/DB-avbrott ger generiska 503 för readiness; commerce-fel
ger generiskt 500 utan DB-feltext. Production-worker gör inga startupmigrations
och kör inte när launch är stängd. Betalningsnotifieringar följer befintliga
signature/provider-grindar; P15 aktiverar inte dem.

## Incidentrunbook

1. Notera tid, symptom, request-ID, release-SHA och påverkan i privat incidentlogg.
   Kopiera aldrig kundunderlag/credentials till repo, Actions eller offentligt ärende.
2. Vid pågående affärsrisk: följ P12:s stängningsförfarande för nya köp. Behåll
   giltiga betalningsnotifieringar för tidigare köp enligt design; stängning är
   inte en uppmaning att radera eller återställa databasen.
3. Kör liveness, readiness/privat CLI och kontrollera PHP-/operationslogg,
   quota/inodes, DB-anslutning, deployfiler/rättigheter och Simply-status.
   Kör aldrig bootstrap/migration som felsökningsförsök mot live.
4. För DB/diskfel: rätta credential/host-/utrymmesproblemet privat. Vid utredning
   av skrivfel kontrollera order, betalning och outbox-status före retry. Återbetalning,
   webhook-replay och mailretry får inte upprepas blint.
5. Vid releasefel: följ P13:s privata backup/restore och P12:s rollback, med
   avstämning av externa betalningar. P14-sessioner/MFA-state måste hanteras
   enligt P14-runbook efter restore. Radera inget utan läsbar verifierad backup.
6. Återkontrollera symptom, readiness, idempotens och loggning, dokumentera
   orsak/åtgärd/bevis och ansvarig. Ny launch kräver ursprungliga beslutsgrindar;
   grön readiness eller återställd server ger inget godkännande för Stripe live/DNS.

## Första skarpa veckan efter separat launchbeslut

- Varje morgon och kväll: åtkomst/HTTPS, liveness, readiness/CLI, DB/disk/quota,
  PHP/operationsfel, outbox/webhook/effektstatus och senaste lyckade worker.
- Dagligen: verifierad privat backupålder/läsbarhet, 0600/0700 och publika nekanden,
  P14-audit/sessionrevoke, serverklocka, loggstorlek, nya ändringar och incidenter.
- Följ första riktiga ordern hela vägen i privat admin utan att skapa nya köp
  för kontrollen; stäm av providerbetalning, medlems-/batch-/mailstatus.
- Syntetisk köp-/refundkontroll sker i separat staging/testläge. Genomför inte
  testbetalningar med Stripe live för driftkontrollen.
- Vid veckans slut: dokumentera observerade volymer/latens, avvikelser och
  justering av trösklar med ansvarig. Ingen automatisk gallring eller betald tjänst
  införs här; retentionbeslut/implementation tillhör P16.

## Verifiering och avgränsning

P15 CI kör verklig PHP HTTP, MySQL 8.4 och SELECT-only användare: minimal JSON,
server-ID som ignorerar klientens ID, 405, stängd commerce, oförändrat P13-schema/
rader/FKs före och efter probes, generiskt DB-fel, due/failed outbox, transport-
och webhook-felkoder med PII/secret-canaries samt privat permissions-, symlink-
och verklig loggkapacitetsvägran före mutation. Disktrösklar testas på båda sidor;
ingen fysisk hostingdisk fylls. Liveness fortsätter svara när DB är nere.
Inga logg-/data-/konto-artifacts publiceras. Faktiskt production-TLS, cron,
quota/inodes, hostkonto, flödes-E2E och bemannad drift är inte bevisade av CI.

P15 run 37157440871 passerade på första kodversionen. Slutliga korrigerade
P14/P15- och regressionsreferenser förs in när samtliga kontroller är verifierade.
