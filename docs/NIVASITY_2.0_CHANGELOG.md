# Nivasity 2.0 — Upgrades & Fixes Tracker

> Snapshot of work delivered across the Nivasity platform from the launch of **Nivasity 2.0** through the past six weeks.
>
> - **Nivasity 2.0 introduction modal landed:** 2026-04-18 (commit `3c7d138` — *feat(intro-modal): Add Nivasity 2.0 introduction modal with user preference tracking*).
> - **Mobile app version bumped to 2.0.0:** 2026-04-18 (commit `1228f24`).
> - **Reporting window:** 2026-04-04 → 2026-05-16 (last 6 weeks).
> - **Surfaces tracked:**
>   - Student / school web app — `c:\xampp\htdocs\nivasity`
>   - Command Center dashboard — `c:\xampp\htdocs\cc_dashboard`
>   - Public marketing site (React/Vite) — `c:\Users\Emmanuel\Documents\Projects\nivasity`
>   - Mobile app (Expo/React Native) — `c:\Users\Emmanuel\Documents\Projects\nivasity_app`

---

## 1. Headline themes of Nivasity 2.0

| Theme | What shipped | Where |
| --- | --- | --- |
| Nivasity Wallet (in‑app balance, funding, PIN) | New wallet module, PIN management, funding sync, charge handling, refresh credits | Web app, Mobile, Command Center |
| Bulk Material Payment for staff/HOC | CSV + paste flow, preview/validation, audit, exports, claim resolution | Web app, Command Center |
| School Settlements (manual) | Staging batches, Paystack transfer reference verification, ledger‑outstanding settlement | Command Center |
| Refund engine v2 | Wallet refunds, school‑payable consumption tracking, material revocation on refund | Web app, Command Center |
| Careers platform | Public careers page + prerender, in‑app applications, admin management | All four surfaces |
| Platform safety nets | Payment freeze (all/gateway), DVA bulk refresh, ledger repair, wallet pre‑credits | Web app, Command Center |
| Mobile experience parity | Wallet screens, Google Sign‑In, OTP email change, dark mode polish | Mobile |

---

## 2. Web app (`nivasity/`)

### Nivasity Wallet
- 2026-04-07 `443e313` — Restore PHP opening tag / DB include on `passwordreset.php`.
- 2026-04-09 `a915039` — Internal wallet creation & summary endpoints.
- 2026-04-09 `d981942` — Wallet funding sync + refresh‑credits endpoint.
- 2026-04-09 `24d97ff` — Refund processing integrated with wallet.
- 2026-04-09 `38f87a8` — Cart + payment init updated for wallet checkout.
- 2026-04-09 `1ea8a2f` — Nivasity Wallet UI integration.
- 2026-04-09 `69e23c3` → `14faf08` — Wallet PIN system (creation, verification, token, revamped step UI, styling).
- 2026-04-10 `be1ec7b` — Wallet PIN verification wired into payment init / checkout.
- 2026-04-10 `95c380a` — Wallet handling fee structure + payment calculations.
- 2026-04-10 `eaf3f79` — Provider charge amount recorded on wallet funding.
- 2026-04-10 `323a981` / `534e835` — Cart payment summary refreshed for wallet (fee visibility, combined options).
- 2026-04-11 `673de48` — Paginated wallet transactions endpoint.
- 2026-04-19 `dbca540` / `8966262` — Funding charge tracking, recovery logic, historical profit recalculation.
- 2026-04-19 cart styling sweep (`a5f5166` → `bfe8eac`) for wallet fee/discount lines.
- 2026-04-18 `e34a050` — Nivasity Wallet sidebar link reintroduced.
- 2026-04-25 wallet transfer module (`f929673`, `84c5161`, `04f6963`, `d472a72`, `a632332`, `a03097b`) — recipient lookup, transfer modal, layout/accessibility revamp.

### Bulk Material Payment (staff/HOC)
- 2026-04-24 `2a39322` — Bulk material payment with CSV upload and validation.
- 2026-04-24 `bff6627` — Resolve bulk payment claims + pending claims endpoint.
- 2026-04-25 — Full UX pass on the bulk flow:
  - `1036b10` AJAX preview; `780a85a` wallet PIN preview reopen.
  - `8145787` / `4e033ab` / `3b04645` preview payload + saved‑preview UX.
  - `cd1bf51`, `5045a9f`, `06b81c7`, `e765d31`, `61f4150` modal, Select2, error handling.
  - `2c972d9`, `f0f79ff`, `460fc9c`, `5cf9494` manual purchase creation, upserts, orders display, receipt bulk export audit.
  - Styling: `7013447`, `e89b2be`, `70f92be`, `716c4b0`, `e37ab30`, `2f16035`, `0418ced`.
  - Copy/label clarity: `9fd0a63`, `9b4a573`, `ba85aa5`, `d0e3034`.
