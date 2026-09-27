# P5 – Match kit batching

Datum: 2026-09-27

## Mål
Automatisera matchställsbeställningarna så att flera betalda order kan samlas till en leverantörsorder för att minska frakt och handpåläggning.

P5 ska inte aktivera verklig betalning eller skicka externa mejl i staging.

## Startregel
Konfigurerad P5-start:
- tröskel: **8 betalda matchställ**
- max väntetid: **168 timmar / 7 dagar**
- Erik/admin kan alltid välja **Skicka batch nu**

Värdena ligger i \`bois_fulfillment_rules\` och kan ändras utan ombyggnad.

## Kandidatkrav
En orderrad får bara batchas om:
1. fulfillment_type = \`BATCH_SUPPLIER\`
2. orderns payment_status = \`PAID\`
3. orderradens fulfillment_status = \`WAITING_BATCH\`
4. orderraden inte redan finns i \`bois_batch_items\`

Det innebär att obetalda order, medlemskap och gymkort aldrig kan hamna i matchställsbatchen.

## Flöde
Efter betalning:
1. order = PAID
2. matchställsrad = WAITING_BATCH
3. batchmotorn räknar väntande antal och äldsta order
4. tröskel 8 eller 7 dagar → batch skapas
5. orderrader låses och kopplas till exakt ett batch-ID
6. leverantörs-CSV skapas
7. SHA-256 av CSV sparas i batchen
8. e-postmeddelande skapas i \`bois_email_outbox\`
9. orderrad = BATCHED
10. efter lyckad mailsändning → SENT_TO_SUPPLIER

## Idempotens
- \`bois_batch_items.order_item_id\` är UNIQUE.
- Samma orderrad kan därför inte finnas i två batcher.
- Outboxens \`message_key = supplier_batch:<batch-id>\` är UNIQUE.
- Samma batchmail kan inte köas dubbelt.
- Batchskapandet körs i transaktion med radlåsning.

## CSV
P5-genererad leverantörsfil innehåller:
- ordernummer
- lag
- spelare
- tröjstorlek
- byxstorlek
- nummer
- namntryck
- nummertryck
- antal
- SKU

Kolumnordningen kan justeras när verklig leverantör lämnat sitt format.

## E-post/outbox
Datamodellen stödjer:
- till-adress
- kopia
- ämne
- exakt batchpayload
- CSV som base64 snapshot
- CSV SHA-256
- attempts
- not_before
- retry
- last_error
- sent_at

Retry använder exponentiell väntetid och går till FAILED efter fem misslyckade försök.

## Staging-säkerhet
I alla miljöer som inte är \`production\` tvingas mottagarna till:
- \`supplier@example.invalid\`
- \`erik@example.invalid\`

Det betyder att en stagingbatch inte kan råka mejlas till verklig leverantör eller Erik.

Mailtransport är dessutom \`disabled\` på AlberIQ-staging. Admin kan skapa batch/outbox men inget externt mejl skickas.

## Admin
P5 shopadmin visar:
- väntande order/ställ
- hur många som återstår till tröskeln
- äldsta väntetid
- batchhistorik
- trigger: THRESHOLD / MAX_WAIT / MANUAL
- e-post/outbox-status och attempts
- leverantörs-CSV
- knapp: Skicka batch nu
- knapp: Kontrollera automatik
- stagingknapp: Simulera betald

## Koppling till P6
P6 betalningswebhook ska anropa samma betald-händelse som P5:s staging-simulering.

När en riktig matchställsbetalning går igenom:
- payment_status → PAID
- orderrad → WAITING_BATCH
- P5 utvärderar tröskeln direkt

För 7-dagarsregeln finns \`p5-worker.php\`, som kan köras schemalagt. Schemaläggning aktiveras först när riktiga betalningar är live för att undvika onödiga körningar/kostnader i utvecklingsfasen.

## Definition of Done
P5 är klar när:
- 8 betalda testställ skapar exakt en THRESHOLD-batch
- ny körning duplicerar inte batchen
- manuell batch fungerar under tröskeln
- 7-dagarsregeln skapar MAX_WAIT-batch
- CSV och hash verifieras
- outbox skapas exakt en gång per batch
- misslyckad mailtransport ger RETRY
- retry kan lyckas och markera batch/order SENT
- live staging har mailtransport disabled
- inga nya externa kostnader har aktiverats
