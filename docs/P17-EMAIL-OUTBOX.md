## Aktuell styrning 2026-10-05

## Aktuell lokal hoststatus 2026-10-06

Stängd production på dedikerad socen.se-produkt är uppgraderad till main
`cd76ea8ffedd6ca63eaf3905eda40031327421dc` efter PR42. Verifierad privat
backup, isolerad restore, rollback, readiness och syntetiska hostprov finns.
Verifierad krypterad offsitekopia finns på Besovida. Daglig Simply-backup är
installerad. Albert har accepterat driftansvar, RPO 24 h och RTO 4 h som mål.
P18A är DONE; P18B/P18 är fortsatt BLOCKED vid SELECT-only, automatisk
offsiteöverföring, övervakning/key escrow och full hostbrowseracceptans. Se [nya hostbevis och begränsningar](P18B-LOCAL-HOST-EVIDENCE.md).
Den äldre molnkörningens credentialblockerare nedan är historik och gäller
inte som aktuell lokal status. Staging är separat och har inte ändrats.

## Tidigare dokumentation (historik)

P18A är DONE och mergad via PR40. P18B är beställd men **BLOCKED**:
run37241703902 på main `4e4e2349631d434160b77bd9b2b2d3e7be7db349`
saknar produktionscredentials i GitHub och stannade före DB-anslutning.
Befintlig privat produktionsåtkomst är dokumenterad på Alberts lokala dator.
Ingen ny hostdeployment, migration eller aktivering utfördes.
P12 är accepterad av Albert; återstående hostbevis ligger i P18B.
P13–P17 är klara i kod/isolering och får inte beskrivas som fullt installerade.
P18 och P20 förblir BLOCKED vid hostacceptans; P19:s verksamhetssvar är öppna.
Se [aktuell hostacceptans och lokal fortsättning](P18B-HOST-ACCEPTANCE.md).
Äldre nästa-etapp-rader nedan är historik och gäller inte som arbetsinstruktion.

---

# P17 Email and outbox production readiness

## Scope and activation boundary

P17 implements a testable mail chain, not mail activation. Both existing queues
(`bois_email_outbox` for supplier batches and `bois_payment_outbox` for payment
messages) use `server/p17_mail.php`. No migration or new table is required.
Production defaults remain `mail_transport=disabled` and
`payment_mail_transport=disabled`. No Simply deployment, SMTP account, real
recipient, live Stripe, DNS change, mail delivery or new cost is part of P17.

Albert accepted P12 on 2026-10-04 and authorized P17. This records the acceptance
decision; it does not invent additional P12 host-test evidence or authorize
production activation. socen.se remains dedicated closed production; protected
mock staging and separate Stripe sandbox on alberiq.se are unchanged.

## Message chain

Signed verified PAID queues an order confirmation/payment receipt. Verified
FAILED/CANCELLED queues payment information within the payment transaction;
verified refund queues its receipt in the same transaction as the refund update.
Refund still requires manual fulfillment review and never silently revokes a
membership or delivery. The PAID effects marker is set only after receipt
enqueue, so a failed enqueue can be retried without duplicate fulfillment.
Existing unique message keys prevent repeated webhook receipts. Failure/cancel
notifications are coalesced per payment/target, not sent for every replay.

P5 retains its paid-only threshold/time/manual batching. The supplier template
renders batch counts and a real CSV MIME attachment. Base64, filename, size and
SHA-256 are validated against the stored batch payload. Existing CSV content and
fulfillment transitions are preserved; no extra administrator audience is added.
Subjects, To/CC and sender reject CR/LF/NUL injection. Monetary templates render
two decimals in SEK. Unknown/malformed templates fail rather than send fallback
content. No public order token, secret, exception or transport credential is
added to mail/logs.

## Adapters and private configuration

