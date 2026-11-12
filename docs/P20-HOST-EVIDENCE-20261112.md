# P20 – faktisk hostinstallation och skyddat test 2026-11-12

Albert beställde installation även på den avsedda production-hostingen för
iPhoneprov. Ingen publik lansering eller skarp transport har aktiverats.

## Socen production och isolerad köpvy

Installerad app: `0aecd183a708c5d8144a631a2b919824fc7ee6a6`, main efter PR52.
Alla 16 PR-workflows passerade på `c96f8660bc567dd8f87c435294a50efdae9f1825`.
Paketets 62 filer och SHA256-manifest verifierades på hosten före installation.
Windows-arkivets katalogrättigheter normaliserades innan lyckad extraktion;
första extraktionen stoppade innan någon app växlades.

Färsk komplett backup före ändring:
`.bois-production/backups/p20-host-20261112/` på Simply. Båda SQL-dumparna,
runtime/release, konfiguration, auth, privata state/loggar och webb-filer är
kontrollerade för läsbarhet och SHA256. Äldre public-katalog och config bevaras.
Separat krypterad production-backup skapades före och efter installationen.
Offsiteöverföring ska verifieras separat mot aktuell source_revision.

Production på `https://socen.se/bois-shop-production/` är uppgraderad men
köpstängd. Personlig admin och det befintliga Basic Auth-skyddet bevaras.
Alla 24 tabellers schema/rader/migrationsledger/FK matchar före-snapshot.
CLI: ready_for_closed_verification=true, ready_for_launch=false.
Den gamla lokala kopian av productionlösenordet matchar inte längre dess
hash; autentiserad production-HTTPS påstås därför inte som nytt bevis.
Oautentiserad åtkomst kräver fortfarande Basic Auth.

Separat test-URL: **https://socen.se/bois-p20-test/**.
Endast testdatabasen `socen_se_db` återställdes från den färska tomma
production-backupen för en ny syntetisk fixtur. Dess tidigare data/runtime
bevarades först i verifierad backup. Productiondatabasen
`socen_se_db_BoIS_prod` och alberiq-staging återställdes aldrig.
Testytan lämnas skyddat tillgänglig för Alberts iPhoneprov; det är inte en
publikt öppnad production-shop. Testkonfigurationen använder mockbetalning,
avstängda externa mail, kalenderår och månadsbatch med null-dag.

En dedikerad Basic Auth-post gäller enbart denna testyta; productionfilen
för auth ändrades inte. Inloggning och testadminnyckel förvaras lokalt utanför
OneDrive/repo i privat BoIS-katalog, ACL endast Albert och SYSTEM.
Inga credentials finns i detta dokument eller test-URL:en.

## Full browser och datautfall

Skyddad HTTPS gav 200 för shop, medlemskap, tröja, admin och katalog.
Sammanhängande Edge/Playwright på 375/390/1280 passerade medlems-/gymköp,
retry/cancel/failed/PAID, consent/attribution/återkallelse, adminverifiering,
faktisk manuell batch och mockrefund. Inget batchsteg hoppades över.
Medlemsdata kontrollerades efteråt: inga giltighetsslut utanför 31 december.
Production-snapshot är fortsatt oförändrad; mail disabled, provider mock,
månadsdag null och giltig cookie-scope `/bois-p20-test/` verifierades.

Två tidigare browserförsök stoppade vid consent. Först ärvdes den äldre
testadressens cookie-scope, sedan saknades avslutande snedstreck som krävs
av valideringen. Efter korrekt scope passerade hela testet. Provdata och
privata fel-/browserloggar bevaras; inga testorder skapades i production.
Privata slutbevis: `p20-browser-20261112T210152/`,
`p20-post-install-verification.json` och `p20-https-checks.json` i BoIS private.
Alberts faktiska iPhone/Safariacceptans återstår; se [testguide](P20-IPHONE-TEST.md).

## Alberiq staging och rättningen

Första deploymentrun `43295168156` installerade samma app, verifierade färsk
backup och oförändrade äldre order/gymkvoter, men HTTPS-steget gav 403.
Orsaken var public-rotens 0700 från privat umask. PR53 sätter explicit 0755,
utan att ta bort Basic Auth eller ändra privata filers skydd.
Backupverktyget omfattar även aktuell privat P20-runtime/config vid en
senare installation. Ny körning och slutlig HTTPS ska verifieras efter merge;
den första körningen får inte beskrivas som helt grön.

## Fortsatt spärrat

BoIS launchbeslut, leverantör/månadsdag/tryck, riktiga villkor, säker
Nordic-process, Stripe merchant/live, extern mail, skarp gallring och slutlig
DNS-cutover. Testinstallation ersätter inte dessa beslut eller den framtida
backupmodellen för öppnad production.
