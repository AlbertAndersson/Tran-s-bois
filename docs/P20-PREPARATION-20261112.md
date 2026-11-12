# P20 – stängda förberedelser 2026-11-12

Albert har uttryckligen bekräftat priserna i kod (gym 2 650 kr; medlemskap
ungdom 200 kr, vuxen 350 kr, pensionär 300 kr) och godkänt föreslagna
lagringstider. Leverantör och månadsdag kommer senare. Tillståndet gäller
förberedelser utan skarp aktivering. BoIS lanseringsbeslut saknas.

## Förberett i kod

- Opt-in `membership_period=calendar_year` för betalad ny medlem, förnyelse
  och manuell verifiering. Giltighet till 31 december i Europe/Stockholm.
  Samma års köp lägger inte till ett år, och längre befintlig giltighet
  förkortas inte. Betalningsreplay fortsätter vara idempotent. Historiskt
  rolling_days-beteende och befintliga stagingdata ändras inte.
- Opt-in `matchkit_batch={mode: monthly, day: null}`. Ingen automatisk batch
  när dagen saknas; ingen fallback till åtta order eller maxväntetid.
  Manuell batch finns kvar. Framtida dag 1–31 stöds; korta månader använder
  sista dagen. Missad dag fångas upp senare samma månad. Rule-row-lås och
  en lagrad MONTHLY-batch hindrar upprepad automatisk batch samma lokala
  månad. Misslyckad mailleverans återförsöker samma outbox/batch.
- `server/p20-preparation.example.php` sammanställer säljare, org.nr,
  socen.se, support, kalenderår och godkända privacyvärden utan credentials.
  Alla aktiveringsgrindar, externa transporter och retention-apply är av;
  legal_hold är på och production_decisions är tom.
- `data/p20-approved-catalog.json` är ett granskningsunderlag med priser,
  S/M/L, shirt-only, lagutlämning och öppna leverantörsbeslut. Det läses
  inte automatiskt av katalogen och kör ingen seed/migration. Befintlig
  stagingkatalog/UI och productiondata ändras inte.

## Godkända lagringstider och implementationsgräns

Godkännande avser det dokumenterade arbetsförslaget i P19-registret:
samtyckesval 180 dagar, individanknuten statistik 90 dagar, inaktiva
samtyckesposter 180 dagar, tekniska loggar 30 dagar, avslutade supportärenden
12 månader och granskning av utgångna/obetalda köp efter 30 dagar.
Medlems-/partnerunderlag kräver fortfarande egen bedömning. Bokföringsspår
är separat klassificerade och gallras inte genom dessa generella tider.

Kandidatkonfigurationen mappar befintligt P16-stöd för events/sessions till
90 dagar och inaktiva samtycken till 180 dagar. Logg-/support-/övergivna köp-
regler kräver separata anpassningar och verifierad dry-run. Ingen skarp
gallring eller backupgallring implementeras/aktiveras här. Godkända tider är
inte en slutsats att varje befintlig tabell eller extern mailbox redan följer dem.

## Verifiering

`tests/p20-preparation.php` provar lokal årsskiftesgräns, kalenderförnyelse,
bevarad framtida giltighet, historisk rullande period, månadsslut/skottår,
null/ogiltig månadsdag, lokal månadsgräns för batch och stängd config.
P4/P5 MySQL-smoke har betalningsreplay respektive verklig månadsbatch,
upprepad scheduler med nya order och manuell batch efter automatisk batch.
P4-workflow kör gränsproven; tillämplig regression körs i PR före leverans.
Verifieringsutfall registreras vid avslut; planerade kontroller är inte hostbevis.

## Kvar före P20-aktivering

Leverantör, månadsdag och tryckinnehåll; färdig katalog-/UI- och
kundtextsinstallation; godkända villkorsversioner; säker Nordic-process;
mailbox/provider/DNS/TLS; personlig production-admin/MFA; BoIS merchant och
Stripekonto; privacyimplementation utanför P16; backup som fungerar även vid
öppnad production; färska host-/rollbackbevis och Alberts iPhoneacceptans.
Slutlig launch, Stripe live, extern mail och DNS kräver egna uttryckliga
godkännanden. Inga riktiga personnummer eller secrets läggs i repo.
