# Demo · syntetiska data

Använd endast namnen Ada Test, Bo Test, Cia Test och adresser på `example.invalid`. Endast BoIS staging med mock/testmode. Notera order-ID och token privat för just detta demo; visa aldrig adminnyckel i skärmbild.

1. Ny medlem + gym: avvisa statistik, skapa order för Ada Test, betala med Test-Swish och kontrollera `PAID`, medlemskap `ACTIVE`, Nordic `ELIGIBLE`. Ingen sales-session eller attribution får skapas.
2. Befintlig medlem + gym: acceptera statistik före en ny sidvisning, välj befintlig medlem för Bo Test, betala med Test-kort. Kontrollera `PENDING_MEMBER_VERIFICATION`; verifiera manuellt i admin och kontrollera `ELIGIBLE`.
3. Matchställ: beställ ett testställ för Cia Test, betala i mock och kontrollera väntande batchkö. Använd admin `Skapa batch nu` för en identifierad syntetisk rad; kontrollera batchhistorik/CSV. Extern e-post är avstängd.
4. Betalningsfel: skapa separat testorder och välj misslyckad mockbetalning. Följ orderstatus, gör nytt försök och kontrollera `PAID` utan dubbel fulfillment.
5. Återkalla statistik via Kakinställningar. Kontrollera att nycklarna `boisSalesSession` och `boisSalesAttribution` försvinner och att fortsatt navigering och köp inte skriver sales-event/attribution. Orderstatus ska fungera.

Städning: identifiera demots exakta order-ID, session-ID och eventnycklar i protokollet innan åtgärd. Ta endast bort demoposter med dokumenterad BoIS-specifik, FK-säker rutin efter beslut om revisionsspår; bevara betalningshistorik, andra order och privata rollback-backuper. Ingen allmän databasåterställning eller `DELETE` på hela tabeller. Utan en verifierad säker rutin lämnas posterna kvar märkta som syntetiska.

Bredare delning blockeras tills stagingens autentiseringsskydd har verifierats. `noindex` och okänd URL är inte åtkomstkontroll.
