# P10 – Production Hardening

Status: IN PROGRESS / implementation awaiting CI and protected staging verification.
Baseline main: `0a25f1be0114d147222518b69be805fe75c10d30`.
Scope follows the operative Drive development queue; P11–P20 are not started.

## Controls

- Shared HTTP controls load before runtime/DB errors; security headers and opaque errors apply to failures and CSV responses too.
- Explicit route/method map returns 404/405. Origin allowlist and Fetch Metadata block cross-site browser requests. Mutations require JSON and a bounded body (64 KiB, signed webhooks 1 MiB). JSON is not a safelisted form content type; no ambient Basic Auth or consent cookie grants admin access.
- Admin capability is required before migrations/queries, including exports. Missing, empty, incorrect and Basic Auth credentials cannot authorize admin operations.
- Atomic private file counters use REMOTE_ADDR, HMAC pseudonyms, a 60-second window, 256 bounded shards, at most 1024 active entries per shard. No forwarded client IP is trusted. Customer mutations/secret reads: 60/minute/action/IP; sales events: 300; admin attempts and requests: 120/minute/IP. Storage/lock/parse failures deny work. No external service or new database tables.
- Signed payment callbacks bypass customer-IP quotas and origin checks so a checkout stop or shared provider IP cannot discard payment events. Existing signature/timestamp/idempotency validation remains authoritative.
- Internal includes, migrations and workers reject direct HTTP access in PHP as well as Apache. CLI operations remain available.
- Apache headers cover HTML/assets and Basic Auth responses; CSP permits only same-origin scripts/connections, existing logo host and inline styles. No inline scripts, eval, frames, objects or base changes. Inline styles remain a documented residual risk.
- Consent cookie remains HttpOnly, SameSite=Lax, Secure outside isolated test mode, without Domain. No PHP session cookie is introduced. All app responses are no-store.
- Unexpected server failures expose no SQL, stack or config details; application error log records a generic error category.

## HSTS strategy

Do not add HSTS to this path on the shared `alberiq.se` host: HSTS applies to the entire hostname, not a directory. HTTPS remains required for demo. Future dedicated production hostname: verify HTTPS/certificate/redirects first, start `max-age=300`, increase after observation. No includeSubDomains or preload until ownership, certificates and all subdomains are verified. This phase changes no domain-wide HSTS policy.

## Verification

`tests/security-hardening-smoke.php`: real loopback requests with an unavailable DB prove early method/origin/admin/content-type/body controls, private endpoints, safe 500 responses, headers, rate limits and ignored forwarded IP spoofing.
Existing P2–P9 and Security controls CI prove preserved business/payment/consent behavior. P9 staging deploy includes explicit P10 authenticated negative checks, static CSP headers and the existing complete synthetic browser/E2E acceptance.

## Residual technical risk register

| Risk | Remaining treatment |
| --- | --- |
| Shared staging admin token; no personal accounts/MFA | P14; demo token is not a production identity model |
| Local rate limiter is per host and per peer IP; proxy/shared-NAT sensitivity | Review production ingress/peer topology; use a verified proxy policy before trusting forwarding headers. Distributed denial of service is outside this local limiter |
| Quotas may need adjustment for normal bursts | Observe synthetic demo; tune explicit server constants with regression proof |
| Inline styles and external existing logo | P18 review; styles can later move to CSS, logo can be self-hosted after rights review |
| HSTS requires hostname-level ownership and TLS validation | P12/P18 before dedicated production activation |
| Restore, retention, structured observability and personal IAM | P13/P14/P15/P16; not claimed complete here |
| Existing runtime migration model on authorized requests | Move to deliberate bootstrap/cutover in P12; unauthorized sensitive requests now fail before migrations |
| Host-managed clearance cookie and platform headers | Reassess production cookie inventory and hosting policy |

Production launch remains false; demo mock/testmode; no Stripe live, real payment/refund, external email/SMS, analytics/advertising or new cost.