`disabled` does not select or mutate queued rows. `sink` serializes rendered
envelopes into private local JSON files and never calls mail/network. Only
`@example.invalid` To/CC are accepted by sink and injected fixture adapters.
Sink is refused in production. Provision an existing canonical 0700 directory
outside the existing public root, with config `mail.sink_dir` and
`mail.public_root`; do not use a symlink. Files are 0600, locked, flushed/fsynced
and identified by SHA-256 of the queue-specific message key. Repeating identical
content is idempotent; changed content for the same key is refused. Sink files
contain synthetic bodies and CSV, so do not publish or upload them as artifacts.
Remove a disposable sink only after test/reconciliation decisions. A partial
write is fail-closed and requires private inspection, not automatic truncation.

The existing `php_mail` host adapter is behind all of these strict boolean gates:
production mode, `production_launch_enabled=true`,
`mail.external_delivery_approved=true`, `mail.domain_verified=true`,
`mail.provider_verified=true`, and valid `mail.from`. No callback injection is
allowed in production. Non-production can never invoke `php_mail` directly.
Production config/secrets stay in private runtime outside the webroot; the
example has empty sender/paths and false approval flags. Host MTA/relay credentials
are provisioned privately by the host/provider, never in repo or message payload.
Future authenticated provider adapters can consume the same rendered envelope;
test callback compatibility accepts the original queue row only in synthetic mode.

## Retry and concurrency

The worker locks and rechecks the due row before invoking transport: status,
not-before and attempts are checked again under `FOR UPDATE`. Concurrent workers
cannot both send the same current row. Success and existing supplier completion
effects commit together; failures store only `transport_failed`, with exponential
5/10/20/40/80 minute backoff and a five-attempt cap. Existing manual retry does not
reset attempts; exhausted rows require deliberate private reconciliation rather
than silently bypassing the cap.

SMTP/MTA delivery is **at least once**, not exactly once: a crash after external
acceptance but before DB commit can require reconciliation and can duplicate an
external message. A stable Message-ID assists that reconciliation but does not
promise recipient deduplication. The local sink is idempotent by message key.
Never mark an ambiguous external send as definitively delivered without evidence.

`server/p17-worker.php` is CLI-only, drains both queues, creates no new supplier
batches and executes no DDL/bootstrap. Production launch false stops it before DB.
Existing admin P5/P6 drain/retry routes use the same transport layer and keep
their existing role/CSRF/audit controls. P15 logging remains private and generic.
No cron is installed or enabled in this delivery.

## Future external activation checklist

1. Approve sender/support address and recipient/CC rules with BoIS. Set the
   private sender; confirm the host/provider MTA, auth/credentials, envelope
   sender, quotas, timeouts, accepted-versus-delivered semantics and bounce handling.
2. Verify **the final selected sender domain** has provider-correct SPF, DKIM and
   DMARC alignment. Existing AlberIQ/M365 DNS is not proof for BoIS shop delivery.
   No DNS record or domain verification flag is changed by P17.
3. Review old queued messages, consent/retention, failed/exhausted rows and batch
   attachments privately. Never bulk-send the historical synthetic queue.
4. Obtain separate explicit authorization for external mail and launch. Review
   production config and host smoke/reconciliation/rollback before enabling an
   approved transport. Keep disabled if any requirement is unresolved.

## Verification

The P17 disposable MySQL test includes the existing P6 signed mock fixture and a
new PAID match-kit order through real batch creation. It verifies all four payment
templates, supplier CSV and MIME, both queue drains, duplicate sink acceptance,
key conflict, disabled/no-write behavior, each activation gate, production
callback refusal, synthetic To/CC, subject injection, malformed/hash-tampered
CSV, public/symlink/world-accessible sink paths, five attempts/backoff/exhaustion,
and two independent concurrent processes for each queue. Parent and children
disable `mail`, `curl_exec`, `curl_init`; no bodies/config are artifacts.

P15's disposable privacy fixture now explicitly uses test mode only for its
injected failing adapter, with a valid synthetic envelope. The production gate
is not weakened to accommodate the test. P14 browser/admin, P13 restore, P16
privacy and P5/P6/payment/security regression must also pass before closeout.
Host MTA delivery, actual DNS/provider validation, deliverability, bounce handling
and physical-device/business acceptance are not claimed by isolated CI.

Run/SHA references are recorded in `CURRENT_STATUS.md`. Next stage is P18;
P18 is not started by P17.
