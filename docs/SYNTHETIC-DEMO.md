# Demo · syntetiska data

**Redo för begränsad demo med Erik/BoIS.** Skyddad staging och den automatiserade demokörningen verifierades i run `36623915463` (success, job `109595921802`). Faktisk genomgång och återkoppling från Erik/BoIS återstår och ska inte förväxlas med automatiserad acceptans.

Öppna https://alberiq.se/bois-shop-p3/ med behörig Basic Auth enligt `STAGING-DEMO-ACCESS.md`. Dela demoinloggningen privat. Adminnyckeln är separat och hanteras av behörig administratör; demonstrera admin under handledning utan att visa eller publicera nyckeln.

Använd endast namnen Ada Test, Bo Test, Cia Test och adresser på `example.invalid`. Endast BoIS staging med mock/testmode. Notera order-ID och token privat för just detta demo; visa aldrig adminnyckel eller ordertoken i skärmbild.

## Åtkomst inför demo

Kunddelens katalogskydd använder Basic Auth-användaren `bois-demo` med privat demolösenord. Administrationen kräver dessutom den separata staging-adminnyckeln från `BOIS_STAGING_ADMIN_TOKEN`. Dela båda via privat kanal, men behandla dem som två olika behörigheter. Skriv aldrig värdena i detta dokument eller i skärmbilder. Klicka **Logga ut** i admin efter testet så `boisP3Admin` rensas ur sessionStorage.

0. Endast medlemskap: välj ungdom 200 kr, avmarkera gym, prova obligatoriska fält och skapa syntetisk order. Test-Swish → PAID/ACTIVE; PAID får inte erbjuda nytt betalningsförsök.
1. Ny medlem + gym: lämna statistikvalet obesvarat, skapa order för Ada Test, betala med Test-Swish och kontrollera `PAID`, medlemskap `ACTIVE`, Nordic `ELIGIBLE`. Ingen sales-session eller attribution får skapas.
2. Befintlig medlem + gym: avvisa statistik och vänta tills valet sparats, välj befintlig medlem för Bo Test, betala med Test-kort. Kontrollera `PENDING_MEMBER_VERIFICATION`; verifiera manuellt i admin och kontrollera `ELIGIBLE`.
3. Matchställ: acceptera statistik och vänta tills valet sparats; öppna därefter en syntetisk UTM-kampanjlänk och beställ ett testställ för Cia Test, betala i mock och kontrollera väntande batchkö. Använd admin `Skapa batch nu` för en identifierad syntetisk rad; kontrollera batchhistorik/CSV. Extern e-post är avstängd.
4. Betalningsfel: skapa separat testorder och välj misslyckad mockbetalning. Följ orderstatus, gör nytt försök och kontrollera `PAID` utan dubbel fulfillment.
5. Återkalla statistik via Kakinställningar. Kontrollera att nycklarna `boisSalesSession` och `boisSalesAttribution` försvinner och att fortsatt navigering och köp inte skriver sales-event/attribution. Orderstatus ska fungera.

## Samla faktisk återkoppling

Notera per punkt: vad Erik/BoIS försökte göra, vad som var tydligt eller svårt, förväntat och observerat resultat samt prioritet. Skilj på fel, förbättringsönskemål och verksamhetsbeslut. Notera faktisk enhet/webbläsare; fysisk iPhone/Safari är ännu inte testad genom CI. Inget verksamhetsgodkännande registreras innan deltagarna själva lämnat det.

Avsluta genomgången med en gemensam prioritering. Gör inte fler generella tekniska ändringar innan det finns konkret återkoppling och ett avgränsat nästa uppdrag.

## Bevis och avgränsning

Senaste verifierade skyddade demokörning: `36623915463`; workflowref `4918cef10bcc213ef9c756d407ca88d4990971fa`, publicerad appref `c49ebcd9af9f7081e1764d28419d92dd05b4fd48`. Chromium headless på Linux testade 375/390/1280 px. 10 PNG finns i [artifact bois-p9-synthetic-browser-36623915463](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/36623915463/artifacts/11059388459), till 2026-10-06 20:08:02 UTC. Bilderna är inte permanent arkiverade av denna closeout.

Historisk run `36517329717` verifierade det tidigare öppna demot. Dess åtkomstblockerare är nu löst. Endast ett begränsat antal behöriga granskare ska få tillgång; ingen publik kundlansering eller användning av verkliga kunduppgifter.

## Städning

Identifiera demots exakta order-ID, session-ID och eventnycklar i protokollet innan åtgärd. Ta endast bort demoposter med dokumenterad BoIS-specifik, FK-säker rutin efter beslut om revisionsspår; bevara betalningshistorik, andra order och privata rollback-backuper. Ingen allmän databasåterställning eller `DELETE` på hela tabeller. Utan en verifierad säker rutin lämnas posterna kvar märkta som syntetiska. Dokumentationscloseouten raderar inga poster.

Stripe/riktig betalning, extern mejlsändning/analytics och produktion är fortsatt avstängda. P8 är **TECHNICALLY COMPLETE / NOT ACTIVATED**; ny extern kostnad 0 kr.
