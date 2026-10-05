## Aktuell styrning 2026-10-05

## Aktuell lokal hoststatus 2026-10-06

Stängd production på dedikerad socen.se-produkt är uppgraderad till main
`cd76ea8ffedd6ca63eaf3905eda40031327421dc` efter PR42. Verifierad privat
backup, isolerad restore, rollback, readiness och syntetiska hostprov finns.
Verifierad krypterad offsitekopia finns på Besovida. Daglig Simply-backup är
installerad. Albert har accepterat driftansvar, RPO 24 h och RTO 4 h som mål.
Separat nyckelförvaring i iPhones Lösenord bekräftad av Albert 2026-10-06.
Albert har nu beställt fortsättning genom P20. Go-live-underlag förbereds,
men publik aktivering kräver kvarstående P18B-bevis, P19-verksamhetsbeslut
samt separat godkännande av Stripe live och slutlig DNS-cutover.
Se [P20-plan och lanseringsgrindar](P20-GO-LIVE.md).
P18A är DONE; P18B/P18 är fortsatt BLOCKED vid SELECT-only, automatisk
offsiteöverföring, övervakning/retention och full hostbrowseracceptans. Se [nya hostbevis och begränsningar](P18B-LOCAL-HOST-EVIDENCE.md).
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

# P14 – personliga konton, roller och MFA-readiness

