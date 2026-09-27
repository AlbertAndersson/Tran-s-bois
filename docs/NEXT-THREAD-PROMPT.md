# NEXT THREAD PROMPT

Ta över projektet **Tranås BoIS – Webbshop** och fortsätt från verifierad P4 + P5.

GitHub-repot `AlbertAndersson/Tran-s-bois` är source of truth. Börja på `main` och verifiera aktuell HEAD. Handoff-basen när denna prompt skrevs är `d1fdd3336ca386cd4703d75f3a257c6532dcc15e`.

Läs först:
1. `README.md`
2. `docs/CURRENT_STATUS.md`
3. `docs/00-WORK-HANDOFF.md`
4. `docs/P3-COMMERCE-CORE.md`
5. `docs/P4-MEMBERSHIP-NORDIC.md`
6. `docs/P5-MATCHKIT-BATCHING.md`

Deployment ligger i `AlbertAndersson/work-capture`. Aktiv workflow är:
`.github/workflows/simply-deploy-bois-p4.yml`

Senast verifierad P4+P5-deploy:
- workflow run `36323453297`
- conclusion: success

Aktiv staging:
- https://alberiq.se/bois-shop-p3/
- https://alberiq.se/bois-shop-p3/admin.html

P1–P5 är tekniskt klara. P4 medlemsregister/Nordic och P5 matchställsbatchning är live-verifierade i staging. Betalning och extern mejlsändning är fortfarande avstängda.

**Nästa fas är P6 – Payment.**

Bygg P6 så att en verifierad payment webhook återanvänder samma `PAID`-händelse som P4/P5 redan använder.

Implementera:
- serverstyrd checkout
- Swish/kort via vald provider
- webhook med signaturverifiering
- idempotens
- payment state machine
- refunds/cancellations
- kvitto/orderbekräftelse
- retry/felhantering
- testmode

Säkerställ att:
- inget medlemskap aktiveras före verifierad `PAID`
- inget Nordic-ärende blir eligible före verifierad `PAID`
- inget matchställ går till batch före verifierad `PAID`
- samma payment event aldrig processas två gånger
- staging inte skickar verkliga leverantörsmejl
- inga riktiga kunduppgifter används i staging
- inga nya kostnader eller betaltjänster aktiveras utan uttryckligt godkännande

Behåll affärsreglerna:
- ungdomsmedlemskap 200 kr
- vuxenmedlemskap 350 kr
- pensionärsmedlemskap 300 kr
- Nordic Wellness gymkort 2 650 kr för aktiv medlem
- matchställ 998 kr är endast staging/testpris
- övrig merch hålls dold till efter Intersport-avtalets slut

Dokumentera löpande i GitHub och synka handoff/status till Google Drive.
