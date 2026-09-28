# NEXT THREAD PROMPT

Ta över **Tranås BoIS – Webbshop** från `AlbertAndersson/Tran-s-bois`. GitHub är source of truth och BoIS äger kod, CI, secrets och deployment. Verifiera aktuell `main` HEAD; utgå inte från ett gammalt handoff-SHA.

**Aktuell arbetsgrind:** Rättningsdeploy `36463946785` lyckades med pinnad kodref `fdb1053c69e929fa7430c74b9ea5a4e47cac31eb`. PR #12 inför C1–C4 och syntetiskt browsertest, men ny kod är ännu inte driftsatt. Kontrollera grön P2–P9 och Security controls CI på slutlig PR-head, mergea, uppdatera workflowens source-ref, kör manuellt skyddad stagingdeploy och läs Chromiumbevis innan C1–C4 eller kvalitetsrundor markeras liveverifierade. Bredare demo blockeras tills faktiskt åtkomstskydd har verifierats. Senare stycken om att rättningsdeploy och samtycke återstår beskriver det äldre överlämningsläget.

## Läs först

1. `docs/SECURITY-CONTROL-POINTS.md`
2. `docs/COOKIES-AND-CONSENT-PLAN.md`
3. `README.md` och `docs/CURRENT_STATUS.md`
4. `docs/00-WORK-HANDOFF.md`
5. `docs/P6-PAYMENT.md` och `docs/P7-2027-ASSORTMENT.md`
6. `docs/P8-PAYMENT-STRIPE.md`, `docs/P8-PRODUCTION-READINESS.md`, `docs/P8-CUTOVER-RUNBOOK.md`
7. `docs/P9-SALES-ENGINE.md`
8. detta dokument

## Verifierad baseline

P1–P7 är tekniskt levererade. P8A–D är **TECHNICALLY COMPLETE / NOT ACTIVATED**. P9 är **COMPLETE / LIVE STAGING VERIFIED** för nedanstående kodref; detta betyder inte att senare rättningar redan ligger på Simply.

P9: BoIS-ägd run `36445454316`, job `109006483514`, success. Workflow-main `6aabb41c7f0025cf99749919693d84c391f9ce24`, pinnad kodref `3219dba57fca1eb97b9d50022477131c8db2501b`. Closeout-main `3983c5e65726390cd59fcf65fe3d69000376030f`.

Verifierat i den körningen: P9, mock/testmode, first-party sales engine, syntetisk UTM/referral-session → order → signerad mock-PAID, kampanjattribution, rekommendation utan rabatt, P4 ACTIVE/Nordic ELIGIBLE, P5 8/168h, P7 dold/blockerad merch, avstängd e-post och ingen extern kostnad. Sales-ID:n är pseudonyma, inte anonyma.

Historisk BoIS-ägd P8-readiness: run `36414148818`, job `108901126748`, success från workflow-main `1a29eb5ddb229144f255fe92a837d20953617832`, pinnad P8-kodref `818c262af23431be972986b9c79f70f319da90e2`.

Work Capture äger inte längre BoIS deployment eller databas. Gör inte om databasflytten eller återställ gamla deployworkflows där.

## Aktuell prioritering

Albert har godkänt att kontrollpunkterna rättas först och att kakor/samtycke läggs in i planen framåt.

1. **Kontrollrättningarna:** kräv lanseringsgodkännande i den faktiska Stripe-checkoutvägen, inte bara i en informationskontroll. Spårning av ska betyda noll sales-skrivningar via både events och order, och ingen åtkomst till P9-lagring i browsern. Se `docs/SECURITY-CONTROL-POINTS.md` för exakt kod/test/deploystatus.
2. **Stagingacceptans av rättad kod:** kontrollera CI, rätt source-ref och manuellt skyddad BoIS-deploy. Fortsatt endast mock och syntetiska data. Registrera nytt run-ID först efter success.
3. **Kakor och samtycke:** implementera C1–C4 enligt `docs/COOKIES-AND-CONSENT-PLAN.md` innan någon valfri mätning används för riktiga besökare. Ingen cookie-/samtyckeskomponent ska betraktas som levererad enbart för att avstängningsspärren rättats.
4. **De tre godkända kvalitetsrundorna:** mobil kundresa, Eriks administrativa arbete och ett sammanhållet syntetiskt demo-/acceptanspaket. De är ännu inte rapporterat genomförda. Bevara dem i planen, men rapportera endast faktiskt testade resultat.

Personliga adminkonton, roller och MFA är ett separat kvarvarande krav före riktiga kunduppgifter. En delad stagingnyckel ska inte beskrivas som en färdig produktionsinloggning.

## Hårda gränser under fortsatt utveckling

- Ingen Stripe-aktivering, KYC, riktig betalning eller extern Stripe-API-körning.
- Inga externa mejl/SMS, annonser eller analystjänster och ingen ny kostnad utan separat godkännande.
- Produktionsgrinden förblir stängd. Staging har tomma Stripe-nycklar och mockbetalning.
- Production tracking är hårt blockerad tills separat samtyckeshantering är implementerad och verifierad. En frontendflagga eller orderns godkännandefält får inte aktivera statistik.
- Explicit syntetisk staging/test kan använda P9. Staging är inte en miljö för riktiga kunder; verifiera åtkomstskydd före bredare demo.
- Bevara P4–P9-regressionerna och P7:s serverstyrda lanseringsspärr.
- Stängning för nya checkouts får inte stoppa verifierade försenade händelser för redan påbörjade betalningar.

## P8 – uppgifter och beslut som fortfarande väntar

Juridisk betalningsmottagare, Stripe-kontoägare/KYC, bankkonto, godkända avgifter, verifierad Swish-access, produktionsdomän, separat produktionsdatabas, slutliga köpvillkor/integritet/återbetalningsregler, support och skarp mailtransport. Backup/återställningsprov och slutlig säkerhetsgranskning krävs också. Aktivera inget utan uttryckligt godkännande.

## Befintliga affärsregler

Ungdomsmedlemskap 200 kr, vuxen 350 kr, pensionär 300 kr. Nordic Wellness-gymkort 2 650 kr för medlem. Matchställ 998 kr är **endast staging/testpris**.

P7-produkter får inte bli publika/orderbara före 1 januari 2027, och även därefter krävs verifierade kommersiella uppgifter och BoIS produktgodkännande. Riktiga leverantörsofferter, SKU, marginaler och produktbilder återstår. Rekommendationerna i P9 ändrar inte priser och ger ingen rabatt.
