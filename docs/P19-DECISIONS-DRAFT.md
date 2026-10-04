# P19 – beslutsunderlag, utkast inför Erik/BoIS

Förberett inom P18A. Ej skickat och inga beslut godkända. P19 är TODO.
Registrera svar, beslutsfattare, datum och bevis/länk innan slutkonfiguration.

| Beslut | Förslag/fråga | Ansvarig och beroende |
|---|---|---|
| Säljare och slutlig shop-URL | Juridiskt namn/org.nr, support och ansvar för reklamation/refund; slutliga villkor/privacy-URL | Erik/BoIS; P12-konfig och P20 |
| Betalning | Merchant, avgifter och refundpolicy; accepterade metoder och Swishförutsättningar | BoIS; Stripe live är spärrad |
| Medlemskap | Giltighetsmodell (365 dagar är stagingförslag), pris/kategorier, befintlig-medlem-verifiering och ansvarig | Erik/BoIS; P4 och skarp kundtext |
| Nordic | Bekräfta 20 per kalenderår, partneraktivering, befintlig medlem och återköp/återbetalningsvillkor | BoIS/Nordic; teknisk serverkvot bevaras |
| Matchtröja | Shirt-only, inga shorts. Bekräfta 998 kr testpris eller nytt godkänt pris, decoration/tryck, batchtröskel/48 h, leveranstid, leverantör och MOQ | Erik/BoIS/leverantör; testpris är inte godkännande |
| Mail | Avsändare, befintlig provider, domän/SPF/DKIM/DMARC, support/leverantörsmottagare och tillstånd för extern leverans | BoIS/teknisk ansvarig; P17 hostverifiering och aktiveringsgrindar |
| Statistik och cookies | Ändamål, integritetstext, cookie/provider-verifiering, consentperiod (180 dagar är testvärde), frånvaro av extern analytics | Verksamhets-/privacyansvarig; P16/granskning |
| Retention | Perioder för sales/inaktiv consent, order/betalning/medlemskap/leverans, rättslig grund och legal hold | Verksamhets-/privacyansvarig; inga skarpa standardvärden sätts av kod |
| Drift | Incident-/återställningsansvar, backupomfattning, RPO/RTO, privat/offsite-kopia och eventuell cron | BoIS/hostansvarig; P18B måste ge hostbevis |
| Acceptans | Namngivna granskare, demofeedback och vilka webbläsare/enheter som krävs | Erik/BoIS; automatiserad Chromium ersätter inte användaracceptans |
| 2027-sortiment | Vilka produkter, leverantörer, pris/marginal/MOQ och datum ska godkännas? | Erik/BoIS; preview saknar köp, P7-spärr 2027-01-01 Europe/Stockholm kvarstår |

Separat uttryckligt lanseringsbeslut krävs efter teknisk P18A+P18B-acceptans och
relevanta registrerade verksamhetsbeslut. Utkastet ger ingen aktiveringsbehörighet.
