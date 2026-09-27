# CURRENT STATUS

Datum: 2026-09-27

## Status
**P1 – skarp beställningsmotor utan betalning: COMPLETE / STAGING VERIFIED**

## Publik demo
- Kunddemo: https://albertandersson.github.io/Tran-s-bois/
- Ledarvy: https://albertandersson.github.io/Tran-s-bois/admin.html
- GitHub Pages publicerar endast den statiska `site/`-ytan.
- Publika demon sparar inga personuppgifter och genomför ingen betalning.

## P1 staging
- Kund: https://alberiq.se/bois-bestallning-p1/
- Admin: https://alberiq.se/bois-bestallning-p1/admin.html
- Staging använder endast testuppgifter.
- Orderlagring: en privat JSON-fil per order.
- Unika ordernummer och idempotent registrering.
- Statusflöde: Ny → Kontrollerad → Klar för leverantör → Beställd / Avbruten.
- Ledarvy: sök, lagfilter, statusfilter och statusändring.
- CSV-export till leverantör.
- Betalning: AVSTÄNGD.
- Ny extern kostnad: **0 kr**.

## Verifiering
Senaste automatiserade end-to-end-körningen på Simply:
- PHP syntax + P1 smoke test: pass
- HTTPS health: pass
- kundsida: pass
- adminvy: pass
- skapa testorder: pass
- admin list orders: pass
- statusändring: pass
- CSV-export: pass
- skydd av `config.php`, backendkod och orderfil: pass
- Simply WAF-verifiering: löst med identifierad deploy-check user-agent

## Säkerhet
- Ingen betalningsnyckel eller kundcredential finns i publika repot.
- Adminnyckel genereras vid staging-deploy och committas inte.
- Servervalidering och serverberäknade priser.
- Rate limiting + honeypot.
- API-svar har no-store.
- Auditlogg för order- och statusändringar.
- Känsliga stagingfiler skyddas via serverregler.
- Produktionsversion ska flytta runtime-konfiguration/orderdata utanför webbrot.

## Kostnadsspärr
Inga nya abonnemang eller betaltjänster har aktiverats. P1 använder GitHub Pages + redan befintlig AlberIQ/Simply-hosting.

## Nästa steg – P2 / produktionsförberedelse
1. Erik/BoIS bekräftar verkliga lag, produkter, storlekar och priser.
2. Bekräfta exakt orderformat som klädleverantören vill ha.
3. Ersätt stagingvärden med skarpa produktdata.
4. Lägg skarp integritetsinformation, köpvillkor och orderbekräftelse.
5. Bestäm slutlig domän/subdomän.
6. Först efter uttryckligt godkännande: koppla Swish/kort och betalstatus.


## Slutverifiering av source
- P1-källkoden återställd och verifierad på `main`.
- Source-restaurering: `5384421198abb7f781f7b6e0ecd4dc8a88ac8e15`.
- Korrigering av admin-JavaScript: `399fa7032b42de012769ff46fea99ebabc5ad9cd`.
- Efter korrigeringen passerade GitHub Pages: checkout, JavaScript-validering, Pages-konfiguration, artifact-upload och deploy.
- Simply-staging kontrollerades på nytt: kundsida och adminvy svarar, API health returnerar `ok:true`, `mode:staging` och `storage_writable:true`.


## Hostingflytt 2026-09-27
- P1-staging flyttad från DMA Motor till AlberIQ.
- Ny adress: https://alberiq.se/bois-bestallning-p1/
- Ny adminadress: https://alberiq.se/bois-bestallning-p1/admin.html
- Runtime-konfiguration och orderdata ligger utanför webbroot på AlberIQ-hostingen.
- Den tidigare DMA-adressen är borttagen: `https://test.dmamotor.se/bois-p1/` svarar inte längre med BoIS-sidan.
- BoIS deployment-workflow är borttagen ur `AlbertAndersson/Dmamotor` så sidan inte kan återskapas där av misstag.
- Ny extern kostnad: **0 kr**.