- 2026-04-30 `1877177` — Validation messages for bulk payment preview.
- 2026-05-01 `e02a8bd` → `1f4c8b7` / `8eec85c` — Paste student records support, new divider/layout, clarified copy, draft message cleanup (`3060a1d`).
- 2026-05-01 `ef8f679` / `d1999be` / `63034db` — Name + department validation, name pair signature, user label helpers, improved mismatch handling.
- 2026-05-01 `3ed80e2` — Wallet batch processing + `manuals_bought` backfill.
- 2026-05-01 `06feec8` — Manual bulk payment table checks & successful student purchase handling.
- 2026-05-01 `e5ffbee` / `29a902e` — Backfill refinements; scripts to clean duplicate/orphan bulk school payables.
- BOM safety: `bulk_material_payment.php` strips UTF‑8 BOM before validating CSV headers.

### Materials, exports & receipts
- 2026-04-10 `4ef7893` / `d829bf0` / `155b317` — Material requests feature, material change flow, 72‑hour change restriction.
- 2026-04-10 `ecae25f` / `104d1e2` — PDF export for manual payments + conditional `last_student_id` in insert.
- 2026-04-10 `080de78` — Academic role switching.
- 2026-04-18 export/receipt PDF composer cleanup (`008babc`, `883d1b6`, `151514a`).
- 2026-04-23 `0aa9fa1` / `7625624` — Lost material tracking + simplified request display.
- 2026-04-25 `5fcf7de` — Helper to allow “mark as lost” after 48 hours of purchase.

### Refunds, ledgers & wallet integrity
- 2026-04-20 `30fb67a` — Paystack payload validation helpers + funding handling hardening.
- 2026-04-20 `289e851` — Audit/reverse invalid wallet purchases caused by misclassified funding.
- 2026-04-20 `2fc26dc` — Ledger repair across duplicate transactions in payment handling.
- 2026-04-20 `02036b1` / `c797f8f` / `8c9f461` — Backfill missing school payable ledgers + wallet verification.
- 2026-04-21 `babb36f` — Bulk DVA wallet credits refresh with CLI support.
- 2026-04-21 `e999f54` — School ledger repair endpoint for missing payable rows.
- 2026-04-21 `75560f3` — School payable repair sweep tracks transactions from 2026-04-18.
- 2026-04-22 `849ef69` — Transaction refund handling zeroes refund on new transactions/updates.
- 2026-04-25 `6d58cd5` / `96df63e` — Refund consumption columns + payable calculation accounting for consumed amounts.
- 2026-04-25 `9a80438` / `49cff0b` — Refund reallocation/normalization + backfill of ledger links for consumed refund reservations.
- Repaired ledger rows can post later than the original purchase; reconciliation now keys off purchase date, not `created_at`.

### Payment freeze & gateway safety
- 2026-04-28 `863261e` / `449c731` — Payment freeze with detailed response, manual disable, configuration updates.
- `model/payment_freeze.php` exposes `PAYMENT_FREEZE_SCOPE` (`all` / `gateway`); gateway scope blocks hosted checkout but keeps wallet/free checkout alive. `API/payment/init.php` returns a 403 wallet‑only message.
- 2026-05-01 `6fbeab9` — Cart cleanup of unused gateway freeze message after the new system shipped.

### Intro modal & UX
- 2026-04-18 `3c7d138` — **Nivasity 2.0 intro modal** with user preference tracking.
- 2026-04-29 `9f4e03c` / `f84040f` — Bulk payment modal + 3‑column intro modal grid.
- 2026-04-30 `5554250` — Bootstrap modal compatibility fix (`new bootstrap.Modal(...)` fallback when `getOrCreateInstance` is missing) so storefront cart binding never aborts.
- 2026-04-18 `3bb1222` — Due‑date display removed across components for consistency.
- 2026-04-25 `0bf534c` — Tawk.to integration removed.

### Accounts, auth & misc
- 2026-04-14 `3017c5b` — Email change with OTP verification.
- 2026-04-11 `7948d0a` — App update configuration endpoint + DB table.
- 2026-04-24 `344c9c1` — Signin redirection uses dynamic URL generation.
- 2026-05-06 `a31cfd6` — Phone number made optional during registration (+ README update).
- 2026-05-07 `92b06bd` — App Store URL added; mobile feedback prompt is now campaign‑scoped (`mobile_experience_prompt_get_current_campaign_key`), with legacy responses tagged `legacy` so future rollouts can re‑prompt by bumping the campaign key.
- 2026-05-13 `7700da9` — `wallet_pre_credits` table + functions; wallet funding mutation reconciles matching pre‑credits before posting webhook/refresh credits to prevent double crediting on delayed Paystack delivery.

