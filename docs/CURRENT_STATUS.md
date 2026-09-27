# CURRENT STATUS

Datum: 2026-09-27

## Övergripande status
- **P1 – ordermotor: COMPLETE**
- **P2 – produktionsförberedelse: COMPLETE**
- **P3 – Commerce Core / MySQL: COMPLETE**
- **P4 – Membership & Nordic Wellness: COMPLETE / LIVE STAGING VERIFIED**
- **P5 – Match kit batching: COMPLETE / LIVE STAGING VERIFIED**
- **P6 – Payment: COMPLETE / LIVE STAGING VERIFIED**

Betalning: **ISOLERAD MOCK/TESTMODE I STAGING – RIKTIG PROVIDER AVSTÄNGD**  
Extern mejlsändning: **AVSTÄNGD I STAGING**  
Ny extern kostnad: **0 kr**

## Aktiv staging
- Shop: https://alberiq.se/bois-shop-p3/
- Medlemskap + gym: https://alberiq.se/bois-shop-p3/membership.html
- Matchställ: https://alberiq.se/bois-shop-p3/match-kit.html
- Orderstatus: https://alberiq.se/bois-shop-p3/order.html
- Shopadmin P4 + P5 + P6: https://alberiq.se/bois-shop-p3/admin.html
- Testbetalning P6: https://alberiq.se/bois-shop-p3/payment.html
- API health: https://alberiq.se/bois-shop-p3/commerce-api.php?action=health

Staging använder endast testuppgifter.

## Affärsbeslut
Säljbara före 31 december 2026:
- medlemskap
- Nordic Wellness gymkort för medlem
- matchställ

Priser:
- ungdomsmedlemskap: 200 kr
- vuxenmedlemskap: 350 kr
- pensionärsmedlemskap: 300 kr
- Nordic Wellness gymkort: 2 650 kr

Matchställspriset 998 kr är fortsatt **endast staging/testpris**.

## P4 – Membership & Nordic Wellness

### Medlemsregister
P4 har:
- `bois_members` för aktuell medlemsstatus
- `bois_benefit_entitlements` för Nordic-förmånsärenden
- `bois_memberships` kvar som order-/transaktionshistorik

Efter `PAID`:
- medlemskap → `ACTIVE`
- medlemsregister skapas/uppdateras
- stagingmedlemsperiod = 365 dagar
- obetalda medlemskap aktiveras aldrig

### Förnyelse
- aktiv medlem med samma e-post återanvänds
- samma `member_uuid` behålls
- giltigheten förlängs
- ingen dubblettmedlem skapas
- ny order sparas separat

### Nordic-flöde
Ny medlem + gym:
- PAID → medlemskap ACTIVE → gym `ELIGIBLE`

Befintlig medlem + gym:
- PAID → `PENDING_MEMBER_VERIFICATION`
- Erik/admin verifierar medlem
- → `ELIGIBLE`

Fortsatt status:
- `ELIGIBLE`
- `SENT_TO_PARTNER`
- `READY_FOR_PICKUP`
- `ACTIVATED`
- eller `REJECTED`

### Admin
Admin stödjer:
- medlemsregister
- aktiva medlemmar
- väntande medlemskontroller
- eligible/aktiverade gymkort
- verifiera befintlig medlem
- Nordic-referens
- markera skickad till Nordic
- markera klar att hämta
- markera aktiverad
- Nordic CSV-export

### Personuppgifter
P4 samlar **inte in personnummer**.

Nordic-exporten innehåller order, namn, e-post, telefon, medlemsnamn, medlemstyp, medlemsperiod och status.

### Partnerintegration
P4 är komplett med manuell partnerhandoff. Nordic-transporten kan senare bytas till API, SFTP, portalimport eller annat format utan att medlems-/eligibilitylogiken byggs om.

## P5 – Match kit batching
- tröskel: **8 betalda matchställ**
- max väntetid: **168 timmar / 7 dagar**
- admin kan välja **Skicka batch nu**
- endast `PAID + BATCH_SUPPLIER + WAITING_BATCH` kan batchas
- dubblettskydd
- CSV + SHA-256
- e-post-outbox + retry/backoff
- historik i admin

Staging:
- leverantör = `supplier@example.invalid`
- cc = `erik@example.invalid`
- `mail_transport = disabled`