Status: DONE. Baslinje main `c6e5787fe47cc46642a7b7bafbefbf9221c83eb8`.
PR [#32](https://github.com/AlbertAndersson/Tran-s-bois/pull/32).
Ingen productiondeploy eller kontoregistrering utförs. Inga riktiga konton seedas.
P12:s uppskjutna verifiering består och production förblir stängd.

## Implementerad modell

Servern väljer alltid personlig autentisering i `mode=production`. Bearer- eller
`X-Bois-Admin-Token`-nyckeln ger ingen productionbehörighet, inte ens som fallback.
Staging behåller sin befintliga separata nyckel. Personligt läge kan provas med
syntetiska konton när `personal_admin.enabled=true` i isolerat testläge.

| Roll | Behörighet |
| --- | --- |
| operator | Läsa order, katalog, sortiment och batchöversikt |
| club_admin | Ovanstående, medlemsverifiering, förmånsstatus, Nordic-export och manuell batch/CSV |
| superadmin | Ovanstående, betal-/salesöversikt, readiness, refund, outbox/worker och sessionsrevoke |

Alla roller nekas mock-simulate-paid och okända åtgärder i personligt läge.
Serverns actionlista bestämmer rättigheter; klienten kan inte skicka roll eller
identitet. Operatören får inte läsa medlems-/betalnings-/salesöversikt eller exportera
Nordic-data. Orderöversikten innehåller kontaktuppgifter och rollen ska därför
endast ges till en person som behöver dessa uppgifter. Konto-/rollprovisionering
sker via teknisk privat filhantering, inte en generell webb-IAM eller självregistrering.

Lösenord verifieras med PHP `password_verify`: bcrypt minst cost 12 eller
Argon2id minst 64 MiB. TOTP enligt [RFC 6238](https://www.rfc-editor.org/rfc/rfc6238)
krävs vid varje ny login: sex siffror, SHA-1, 30 sekunder, högst ± ett tidssteg.
Senast accepterade tidssteg lagras under lås; samma/äldre OTP nekas. Production
vägrar återanvända samma TOTP-secret för två konton. Hemligheter finns endast
i privat registry, aldrig i repo eller klientens konfiguration.

Slumpmässig sessionscookie: `__Host-BoISAdmin`, Secure, HttpOnly, SameSite=Strict,
Path=/, ingen Domain. Endast tokenhash lagras på servern. CSRF-token hålls i
klientminnet; skrivningar kräver både exakt tillåtet Origin och `X-Bois-CSRF`.
Login kräver också tillåtet Origin och HTTPS utom i uttryckligt syntetiskt testläge.
Productionkontroller kräver HTTPS. Inga forwarded headers används för TLS eller IP.

Sessionen har 15 minuters idle-timeout och åtta timmars absolut maxlivslängd.
Logout tar bort serverns session och cookie. Superadmin kan återkalla samtliga
sessioner för ett konto. Disable, ändrad roll, epoch eller lösenord/MFA-secret
ogiltigförklarar befintlig session vid nästa anrop. Ny login genererar en ny
slumpmässig token; klientstyrt sessions-ID accepteras inte.

Login spärras efter fem fel per konto/identifierare under 15 minuter. Befintlig
privat IP-HMAC-ratebegränsning skyddar även authvägarna. State är begränsad till
256 sessioner, 512 samtidiga failurebuckets och 50 konton; lagringsfel nekar åtkomst.

Privat appendlogg registrerar actor-ID, serverbestämd roll, action, tid och
slumpmässigt request-ID. Skrivvägar registrerar intent före DB-åtkomst och
success/failed efter svaret. Login, logout, nekad roll och revoke omfattas.
Inga lösenord, OTP, sessions-/CSRF-token, IP, user agent, kunduppgifter eller
requestbody loggas. Auditfel före en mutation stoppar den. Intent utan completion
kan uppstå vid processavbrott eller auditfel efter DB-commit: avstäm request-ID
och faktisk affärsstatus före återförsök. Loggen är inte en manipulationssäker WORM-logg.

## Microsoft/Google/OIDC-bedömning

Google dokumenterar OIDC Authorization Code och ID-tokenvalidering samt
clientregistrering. Den kanoniska ID-tokenlistan ger inget generellt MFA-bevis;
en lyckad Google-inloggning får inte själv tolkas som verifierad MFA.
Se [Google OIDC](https://developers.google.com/identity/openid-connect/openid-connect)
och [ID-tokenreferens](https://developers.google.com/identity/openid-connect/reference).

Microsoft skiljer tokenversioner och dokumenterar `amr` för v1-token.
En framtida integration måste verifiera utfärdare, tenant, audience, signatur,
nonce, tider och rätt MFA-claim/policy; v2-login ensam räcker inte.
Se [Microsoft ID-token claims](https://learn.microsoft.com/en-us/entra/identity-platform/id-token-claims-reference).
Appregistrering, tenantägare, tillgängliga licenser och faktisk MFA-policy är
inte verifierade i denna etapp. Ingen kostnadsfri befintlig tenant har antagits.

P14 implementerar därför en liten lokal lösenord+TOTP-modell med befintlig PHP,
utan ny tjänst, paketinstallation eller extern kontoregistrering. OIDC är en
möjlig senare ersättning; ingen halvfärdig OIDC- eller MFA-claimfallback aktiveras.
Inga externa identitetsåtgärder blockerar den syntetiska P14-acceptansen.

## Privat provisionering och återkallning inför aktivering

Detta är en runbook, inte ett godkännande att skapa konton eller öppna production.

1. Namnge kontoansvarig och godkänn person/roll. Använd ett individuellt ASCII-ID,
   aldrig e-post från request som behörighetsnyckel. Verifiera identiteten separat.
2. Skapa privat katalog utanför `public_html`, repo och synkad dokumentyta:
   katalog 0700, registry/state/audit 0600. Sätt `personal_admin.users_file`,
   `state_dir` och `public_root` i privat runtime. Registry är en JSON-map med
   `enabled` (bool), `epoch` (positivt heltal), `role`, `password_hash`, `totp_secret`.
   Exempelkonfigurationen är avstängd och innehåller inga konton eller credentials.
3. Välj individuellt starkt lösenord, minst 15 tecken, genom privat process och
   använd `password_hash` med godkänd cost. Slumpa minst 20 bytes individuell
   TOTP-nyckel och Base32-koda den. Leverera enrollment privat efter ID-kontroll,
   inte via logg, repo, URL, mejlutskick eller GitHub artifact. Ingen enrollment-UI
   eller automatisk recovery är byggd; teknisk ansvarig hanterar detta kontrollerat.
4. Gör atomic registryreplacement och kontrollera privata rättigheter. Vid
   återhämtning/rolländring: höj epoch, rotate credential och återkalla sessioner.
   Borttappad MFA får aldrig leda till lösenord-only login eller delad fallbacknyckel.
5. Synka serverklockan, verifiera HTTPS/tillåtna Origins, privat audit/readiness
   och NTP-avvikelse. Konfigurera klienten med `adminAuth:'personal'`. Backendens
   productionval är bindande oavsett klientflagga. Genomför syntetisk smoke först.
6. Deploya granskad P14-kod till privat productionrelease före eventuell launch.
   Nuvarande hostrelease är fortfarande den stängda P12-kandidaten. Markera inte
   `production_decisions.personal_admin_mfa` godkänd enbart för att CI är grön.
7. Ta privat backup av registry och audit enligt P13. State innehåller sessionshashar
   och OTP-replayhistorik: efter en incidentrestore måste alla sessioner återkallas
   och MFA-replayläget avstämmas. Återställ aldrig registry publikt eller till staging.
   Konto-/auditretention och kontrollerad backupåtkomst beslutas före verklig drift.

Ingen ändring av commerce-schema/migrationsledger sker i P14. P13:s 24 tabeller
och åtta migrationer består. Inga livebetalningar, mail, DNS-ändringar eller kostnader.

## Verifiering

`tests/p14-admin-smoke.php`: RFC:s SHA-1-testvektorer inklusive tid efter 2038,
password/MFA/replay, roller, CSRF, timeout, logout, revoke, disable/role/credential
ändringar, privata paths/audit och verkligt HTTP-authflöde före DB-anslutning.
`tests/p14-browser.test.cjs`: verklig Chromium-login med syntetiska konton,
serversession/cookie, serverstyrda roller, reload och logout mot isolerad MySQL.
P14 workflow exporterar inga konto-/state-/audit-artifacts.

Slutlig kod-SHA `899913d716d9f820d7c1fda1ddad4392bc0675c6`: P14 [37156273030](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37156273030), jobb `111300181791`, success inklusive PHP/HTTP och Chromium. P2 37156273007, P3 37156273005, P4 37156273078, P5 37156272994, P6 37156272925, P7 37156273114, P8 37156272949, P9 37156272981, P12 37156273026, P13 37156273006 och Security 37156272987: samtliga success. Inga P14-blockerare; verklig kontoprovisionering och godkänd aktivering återstår inför drift.
P15 är nu DONE via PR #33; se P15-OPERATIONS.md. P16 har inte startats.

PR #32 merge: `7727dedd50fda3919b3915bb361d3e921d478734`. Main före P14:
`c6e5787fe47cc46642a7b7bafbefbf9221c83eb8`. Koden är mergad, men ingen
productiondeploy har gjorts. P15 är nu DONE via PR #33; se P15-OPERATIONS.md. P16 har inte startats.