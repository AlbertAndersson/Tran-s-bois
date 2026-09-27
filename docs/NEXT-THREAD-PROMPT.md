# NEXT THREAD PROMPT

Ta över projektet **Tranås BoIS – Webbshop** och fortsätt från verifierad P6.

GitHub-repot `AlbertAndersson/Tran-s-bois` är source of truth. Börja på `main` och verifiera aktuell HEAD.

P6 slutlig hardening merge:
- `b5b9a157a7462277cdab27bb304c5c1b31706fa0` (PR #3)

Läs först:
1. `README.md`
2. `docs/CURRENT_STATUS.md`
3. `docs/00-WORK-HANDOFF.md`
4. `docs/P3-COMMERCE-CORE.md`
5. `docs/P4-MEMBERSHIP-NORDIC.md`
6. `docs/P5-MATCHKIT-BATCHING.md`
7. `docs/P6-PAYMENT.md`
8. `docs/P7-2027-ASSORTMENT.md`
9. `docs/P8-PAYMENT-STRIPE.md`

Deployment ligger i `AlbertAndersson/work-capture`. Aktiv workflow är:
`.github/workflows/simply-deploy-bois-p4.yml`

Senast verifierad P6-deploy:
- workflow run `36327128975`
- conclusion: **success**
- deployment commit: `68412823f65e120484febc091c052df8b4bd4564`
- 38 icke-BoIS-tabeller: identisk snapshot-hash före/efter

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
- `REFUND_PENDING` / partiell refund → manuell `REVIEW_REQUIRED`
- receipt/refund outbox
- `payment_mail_transport = disabled`

Riktig payment provider, merchant-onboarding och providerkostnad är **inte** aktiverade. **Stripe är vald som målprovider för P8**, men detta är endast ett dokumenterat framtida beslut under P7.

**Nästa fas är P7 – 2027 assortment.**

P7 ska **inte** implementera Stripe. Stripe hör till P8 production launch. P7 får endast bevara kompatibiliteten med den befintliga provider-adaptern och dokumentera eventuella beroenden som upptäcks.

Genomför P7 enligt `docs/P7-2027-ASSORTMENT.md`. Dela arbetet i **P7A–P7D** och committa efter varje verifierbart delsteg. Supporter-/merchsortimentet ska bli lanseringsklart men fortsatt dolt/orderblockerat före **1 januari 2027**.

P7A–D:
- **P7A:** assortment research + commercial model med tydlig VERIFIED / ESTIMATE / TBD-status
- **P7B:** Commerce Core schema/catalog för leverantör, inköpspris, pris, marginal, SKU/varianter och fulfillment
- **P7C:** admin + intern staging-preview av 2027-sortimentet
- **P7D:** serverstyrd launch gate + P4–P6 regression + dokumentationscloseout

Säkerställ att:
- inget 2027-sortiment blir publikt/orderbart före 1 januari 2027
- befintliga medlems-/Nordic-/matchställsflöden inte bryts
- P6 payment gate förblir intakt
- staging bara använder testuppgifter
- inga riktiga externa mejl skickas
- inga nya kostnader eller betaltjänster aktiveras utan uttryckligt godkännande
- Stripe-konto/onboarding/credentials eller Stripe-kod inte skapas i P7
- P8-planen behåller Stripe som vald provider och verifierar senare Swish-tillgänglighet för BoIS-kontot

Behåll affärsreglerna:
- ungdomsmedlemskap 200 kr
- vuxenmedlemskap 350 kr
- pensionärsmedlemskap 300 kr
- Nordic Wellness gymkort 2 650 kr för aktiv medlem
- matchställ 998 kr är endast staging/testpris

Hitta inte på leverantör, inköpspris eller SKU; märk osäkra uppgifter `TBD`/`ESTIMATE`. Dokumentera löpande i GitHub och synka handoff/status till Google Drive.

## Verifierad P7-startpunkt
P6-hardening är redan verifierad och ska inte återöppnas i P7:
- PR #3 → `b5b9a157`
- P2–P6 CI: **success**
- Simply deploy `36327128975`: **success**
- adminens staging-simulering går via signerad P6-mock
- order/session/valuta/belopp verifieras
- kundvy visar betalreferens och admin visar betalningar/event
- REFUND_PENDING och partiell refund finns
- extern e-post och riktig provider är avstängda

Gå direkt vidare med P7A–P7D enligt `docs/P7-2027-ASSORTMENT.md`.
