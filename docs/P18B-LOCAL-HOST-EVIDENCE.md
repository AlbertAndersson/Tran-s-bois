# P18B – lokal hostverifiering 2026-10-06

Denna status ersätter tidigare åtkomstblockerare från molnkörningen. P18A är
DONE; P18B och P18 är fortsatt BLOCKED vid återstående driftacceptans. P20
startas inte. Ingen RC/tag eller PRODUCTION READY-status sätts.

## Installerad release och separation

Aktuell main `cd76ea8ffedd6ca63eaf3905eda40031327421dc` är installerad som
stängd production på socen.se efter PR42. Manifestets 60 filer och PHP-
dependencies är verifierade. P18A-paketet ersatte den tidigare P12-releasen;
PR42 korrigerar en reproducerad samtidighetskonflikt i operationsloggen med
en begränsad 200 ms väntan. Tolv utlösta PR-workflows passerade på kod-SHA
`5572ec1947c40da0795b41e65184f566559a3269`, inklusive tvåprocessprovet för
logglås och P18A [run37380543139](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37380543139).

Production: dedikerad Simply-produkt socen.se, DB `socen_se_db_BoIS_prod`.
Staging ligger kvar på alberiq.se med annan DB-host, databas och DB-användare.
Produktionens credential nekas SELECT mot staging. Inget Lamport eller annat
system har installerats på socen.se. Stagingens äldre 31 PAID-gymkort har inte
ändrats eller återställts.

Simply PHP CLI 8.5.11 och MySQL 8.4.11-11 verifierades. Privata release-/config-
och loggfiler ligger utanför public_html, kataloger 0700 och filer 0600.
Public UI har kataloger 0755 och filer 0644, bakom befintlig Basic Auth.
Production använder personlig admin med tomt användarregister och privat
observability. Inga testkonton har skapats i production.

## Backup, restore och rollback

Privat preinstall-backup på Simply:
`.bois-production/backups/p18-preinstall-20261005/` (SQL, tidigare runtime,
release, public UI, Basic Auth-konfiguration, före/efter-snapshot och SHA256).
Ny verifierad backup före PR42-installationen:
`.bois-production/backups/p18-before-cd76ea8-20261006/`.
Läsbara kopior finns lokalt under Alberts privata Codex-katalog för BoIS
production, utanför repo och OneDrive. Tidigare backuper har bevarats.

SQL återställdes till den från början frånvarande standarddatabasen
`socen_se_db`, med explicit frånvarokontroll före skapande. Alla 24 tabellers
schema, rader, migrationsledger och FK-kontroller matchade productiondumpen.
Det är en separat syntetisk test/restore-DB på productionprodukten med samma
Simply DB-användare; den är inte den separata stagingmiljön.
Runtime-/release-/auth-filer återställdes privat och jämfördes med originalet.
Stängd hostrelease byttes faktiskt från P18A till P12 och tillbaka med
read-only HTTP-kontroll. Produktionsdata återställdes inte och förblev
identiska med preinstall-snapshoten under uppgraderingarna.

Albert har valt Besovida-servern som offsite-mål, SSH `albert@100.66.179.74`,
katalog `/home/albert/Bois`. AES-256-GCM-krypterad kopia har förberetts och
autentiserad dekryptering verifierats lokalt. Nyckeln ligger separat privat.
Offsite-filer finns nu i denna katalog: `bois-p18-cd76ea8-final-20261006.aesgcm`
och `bois-daily-20261005T223322Z-cd76ea8.aesgcm`. Serverns ED25519-hostnyckel
matchade Alberts verifiering från Termius. Dedikerad nyckel till Besovida har
forced SFTP och restrict. Remote återläsning, SHA256 och autentiserad dekryptering
passerade; även arkivens interna hashar/rättigheter och nekad manipulerad
backup verifierades för det nya backup-programmet. Katalog 0700, filer 0600.
Återställningsnyckeln ligger privat på Simply och lokalt; den ingår inte i
offsitearkiven. Separat säker escrow återstår.

Albert har uttryckligen accepterat driftansvar, RPO högst 24 timmar och RTO
4 timmar. Dessa är mål, ingen uppmätt garanterad SLA. Krypterad backup är
installerad dagligen 03:00 hosttid (CEST vid kontrollen). Manuell körning av
samma program passerade; första tidsstyrda körningen har ännu inte observerats.
Se [driftinstruktionen](P18-BACKUP-OPERATIONS.md).

## Hostbevis och begränsningar

- Slutlig production HTTPS: liveness 200, readiness 200, catalog och admin_orders
  503 enligt stängd produktionsgate. CLI readiness passerar samtliga kontroller,
  `ready_for_closed_verification=true`, `ready_for_launch=false`.
- Ingen ny schemaändring behövdes: 24 tabeller och befintlig migrationsledger
  matchar. Production har fortsatt inga kunder, köp, medlemskap eller outboxrader.
- Fulla syntetiska köp, medlemsflöden, mockbetalning och leverantörsbatch
  provades på separat DB/privat testconfig i Simply-stack. Outbox levererades
  endast till privat lokal sink; inga externa mail skickades.
- Personlig admin över faktisk HTTPS: MFA, Secure/HttpOnly/SameSite-cookie,
  tre roller, nekad CSRF, revoke och logout passerade i testinstansen.
- Åtta parallella adminanrop reproducerade två HTTP500 vid kort logglås.
  Efter PR42-fixen passerade två vågor med åtta anrop utan HTTP500.
- Browserköp och medlemsprov samt vyer 375/390/1280 har partiellt hostbevis;
  fokuserat adminbrowserprov passerade. En sammanhängande full hostbrowserkörning
  är **inte grön**: Simply-anslutningar timeoutade. CI-browserbevis ersätter inte
  detta återstående hostbevis.
- Retention kördes endast dry-run. Produktionsworkers vägrar starta enligt
  stängda flaggor; inga mail-/affärsworkers schemaläggs. Backupcron är installerad.
  Automatisk offsiteöverföring och verifierad övervakningsmodell återstår:
  Simply godtar inte den vanliga authorized_keys-filen i genomfört prov,
  och panelen visar inget stöd för att begränsa en backupnyckel till krypterade
  filer. Ingen bred productionnyckel har lagts på Besovida.
- SELECT-only credential saknas. Simply-kontot har ALL på sina två DB:er och
  saknar CREATE USER/GRANT OPTION; kontrollpanelen visar ingen användarhantering.
  Providerlösning eller uttryckligt accepterat undantag krävs. Detta är inte
  ett tyst accepterat undantag.
- Backupretention och key escrow återstår. Inga gamla backuper raderas.
- SharePoint-kön och överlämningen uppdateras med samma lokala hostbevis och
  driftbeslut; originalens historik bevaras och innehållet återläses för verifiering.

## Nästa arbete

Slutför begränsad automatisk offsiteåtkomst, övervakning, separat key escrow,
retention och SELECT-only/providerundantag. Kontrollera första tidsstyrda
backupen och utför ett tidsatt katastrofåterställningsprov för RTO-målet.
Den syntetiska publika testytan är stängd och dess data bevarade; full
hostbrowseracceptans kräver en ny kontrollerad testperiod. Markera P18B DONE
endast när kraven faktiskt är uppfyllda.

Stripe live, extern transport, skarp gallring, DNS-cutover och P20 är fortsatt
stängda. Ingen extern kostnad eller verksamhetsacceptans har fabricerats.
