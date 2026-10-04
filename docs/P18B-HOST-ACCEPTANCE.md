# P18B – hostacceptans och åtkomstblockerare

## Aktuell status 2026-10-05 Europe/Stockholm

**P18B: BLOCKED. P18A: DONE. P18: BLOCKED vid hostacceptans. P19: öppna verksamhetssvar. P20: BLOCKED.**

Albert beställde P18B och uppdatering av dokumentationen. Granskad main var
`4e4e2349631d434160b77bd9b2b2d3e7be7db349` (PR40); inga öppna PR:er fanns.
Ingen hostrelease, DB-migration, credentialrotation, extern transport eller
aktivering utfördes i denna körning.

## Faktiskt genomförd kontroll

Befintlig workflow `Simply - validate Tranås BoIS closed production readiness`
kördes på main med `VALIDATE_CLOSED_BOIS_PRODUCTION`.
[Run 37241703902](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37241703902),
jobb `111551551547`, avslutades med failure i secretinventeringen.
Checkout och cleanup lyckades; configbygge och DB-readiness hoppades över.

Följande saknas för workflowens nuvarande körväg:
- `BOIS_PROD_DB_HOST`
- `BOIS_PROD_DB_NAME`
- `BOIS_PROD_DB_USER`
- `BOIS_PROD_DB_PASSWORD`
- `BOIS_PROD_ADMIN_TOKEN`

Loggen bekräftar `SECRETS_EXPOSED: no`,
`STRIPE_LIVE_CREDENTIALS_READ: no`,
`EXTERNAL_FUNCTIONS_ACTIVATED: no` och
`Closed production credentials unavailable; no database connection attempted`.

Detta visar att GitHub-runnern saknar åtkomst, inte att hostens befintliga
databas/config saknas eller är felaktig. P12:s privata produktionsåtkomst är
dokumenterad på Alberts lokala dator under
`C:\\Users\\AlbertAndersson\\.codex\\private\\bois-production\\`.
Den är inte tillgänglig i denna molnarbetsyta. Använd den befintliga lokala
åtkomsten för fortsättningen; skapa inte nycklar eller publicera credentials
enbart för att få denna workflow grön.

Ett oautentiserat HTTPS HEAD från molnarbetsytan till
`https://socen.se/bois-shop-production/` gav Simply HTTP 455.
Det är inte bevis för 401-skydd eller fungerande app och ingen bypass gjordes.
Ingen autentiserad browser-/API-/SSH-kontroll kunde genomföras.

## Releasepekare – senast dokumenterade, inte ny hostverifiering

| Miljö | App-SHA | Bevis och begränsning |
|---|---|---|
| Skyddad staging | `276558e9b187aeea7d0324b662ab20c6513d125b` | Run37219659212, äldre än full P18A |
| Stängd socen-production | `020446867dcf29b7ebe63a060f8594e8f8da8aff` | P12-dokumentation; måste läsas från host igen |
| P18A kod på main | `4e4e2349631d434160b77bd9b2b2d3e7be7db349` | PR40; main P18-run37236171865, inte hostdeployment |

App-SHA och workflow-/dokumentations-SHA ska registreras separat.
Stagingens 31 äldre syntetiska PAID-gymkort ska bevaras; ingen kvotreset ingår.

## Återstående acceptans och ansvar

Ansvar för fortsättningen: teknisk utförare med Alberts befintliga privata
socen-åtkomst, lämpligen lokal Codex. Driftägare/RPO/RTO/offsite beslutas med BoIS.

| Kontroll | Status | Bevis som krävs |
|---|---|---|
| Aktuella hostreleasepekare, PHP, privata paths och filrättigheter | EJ VERIFIERAT | Läs actual release/config/wrappers utan secretutdata |
| Produktions-DB/grants och separation från staging | EJ VERIFIERAT | Hostreadiness, explicit grants och nekad korsåtkomst |
| SELECT-only konto | EJ VERIFIERAT | Befintligt konto eller dokumenterad teknisk begränsning; inget tyst undantag |
| Privat fullständig backup före installation | EJ VERIFIERAT | DB + release + runtime, kontrollsummor och åtkomstskydd |
| Återställning/rollback på isolerat mål | EJ VERIFIERAT | Matchande schema/rader/filer, fungerande isolerad restore och releasebyte |
| P18A-installation via säker process | EJ UTFÖRT | Manifest, verifierad backup/rollback, actual app-SHA |
| Stängd launch/checkout/externa sidoeffekter | EJ NYVERIFIERAT | Autentiserade negativa kontroller mot faktisk production |
| Personlig admin, MFA och revoke | EJ VERIFIERAT PÅ HOST | Privat införande och prov i separat syntetisk testinstans |
| Observability/loggning och retention-dry-run | EJ VERIFIERAT PÅ HOST | Privata loggar, inga persondata/secrets i rapport, ingen apply |
| Syntetiskt köp/admin/medlemsflöde på hoststack | EJ VERIFIERAT | Egen test-DB; skillnader mot production dokumenteras |
| Workers/cron/backupmodell | EJ VERIFIERAT INSTALLERAT | Faktisk installation/intervall/kontrollansvar eller kvarstående blockerare |
| Extern mailprovider/avsändare | EJ AKTIVERAT | Egen framtida verifiering; äldre syntetisk outbox får aldrig skickas |

CI-bevis från P13–P18A är kvar, men ersätter inte hostbevis.
Ingen samlad RC/tag eller PRODUCTION READY-status sätts.

## Fortsättning för lokal Codex

1. Läs aktuell main, denna fil, `P18-RELEASE-ACCEPTANCE.md`,
   `NEXT-THREAD-PROMPT.md` och SharePoint-utvecklingskön. Jämför opushat lokalt arbete
   innan ändringar. Bevara senare arbete och registrera branch/PR.
2. Använd befintlig privat socen-SSH/config. Skriv aldrig credentials,
   kontaktdata, DB-innehåll, TOTP-seeds eller backups till GitHub/SharePoint/loggar.
   Bekräfta host fingerprint med befintlig källa; ingen säkerhetsbypass.
3. Läs faktisk release och stängda runtimeflaggor. Ta verifierad privat
   DB-/release-/runtimebackup innan uppgradering. Ingen äldre backup får antas aktuell.
4. Kör ovanstående acceptanspunkter och P18B-planen. Produktion ska förbli
   production och stängd; alla fulla köpprov sker i separat syntetisk testinstans.
   Skapa inte testkonton i production och ändra inte demoordrar/årsgräns.
5. Installera komplett P18A-paket först när backup och rollback är verifierade.
   Redovisa hostens begränsningar, inklusive eventuellt SELECT-only-konto och
   offsite/cron. Osäkra eller saknade bevis förblir tekniska blockerare.
6. Uppdatera GitHub-status/runbooks samt båda SharePoint-överlämningarna med
   datum, app-/workflow-SHA, resultat och begränsningar. P18B får markeras DONE
   först när kriterierna faktiskt är uppfyllda.

P20, Stripe live, extern mail, skarp gallring, DNS-cutover och nya externa
kostnader ingår inte. Följ befintlig releaseacceptans; ingen ny affärsacceptans
fabriceras. Erik-utkastet med leverantörsexempel är sparat i Outlook men inte
skickat av assistenten; det är inte ett verksamhetsgodkännande.
