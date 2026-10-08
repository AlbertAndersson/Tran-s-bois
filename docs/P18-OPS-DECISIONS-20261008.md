# P18 – godkända driftbeslut, registrerade 2026-10-08

Beslutsfattare och driftansvarig: **Albert Andersson**. Bevis är Alberts
uttryckliga chattsvar i denna körning, efter de dokumenterade alternativen och
kompensationskontrollerna i [driftbevisen](P18B-OPS-EVIDENCE-20261007.md).
Besluten ersätter äldre uppgifter om obeslutad backupretention och frånvaro
av accepterat SELECT-only-undantag. De öppnar inga produktionsflaggor.

## Backupretention – GODKÄND

Albert: ”Ja godkänner 30 dagar retention.”

- Nya automatiska produktionsbackuper bevaras **30 dagar från backupens
  skapandetid**, på Simply, Windows-relay och Besovida. Två generationer per
  dygn ändrar inte antalet dagar.
- Befintliga äldre backuper, manuella kontrollpunkter, preinstall-/rollback-
  backuper och DR-bevis bevaras separat. De omfattas inte av framtida
  automatisk radering utan ett nytt uttryckligt beslut.
- Ingen raderingskod är byggd eller aktiverad genom detta beslut. Nuvarande
  backup/relay bevarar alla backuper. Ett senare införande behöver dry-run,
  tydligt mål/filmanifest, verifierad färsk återställningsbar offsitekopia och
  skydd för senaste goda backup samt manuella/legal-hold-kontrollpunkter.
- Perioden gäller backupkopior; den är inte ett beslut om gallring av order,
  medlemskap, betalningsspår, samtycke eller annan verksamhetsdata i P16/P19.
- Albert kontrollerar utrymme och felstatus dagligen. Simply-scriptets
  befintliga 2 GiB-gräns och 100 MiB bundlegräns nekar fortsatt ny backup vid
  överskridande; inget tas bort automatiskt för att kringgå gränsen.

## SELECT-only – GODKÄNT TEKNISKT UNDANTAG

Albert godkände uttryckligen ”tekniskt undantag för kompensationskontroller”.
Formellt valt spår är därmed **dokumenterat tekniskt undantag**, inte ett
installerat SELECT-only-konto och inte en beställd provideruppgradering.

Scope: driftkontroller för BoIS på den dedikerade socen.se-productionprodukten.
Simply-användaren har fortfarande ALL på produktens två BoIS-databaser och
saknar CREATE USER/GRANT OPTION. Panelen saknar separat DB-userhantering.
Det befintliga kontot får användas av de granskade driftkontrollerna under
följande kompensation, som redan finns och har host-/kodbevis:

1. P12-preflight/readiness, P13-snapshot, P15-readiness och P16-dry-run använder
   READ ONLY-transaktioner. P15 begränsar även querytid till 2 sekunder.
   Normal driftkontroll kör dessa granskade CLI-/readiness-vägar; ingen
   godtycklig skrivande driftfråga ingår i undantaget.
2. Credentials, privata configs, loggar och snapshots hålls utanför public,
   repo och OneDrive. På Windows har BoIS private-katalogen endast Albert
   och SYSTEM som tillåtna ACL-principals; på Simply är privat runtime 0700/0600.
3. Staging ligger på separat alberiq.se-produkt med annan DB-host/användare.
   Productioncredential nekades faktisk staging-SELECT. Undantaget ger ingen
   sammanslagning av credential boundaries.
4. Production är fortsatt fail-closed för launch, köp, externa mail och
   statistik. Personliga production-adminkonton är ännu inte provisionerade;
   ingen testadmin har lagts i production. Operationslogg och negativa gates
   är verifierade och backuper/återställning har faktiskt provats.
5. Återställning/migration är separata, uttryckligt styrda skrivoperationer
   med verifierad backup och isolerat mål; de får inte beskrivas som
   SELECT-only-driftkontroller.

Kvarstående risk: READ ONLY i granskad kod reducerar oavsiktliga skrivningar
men tar inte bort DB-serverns ALL-rättigheter. En komprometterad credential
eller ändrad kod kan fortfarande skriva eller ändra schema inom produkten.
Undantaget måste omprövas vid provider-/produktbyte, credential-/rättighets-
ändring och inför slutlig go-live-granskning. Det ger inget godkännande av
Stripe live, extern transport, DNS-cutover eller publik lansering.

## Browsermetod – GODKÄND

Albert: ”Du får köra playwright.” Befintlig automatiserad browseracceptans får
köras med privata Basic Auth-credentials mot skyddad syntetisk socen-testyta
och Edge headless. Inga verkliga kunder, pengar eller externa mail används.
En tillfälligt ren fixture får endast användas efter verifierad backup och
ska avslutas med bevarade provdata privat, återställda tidigare syntetiska
data, stängd testyta och oförändrad production. Resultatet dokumenteras
separat; metodgodkännandet är inte ett fabricerat testresultat eller
Erik/BoIS-verksamhetsacceptans.
