> Beslut 2026-10-08: 30 dagars retention för nya krypterade backuper och avgränsat SELECT-only-undantag är godkända. Villkor i [P18B-DECISIONS-20261008.md](P18B-DECISIONS-20261008.md). Inga backuper har raderats och P18B är inte färdigverifierad.

## Aktuell driftverifiering 2026-10-07

PR46 är mergad. Windows-task är installerad och ett verkligt tidsutlöst
relayprov har passerat. Simply-backupen 03:00 är observerad. Crontab är nu
03:00 och 15:00 för marginal till RPO; nya schemat är ännu inte observerat.
Fullständigt
DR med DB + runtime + HTTPS från Besovida passerade på 216,437 sekunder,
inklusive korrigerade försök; production och ursprungliga testdata bevarades.
Retention, SELECT-only och sammanhängande hostbrowseracceptans återstår.
P18B/P18 och publik P20-aktivering är fortsatt BLOCKED. Se [aktuella bevis](P18B-OPS-EVIDENCE-20261007.md).

Äldre motstridiga uppgifter nedan är historik.

# P18 – installerad backupdrift 2026-10-06

Driftansvarig: Albert, uttryckligen accepterat. Mål: RPO högst 24 timmar och
RTO 4 timmar. Målen kräver löpande övervakning och ett tidsatt DR-prov; de får
inte beskrivas som garanterad uppmätt återställningstid.

## Installerat på Simply

Den dedikerade productionprodukten socen.se kör privat
`.bois-production/p18-daily-backup.php`, granskbar källa i
`scripts/p18-host-backup.php`. Scriptet måste placeras i den privata runtime-
katalogen, där befintlig config, CNF, current-release och separat 32-byte AES-
nyckel finns. Det får inte läggas i public_html eller köras genom HTTP.

Installerad crontab:

```text
0 3 * * * umask 077 && /usr/bin/php /var/www/socen.se/.bois-production/p18-daily-backup.php > /var/www/socen.se/.bois-production/logs/p18-backup-cron-latest.log 2>&1
```

Hosttid var CEST vid kontrollen. Tidigare crontab var tom och har sparats
privat före ändring. Inga mail-/affärsworkers schemaläggs. Manuell körning av
samma program och verifierad återläsning från offsite passerade; **första
tidsstyrda körningen är ännu inte observerad**.

Programmet tar single-transaction MySQL-dump med privat CNF, arkiverar faktisk
release, productionconfig, admin-state, operationslogg, Basic Auth, public UI
och backupkonfiguration samt crontab. Produktionsschema/rader jämförs före/
efter. Intern manifest med SHA256 följer med. SQL och runtime förblir privata.
Kataloger är 0700 och filer 0600; dumpen och tar läses före kryptering.

AES-256-GCM-format: ASCII `BOIS_BACKUP_AES256_GCM_V1` följt av LF, 12-byte
slumpad nonce och ciphertext med avslutande 16-byte tag. Header används som
AAD. Autentiserad dekryptering måste lyckas innan något återställs. Nyckeln
ingår inte i arkivet och får inte överföras till backupservern tillsammans
med filen. Krypterad fil publiceras först efter lokal decrypt-kontroll.

Senaste verifierade backup:
`bois-daily-20261005T223322Z-cd76ea8.aesgcm`, SHA256
`eeda45b2eee381abb652c4cabb7dace3777ee62d8adb8abbd5f423062371cbeb`.
Verifierad från Besovida-återläsning med separat Python AESGCM: dekryptering,
alla interna hashar, filrättigheter och manipulerad tags fail-closed.

## Albert kontrollerar dagligen

Kontrollera privat `p18-backup-status.json`, att senaste lyckade backup är
yngre än 24 timmar, `p18-backup-last-failure.json` om den finns och privat
cronlogg. Kontrollera offsitefilens verkliga datum och verifierad hash, inte
bara att en gammal fil finns. Nuvarande status anger `offsite_automatic=false`.
Ingen automatisk alarmleverans är installerad.

