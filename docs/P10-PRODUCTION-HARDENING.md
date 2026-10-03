# P10 – Production Hardening

Status: DONE / CI and protected staging verified.
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

## P10 – DONE / skyddad staging slutverifierad

Baslinje main: `0a25f1be0114d147222518b69be805fe75c10d30`. PR #28 merge: `73aaefeb102d7c71ba0ae08231d203166aa843c6`. Workflowfix: `bc692e6ae991d606442155b0a9efb236f0803a85`.
Publicerad applikationsref: `f355ff5c26ce11ad20815e2f48b35ec2f4bbc064`.
Slutlig stagingrun: [37128656576](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37128656576), jobb `111219110739`, **success**, samtliga steg gröna. Workflowref `bc692e6ae991d606442155b0a9efb236f0803a85`; senare dokumentationscommits är inte ny appdeploy.

Säkerhetsheaders/CSP på HTML och API, method/origin/JSON/body-kontroller, separat adminbehörighet före DB, privat atomiskt missbruksskydd och interna endpointspärrar är verifierade. HTTP Basic Auth består. Full P4–P9 mock-E2E och Chromium 375/390/1280 passerade: köp utan statistik, nekad/avbruten betalning och nytt försök, samtycke/attribution/återkallelse, medlemsverifiering, batch och mockrefund. Inga främmande tabeller tillkom; tabellnamnshash/count oförändrade, antal 0. Detta är inte en restoreövning.

Stripe live: NEJ. Riktig betalning/refund: NEJ. Extern mail/SMS: NEJ. Extern analytics/annonser: NEJ. Produktion aktiv: NEJ (`production_launch_enabled=false`). Ny extern kostnad: 0 kr.

P10-blockerare: inga. Kvarvarande tekniskt riskregister och HSTS-strategi finns i `P10-PRODUCTION-HARDENING.md`. Personliga adminroller, backup/restore, retention och produktionsdrift hanteras i senare etapper. Nästa öppna etapp: **P11 – Stripe Sandbox E2E**; den har inte startats här.

CI på merge-SHA, samtliga success:

| Kontroll | Run |
| --- | --- |
| P2 | [37128431291](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37128431291) |
| P3 | [37128431333](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37128431333) |
| P4 | [37128431304](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37128431304) |
| P5 | [37128431347](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37128431347) |
| P6 | [37128431339](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37128431339) |
| P7 | [37128431356](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37128431356) |
| P8 | [37128431275](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37128431275) |
| P9 | [37128431363](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37128431363) |
| Security controls | [37128431415](https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37128431415) |

Browserartifact: `11276236203`, `bois-p9-synthetic-browser-37128656576`, 1 232 975 byte, digest `sha256:fcb0fcb1019af404ac1d17a3ba56a8203905e9c61967eb3328ce7c15bf74065b`, giltig till 2026-10-10 14:11:43 UTC. Fysisk iPhone/Safari och verksamhetsacceptans är inte testade av Chromiumkontrollen.

Första stagingrun `37128488635` deployade samma appref men stoppades på en ny verifieringscurl utan etablerad User-Agent (HTTP 455). Workflowfixen återanvänder befintlig klientidentifiering. Slutrun ovan passerade hela kedjan.