---

## 3. Command Center dashboard (`cc_dashboard/`)

### Refunds & wallet settlement
- 2026-04-10 `e3d6315` — Refund processing + wallet settlement support.
- 2026-04-18 `514ee19` / `f09d02a` — Refund creation validation/error handling, remove unnecessary include.
- 2026-04-22 `48e16a5` — Batch payments refactor: drop Paystack subaccount, post into `school_payable_ledger`.
- 2026-04-22 `d0d8ec7` — Refund process now credits wallets and dispatches notifications.
- 2026-04-22 `087d832` — SQL script to repair transaction refund amounts based on refunds.
- 2026-04-23 `3d6f7eb` / `6f2d35d` / `00be5e7` — Refund retrieval/payload refactor, `dbFetchAll`, expression naming fix.
- 2026-04-25 `b934750` — `batchFinalizeTransactionForItem` for transaction finalization.
- 2026-04-25 `8ac729b` — Named locks for sensitive DB operations.
- 2026-04-25 `69dd1a6` — Material access revocation when a refund is applied.
- 2026-04-25 `7b10ef0` / `7dfa459` / `6dedbd5` — Refund consumption columns, ledger reservation binding, corrected refund math.
- 2026-04-25 `ddd51d1` / `ad635b2` — Column‑existence check optimization; join fix in `listEligibleSchoolPayableLedgerRows`.

### School settlements (manual)
- 2026-05-01 `7bb56fb` — School settlements management feature launched.
- 2026-05-01 `8cd8063` — Initial functional/performance pass.
- 2026-05-01 `30c7984` — Paystack transfer reference label + verification message updates.
- 2026-05-01 `5d057fd` / `c72ceb3` — DataTable integration for preview, active and recent batches.
- 2026-05-01 `d0f508b` — Snapshot/ledger queries filter outstanding amounts by status.
- Behavior: admin roles 1/2/4 only; stages into `settlement_batches` + `settlement_batch_items`; manual Paystack reference stored in `provider_reference`; completion verifies the reference live and blocks if outstanding < `allocated_amount`. Stage and completion settle from ledger outstanding (`payable_amount − settled_amount`), not raw totals.

### Student wallets surface
- 2026-04-20 `1942c1a` — Student Wallets page with management features.
- 2026-04-20 `43f0e86` / `9108604` / `2b5a74a` — Lookup, redundant copy removed.
- 2026-04-27 `1a1a3c0` / `06738bf` / `f435b07` — Wallet transactions page with school‑code routing, filters, data fetching.

### Wallet pre‑credits
- 2026-05-13 `d20f504` / `5a2901e` — Wallet pre‑credit management with status flow `pending_confirmation → confirmed | amount_disputed`. UI lives in `wallet_pre_credits.php`, with `model/wallet_pre_credit_service.php` and `model/functions/wallet_pre_credits.js`.

### Manual batch payments
- 2026-04-10 `4ad01ff` — Batch CSV upload with unmatched‑student handling.
- 2026-04-10 `bcb6a81` — Multiple matric values + duplicate detection in CSV parsing.
- 2026-04-10 `38588e2` — Note column removed; unmatched alert copy clarified.
- 2026-04-10 `0188dca` — Paystack subaccount code on manual batches.
- 2026-04-10 `028b034` — Select2 init for batch creation modal.
- 2026-04-10 `1643f17` — Gateway support for manual payment batches.

### Materials & access control
- 2026-04-10 `8907049` / `2644c01` — Material requests page + faculty‑name resolution for audience labels.
- 2026-04-10 `cf39e68` / `96f28e9` — Admin session validation + `manuals.admin_id` backfill; material level dropdown restored.
- 2026-04-10 `ecbe3aa` — Wallet fee thresholds management page.

### Careers (admin side)
- 2026-05-13 `4022baa` — Careers model for managing openings + applications.
- 2026-05-13 `859f5fc` — AJAX functionality for openings management.
- 2026-05-13 `c1059ae` — Eligibility/level wording + internship duration label refinement.
- Touches: `model/careers.php`, `careers_openings.php`, `careers_applications.php`, `API/careers/index.php`; sidebar entry in `partials/_sidebar.php`; migration `sql/add_careers_tables.sql`.

### System alerts, app update & infra
- 2026-04-10 `dc3efad` / `efa8ada` — System alerts management page + create/edit modal.
- 2026-04-12 `b425e65` — App Update Configs page + sidebar link.
- 2026-04-28 `4894a76` / `5871f19` — Mail refactor; `sendMailBatch` now sends directly per recipient (no support@nivasity.com primary + BCC).
- 2026-05-13 `df4f51e` — Additional allowed origins added to CORS headers.

---