## P6 – Payment
P6 använder en isolerad `mock`-provider i staging. Ingen verklig betaltransaktion eller extern provider är aktiverad.

Kärnflöde:
- serverstyrd checkout
- Test-Swish och Test-kort
- signerad HMAC-SHA256-webhook med timestamp-tolerans
- unik `(provider,event_id)` för webhook-idempotens
- `effects_status` för retry-säker downstream-applicering
- verifierad `PAID` återanvänder P4/P5:s gemensamma betalgräns
- medlemskap/Nordic/matchställ kan inte gå vidare före `PAID`
- refunds sätter finansiell status och `REVIEW_REQUIRED` för fulfillment
- kvitto/refund-outbox har retry/backoff
- `payment_mail_transport = disabled`
- stagingkvitton tvingas till `customer@example.invalid`

Payment states:
- `PENDING`
- `PAID`
- `FAILED`
- `CANCELLED`
- `PARTIALLY_REFUNDED`
- `REFUNDED`

## Verifiering

### GitHub CI / MySQL 8.4
P4:
- ny medlem + gym → ELIGIBLE: pass
- befintlig medlem kräver verifiering: pass
- adminverifiering → ELIGIBLE: pass
- förnyelse utan dubblett: pass
- Nordic-status till ACTIVATED: pass
- Nordic CSV utan personnummerfält: pass
- unpaid membership gate: pass
- admin JavaScript: pass
- privacy/safety: pass

P5:
- threshold batch: pass
- max-wait batch: pass
- manual batch: pass
- dubblettskydd: pass
- retry: pass

P6 – GitHub Actions run `36324968897`:
- PHP syntax: pass
- MySQL payment smoke: pass
- korrekt signerad webhook: pass
- ogiltig signatur nekas: pass
- event-idempotens: pass
- P4 först efter verifierad PAID: pass
- P5-kö först efter verifierad PAID: pass
- refund state machine: pass
- receipt outbox retry: pass
- real payment sent: no
- real email sent: no

### AlberIQ / Simply
Senast verifierad P6-deploy: workflow run `36325200482`, conclusion **success**.

- P6 migration: pass
- filrättigheter/privat runtime: pass
- icke-BoIS-tabeller oförändrade: pass
- API `phase=P6`: pass
- `payment_enabled=true` i isolerat staging-testmode: pass
- `payment_provider=mock`: pass
- serverstyrd checkout live: pass
- Test-Swish live: pass
- verifierad PAID → medlemskap ACTIVE: pass
- verifierad PAID → Nordic ELIGIBLE: pass
- andra PAID-eventet applicerar inte P4/P5 igen: pass
- P5-regel 8 / 168h bevarad: pass
- payment mail transport disabled: pass
- real payment provider: no
- new external cost: 0

## Drift
Aktiv deployment:
- `work-capture/.github/workflows/simply-deploy-bois-p4.yml`

Workflowen är nu uppgraderad till P6. Äldre P3- och P5-deployworkflow är pensionerade så de inte kan skriva över aktuell staging.

## Kostnad
**Ny extern kostnad: 0 kr.**

## Nästa fas – P7 2027 assortment
P7 ska förbereda supporter-/merchsortimentet för lansering efter Intersport-avtalets slut.

Prioriterat:
- verkliga leverantörer och inköpspriser
- försäljningspriser/marginal
- SKU/artikelnummer och varianter
- produktbilder och produktcopy
- fulfillment per produkt
- lager/direct supplier-regler
- launch gate som gör att sortimentet **inte blir publikt/orderbart före 1 januari 2027**

P6 förblir testmode tills merchant, provider, kostnad och produktionsupplägg har godkänts uttryckligen.

## P6 verifieringsskärpning efter första stagingleverans
En granskning efter P6:s första deploy fann att `admin_simulate_paid` fortfarande gick direkt via P5. Separat branch `chatgpt/p6-payment-hardening-20260927` tar bort den alternativa vägen, binder signerade event till order/session/valuta/belopp, serialiserar behandling per betalning och visar betalreferens/event. Tidigare live-verifiering avser första P6-implementationen; denna skärpning kräver egen CI och stagingdeploy innan den räknas som live.
