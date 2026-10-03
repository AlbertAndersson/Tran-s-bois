# P11 – Stripe Sandbox E2E

Status: **DONE – VERIFIED IN SEPARATE SANDBOX / PRODUCTION NOT ACTIVATED**.

## Verified deployment

- Main before P11: `24bebd88a3e3ec36088b6de9070c8febee025b62`.
- Application source: `6d13f811d9bce8ebcefec421ab6d360642353bce`.
- PR #29: https://github.com/AlbertAndersson/Tran-s-bois/pull/29
- Merge: `636feb7870ac2212363400f81afa6952dbde1463`.
- E2E workflow ref: `b99733944248f02d11cd573abf5b36bfe75e4f8c`.
- Successful deploy and full E2E: https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37131884326
- Job: `111228446294`; all deployment, E2E, mock regression and cleanup steps passed.
- Restricted-key preflight: https://github.com/AlbertAndersson/Tran-s-bois/actions/runs/37132159485 ; job `111229248605`, success. Reports only `STRIPE_KEY_CLASS: restricted_test` and that the independent signing secret is configured. No secret values disclosed.
- Main before documentation closeout: `d8419791c88c97d8cda66c61bcff30a8818c60d9`.

## Changes

Sandbox HTTP requests previously loaded the default mock runtime while migrations used the Stripe runtime. Deployment now provides a `commerce-api.php` bootstrap that explicitly selects the private `.bois-stripe-sandbox/config.php`. The implementation file is denied direct HTTP access. P10 permits the known public sandbox callback wrapper to reach the existing POST/signature gates; internal includes remain blocked.

Hosted Checkout uses Dashboard-managed dynamic payment methods. BoIS's method gate remains and Swish is off. The preflight amount is 300 öre, matching Stripe's SEK minimum. The previous 100 öre preflight was rejected without any charge.

## E2E results

| Scenario | Result |
|---|---|
| Repeated order creation with identical idempotency key | Same order |
| Order → hosted Checkout with test card 4242 | Real Stripe sandbox event → BoIS PAID |
| Session `livemode`, metadata, client reference, SEK and amount | false; exact server order binding |
| Recorded Stripe event before synthetic replay checks | `evt_…`, payment.succeeded, PROCESSED |
| Invalid signature and expired timestamp | HTTP 400 |
| Signed incorrect amount | HTTP 400 |
| Repeated identical signed event | HTTP 200, duplicate=true; P4 state unchanged |
| Hosted Checkout cancel/return | Remains PENDING; return does not settle payment |
| Decline test card 4000…0002 | Hosted decline; remains PENDING |
| Refund through BoIS admin and Stripe test API | Signed callback → REFUNDED; fulfillment REVIEW_REQUIRED |
| Mock staging browser regression | All existing synthetic customer/admin checks passed |

Successful card/refund callbacks came from Stripe. Separate HMAC fixtures tested negative signature/timestamp/amount and replay behavior; those fixtures are not represented as Stripe-originated deliveries. A card decline leaves the Checkout session open for retry and does not imply the asynchronous payment.failed state. Existing P8 contracts cover asynchronous failed/expired events.

Sandbox Chromium viewport: 390 px. Mock staging Chromium: 375, 390 and 1280 px. No physical iPhone/Safari or business acceptance is claimed. Hosted Checkout showed its sandbox badge; regional/adaptive-currency options are controlled by Stripe settings and need the final business configuration before live launch.

## CI and evidence

PR CI: P2 `37131250045`, P7 `37131250029`, P8 `37131250018`, P9 `37131249990`, Security `37131250112`: success. P8 push CI `37131248056`: success. Merge CI: P2 `37131328400`, P8 `37131328324`, Security `37131328362`: success. P8 also executed existing P3–P7 smoke regressions on isolated databases. Security CI exercised the new HTTP sandbox runtime/callback routing test and P10 controls. Local security browser gates: 21 passed; Node syntax passed. PHP/MySQL checks ran on GitHub, not locally.

Artifact: `11277082294`, `bois-p11-sandbox-37131884326`, 1,469,845 bytes. SHA256 `16ebfe803e0ad80080c49a88416edbbebb08221ceb6fbaa55abfc9f55588ed78`; expires 2026-10-10 15:05:16 UTC. Contains 14 synthetic screenshots including Checkout, PAID, decline, REFUNDED and the mock regression. A persistent copy is provided with the completion report.

## Boundaries and next stage

Restricted test key and signing secret travel only through GitHub Secrets into private server runtime. `/bois-shop-p3/` remains mock; sandbox lives at `/bois-shop-stripe-sandbox/`. Existing dedicated BoIS staging DB is retained. Only synthetic customer data was used. Work Capture was not changed.

`production_launch_enabled=false`; Stripe live calls: no; real payment/refund: no; external mail/SMS/analytics/ads: no; KYC/merchant activation: no; new external cost: 0 kr. Swish remains off and was not tested; sandbox support is not a P11 blocker.

No P11 blockers remain. Next open stage: **P12 – production environment and database cutover readiness**. P12 has not started. P20 still requires explicit live activation approval.
