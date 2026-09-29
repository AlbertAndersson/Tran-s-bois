# NEXT THREAD PROMPT

## Nästa steg: faktisk demo och återkoppling från Erik/BoIS

Ta över **Tranås BoIS – Webbshop** från `AlbertAndersson/Tran-s-bois`. Läs aktuell main och bevara senare ändringar. GitHub äger kod, CI, secrets och deployment. Gör inte om databassepareringen och ändra inte Work Capture.

**Skyddad staging är verifierad. Bygg inte en ny generell teknisk fas och kör inte om gamla uppdrag som redan är klara.** Använd först demot för att samla konkret återkoppling och besluta om ett litet, prioriterat nästa uppdrag.

## Verifierad utgångspunkt

Run `36623915463`, job `109595921802`: **success**, samtliga jobbsteg passerade. Workflow/main vid körningen var `4918cef10bcc213ef9c756d407ca88d4990971fa`. Publicerad applikationsref är `c49ebcd9af9f7081e1764d28419d92dd05b4fd48`.

Basic Auth över HTTPS skyddar hela stagingkatalogen. Obehörig direktåtkomst till shop, admin, order, payment, consent, assets och API samt fel credentials gav 401. Behörig P4–P9-demo, mockbetalning, medlemskap/Nordic, matchställ/batch, betalningsfel/nytt försök, mockrefund och samtycke inget val/nej/ja/återkallelse passerade bakom inloggningen.

Chromium headless på Linux: 375/390/1280 px. Artifact `bois-p9-synthetic-browser-36623915463`, ID `11059388459`, innehåller 10 PNG och löper enligt metadata ut 2026-10-06 20:08:02 UTC. Inget fysiskt iPhone-/Safari-test eller faktiskt Erik/BoIS-godkännande får härledas ur denna automatiserade körning.

P8 är fortsatt **TECHNICALLY COMPLETE / NOT ACTIVATED**. P9 och samtyckeskomponenten är liveverifierade i skyddad syntetisk staging. Ingen Stripe, riktig betalning, extern analytics/mejl, annonsering eller produktion aktiverades; ny extern kostnad 0 kr.

Skillj alltid mellan faktisk publicerad applikationsref, workflowref och senare dokumentations-HEAD. Historiska run `36517329717` och dess öppna staging är ersatta av den skyddade verifieringen ovan.

## Läs först

1. `docs/CURRENT_STATUS.md`
2. `docs/STAGING-DEMO-ACCESS.md`
3. `docs/SYNTHETIC-DEMO.md`
4. `docs/CONSENT-INVENTORY-AND-ACCEPTANCE.md`
5. `docs/00-WORK-HANDOFF.md`
6. Relevanta P6–P9-, säkerhets- och samtyckesdokument endast när de behövs för en konkret fråga.

## Genomför genomgången

Använd befintligt demomanus och bara syntetiska uppgifter/`example.invalid`. Demonstrera kundens medlemskap/gym/matchställ, köp utan statistikmedgivande, ja/nej/återkallelse, betalningsfel och nytt försök samt Eriks order-, Nordic- och batcharbete. Visa P9:s aggregerade syntetiska försäljningsöversikt.

Dela demoåtkomst privat med ett begränsat antal behöriga granskare. Visa inte lösenord, ordertoken eller adminnyckel i skärmbilder eller dokumentation. Basic Auth ger inte adminbehörighet; adminnyckeln är separat och hanteras av behörig administratör.

Samla per återkopplingspunkt: vad deltagaren försökte göra, förväntat resultat, observerat problem/önskemål, skärmbild utan hemligheter när relevant, prioritet och beslut. Påstå inte att Erik/BoIS har godkänt något de ännu inte provat. Registrera faktisk webbläsare/enhet; fysisk iPhone/Safari är en möjlig manuell demokontroll, inte redan verifierad.

Gör inga nya kodändringar eller deployer enbart för att hålla projektet igång. Förankra ett avgränsat uppdrag utifrån konkret feedback. Bevara den manuella deploygrinden och befintligt åtkomstskydd vid eventuella senare ändringar.

## Gränser

- Endast mock/testmode, syntetiska order och behöriga testare.
- Ingen Stripe/KYC, extern Stripe-API-körning, riktig betalning/refund eller nya credentials.
- Inga externa mejl/SMS, annonser, externa analystjänster, abonnemang eller nya kostnader.
- Produktionsgrind och produktionsspårning hålls stängda. Samtyckeskomponenten är inte ett produktionsbeslut.
- Giltigt serververifierat statistikmedgivande krävs utöver teknisk tillåtelse. Köp ska fungera utan medgivande.
- P4–P9-regressioner och P7:s produktspärrar bevaras. Stopp för nya köp får inte tappa signerade händelser för tidigare betalningar.
- Ingen generell databasstädning. Radera inte order-/betalningshistorik, revisionsspår eller privata rollback-backuper.

## Kvarvarande skarpa beslut

Merchant/kontoägare/KYC, bank, godkända avgifter/Swish-access, domän, separat produktionsdatabas, villkor/integritet/refundpolicy, support/mailtransport, produkt-/partnerdata och medlemsperiod. Personliga adminkonton/roller/MFA, missbruksskydd, backup/återställningsprov, gallring och slutlig säkerhetsgranskning krävs också. Demots Basic Auth ersätter inget av detta.

Medlemskap: ungdom 200 kr, vuxen 350 kr, pensionär 300 kr; Nordic 2 650 kr för medlem. Matchställ **998 kr är endast testpris**. Merch förblir dold/ej orderbar före 1 januari 2027 och kräver även därefter verifierad kommersiell data och BoIS produktgodkännande. Ingen rabatt införs genom P9.

Avsluta nästa demoarbete med faktisk återkoppling och en beslutad prioriteringslista, inte med ett automatiskt startat nytt utvecklingsprojekt.
