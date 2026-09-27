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
- Kund: https://test.dmamotor.se/bois-p1/
- Admin: https://test.dmamotor.se/bois-p1/admin.html
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
Inga nya abonnemang eller betaltjänster har aktiverats. P1 använder GitHub Pages + redan befintlig Simply-hosting.

## Nästa steg – P2 / produktionsförberedelse
1. Erik/BoIS bekräftar verkliga lag, produkter, storlekar och priser.
2. Bekräfta exakt orderformat som klädleverantören vill ha.
3. Ersätt stagingvärden med skarpa produktdata.
4. Lägg skarp integritetsinformation, köpvillkor och orderbekräftelse.
5. Bestäm slutlig domän/subdomän.
6. Först efter uttryckligt godkännande: koppla Swish/kort och betalstatus.
