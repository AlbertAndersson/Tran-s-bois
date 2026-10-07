# P18B – observerad drift och fullständigt DR-prov 2026-10-07

Detta är aktuell status och ersätter äldre uppgifter om oinstallerad Windows-
task och oobserverad backup. P18B är fortfarande BLOCKED vid retentionbeslut,
SELECT-only-beslut och sammanhängande hostbrowseracceptans. Ingen go-live sker.

## Windows och tidsstyrd backup

PR46 är mergad; utgångspunkt är main
`6127115b907bf63400bf583fd965706538f04337`. Appen på Simply är fortsatt
`cd76ea8ffedd6ca63eaf3905eda40031327421dc`; relayändringen ändrar inte appen.

Task `Tranas BoIS P18 offsite relay` är installerad på Alberts Windows-dator.
Principal är `AzureAD\AlbertAndersson`, Interactive/Limited, utan sparat
Windows-lösenord. Ordinarie triggers: inloggning samt 00:15, 06:15, 12:15 och
18:15 lokal tid. Datorn måste vara påslagen, ansluten och Albert inloggad.
Nästa ordinarie körning vid kontrollen: 2026-10-08 00:15 CEST.

Scripts körs från en privat kopia utanför OneDrive och repo:
`C:\Users\AlbertAndersson\.codex\private\bois-production\p18-task-release-636fca8\scripts`.
Kopian bygger på PR46 head `636fca8bce223f28170ee21abbf5aba3d2ae72bb` med den
Windows-SFTP-rättning som dokumenteras nedan. Mappnamnet är inte ett bevis för
att den korrigerade relayfilen är identisk med ursprunglig PR46.

Verklig Simply-cron skapade backupen 2026-10-07 03:00:03 CEST:
`bois-daily-20261007T010002Z-cd76ea8.aesgcm`, SHA256
`3d2eda62325608e58cc7ca67807f1bd7ca65b971dc76d904d87ee70d0a68bad5`.
Privat cronlogg rapporterade
`P18_SCHEDULED_BACKUP_SQL_RUNTIME_STABLE_HASH_AES_GCM:pass`.

Efter provet ändrades SSH-crontab från dagligen 03:00 till **03:00 och 15:00**.
Ett dygn mellan backups kombinerat med relay först 06:15 kan annars ge en
offsitekopia som är 27 timmar 15 minuter gammal, över RPO-målet. Två backups
per dygn ger beräknad maximal ålder 15 timmar 15 minuter med den installerade
relaycadencen när dator/anslutning och båda jobben fungerar. Det är en
beräkning, inte en fler-dygnsmätning eller SLA. Crontab före ändring sparades
läsbart privat, enbart den exakta backup-raden ändrades och nya crontab
återlästes identiskt. Privata bevis: `p18-cron-before-margin-20261007.txt` och
`p18-cron-after-margin-20261007.txt`. Simply-panelens separata URL-cronvy är
tom och beskriver inte detta SSH-jobb. Det nya två-gånger-per-dygn-schemat har
ännu inte observerats; den ovanstående 03:00-körningen gällde tidigare schema.

Efter manuell Task Scheduler-körning utlöstes en kontrollerad engångstrigger
2026-10-07 20:55:51 CEST. Task Scheduler rapporterade exitkod 0; relayens
faktiska Besovida-återläsning verifierades 20:55:55 CEST med SHA256, AES-GCM
och intern manifestkontroll. Engångstriggern togs bort efter provet och de
fem ordinarie triggers återlästes. Detta är ett observerat tidsutlöst prov,
inte ännu en observerad ordinarie sex-timmarskörning över flera dygn.

Hostens generatorstatus `offsite_automatic=false` beskriver hostscriptet:
Windows-tasken är separat. Health gate passerade. En tidigare verklig task-
failure gav privat alertfil; framgång tog bort felmarkören. Lokal `msg.exe`-
notis försöktes vid failure, men dess synliga leverans är inte verifierad.
Ingen extern notifiering skickas. Albert måste fortsatt kontrollera status.

Privata bevis finns i BoIS private-katalogen:
`p18-task-installed-20261007.xml`, `p18-timed-task-evidence-20261007.json`,
`p18-offsite-relay-status.json` och installerade scripts. Inga secrets finns
i denna dokumentation. Inga gamla backuper raderades.

## Reproducerat fel och rättning

