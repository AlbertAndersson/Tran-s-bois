# Stagingåtkomst för begränsad syntetisk demo

Status: implementation under verifiering. Den äldre run `36517329717` saknade åtkomstskydd. Bredare delning får ske först när en ny manuell deploy och direktåtkomsttest är gröna.

BoIS-katalogen `/bois-shop-p3/` skyddas vid webbservern med HTTP Basic Auth över HTTPS. En enda `.htaccess` i katalogens rot omfattar shop, medlemskap/Nordic, matchställ, payment, orderstatus, admin, consent, assets och `commerce-api.php`, även med query strings och direkta URL:er. Befintliga PHP-interna filer fortsätter att ge `Require all denied` även efter demoautentisering. Detta använder Simply-hostingens befintliga stöd för `.htaccess`, utan extern tjänst eller ny kostnad. Skyddet gäller endast staging och ersätter inte framtida personliga adminkonton, roller och MFA.

Simply beskriver katalogens `.htaccess` som gällande även undermappar: https://www.simply.com/dk/support/faq/php/27-aendring-af-php-indstillinger-med-htaccess/. Den absoluta värdsökvägen hämtas via SSH vid varje deploy; den antas inte från repo.

`BOIS_STAGING_DEMO_PASSWORD` ska vara ett starkt, privat Actions-secret med minst 24 tecken ur `A–Z`, `a–z`, `0–9`, `_`, `-`. Deployment avbryts innan publicering om det saknas. Workflowen genererar bcrypt-hash med `htpasswd` på runnern och installerar enbart hashfilen utanför `public_html` på Simply; lösenordet finns inte i repo, payload, skärmbilder eller sammanfattning. Användarnamnet är `bois-demo`. Dela lösenordet enbart genom en godkänd privat kanal med ett litet antal behöriga granskare; rotera secret och gör en ny manuell deploy när åtkomst ska dras tillbaka. Basic Auth skickar credentials per HTTPS-anrop. Kontrollera att åtkomst till den privata hashfilen fungerar på värden före delning.

Den befintliga adminnyckeln är separat från demoåtkomsten. Webbservern använder HTTP `Authorization: Basic`, medan BoIS admin-API tar `X-Bois-Admin-Token` och fortsätter att kontrollera samma privata runtime-token. Äldre Bearer-anrop behålls för isolerade testkontrakt. Adminnyckeln ska aldrig delas som allmän demoinloggning.

Manuell deploygrind och pinnad applikationsref består. Workflowen verifierar publicerad `.htaccess` och applikationsfilers SHA-256, kräver `401` utan credentials för direkt HTML-, JS- och API-åtkomst, kräver `401` med fel demohemlighet och kör därefter P4–P9 samt Chromium bakom Basic Auth. Dessutom ska en oberoende webbläsarkontroll utan inloggning och med korrekt behörighet göras efter deploy. Om `401` uteblir eller värden inte kan läsa `AuthUserFile`, stoppa delning och rätta den specifika värdkonfigurationen. `noindex` är endast en indexeringssignal.

Inga riktiga namn eller e-postadresser används i testet. Mock/testmode, avstängd extern mail/analytics och P8 `TECHNICALLY COMPLETE / NOT ACTIVATED` kvarstår. Fysisk iPhone/Safari är inte verifierad och är en separat manuell granskning.