Backuper bevaras tills retention beslutats; ingen automatisk radering finns.
Programmet vägrar vid sammanlagt 2 GiB backupdata i sina två kataloger och
vid ett bundle över 100 MiB. Detta skyddar utrymmet men kräver att Albert
agerar på felstatus innan RPO överskrids. Private feldata skrivs bara på host.
Detta script vägrar även om production öppnas; framtida go-live måste granska
backupmodellen för öppnad drift innan flaggorna ändras.

## Offsite och kvarstående blockerare

### Förberedd Windows-relay, ännu inte schemalagd

`scripts/p18-offsite-relay.py --private-dir <privat-BoIS-katalog>` använder
befintlig Simply-nyckel bara på Alberts dator och Besovidas SFTP-begränsade
nyckel. Den kontrollerar backupens 24-timmarsgräns, checksumma, AES-GCM och
interna hashar före överföring, samt faktisk offsite-återläsning efteråt.
En befintlig korrupt offsitefil skrivs inte över. Inga backuper raderas,
credentials provisioneras inte och programmet installerar inget schema.

Manuellt host/offsiteprov passerade för
`bois-daily-20261005T225830Z-cd76ea8.aesgcm`. Sex syntetiska negativa/integrations-
tester passerade lokalt och ingår nu i P18-CI. Privata statusfilen heter
`p18-offsite-relay-status.json`. Pythonmiljön som verifierades använder
`cryptography==50.0.1`; använd en betrodd befintlig installation.

Albert har beställt fortsatt P18B-arbete. En guarded Windows-installationsväg
är nu förberedd i `scripts/install-p18-offsite-task.ps1`, men den är **inte
installerad från molntråden**. Installern är preview-only utan `-Install`,
använder begränsad Interactive-principal utan lagrat Windows-lösenord och
förbereder körning vid inloggning samt dagligen 00:15, 06:15, 12:15 och 18:15. Nackdelen
är explicit: reläet kan inte köras före Alberts första Windows-inloggning efter
omstart.

`scripts/p18-offsite-cycle.ps1` kör relay + fail-closed health check. Vid fel
skriver den endast privat `p18-offsite-alert.json`, returnerar felkod och försöker
visa en lokal Windows-notis via `msg.exe`; ingen extern mail/webhook skickas.
`scripts/p18-offsite-health.py` kräver verifierad offsite-readback, noll
raderingar och färsk relay/backup. Dess 30-timmarsgräns är en teknisk
övervakningströskel för sex-timmarscadencen, **inte** ett retentionbeslut.

Backupretention är fortsatt obeslutad och ingen automatisk radering finns.
Inga nycklar till bred productionåtkomst har flyttats till Besovida. Den
ursprungliga blockeraren nedan avser fristående server-till-server-hämtning.

Mål: Alberts Besovida-server, `/home/albert/Bois`, privat 0700. Två aktuella
krypterade backupfiler finns där med 0600. Serverns hostnyckel är verifierad
mot Termius; dedikerad clientnyckel till servern tillåter endast SFTP.
Överföring och faktisk återläsning/dekryptering är genomförda. Ingen åter-
ställningsnyckel eller bred productionnyckel finns på backupservern.

Automatisk överföring är ännu **inte installerad**. Simply ignorerade en
vanlig authorized_keys-fil med en forced-command-nyckel i provet; panelen
visar bara allmänna SSH-nycklar. Providerstödd begränsad läsning av krypterade
backuper behöver lösas innan schemalagd hämtning kan införas. Tills dess är
överföringen från Simply till Besovida manuell och offsite-RPO inte bevisat.

Separat key escrow är bekräftad av Albert 2026-10-06: nyckeln är sparad som
vanlig lösenordspost i iPhones Lösenord, i Base64-format. Vid återställning
avkodas texten från Base64 till den ursprungliga 32-byte AES-nyckeln. Nyckeln
får inte behandlas som TOTP-inställningsnyckel eller skickas i chatten.
Bekräftelsen av sparandet är Alberts uppgift; den tekniska dekrypteringen
verifierades före sparandet.

Beslutad retention, övervakning/alarmering och
tidsatt katastrofåterställningsprov återstår. Återställ endast till isolerad
privat testmiljö först och jämför manifest/schema/ledger/FK. Restore till
production ingår inte i dessa tester. P18B/P18 är BLOCKED tills kvarstående
drift- och hostacceptanskrav är uppfyllda.
