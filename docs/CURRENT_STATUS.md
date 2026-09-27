# CURRENT STATUS

Datum: 2026-09-27

## Övergripande status
- **P1 – ordermotor: COMPLETE**
- **P2 – produktionsförberedelse: COMPLETE**
- **P3 – Commerce Core / MySQL: COMPLETE**
- **P4 – Membership & Nordic Wellness: COMPLETE / LIVE STAGING VERIFIED**
- **P5 – Match kit batching: COMPLETE / LIVE STAGING VERIFIED**
- **P6 – Payment: NEXT**

Betalning: **AVSTÄNGD**  
Extern mejlsändning: **AVSTÄNGD I STAGING**  
Ny extern kostnad: **0 kr**

## Aktiv staging
- Shop: https://alberiq.se/bois-shop-p3/
- Medlemskap + gym: https://alberiq.se/bois-shop-p3/membership.html
- Matchställ: https://alberiq.se/bois-shop-p3/match-kit.html
- Orderstatus: https://alberiq.se/bois-shop-p3/order.html
- Shopadmin P4 + P5: https://alberiq.se/bois-shop-p3/admin.html
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

### AlberIQ / Simply
- P4 + P5 migration: pass
- filrättigheter korrigerade och verifierade: pass
- icke-BoIS-tabeller oförändrade: pass
- API `phase=P4+P5`: pass
- ny medlem + gym live: pass
- befintlig medlem + gym live: pass
- adminverifiering live: pass
- Nordic-status till ACTIVATED live: pass
- Nordic CSV live: pass
- offentlig orderstatus med medlems-/förmånsstatus: pass
- P5-regel 8 / 168h bevarad: pass
- payment_enabled=false
- mail_transport=disabled

## Drift
Aktiv deployment:
- `work-capture/.github/workflows/simply-deploy-bois-p4.yml`

Äldre P3- och P5-deployworkflow är pensionerade så de inte kan skriva över P4+P5.

## Kostnad
**Ny extern kostnad: 0 kr.**

## Nästa fas – P6 Payment
P6 ska lägga till:
- Swish/kort
- betalwebhook
- idempotent betalstatus
- refunds
- kvitto/orderbekräftelse
- fel-/retryhantering

Riktig `PAID` ska anropa samma P4/P5-logik som stagingens simulerade betalning. Medlemskap, Nordic eligibility och matchställsbatchning behöver därför inte byggas om.
