# P20 – go-live-förberedelse 2026-10-06

Albert har beställt fortsatt arbete genom P20. Det ersätter den äldre
avgränsningen där P20 inte ingick, men ersätter inte verksamhetsbeslut eller
separat uttryckligt godkännande av Stripe live och slutlig publik DNS-cutover.

**P20: förberedelse pågår; publik aktivering BLOCKED.** P18B:s återstående
host- och driftbevis samt P19:s verksamhetssvar saknas. Ingen PRODUCTION READY,
samlad RC/tag eller affärsacceptans sätts innan kraven faktiskt är uppfyllda.

## Miljö och installerad kod

- Production är dedikerad Simply-produkt socen.se, DB `socen_se_db_BoIS_prod`.
- Staging ligger separat på alberiq.se med egna credentials och bevarade data.
- Installerad app är `cd76ea8ffedd6ca63eaf3905eda40031327421dc` efter PR42.
  Baseline för denna fortsättning är main `d34194bcc072a1d634c76cef5fcd2bb96e89db11`.
  Senare dokumentation och relayverktyg ändrar inte den installerade appen.
- Launch, checkout, Stripe live, externa mail och extern analytics är stängda.
  HTTPS liveness/readiness 200 och catalog/admin_orders 503 verifierades igen.

Produktseparationen är uppfylld. SELECT-only är ett annat krav: ett extra
begränsat production-konto för driftkontroller. Simply-kontot har ALL på
productionproduktens två DB:er. Panelen saknar separat användarhantering;
providerlösning eller uttryckligt dokumenterat undantag återstår. Alberts
fråga om socen-separation är inte registrerad som ett godkänt undantag.

## Lanseringsgrindar och faktiska beroenden

`server/production.php` kräver godkända referenser i privat
`production_decisions`; ett färdigskrivet dokument öppnar aldrig appen.
Varje beslut behöver faktisk beslutsfattare, datum och bevis, utan secrets.

| Privat beslut | Underlag före aktivering | Aktuellt läge |
|---|---|---|
| `go_live` | Uttryckligt lanseringsbeslut för exakt release, miljö och domän | Saknas |
| `p18_release` | Full P18A/P18B-acceptans och grön slutlig revision | P18A klar; P18B blockerad |
| `backup_restore` | Färsk full backup, offsite, övervakning, retention och tidsatt full DR | Offsite/filrestore verifierad; övriga krav återstår |
| `personal_admin_mfa` | Namngivna personliga production-konton, roller, MFA och återkallningsansvar | Productionregister tomt; syntetiska hostprov finns |
| `seller_merchant_bank` | BoIS juridiska säljare, betalningsmerchant och bankansvar | P19 öppet |
| `legal_policies` | Godkända köpvillkor, reklamation/refund, integritetstext och slutliga URL:er | P19 öppet |
| `product_partner_prices` | Godkända priser, Nordicvillkor, tröjleverantör, tryck, batch/MOQ/leverans | P19 öppet |
| `membership_period` | Giltighetsmodell, kategorier och verifiering av befintlig medlem | P19 öppet |
| `support_mail` | Avsändare/support/mottagare, provider, SPF/DKIM/DMARC och leveransprov | P19 öppet; extern transport stängd |
| `domain_dns_tls` | Slutlig shopdomän, TLS, verifierat DNS-underlag och separat cutoverbeslut | Slutlig URL/beslut saknas |
| `privacy_retention` | Verksamhetsbeslut om lagring/gallring, cookies och legal hold | P19 öppet; endast dry-run |
| `final_smoke_rollback` | Full hostbrowseracceptans, mänsklig acceptans och aktuell rollback | Partiella hostbevis; full acceptans återstår |

## Genomförande när underlagen finns

1. Stäm av main och installerad app; välj exakt grön app-SHA. Granska ändringar
   efter befintlig release och uppdatera privata runtimefiler först efter ny
   verifierad SQL/runtime/auth-backup och verifierad rollback.
2. Slutför P18B: begränsade driftkontroller, verifierad backup/offsite/alarmering,
   full tidsatt DR och full hostacceptans på avgränsad syntetisk instans.
   Produktion och befintlig staging används inte som kundtestfixture.
3. Registrera de verkliga P19-besluten. Skapa personliga production-admins genom
   befintlig säker P14-process; hemligheter och TOTP-seeds lagras enbart privat.
4. Förbered exakt privat go-live-konfiguration utan att slå på flaggor.
   Granska backup-programmet för öppen drift: den nu installerade versionen
   vägrar när production öppnas och får inte lämnas sådan vid lansering.
5. Förbered separat Stripe-live- och DNS-cutoverunderlag. Verkställ inte dessa
   åtgärder utan deras uttryckliga godkännanden. Extern mailaktivering kräver
   dessutom godkända mottagare/provider och verifierad transport; äldre
   syntetiska köer får aldrig skickas externt.
6. Kontrollera samtliga runtimegrindar, hostreadiness, backup, larm och rollback.
   Begär det slutliga lanseringsbeslutet på det konkreta färdiga underlaget.
7. Efter godkänd aktivering: verifiera slutlig HTTPS/shop-URL, admin, godkänd
   betalnings- och mailkontroll, loggning och rollbackberedskap. Dokumentera
   faktiskt installerad revision, beslut och driftöverlämning till Albert.

Ingen ny extern kostnad, verklig betalning, skarp gallring eller meddelande till
Erik/Simply har utförts som del av denna förberedelse.
