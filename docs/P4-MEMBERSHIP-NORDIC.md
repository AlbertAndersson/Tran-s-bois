# P4 – Membership & Nordic Wellness

Datum: 2026-09-27

## Status
**COMPLETE / LIVE STAGING VERIFIED**

## Mål
Göra medlemskap och Nordic Wellness-förmånen operativt hanterbara i BoIS egen shop utan att vara beroende av ett Nordic Wellness-API.

## Grundmodell
P4 använder två separata nivåer:
- `bois_members` – aktuell medlemsstatus
- `bois_benefit_entitlements` – Nordic Wellness-förmånsärenden

Transaktionsraden `bois_memberships` behålls som köp-/orderhistorik.

## Medlemskap
Efter `PAID`:
- medlemsköp → `ACTIVE`
- medlemsregisterpost skapas eller uppdateras
- giltighet beräknas från konfigurerad regel
- staging använder `membership_validity_days = 365`
- obetalda medlemskap aktiveras aldrig

## Förnyelse
- aktiv medlem med samma e-post återanvänds
- samma `member_uuid` behålls
- medlemsperioden förlängs
- ingen dubblettmedlem skapas
- den nya ordern finns kvar som separat historik

## Nordic eligibility

### Ny medlem + gym i samma order
1. order blir betald
2. medlemskap aktiveras
3. medlemsregister skapas
4. Nordic entitlement länkas till medlem
5. status → `ELIGIBLE`

### Redan medlem + bara gym
1. order blir betald
2. Nordic entitlement → `PENDING_MEMBER_VERIFICATION`
3. Erik/admin verifierar medlemskapet
4. medlemsregister länkas eller skapas
5. status → `ELIGIBLE`

## Nordic-statusflöde
- `PENDING_PAYMENT`
- `PENDING_MEMBER_VERIFICATION`
- `ELIGIBLE`
- `SENT_TO_PARTNER`
- `READY_FOR_PICKUP`
- `ACTIVATED`
- `REJECTED`

## Shopadmin
Admin stödjer:
- aktiva medlemmar
- väntande medlemskontroller
- eligible gymkort
- aktiverade gymkort
- medlemsregister
- Nordic-ärenden
- verifiera befintlig medlem
- partnerreferens
- markera skickad till Nordic
- markera klar att hämta
- markera aktiverad
- Nordic CSV-export

## Kundens orderstatus
Orderstatus visar även:
- medlemsstatus
- Nordic-förmånsstatus

## Personuppgifter
P4 samlar **inte in personnummer**.

Nordic-exporten innehåller:
- order
- namn
- e-post
- telefon
- medlemsnamn
- medlemstyp
- medlemsperiod
- status

Om Nordic i det nya avtalet uttryckligen kräver ytterligare identifierare läggs de inte till förrän kravet och integritetsbehovet är bekräftat.

## Partnerintegration
P4 är operativt komplett med manuell partnerhandoff.

När Nordic bekräftar sitt slutliga arbetssätt kan `SENT_TO_PARTNER` kopplas till exempelvis:
- CSV/e-post
- portalimport
- SFTP
- API

Det kräver inte ombyggnad av medlems- eller eligibilitylogiken.

## Koppling till P6
P4 aktiverar inget på obetald order.

I staging används `Simulera betald`. P6 ersätter detta med en riktig betalwebhook och återanvänder samma P4-funktioner.

## Verifiering
MySQL 8.4 CI:
- ny medlem + gym → ELIGIBLE: pass
- befintlig medlem kräver verifiering: pass
- manuell medlemsverifiering: pass
- medlemsförnyelse utan dubblett: pass
- Nordic-status till ACTIVATED: pass
- Nordic-export utan personnummerfält: pass
- payment gate: pass
- admin JavaScript: pass

AlberIQ/Simply:
- P4 migration: pass
- P5 migration bevarad: pass
- filrättigheter verifierade: pass
- icke-BoIS-tabeller oförändrade: pass
- live ny medlem + gym: pass
- live befintlig medlem + gym: pass
- live adminverifiering: pass
- live Nordic-statusflöde: pass
- live Nordic CSV: pass
- publika sidor/API: pass

## Kostnad
Ny extern kostnad: **0 kr**.

## Årskvot för Nordic Wellness

Nytt verksamhetsbeslut 2026-10-04: BoIS får sälja högst **20 Nordic Wellness-gymkort per kalenderår**.

Teknisk modell:
- kvoten är serverstyrd i Commerce Core, inte endast en frontendmarkering
- publikt katalogsvar innehåller aktuell tillgänglighet: limit, sold, reserved, remaining och sold_out
- påbörjade gymorder reserverar en plats kortvarigt för att minska risken för översäljning
- standard reservationstid är 30 minuter
- PAID, PARTIALLY_REFUNDED och REFUND_PENDING räknas mot årets kvot
- nya gymorder stoppas fail-closed när årets tillgängliga saldo är 0
- medlemsflödet visar återstående antal och markerar produkten som **Slutsåld** när kvoten är slut
- samtidig orderläggning serialiseras med databaslås så den 21:a ordern inte kan smita igenom vid race condition

Kvoten gäller kalenderår i Europe/Stockholm. Hur en fullt återbetald/återkallad gymförmån ska påverka den avtalsmässiga 20-gränsen ska bekräftas med BoIS/Nordic innan skarp drift; nuvarande tekniska modell frigör en helt återbetald order från säljsaldot.