Windows OpenSSH SFTP rapporterar frånvarande mål som
`File "<begärd fil>" not found.`. Relayens tidigare kontroll kände bara igen
`No such file`, vilket stoppade första riktiga taskkörningen före uppladdning.
Rättningen godtar exitkod 1 med exakt missing-svar för den begärda get-filen.
Permission denied och andra fel fortsätter att neka uppladdning. Sju relay-
tester, sex health-tester och två Windows-tasktester passerade lokalt.

## Fullständigt tidsatt DR

Ny faktisk SFTP-återläsning av ovanstående backup från Besovida användes.
AES-GCM, externa/interna kontrollsummor och arkivmedlemmarnas säkra paths
verifierades innan SQL eller runtime återställdes. Återställningsnyckeln
förblev separat och privat.

Simply-produkten tillåter två DB:er; försök att skapa en tredje DR-DB nekades
av panelen utan uppgradering eller kostnad. Därför användes befintlig isolerad
syntetisk DB `socen_se_db` tillfälligt. Dess befintliga SQL och runtime/config
säkerhetskopierades och verifierades före återställningen. Productiondatabasen
`socen_se_db_BoIS_prod` och staging på alberiq.se återställdes aldrig.

Provet omfattade:

- Restore av backupens SQL till explicit isolerat DB-mål; ingen USE/CREATE/
  DROP DATABASE accepterades i input.
- Alla 24 tabellers schema, rader, migrationsledger och FK-kontroller jämfördes
  med en read-only snapshot av production.
- Restore av runtime, release, public UI och Basic Auth; privat config fick
  endast isolerat DB-mål och lokala DR-paths. Produktionsflaggorna var stängda.
- Verklig separat HTTPS-tjänst: liveness och readiness 200, catalog och
  admin_orders 503 enligt stängda gates; readiness-body `ready=true`.
- DR-ytan stängdes efter provet. Testdatabasens ursprungliga syntetiska data
  återställdes och matchade dess snapshot före provet. Production-snapshot
  förblev identisk och dess HTTPS 200/200/503/503 verifierades igen.

Total uppmätt tid från start av offsite-återläsning till slutfört och registrerat
prov: **216,437 sekunder**. Tiden inkluderar två stoppade försök och rättningar
i engångsprovskriptet: rekursiv public-katalog, korrekt readiness-field och
restored auth-path som Apache kan läsa. Varje försök återställde testdatabasen.
Det sista hostprovet tog 3,181 sekunder. RTO-målet 4 timmar uppnåddes inom
denna provscope; ingen garanterad SLA följer av en mätning.

Detta verifierar DB + runtime + HTTPS-tjänst på befintlig Simply-host. Det
verifierar inte återuppbyggnad av ett förlorat providerkonto eller ny host,
publik DNS-cutover, öppnad commerce eller Stripe live.

Privata bevis och läsbara återställningsfiler:
`C:\Users\AlbertAndersson\.codex\private\bois-production\p18-full-dr-20261007`.
Hostens försök/pre-restore-backuper är bevarade under
`.bois-production/p18-full-dr-20261007*`; sista rapporten finns i
`p18-full-dr-20261007-retry2/host-report.json`. DR-publicytorna är stängda.

## Kvarstående beslut och acceptans

Retentionsförslag till Albert: 30 dagar för nya dagliga backuper, bevara äldre
befintliga backuper separat. **Inget beslut antas och ingen radering byggs.**

socen.se separerar production från staging med egna produktcredentials.
SELECT-only avser däremot lägre rättigheter inom productionprodukten. Simply-
kontot har fortfarande ALL på produktens två DB:er, saknar CREATE USER/GRANT
OPTION och panelen saknar separat användarhantering. Providerlösning eller
Alberts uttryckliga acceptans av dokumenterat tekniskt undantag återstår.
Förslag till kompensation: granskade CLI-kontroller i READ ONLY-transaktioner,
privata credentials, nekad stagingåtkomst och auditerad drift. Det ersätter
inte DB-serverns rättighetsbegränsning och får inte kallas SELECT-only.

Sammanhängande hostbrowseracceptans är ännu inte genomförd i denna körning.
Den skyddade syntetiska ytan är stängd och dess data bevarade. Tillstånd att
köra befintligt Playwright-test med privat Basic Auth och Edge headless har
efterfrågats eftersom den inbyggda browsern inte klarar denna autentisering.

P18A DONE; P18B/P18 och publik P20-aktivering BLOCKED. P19-verksamhetsbeslut,
Stripe live och slutlig DNS-cutover kräver fortsatt sina respektive beslut.
