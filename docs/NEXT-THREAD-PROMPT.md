# NEXT THREAD PROMPT

Ta över **Tranås BoIS – Webbshop** från GitHub-repot `AlbertAndersson/Tran-s-bois`, som är source of truth. Börja från `main`, verifiera aktuell HEAD och läs README, CURRENT_STATUS, 00-WORK-HANDOFF, P3–P8-planerna och detta dokument.

P1–P6 är klara och live-verifierade. P6 hardening: PR #3 merge `b5b9a157a7462277cdab27bb304c5c1b31706fa0`, Simply run `36327128975` success, 38 icke-BoIS-tabeller identiska före/efter. Staging har enbart mockbetalning och avstängd extern e-post.

P7A–D är implementerade och mergeade via PR #6 på `49883befb360fddb6e63e5c6b6a622fd2c0eed8b`. P2–P7 CI är grön, inklusive MySQL 8.4, P4–P6-regression och serverstyrd launch gate. Kontrollera ny `main` HEAD efter dokumentationsuppdatering.

**P7 är inte stängd:** stagingmigration och live-verifiering återstår. Deployment i `AlbertAndersson/work-capture/.github/workflows/simply-deploy-bois-p4.yml` uppdaterades på `21249c0354f3b487539fc3500cc1f37f872300be`, pinnad till P7-merge. Workflowen är manuellt skyddad och kräver `workflow_dispatch` med `confirm=DEPLOY_BOIS_P7_READY`. Kör den i befintlig Simply-miljö, granska snapshot av icke-BoIS-tabeller före/efter, admin/preview, publik katalog och ordergate, P4–P6 syntetiska flöden, avstängd e-post och mockbetalning. Dokumentera run ID, utfall och stäng därefter P7 i README/status/handoff/P7-planen samt synka Drive.

P7 föreslår BoIS 1941 Hoodie, Supporter-T-shirt och BoIS Läktarmössa. 549/249/199 kr är bara interna prisuppskattningar. Leverantörsspecifika inköpspriser, MOQ, ledtider, dekoration, frakt, riktiga SKU och marginaler är `TBD`; Printful är endast kandidat. All data och källor finns i `data/p7-assortment.json` och `docs/P7A-COMMERCIAL-MODEL.md`. Ingen P7-produkt är godkänd/publik/orderbar. Adminskyddad intern preview har placeholder. Servern blockerar merch före 2027-01-01 Europe/Stockholm även vid direkt API-anrop; därefter krävs verifierad data och uttryckligt godkännande. Inga verkliga leverantörer eller priser får hittas på.

Befintlig staging: `https://alberiq.se/bois-shop-p3/`. Endast syntetiska testuppgifter. Medlemskap 200/350/300 kr, Nordic 2 650 kr för aktiv medlem och matchställ 998 kr endast staging/testpris. Ingen extern e-post, riktig provider, Stripe-kod eller ny kostnad i P7. Stripe är vald som målprovider för P8, men P8 får inte påbörjas förrän P7-closeout och separat produktionsbeslut.

Om den skyddade deployen inte kan initieras med tillgängliga verktyg, be om explicit klartecken för UI-fallback eller be Albert utlösa exakt den befintliga workflowen. Ändra inte dess manuella skydd för att kringgå spärren.
