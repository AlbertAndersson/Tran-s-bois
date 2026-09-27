# NEXT THREAD PROMPT

Ta över projektet **Tranås BoIS – Webbshop** och fortsätt från verifierad P6.

GitHub-repot `AlbertAndersson/Tran-s-bois` är source of truth. Börja på `main` och verifiera aktuell HEAD.

P6 implementation merge:
- `64ac0ed2b1624fb425195c5042f964f0ceb8a2a3`

Läs först:
1. `README.md`
2. `docs/CURRENT_STATUS.md`
3. `docs/00-WORK-HANDOFF.md`
4. `docs/P3-COMMERCE-CORE.md`
5. `docs/P4-MEMBERSHIP-NORDIC.md`
6. `docs/P5-MATCHKIT-BATCHING.md`
7. `docs/P6-PAYMENT.md`

Deployment ligger i `AlbertAndersson/work-capture`. Aktiv workflow är:
`.github/workflows/simply-deploy-bois-p4.yml`

Senast verifierad P6-deploy:
- workflow run `36325200482`
- conclusion: **success**
- deployment commit: `903621e4be684b15848ff94d688d789ee4871b19`

Aktiv staging:
- shop: https://alberiq.se/bois-shop-p3/
- medlemskap/Nordic: https://alberiq.se/bois-shop-p3/membership.html
- matchställ: https://alberiq.se/bois-shop-p3/match-kit.html
- testbetalning: https://alberiq.se/bois-shop-p3/payment.html
- admin: https://alberiq.se/bois-shop-p3/admin.html

P1–P6 är tekniskt klara. P4 medlemsregister/Nordic, P5 matchställsbatchning och P6 payment är live-verifierade i staging.

P6 staging använder:
- isolerad `mock` provider
- Test-Swish/Test-kort
- signerad webhook
- event-idempotens
- payment state machine
- refund → `REVIEW_REQUIRED`
- receipt/refund outbox
- `payment_mail_transport = disabled`

Riktig payment provider, merchant-onboarding och providerkostnad är **inte** aktiverade.

**Nästa fas är P7 – 2027 assortment.**

Bygg P7 så supporter-/merchsortimentet blir lanseringsklart men fortsatt dolt/orderblockerat före **1 januari 2027**.

Prioritera:
- leverantörer
- inköpspris
- försäljningspris och marginal
- SKU/artikelnummer
- storlekar/varianter
- produktbilder/copy
- fulfillmentmodell
- lager/direct supplier
- returer/reklamationer
- launch gate

Säkerställ att:
- inget 2027-sortiment blir publikt/orderbart före 1 januari 2027
- befintliga medlems-/Nordic-/matchställsflöden inte bryts
- P6 payment gate förblir intakt
- staging bara använder testuppgifter
- inga riktiga externa mejl skickas
- inga nya kostnader eller betaltjänster aktiveras utan uttryckligt godkännande

Behåll affärsreglerna:
- ungdomsmedlemskap 200 kr
- vuxenmedlemskap 350 kr
- pensionärsmedlemskap 300 kr
- Nordic Wellness gymkort 2 650 kr för aktiv medlem
- matchställ 998 kr är endast staging/testpris

Dokumentera löpande i GitHub och synka handoff/status till Google Drive.

## Kontroll före P7
Kontrollera P6-skärpningen på `chatgpt/p6-payment-hardening-20260927` och dess CI/deploystatus. Adminens staging-simulering ska nu gå via signerad P6-mock. Kundvy och adminvy ska visa betalreferens respektive event. Fortsätt inte anta att den första P6-deployen inkluderar dessa ändringar förrän ny stagingdeploy har verifierats.
