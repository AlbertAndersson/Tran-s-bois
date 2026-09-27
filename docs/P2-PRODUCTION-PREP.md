# P2 – Produktionsförberedelse

Datum: 2026-09-27

## Mål
Göra P1-beställningsmotorn produktionsnära utan att aktivera betalning, skapa nya abonnemang eller börja samla riktiga personuppgifter.

## Leverans
- Konfigurerbar beställningsperiod.
- Konfigurerbar laglista.
- Konfigurerbar produkt, storlekar, priser och leverantörskod.
- Orderdata versionshöjd till schema 2.
- Servern validerar lag, storlekar, produkt, period och tröjnummer 1–99.
- Orderstatussida för beställaren med säker publik token.
- Digital avbeställning i P2 så länge ordern är Ny/Kontrollerad.
- Ledarvy med automatisk storlekssammanställning.
- Två CSV-exporter: detaljerad beställningsfil och aggregerad leverantörssammanställning.
- Produktionsutkast för personuppgiftsinformation och beställningsvillkor.
- CI för PHP, JavaScript och P1/P2-smoketest.
- Betalning fortsatt avstängd.

## Laglista
Lagytor verifierade mot Tranås BoIS publika webb 2026-09-27:
- P9
- F9
- P13
- F14
- P16
- Skridsko- & bandyskola 26/27

Laglistan är en teknisk konfiguration och ska bekräftas av BoIS före produktionsstart.

## Produktdata
Nuvarande produkt/priser är fortfarande konceptvärden:
- Matchtröja: 449 kr
- Matchbyxa: 349 kr
- Namntryck: 100 kr
- Nummertryck: 100 kr

site/catalog.js är den publika presentationskonfigurationen.
Serverns runtime-konfiguration är fortsatt auktoritativ för validering och prisberäkning.

Före produktion ska Erik/BoIS lämna:
- korrekt produktnamn
- leverantörens artikel-/produktkod
- tillåtna storlekar
- priser
- eventuell sista beställningsdag
- exakt leverantörsexport om annan kolumnordning behövs

## Juridik/GDPR
P2-sidorna privacy.html och terms.html är uttryckligen produktionsutkast.

Före produktionsstart ska följande fastställas:
- föreningens fullständiga säljar-/kontaktuppgifter och organisationsnummer
- personuppgiftsansvarig och kontaktväg
- rättslig grund
- lagringstid/gallringsregel
- vilka leverantörer som är personuppgiftsbiträden och om biträdesavtal krävs
- leverans-/utlämningssätt
- reklamationsrutin
- hur ångerrätt respektive undantag för personligt anpassade varor ska beskrivas

## Säkerhet
- Publika GitHub Pages-demon sparar inga uppgifter.
- P2 staging använder testuppgifter.
- Runtime-konfiguration och orderdata ligger utanför webbroot på AlberIQ-hosting.
- Admin kräver separat token.
- Publik orderstatus kräver ordernummer + 128-bitars slumpad token.
- Pris räknas på servern.
- Rate limiting, honeypot och auditlogg behålls.
- Inga betalningscredentials finns i repo.

## Kostnad
Ny extern kostnad: 0 kr.

## Produktionsspärr
P2 får inte växlas till skarp datainsamling eller betalning förrän:
1. BoIS har bekräftat sortiment, priser och lag.
2. BoIS har bekräftat juridik/personuppgiftsinformation.
3. Slutlig domän/adress är beslutad.
4. Betalningslösning har uttryckligen godkänts.
