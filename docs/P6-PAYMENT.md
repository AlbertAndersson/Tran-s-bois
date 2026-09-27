# P6 – Payment

Datum: 2026-09-27

## Status

**COMPLETE / LIVE STAGING VERIFIED**

P6 bygger en riktig betalningsgräns mellan order och P4/P5 utan att aktivera en extern betaltjänst eller ny kostnad.

I staging används en isolerad provider-adapter: `mock`.

`BoisPaymentProviderAdapter` avgränsar checkout-session och webhookverifiering. `BoisMockPaymentProvider` är den enda aktiva implementationen. `BoisDisabledPaymentProvider` stoppar övriga konfigurationer tills en verklig provider implementeras och godkänns uttryckligen.

Det betyder:
- Test-Swish och Test-kort finns i UI.
- ingen riktig betalning genomförs
- ingen merchant/provider har aktiverats
- ingen ny extern kostnad har skapats

## Grundprincip

En order får inte driva medlemskap, Nordic eligibility eller matchställsbatchning förrän en signerad betalhändelse har verifierats.

Flöde:

1. order skapas
2. servern skapar checkout/session
3. payment status → `PENDING`
4. provider skickar webhook
5. signatur + timestamp verifieras
6. event-id claimas idempotent
7. betalning → `PAID`
8. gemensam verifierad PAID-handler kör P5-betalgränsen
9. P4 medlemskap/Nordic appliceras
10. P5 matchställ går till `WAITING_BATCH`
11. kvitto/orderbekräftelse läggs i separat outbox

## Serverstyrd checkout

Endpoint:
- `POST commerce-api.php?action=checkout`

Kräver:
- `public_id`
- orderns publika token
- metod: `swish` eller `card`

Servern skapar:
- provider-ref
- checkout-session
- separat sessions-token
- payment status `PENDING`

Staging-provider är `mock`.

En framtida verklig provider ska implementeras bakom samma checkout-/webhookgräns.

## Signerad webhook

Endpoint:
- `POST commerce-api.php?action=payment_webhook`

Headers:
- `X-BoIS-Payment-Timestamp`
- `X-BoIS-Payment-Signature`

Signatur:
- HMAC-SHA256
- signerat innehåll: `<timestamp>.<raw-body>`
- serverhemlighet finns endast i runtime-config
- timestamp-tolerans skyddar mot gamla replay-försök

Webhook utan giltig signatur nekas före state transition.

## Idempotens

Ny tabell:
- `bois_payment_events`

Unik nyckel:
- `(provider, event_id)`

Ett färdigprocessat event kan därför inte processas igen.

Om en intern downstream-körning misslyckas lagras eventet som `ERROR` och samma event får köras om.

Betalningsraden har dessutom:
- `effects_status = NOT_APPLICABLE | PENDING | APPLIED`

Det gör att P4/P5-effekter kan återupptas säkert om ett fel uppstår efter att providerbetalningen redan har markerats `PAID`.

P4 har kompletterats så samma redan aktiverade membership-transaction inte kan förlänga medlemstiden en gång till vid retry.

## Payment state machine

Stödda huvudstatusar:

- `NOT_ENABLED`
- `PENDING`
- `PAID`
- `FAILED`
- `CANCELLED`
- `PARTIALLY_REFUNDED`
- `REFUND_PENDING`
- `REFUNDED`

Tillåtna kärnövergångar:

- NOT_ENABLED/FAILED/CANCELLED → PENDING via ny checkout
- PENDING → PAID
- PENDING → FAILED
- PENDING → CANCELLED
- PAID → PARTIALLY_REFUNDED
- PAID/PARTIALLY_REFUNDED → REFUND_PENDING
- REFUND_PENDING → PARTIALLY_REFUNDED / REFUNDED
- PAID → REFUNDED
- PARTIALLY_REFUNDED → PARTIALLY_REFUNDED / REFUNDED

## Refunds

Refund uppdaterar den finansiella statusen idempotent.

P6 gör **inte automatisk reversering av redan startat fulfillment**.

Vid refund:
- ordern går till `REVIEW_REQUIRED`
- matchställ med orderstatus som inte längre är PAID tas automatiskt ur P5-kandidaturvalet
- medlemskap/Nordic eller redan batchad leverantörsorder backas inte tyst
- admin måste besluta om eventuell manuell reversering

Detta är avsiktligt för att undvika att ett finansiellt webhook-event automatiskt återkallar en extern eller redan levererad förmån.

## Kvitto / orderbekräftelse

Ny tabell:
- `bois_payment_outbox`

