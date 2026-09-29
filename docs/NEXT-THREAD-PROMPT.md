# NEXT THREAD PROMPT

Ta över **Tranås BoIS – Webbshop** från `AlbertAndersson/Tran-s-bois`. GitHub är source of truth och BoIS äger kod, CI, secrets och deployment. Verifiera aktuell `main` HEAD; utgå inte från ett gammalt handoff-SHA.

**Aktuell arbetsgrind:** Slutlig kodref `e4ac4ce385fcf751460b4af208756d74e562d54b` publicerades och liveverifierades i Simply-run `36517329717` (success; job `109242442693`). Workflow på `main` `0933351896cf608a121d4cdeda7ce7e0c13b0052` var manuellt skyddad och pinnad till denna kod. P2–P9 + Security controls CI passerade för mobilrättningen (PR #17); de fem berörda workflowarna passerade även för sista browser-testjusteringen (PR #18, Security `36516093807`, P9 `36516093893`). Chromium headless testade 375, 390 och 1280 px, 10 skärmbilder i Actions-artifact `bois-p9-synthetic-browser-36517329717` (7 dagars retention). P8 är TECHNICALLY COMPLETE / NOT ACTIVATED. Ingen Stripe, riktig betalning, extern mejlsändning, extern analytics eller produktionsaktivering; ny extern kostnad 0 kr. Staging är publikt nåbar utan verifierat inloggningsskydd och får inte delas brett. Nästa steg är åtkomstskydd för bredare demo och separata produktionsbeslut. Texten nedan om att rättningsdeploy, C1–C4 eller kvalitetstester återstår är historisk och ersätts av detta läge.

## Läs först

1. `docs/SECURITY-CONTROL-POINTS.md`
2. `docs/COOKIES-AND-CONSENT-PLAN.md`
3. `README.md` och `docs/CURRENT_STATUS.md`
4. `docs/00-WORK-HANDOFF.md`
5. `docs/P6-PAYMENT.md` och `docs/P7-2027-ASSORTMENT.md`
6. `docs/P8-PAYMENT-STRIPE.md`, `docs/P8-PRODUCTION-READINESS.md`, `docs/P8-CUTOVER-RUNBOOK.md`
7. `docs/P9-SALES-ENGINE.md`
8. detta dokument

## Historiska verifieringar och aktuell staging

P1–P7 är tekniskt levererade. P8A–D är **TECHNICALLY COMPLETE / NOT ACTIVATED**. P9 och C1–C4 är liveverifierade på den nya kodrefen `e4ac4ce385fcf751460b4af208756d74e562d54b`; följande äldre körningar bevaras som historik.

P9: BoIS-ägd run `36445454316`, job `109006483514`, success. Workflow-main `6aabb41c7f0025cf99749919693d84c391f9ce24`, pinnad kodref `3219dba57fca1eb97b9d50022477131c8db2501b`. Closeout-main `3983c5e65726390cd59fcf65fe3d69000376030f`.

Verifierat i den körningen: P9, mock/testmode, first-party sales engine, syntetisk UTM/referral-session → order → signerad mock-PAID, kampanjattribution, rekommendation utan rabatt, P4 ACTIVE/Nordic ELIGIBLE, P5 8/168h, P7 dold/blockerad merch, avstängd e-post och ingen extern kostnad. Sales-ID:n är pseudonyma, inte anonyma.

Historisk BoIS-ägd P8-readiness: run `36414148818`, job `108901126748`, success från workflow-main `1a29eb5ddb229144f255fe92a837d20953617832`, pinnad P8-kodref `818c262af23431be972986b9c79f70f319da90e2`.

Work Capture äger inte längre BoIS deployment eller databas. Gör inte om databasflytten eller återställ gamla deployworkflows där.

## Aktuell prioritering

1. Inför och verifiera faktiskt inloggningsskydd före bredare delning av den publikt nåbara stagingmiljön. Använd endast syntetiska uppgifter under tiden.
2. Låt BoIS godkänna slutlig säljar-, integritets- och lagringstidsinformation. Inventera Stripe separat först om en senare aktivering beslutas.
3. Gör eventuell fysisk mobil/Safari-kontroll och följ upp konkret användarfeedback från Erik. Det genomförda Chromiumtestet på 375/390/1280 px och demomanuset finns i acceptansprotokollet.
4. Behåll production tracking och Stripe avstängda. Personliga adminkonton, roller och MFA samt P8:s övriga lanseringsgrindar är separata förutsättningar före skarp drift.

Personliga adminkonton, roller och MFA är ett separat kvarvarande krav före riktiga kunduppgifter. En delad stagingnyckel ska inte beskrivas som en färdig produktionsinloggning.

## Hårda gränser under fortsatt utveckling

- Ingen Stripe-aktivering, KYC, riktig betalning eller extern Stripe-API-körning.
- Inga externa mejl/SMS, annonser eller analystjänster och ingen ny kostnad utan separat godkännande.
- Produktionsgrinden förblir stängd. Staging har tomma Stripe-nycklar och mockbetalning.
- Production tracking är fortsatt hårt blockerad trots implementerat samtycke; skarp aktivering kräver separat beslut. En frontendflagga eller orderns godkännandefält får inte aktivera statistik.
- Explicit syntetisk staging/test kan använda P9. Staging är inte en miljö för riktiga kunder; verifiera åtkomstskydd före bredare demo.
- Bevara P4–P9-regressionerna och P7:s serverstyrda lanseringsspärr.
- Stängning för nya checkouts får inte stoppa verifierade försenade händelser för redan påbörjade betalningar.

## P8 – uppgifter och beslut som fortfarande väntar

Juridisk betalningsmottagare, Stripe-kontoägare/KYC, bankkonto, godkända avgifter, verifierad Swish-access, produktionsdomän, separat produktionsdatabas, slutliga köpvillkor/integritet/återbetalningsregler, support och skarp mailtransport. Backup/återställningsprov och slutlig säkerhetsgranskning krävs också. Aktivera inget utan uttryckligt godkännande.

## Befintliga affärsregler

Ungdomsmedlemskap 200 kr, vuxen 350 kr, pensionär 300 kr. Nordic Wellness-gymkort 2 650 kr för medlem. Matchställ 998 kr är **endast staging/testpris**.

P7-produkter får inte bli publika/orderbara före 1 januari 2027, och även därefter krävs verifierade kommersiella uppgifter och BoIS produktgodkännande. Riktiga leverantörsofferter, SKU, marginaler och produktbilder återstår. Rekommendationerna i P9 ändrar inte priser och ger ingen rabatt.