## 4. Public marketing site (`Projects/nivasity` — React + Vite)

- 2026-04-14 `f4fb894` — iOS app release date updated to May 2026; FUMMSA student support details added.
- 2026-04-14 `89bd421` — Privacy & Terms pages refreshed with full policy detail.
- 2026-05-07 `6c0ec91` / `b730ab8` — Mobile app links + availability copy updated; hero image refresh.
- 2026-05-13 `3529787` — Careers page added with openings integration.
- 2026-05-13 `9b906aa` — Career Detail page + application dialog.
- 2026-05-13 `420cd2f` — API URL normalization + proxy config for careers endpoint.
- 2026-05-14 `1c00b72` / `d0441a8` / `82ecdef` / `92f5d24` / `d63b519` — Careers/Career Detail polish, close‑window formatter, team image, SEO/social meta, and the `prerender-careers.mjs` build step.
- Build now runs `vite build` + the prerender script, emitting `dist/careers/index.html` and `dist/careers/<slug>/index.html` with OG/Twitter tags. nginx serves prerendered files first via `try_files $uri $uri/index.html $uri/ /index.html;`. Stable social image: `public/careers-share-3.png`.

---

## 5. Mobile app (`nivasity_app/` — Expo/React Native)

### Nivasity 2.0 milestone
- 2026-04-18 `1228f24` — Bumped to **2.0.0**.
- 2026-04-18 `a521001` — `google-services.json` OAuth client update.

### Wallet + checkout
- 2026-04-11 `45bb194` — Wallet screens: funding, PIN management, transaction receipts.
- 2026-04-12 `d922721` — Google Sign‑In integrated into the login flow.
- 2026-04-14 `d910f85` — Email change with OTP verification on `ProfileSectionScreen`.
- 2026-04-14 `00d6f0f` — Removed `StaticPageScreen`; navigation updated.
- 2026-04-14 `85f1cdd` — Checkout screen layout + total calculation polish.
- 2026-04-18 `09c39b5` — Month‑range filtering for wallet transactions with UI tweaks.

### Theme + UI polish (dark mode)
- 2026-04-14 `b944fc8` — Dark‑mode color handling fixes across components.
- 2026-04-14 `bea32d7` — Country/Option picker dialogs respect dark mode.
- 2026-04-14 `ec0681b` — Currency formatting now spaces the symbol from the amount.

### iOS / Android build configs
- 2026-05-04 `4d82a57` — iOS encryption settings + Android build properties.
- 2026-05-04 `bb1d2e0` — Google Sign‑In custom URL scheme (iOS).
- 2026-05-04 `2f24f69` — Google Services for iOS + `app.json` update.
- 2026-05-04 `4aec916` — `extraPods` config for iOS GoogleUtilities.
- 2026-05-04 `2cba783` — iOS ASC App ID for production submit (`eas.json`).
- 2026-05-06 `1bde26a` — Registration/login accept optional phone number with improved validation.
- 2026-05-06 `5494176` — postcss override added to `package.json` for compatibility.

---

## 6. Reliability / operational fixes recorded in memory

These are non‑obvious gotchas resolved during 2.0 and worth re‑checking on regression:

- **Bulk wallet payable duplication** — `nivasityRunSchoolPayableRepairSweep` + `nivasityEnsureSchoolPayableForPurchase` ignore student‑level refs from `manual_bulk_payment_students` when the parent `manual_bulk_payment_batches` row is already successful; otherwise the repair sweep duplicates the batch‑level row with per‑student rows (`model/internal_wallet_service.php`).
- **Bootstrap modal compatibility** — `nivasity/index.php` uses `typeof bootstrap.Modal.getOrCreateInstance === 'function' ? … : new bootstrap.Modal(...)` so the intro modal cannot abort the jQuery cart binding in older Bootstrap builds.
- **CSV BOM** — bulk material payment CSV header parsing strips a leading UTF‑8 BOM before validating `first_name,last_name,matric_no`.
- **Ledger reconciliation** — repaired `school_payable_ledger` rows can post after their original purchase; use the source purchase date, not `created_at`, when periodizing.
- **Mail batch routing** — `cc_dashboard/model/mail.php::sendMailBatch()` sends directly to each recipient instead of using `support@nivasity.com` as primary with students in BCC.
- **Wallet pre‑credits race** — wallet funding mutation reconciles matching `wallet_pre_credits` rows before applying webhook/refresh credits, preventing double credit on delayed Paystack delivery.

---

## 7. How this file is maintained

- Generated 2026-05-16 from `git log --since=2026-04-04` across the four workspace repos plus repository‑scoped memory notes under `/memories/repo/`.
- To refresh, re‑run the same `git log` window per repo, fold in any new memory notes added since the previous snapshot, and append a dated section.