Typer:
- `PAYMENT_RECEIPT`
- `REFUND_RECEIPT`

Outbox har:
- unik message key
- attempts
- retry/backoff
- FAILED efter fem misslyckade försök

I staging:
- mottagare tvingas till `customer@example.invalid`
- `payment_mail_transport = disabled`
- inget riktigt kundmejl kan skickas

## Staging UI

Ny sida:
- `payment.html`

Den erbjuder:
- Test-Swish
- Test-kort
- simulerad provider-success
- simulerad provider-failure
- simulerad cancel

Mocken genererar internt samma signerade webhook som en extern provider skulle använda.

## Produktion

P6 aktiverar inte någon verklig provider.

Följande beslut kvarstår:
- vem är merchant/betalningsmottagare?
- vald Swish/kort-provider
- provideravgift
- merchant onboarding
- produktionscredentials/certifikat
- verklig webhook-konfiguration
- refund-policy mot medlemskap/Nordic/matchställ

Ingen sådan kostnad eller extern tjänst ska aktiveras utan uttryckligt godkännande.

## Definition of Done för P6 staging

P6 är stagingklar när:
- serverstyrd checkout fungerar
- Test-Swish och Test-kort fungerar
- signerad webhook accepterar korrekt signatur
- fel signatur nekas
- samma event-id processas exakt en gång
- flera PAID-event för samma order förlänger inte medlemskapet igen
- medlemskap aktiveras först efter verifierad PAID
- Nordic entitlement blir eligible först efter verifierad PAID
- matchställ går till WAITING_BATCH först efter verifierad PAID
- refunds går till definierad state + REVIEW_REQUIRED
- kvitto/refund-outbox har retry
- externa mail är disabled
- ingen extern betaltjänst eller kostnad är aktiverad


## Verifieringsbevis 2026-09-27

### GitHub CI

P6 Payment CI:
- workflow run: `36324968897`
- conclusion: **success**

Verifierat:
- PHP syntax
- MySQL 8.4 smoke
- signerad webhook
- ogiltig signatur nekas
- samma event-id processas inte två gånger
- flera PAID-event applicerar inte medlemskap igen
- medlemskap/Nordic först efter verifierad PAID
- matchställ först till batchkö efter verifierad PAID
- refund state machine
- receipt-outbox retry
- inga riktiga betalningar/mejl

Samma P6-commit passerade även P2, P3, P4 och P5 CI.

### Simply / AlberIQ live staging

Deployment:
- repo: `AlbertAndersson/work-capture`
- workflow: `.github/workflows/simply-deploy-bois-p4.yml`
- workflow run: `36325200482`
- deployment commit: `903621e4be684b15848ff94d688d789ee4871b19`
- conclusion: **success**

Live verifierat:
- P6 source validation
- privat runtime + strict SSH
- P6 migration
- icke-BoIS-tabeller oförändrade
- `phase=P6`
- `payment_enabled=true` endast i staging-testmode
- `payment_provider=mock`
- serverstyrd checkout
- Test-Swish
- mockens signerade webhook-väg
- verifierad PAID → medlemskap ACTIVE
- verifierad PAID → Nordic ELIGIBLE
- andra PAID-eventet ger `already_applied=true`
- P5 batchregel 8 / 168h bevarad
- payment mail transport disabled
- verklig payment provider: **nej**
- ny extern kostnad: **0 kr**

## Nästa fas

P7 – 2027 assortment.

P6:s riktiga produktionsprovider är en separat P8/produktionsfråga och får inte aktiveras utan uttryckligt godkännande av provider, merchant-upplägg och kostnad.

## Uppföljande P6-granskning 2026-09-27
Första leveransen slogs samman i en implementation-commit trots den önskade uppdelningen P6A–D. Granskningen hittade en kvarvarande alternativ PAID-väg i admin. Skärpningen på `chatgpt/p6-payment-hardening-20260927` gör att adminens stagingtest skapar checkout och ett signerat mock-event genom P6. Den kräver också exakt order-ID, sessionsreferens, valuta och belopp, nekar återanvänt event-ID med ändrad payload och använder MySQL named lock per betalreferens över eventclaim och P4/P5-effekter. Publik ordervy visar en testbetalningsreferens; admin visar betalningar och event. Regressionerna omfattar dessa kontroller, CANCELLED och PARTIALLY_REFUNDED. Ny CI och stagingverifiering dokumenteras efter slutförd körning.

`REFUND_PENDING` markerar manuell granskning utan automatisk återgång av fulfillment och kan följas av partiell eller full återbetalning.
