## Aktuell styrning 2026-10-05

P18A är DONE och mergad via PR40. P18B är beställd men **BLOCKED**:
run37241703902 på main `4e4e2349631d434160b77bd9b2b2d3e7be7db349`
saknar produktionscredentials i GitHub och stannade före DB-anslutning.
Befintlig privat produktionsåtkomst är dokumenterad på Alberts lokala dator.
Ingen ny hostdeployment, migration eller aktivering utfördes.
P12 är accepterad av Albert; återstående hostbevis ligger i P18B.
P13–P17 är klara i kod/isolering och får inte beskrivas som fullt installerade.
P18 och P20 förblir BLOCKED vid hostacceptans; P19:s verksamhetssvar är öppna.
Se [aktuell hostacceptans och lokal fortsättning](P18B-HOST-ACCEPTANCE.md).
Äldre nästa-etapp-rader nedan är historik och gäller inte som arbetsinstruktion.

---

# P19 – beslutsunderlag, utkast inför Erik/BoIS

Förberett inom P18A. Ej skickat och inga beslut godkända. P19 är TODO.
Registrera svar, beslutsfattare, datum och bevis/länk innan slutkonfiguration.

| Beslut | Förslag/fråga | Ansvarig och beroende | Svar/status | Datum |
|---|---|---|---|---|
| Säljare och slutlig shop-URL | Juridiskt namn/org.nr, support och ansvar för reklamation/refund; slutliga villkor/privacy-URL | Erik/BoIS; P12-konfig och P20 | ÖPPET | TBD |
| Betalning | Merchant, avgifter och refundpolicy; accepterade metoder och Swishförutsättningar | BoIS; Stripe live är spärrad | ÖPPET | TBD |
| Medlemskap | Giltighetsmodell (365 dagar är stagingförslag), pris/kategorier, befintlig-medlem-verifiering och ansvarig | Erik/BoIS; P4 och skarp kundtext | ÖPPET | TBD |
| Nordic | Bekräfta 20 per kalenderår, partneraktivering, befintlig medlem och återköp/återbetalningsvillkor | BoIS/Nordic; teknisk serverkvot bevaras | ÖPPET | TBD |
| Matchtröja | Shirt-only, inga shorts. Bekräfta 998 kr testpris eller nytt godkänt pris, decoration/tryck, batchtröskel/48 h, leveranstid, leverantör och MOQ | Erik/BoIS/leverantör; testpris är inte godkännande | ÖPPET | TBD |
| Mail | Avsändare, befintlig provider, domän/SPF/DKIM/DMARC, support/leverantörsmottagare och tillstånd för extern leverans | BoIS/teknisk ansvarig; P17 hostverifiering och aktiveringsgrindar | ÖPPET | TBD |
| Statistik och cookies | Ändamål, integritetstext, cookie/provider-verifiering, consentperiod (180 dagar är testvärde), frånvaro av extern analytics | Verksamhets-/privacyansvarig; P16/granskning | ÖPPET | TBD |
| Retention | Perioder för sales/inaktiv consent, order/betalning/medlemskap/leverans, rättslig grund och legal hold | Verksamhets-/privacyansvarig; inga skarpa standardvärden sätts av kod | ÖPPET | TBD |
| Drift | Incident-/återställningsansvar, backupomfattning, RPO/RTO, privat/offsite-kopia och eventuell cron | BoIS/hostansvarig; P18B måste ge hostbevis | ÖPPET | TBD |
| Administratörer | Namngivna personliga konton, roller, MFA och återkallningsansvar | BoIS/teknisk ansvarig; P14 hostinförande | ÖPPET | TBD |
| Acceptans | Namngivna granskare, demofeedback och vilka webbläsare/enheter som krävs | Erik/BoIS; automatiserad Chromium ersätter inte användaracceptans | ÖPPET | TBD |
| 2027-sortiment | Vilka produkter, leverantörer, pris/marginal/MOQ och datum ska godkännas? | Erik/BoIS; preview saknar köp, P7-spärr 2027-01-01 Europe/Stockholm kvarstår | ÖPPET | TBD |

Separat uttryckligt lanseringsbeslut krävs efter teknisk P18A+P18B-acceptans och
relevanta registrerade verksamhetsbeslut. Utkastet ger ingen aktiveringsbehörighet.
