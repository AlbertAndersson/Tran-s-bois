# P18B – driftbeslut 2026-10-08

Beslutsfattare: Albert (teknisk driftansvarig). Källa: uttryckligt beslut i ChatGPT 2026-10-08: "Ja kör på 30 dagar. Kör säkerhetsundantag. Och ge mig länk och instruktion för testet".

## Beslut 1: 30 dagars rullande backupretention

- Gäller **nya tidsstyrda krypterade BoIS-backuper** på Simply och Besovida, efter lyckad verifierad offsitekopiering. Tidsgräns räknas från backupens skapandetid i UTC.
- Bevara minst de senaste 30 dygnen. Äldre historiska P12/P13/P18 preinstall-/release-/DR-backuper och manuella särskilt märkta arkiv omfattas **inte** och ska bevaras separat tills nytt uttryckligt beslut.
- Gallring får aldrig köras om senaste backup/offsite-återläsning är äldre än accepterad RPO, om aktuell backup/manifest/hash eller alarmstatus är fel, om det saknas minst en nyare verifierad offsitebackup eller om aktuell återställningsförmåga är okänd.
- Först inventering, explicit allowlist för `bois-daily-YYYYMMDDTHHMMSSZ-<sha>.aesgcm`, dry-run och loggning av kandidater; därefter särskild teknisk acceptans av automatiserad cleanup på respektive host. Symlänkar, okända filer, .part-filer och äldre arkiv får inte raderas.
- Förvaringen på Simply har 2 GiB-cap och kan behöva justerad teknisk städning efter verifiering. Inga nya kostnader eller extra tjänster godkänns här.
- **Beslutad policy är inte detsamma som installerad gallring.** Ingen backup raderas av detta dokument.

## Beslut 2: avgränsat SELECT-only-säkerhetsundantag

- Accepterat av Albert för befintlig BoIS-produktionsdatabas på Simply `socen.se`, där produktens DB-användare har breda rättigheter och varken CREATE USER/GRANT OPTION eller separat användarhantering finns. Gäller enbart bristen på separat SELECT-only **driftkontrollkonto**, inte andra rättighetskrav.
- Risk: ett komprometterat driftkonto kan ha större DB-behörighet än önskat. Kontot får därför **inte beskrivas som SELECT-only** och undantaget får inte ersätta tekniska begränsningar där de faktiskt går att införa.
- Kompensationskontroller som krävs före slutlig P18B-closeout: granskade, hårdkodade CLI-readiness-/snapshotkontroller utan godtycklig SQL, explicit READ ONLY-transaktion och nekad DDL/DML i verktygens exekveringsväg; privat credential utanför public_html/repo/CI/loggar; 0600/0700-rättigheter; separat staging/production och verifierad nekad korsåtkomst; privat audit/incidenthantering; verifierad backup/restore och negativt test för att kontrollverktygen inte ändrar data.
- Utred om Simply kan tillhandahålla least-privilege-credentials vid framtida ändrad produkt eller hostingmodell. Ompröva undantaget vid sådan förändring, säkerhetsincident och senast vid nästa årliga driftgranskning.
- **Beslutet accepterar residualrisken**, men ovanstående kompensationskontroller måste verifieras mot faktisk host innan undantaget får användas som godkänd P18B-acceptans.

## Status och gränser

P18B är ännu inte DONE: sammanhängande hostbrowseracceptans återstår, liksom kontroll av ordinarie 03:00/15:00-backuper och 00:15/06:15/12:15/18:15-relay över tid, larmets observerbarhet och teknisk implementation av retention. Den redan uppmätta fullständiga DR-övningen (216,437 sekunder) kvarstår som giltigt bevis inom dess avgränsning.

P19:s **personuppgiftsretention** är ett separat verksamhets-/juridikbeslut och påverkas inte av 30-dagars backupretention. Ingen P19-affärsacceptans, Stripe live, extern e-post, DNS-cutover eller production launch följer av dessa beslut.

## Testlänkar

Befintlig skyddad syntetisk stagingdemo: https://alberiq.se/bois-shop-p3/ . Basic Auth krävs och enbart testdata/mockbetalning får användas. P18B:s separata syntetiska hostbrowseryta på socen.se är dokumenterad som **stängd** efter DR och kan inte påstås vara en aktiv köpdemolänk. Lokala Codex måste öppna den avgränsat, verifiera exakt URL och Basic Auth privat, köra full Playwright/Edge-acceptans, stänga den igen och dokumentera resultatet. Production-URL https://socen.se/bois-shop-production/ ska förbli stängd för köp.
