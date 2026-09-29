# Stagingåtkomst för begränsad syntetisk demo

Status: **IMPLEMENTED / DEPLOYED / LIVE VERIFIED**.

Den tidigare blockeraren är löst genom run [36623915463](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/36623915463), job `109595921802`, **success**, 2026-09-29. Workflow/main: `4918cef10bcc213ef9c756d407ca88d4990971fa`. Publicerad applikationsref: `c49ebcd9af9f7081e1764d28419d92dd05b4fd48`. Jobbsteg, logg och artifactmetadata är efterkontrollerade. Äldre run `36517329717` saknade inloggningsskydd och är historik, inte aktuellt åtkomstläge.

## Skydd och behörighet

BoIS-katalogen `/bois-shop-p3/` skyddas vid webbservern med HTTP Basic Auth över HTTPS. Rotens `.htaccess` omfattar shop, medlemskap/Nordic, matchställ, payment, orderstatus, admin, consent, assets och `commerce-api.php`, även med query strings och direkta URL:er. Interna PHP-filer har fortsatt `Require all denied`. Detta använder Simply-hostingens befintliga stöd utan extern tjänst eller ny kostnad.

Skyddet är för begränsad stagingdemo, inte produktionsinloggning. Det ersätter inte framtida personliga adminkonton, roller eller MFA.

Användarnamn: `bois-demo`. Lösenordet hanteras endast i Actions-secret `BOIS_STAGING_DEMO_PASSWORD` och genom godkänd privat kanal till ett litet antal behöriga granskare. Skriv inte lösenord, hash, ordertoken eller adminnyckel i GitHub, Drive eller skärmbilder.

Aktuell workflowgräns efter PR #24 är **minst 8 tecken**, inte dokumentets tidigare 24-teckenskrav. Ett längre unikt lösenord kan användas. Den verifierade körningen accepterade secretet; dess värde har inte lästs ut eller dokumenterats i denna closeout.

## Installation och separata nycklar

Workflowen genererar bcrypt-hash via `htpasswd` och lägger hashfilen utanför `public_html`. Den absoluta sökvägen hämtas via SSH. I den verifierade körningen användes katalogrättighet 755 och hashfilrättighet 644 så att webbservern kan läsa filen. Det är **inte** en hashfil med rättighet 600; runtime-konfigurationen ligger separat med rättighet 600. Inga rättigheter ändras av dokumentationscloseouten.

Webbservern använder `Authorization: Basic`. BoIS admin-API använder separat privat runtime-token via `X-Bois-Admin-Token`. Bearer-kompatibilitet finns för isolerade testkontrakt. Demoåtkomst är inte adminbehörighet, och adminnyckeln ska aldrig delas som allmän demoinloggning.

För att dra tillbaka åtkomst: rotera demo-secretet och kör en ny uttryckligt godkänd manuell deploy med verifierad appref. Rotation blir inte verksam på Simply enbart genom ändring i GitHub Secrets. Behåll inloggningsskyddet vid senare publicering.

## Verifierat i run 36623915463

- Publicerade `.htaccess` och relevanta applikationsfiler matchade payloadens SHA-256.
- Samtliga testade direkta HTML-, JS- och API-GET-anrop utan credentials gav **401**. Shop, admin, order, payment, consent och query strings ingick.
- POST mot `orders`, `checkout`, `mock_payment_event`, `consent` och `sales_event` utan credentials gav **401**.
- Fel demohemlighet gav **401**.
- Behörig P4–P9 API- och Chromiumdemo passerade bakom samma Basic Auth.
- Chromium headless testade 375, 390 och 1280 px med 10 sparade PNG. Bevis: `bois-p9-synthetic-browser-36623915463`, artifact ID `11059388459`, till 2026-10-06 20:08:02 UTC.

Verifieringsmetoden var direkta HTTP-kontroller och behörig automatiserad Chromiumkörning. En ny oberoende manuell webbläsargranskning har inte gjorts i dokumentationscloseouten. Gör en vanlig inloggningskontroll när Erik/BoIS-demot inleds. Fysisk iPhone/Safari är inte verifierad.

Vid framtida uteblivet 401 eller läsfel för AuthUserFile: stoppa delning och rätta värdkonfigurationen. `noindex` är endast en indexeringssignal. Dela nu endast till behöriga granskare, inte öppet till allmänheten.

Mock/testmode och syntetiska uppgifter kvarstår. Stripe/riktiga betalningar, externa mejl/analytics och produktion är inte aktiverade. P8: **TECHNICALLY COMPLETE / NOT ACTIVATED**. Nästa steg är verksamhetsdemo enligt `SYNTHETIC-DEMO.md`; ingen ny teknisk utvecklingsfas startas genom denna verifiering.
