# Skyddat iPhone-test

Öppna i Safari: **https://socen.se/bois-p20-test/**.
Detta är en separat syntetisk testyta på avsedd production-hosting.
Production-appen på `/bois-shop-production/` är uppgraderad men köpstängd.

Inloggning och testadminnyckel finns endast på Alberts laptop i den privata
filen `C:\Users\AlbertAndersson\.codex\private\bois-production\P20-iPhone-testatkomst.txt`.
Den delas inte i repo, URL:er, chatten eller skärmbilder. Testets Basic Auth
är separat från production-skyddet; productionlösenordet har inte ändrats.

Använd påhittade namn, exempelvis iPhone Test, och adresser under
`example.invalid`. Ange inga riktiga personnummer eller kortuppgifter.
Betalningsvyn är mock/test och tar inga pengar. Extern mail är avstängd.
Testet innebär inte en beställning till föreningen eller leverantören.

1. Öppna shopen stående och liggande. Kontrollera läsbarhet, knappar och
   att sidan inte kräver sidledes rullning.
2. Prova att avvisa statistik och genomför ett medlemsköp. Priserna är
   ungdom 200 kr, vuxen 350 kr, pensionär 300 kr. Medlemskapet gäller till
   31 december innevarande år, inte 365 dagar från betalningen.
3. Prova gymtillägg 2 650 kr. Årskvoten är 20 och delas mellan alla prov.
   Befintlig medlem utan import går via adminverifiering. Testa betalad,
   nekad och avbruten betalning samt nytt försök.
4. Beställ en tröja för 998 kr, välj S/M/L, lag, spelare och nummer.
   Endast tröja ingår. Läs informationen om månadsbeställning och lagutlämning.
5. Kontrollera orderstatus efter testbetalningen och återbesök samma order.
   Betalad order ska inte erbjuda ny betalning.
6. Öppna kakinställningarna, acceptera statistik och återkalla sedan valet.
   Kontrollera att köp kan göras även när statistik är avvisad.
7. I `/admin.html`, använd testadminnyckeln från den privata filen. Prova
   medlemsverifiering, orderlista, tröjkö, manuell batch och mockrefund.
   Månadsdag är ännu inte beslutad; automatisk månadsbatch är spärrad.
   Mail får inte skickas externt även om en worker provas.

Återrapportera iPhone-modell, iOS-version, vilket steg som provades och vad
som hände. Skärmbilder ska inte visa inloggningsuppgifter, adminnycklar eller
orderlänkar med åtkomsttoken. Faktisk Safariacceptans registreras först efter
Alberts återkoppling; automatiserad Edge-acceptans är ett separat bevis.
