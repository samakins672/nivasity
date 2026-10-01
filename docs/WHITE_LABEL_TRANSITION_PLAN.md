# White Label Student Portal: Transition Plan

## 1. Goal

`white_label` (React + Vite, one site per school resolved by subdomain) replaces the PHP student website. It should **work exactly like the mobile app**, plus a set of web-only features that exist today only on the PHP site.

| Scope | Features |
| :--- | :--- |
| **Same as mobile app** | Auth, store, cart, checkout (wallet + gateway, driven by the payment-freeze flag), wallet funding via virtual account, wallet transactions, orders and receipts, notifications, support tickets, profile and settings |
| **Web-only extras** | Bulk material payments + bulk claim review, material requests, peer-to-peer wallet transfer, survey banner, system alerts, "mark copy as lost" |
| **Different from app** | Wallet PIN **create** needs no email OTP (web already works this way since `350b513`; the mobile app keeps OTP, see `7a52fe0`) |
| **Removed** | Event tickets: no pages, no cart items, no links |
| **New for everyone** | Semester tagging of materials + "awaiting confirmation" state for carry-over materials (Section 4) |

### Systems involved

| System | Role |
| :--- | :--- |
| `nivasity/API/` (JWT JSON API) | The only backend `white_label` and the mobile app talk to |
| `nivasity/model/*_service.php` | Business logic shared by the PHP website and the API. **Reuse it; do not reimplement logic in API files.** |
| `nivasity/` PHP website | Stays live until cutover and must respect the semester rules too |
| `cc_dashboard` | **The only place materials are created and managed**, plus school settings, surveys, system alerts and external batch payments |
| `nivasity_app` | Mobile app; must not break when shared API endpoints change |

---

## 2. Current State (verified against code)

### 2.1 What `white_label` already has
Routes in `white_label/src/App.tsx`: login, signup, forgot/reset password, verify OTP, dashboard, store, saved, checkout, orders, order receipt, wallet, wallet fund, wallet PIN, wallet receipt, profile, settings, support, notifications.

- `StorePage`: search, sort, level filter. No details modal.
- `CheckoutPage`: gateway and wallet radio, similar to the app. Keep this behaviour.
- `WalletPinPage`: 3-step OTP flow (`send_code` → `verify_code` → `save_pin`). Needs changing for web.
- `OrderHistoryPage`: list only, no "mark as lost".
- Nothing yet for transfers, bulk payment, claims, material requests, surveys or alerts.

### 2.2 API gaps
The web-only features have working **services** but **no API endpoints**:

| Feature | Service / logic (exists) | API endpoint |
| :--- | :--- | :--- |
| Bulk payment preview | `bulk_material_payment_preview_*` functions, currently **inside the page file** `bulk_material_payment.php` | Missing |
| Bulk payment submit | `bulk_material_payment_process_wallet_batch()` in `model/bulk_material_payment_service.php` | Missing |
| Pending claims | `bulk_material_payment_get_pending_claims_for_user()` | Missing |
| Resolve claim | `bulk_material_payment_resolve_claim_for_user()` (web handler: `model/bulk_material_payment_claim.php`) | Missing |
| Material requests | `model/material_request_service.php` (web handler: `model/material_requests.php`, actions `create`, `upvote`) | Missing |
| Survey banner | `surveyBannerGetActiveForUser()`, `surveyBannerDismiss()` in `model/survey_banner.php` | Missing |
| System alerts | `get_active_system_alerts()` in `model/system_alerts.php` | Missing |
| Wallet transfer | `nivasityResolveStudentWalletTransferRecipient()`, `nivasityTransferWalletToStudent()` in `model/internal_wallet_service.php` | Exists but **unsafe** (see 2.3) |
| Direct PIN create | Web logic in `model/wallet-pin.php` (`set_pin_direct`) | API `save_pin` **requires `pin_token`** |
| Mark as lost | `material_copy_mark_lost()` | Exists: `API/materials/mark-lost.php` (`bought_id`) |

### 2.3 Security issue to fix first
`API/wallet/transfer.php` currently:
- does **not** verify the wallet PIN,
- does **not** restrict the recipient to the same school,
- looks up by email only, writes `wallet_ledger_entries` directly, bypasses the `wallet_transfers` table and the service, and ignores query failures inside the transaction.

Any valid JWT can move money to any account by email. Fix this whether or not white_label ships.

---

## 3. Backend Work (Phase 1: do this before any UI)

All new endpoints follow the existing API conventions: `authenticateApiRequest($conn)`, JSON body with `$_POST` fallback, `sendApiSuccess` / `sendApiError`, and `source_channel = 'api'` wherever a service accepts it.

### 3.1 Wallet
| Endpoint | Change |
| :--- | :--- |
| `POST /wallet/transfer.php` | **Rewrite** as a thin wrapper around the service. `action=lookup` (`recipient_identifier` = matric or email, same school) returns name, email and matric for confirmation. `action=transfer` takes `recipient_identifier`, `amount`, `wallet_pin`, optional `description` and `request_token` (idempotency), and calls `nivasityTransferWalletToStudent(..., 'api')`. Check whether the mobile app uses the old `recipient_email` shape (it currently does not). |
| `POST /wallet/pin.php` | Add `action=set_pin_direct` (`pin`, `confirm_pin`), sharing code with `model/wallet-pin.php`. Keep `send_code` / `verify_code` / `save_pin` unchanged for the mobile app. |

**Recommendation on direct PIN:** allow `set_pin_direct` only when the user **has no PIN yet**. Changing an existing PIN should require the current PIN, and "forgot PIN" should keep the OTP flow. Otherwise a hijacked session can reset the PIN and drain the wallet. The web site today allows overwriting without either check, so fix `model/wallet-pin.php` the same way.

### 3.2 Bulk payments
1. Move the `bulk_material_payment_preview_*` functions out of `bulk_material_payment.php` into `model/bulk_material_payment_service.php`, so the page and the API share them.
2. New endpoints under `API/materials/bulk/`:

| Endpoint | Purpose |
| :--- | :--- |
| `GET manuals.php` | Materials the user can bulk-pay for: same visibility rules as `partials/_bulk_payment_modal.php` (dept / faculty / `depts`, `status='open'`, not overdue) **plus the semester filter**. Includes materials the user already owns. |
| `POST preview.php` | `manual_id` + either CSV file (`bulk_csv`) or `records` text. Each row is **matric + first name + last name**. Returns the analysed rows (matched, placeholder, name mismatch, department mismatch, duplicate), subtotal, 5% fee, total, wallet balance and readiness. |
| `POST pay.php` | `manual_id`, `rows` (the preview payload), `wallet_pin`. Calls `bulk_material_payment_process_wallet_batch(..., 'api')`. |

### 3.3 Bulk claims
| Endpoint | Purpose |
| :--- | :--- |
| `GET /materials/claims/pending.php` | `bulk_material_payment_get_pending_claims_for_user($conn, $user, 5)`. Returns claims from **both** sources: HOC/student bulk batches and cc_dashboard external manual batches (`manual_payment_batches`). |
| `POST /materials/claims/resolve.php` | `student_row_id`, `action` (**accept or reject**), `source`. Returns `remaining_claims`. |

Only `student` and `hoc` roles may use these, matching the web handler.

### 3.4 Material requests
| Endpoint | Purpose |
| :--- | :--- |
| `GET /material-requests/list.php` | `nivasityMaterialRequestFetchVisibleRequests()`: requests visible to the user's dept/faculty, with upvote count, expected buyers, `progress_percent`, `threshold_percent` (40) and `threshold_met` |
| `POST /material-requests/create.php` | Course code, title, scope (dept / faculty / custom depts or faculties). The service rejects duplicates and points to an **already-open matching material** when one exists. |
| `POST /material-requests/upvote.php` | `request_id` |
| Share link | Uses the request's `share_token`. The public landing page (currently PHP) must also resolve on white_label subdomains. |

### 3.5 Survey banner and system alerts
| Endpoint | Purpose |
| :--- | :--- |
| `GET /reference/system-alerts.php` | `get_active_system_alerts()`: id, title, message, expiry, colour (`red` default, `green`, `info`) |
| `GET /surveys/active.php` | `surveyBannerGetActiveForUser()`: hidden after 5 dismissals or once the user has responded |
| `POST /surveys/dismiss.php` | `survey_id` → `surveyBannerDismiss()` (increments `dismiss_count`) |

### 3.6 Event tickets
- Remove any event items from API cart and store responses used by white_label.
- Leave the event code inside payment verification and refund paths (for example the `event_tickets` cleanup in `API/payment/verify-bulk.php`) alone, because it handles historical rows. Delete it later in a separate cleanup.

---

## 4. Semester Tagging and Carry-Over Confirmation

### 4.1 Rules
1. Each school has a **current semester** (1 or 2), set in cc_dashboard.
2. Every material belongs to **exactly one semester**. There is no "both semesters" option: if a course needs the material in both, the admin creates a **separate copy** for the other semester (cc_dashboard offers a "Duplicate for other semester" action, see 4.5).
3. A material **cannot go live without a semester**. The semester is required on create, edit and confirm.
4. Students only see materials that are `open` **and** tagged for the school's current semester.
5. When a school's semester switches, open materials tagged for the **outgoing** semester, **and any legacy untagged open materials**, move to `awaiting_confirmation`. They do **not** come back automatically next year: prices may have changed.
6. An admin must **confirm** a material (check or edit the price, set the semester if it has none) to reopen it, or **retire** it (`closed`). Confirming for the upcoming semester is allowed: the material is `open` but stays hidden until the switch.
7. Materials created in advance for the upcoming semester (for example second-semester materials uploaded now) stay `open`. They are hidden only by the semester filter and appear automatically when the semester switches. Their price was just set, so no confirmation is needed.

Why this works without a session/year column: a material that leaves its semester can only be sold again in the next academic year, and it always passes through `awaiting_confirmation` first.

### 4.1.1 Existing (untagged) materials at launch
All current materials have no semester. To avoid emptying the store on deploy:
- The migration leaves them `NULL`. Until the **first semester switch**, open `NULL` materials stay visible, because they are what is being sold in the current semester.
- cc_dashboard shows a "No semester set" badge and filter, so admins can tag them early (tagging is optional before the switch).
- At the first switch, every open `NULL` material moves to `awaiting_confirmation` along with the outgoing semester's materials. Confirming requires choosing a semester.
- After that first switch no open `NULL` material can exist, and the `IS NULL` branch of the filter becomes dead code that can be removed.

### 4.2 Schema
```sql
ALTER TABLE `schools`
  ADD COLUMN `current_semester` TINYINT(1) NOT NULL DEFAULT 1
    COMMENT '1 = First Semester, 2 = Second Semester',
  ADD COLUMN `current_semester_updated_at` DATETIME DEFAULT NULL;

ALTER TABLE `manuals`
  ADD COLUMN `semester` TINYINT(1) DEFAULT NULL
    COMMENT '1 = First, 2 = Second. NULL = legacy, not yet tagged (cannot be confirmed/opened)' AFTER `level`,
  ADD COLUMN `confirmed_at` DATETIME DEFAULT NULL
    COMMENT 'Last carry-over confirmation',
  ADD COLUMN `confirmed_by` INT(11) DEFAULT NULL
    COMMENT 'admins.id';

ALTER TABLE `manuals`
  ADD KEY `idx_manuals_school_status_semester` (`school_id`, `status`, `semester`);
```
- `semester` stays nullable only for legacy rows (4.1.1). cc_dashboard validation, not the database, enforces "required" for new and confirmed materials.
- `manuals.status` is `varchar(20)`, so `awaiting_confirmation` fits without a schema change.
- `manuals` is MyISAM. Run the migration off-peak (table lock) and add it as a file in `nivasity/sql/`.

### 4.3 Why a status value instead of a flag
Almost every store, cart, bulk-picker and material-request query already filters `m.status = 'open'`, so `awaiting_confirmation` materials are hidden everywhere automatically. Two places test for `'closed'` instead and **must be changed to `!= 'open'`**:
- `nivasity/model/cart.php:86`
- `nivasity/model/manual_details.php:33`

### 4.4 Where the semester filter is added
Filter: `(m.semester = <school.current_semester> OR m.semester IS NULL)`. The `IS NULL` branch only covers legacy materials until the first switch (4.1.1).

Add it to a single helper (for example `nivasity_material_semester_where($conn, $schoolId, $alias)` in `model/functions.php`) and use it in:
- `API/materials/list.php` and `API/materials/details.php`
- `API/materials/cart-add.php` (reject adding a hidden material)
- `API/payment/init.php` / wallet checkout (re-check at payment time)
- PHP website store listing, `model/manual_details.php`, `model/cart.php`, `store_share.php`
- Bulk picker (`partials/_bulk_payment_modal.php` and the new `API/materials/bulk/manuals.php`) and bulk preview/pay
- `nivasityMaterialRequestFindOpenMaterialMatch()` (so requests aren't redirected to a hidden material)

**Do not** filter purchased materials, order history, receipts, claims, exports, or cc_dashboard Material Grants lookups (export code / email / matric), since those act on past purchases.

API responses should include `semester` on each material and `current_semester` on the list, so the UI can label them.

### 4.5 cc_dashboard changes
| Area | Change |
| :--- | :--- |
| Create / edit material (`model/materials.php`, `course_materials.php`) | **Required** semester select: "First" or "Second" (no "both" option). Default to the school's current semester. |
| Duplicate for other semester | Action on a material: creates a new `manuals` row with the same title, course code, price, scope and level, the **other** semester, and a **new** `code`. Opens it in the edit form so the admin can adjust the price before saving. Sales, exports and grants stay separate per copy. |
| School page (`school.php`, `model/school.php`) | "Current semester" control. Before switching, show "N materials (tagged Semester X or untagged) will move to Awaiting confirmation; M materials tagged Semester Y will go live." Then run the switch **in one transaction**: update `schools.current_semester`, then `UPDATE manuals SET status='awaiting_confirmation' WHERE school_id=? AND status='open' AND (semester=<outgoing> OR semester IS NULL)`. |
| Materials list | "Awaiting confirmation" filter/tab showing the current price, last sale date and units sold last time; "No semester set" badge and filter for legacy rows |
| Confirm action | Semester required (pre-filled if already tagged), optional price edit → `status='open'`, `confirmed_at`, `confirmed_by`. Log old/new price and semester in `manual_change_logs`. If the chosen semester is not the current one, say "Will go live when Semester X starts". |
| Retire action | `status='closed'` (reuses the existing close-notification logic around `model/materials.php:741`) |
| Open/closed toggle (`model/materials.php:722-766`) | Must not flip `awaiting_confirmation` straight to `open`, and must not open a material with no semester; route both through Confirm instead |
| Permissions | Same admin roles/scopes that can edit materials today |

Past purchases are unaffected by price changes, because `manuals_bought.price` is stored per purchase.

---

## 5. Frontend Work (white_label)

### 5.1 API client (`src/lib/app-api.ts`, `src/types/app.ts`)
Add typed methods for every endpoint in Section 3. Change the wallet PIN methods to `setPinDirect`, and add `changePin` if 3.1's recommendation is adopted.

### 5.2 Screens

| Screen / component | Behaviour |
| :--- | :--- |
| **Wallet PIN** (`WalletPinPage.tsx` + inline prompt at checkout) | No PIN: enter + confirm 4 digits → `set_pin_direct`. PIN exists: current PIN + new PIN. Forgot PIN: existing OTP flow. |
| **Checkout** (`CheckoutPage.tsx`) | Keep matching the app: wallet default; gateway option only when `/payment/gateway.php` says it isn't frozen; show the wallet fee line; insufficient balance → virtual account details + "Refresh balance" (`/wallet/refresh-credits.php`). |
| **Store** (`StorePage.tsx`) | Details modal (course code, title, lecturer/scope, level, semester, price); "Already bought" state from `is_bought`; optional "Showing First Semester materials" label. |
| **Orders** (`OrderHistoryPage.tsx`) | "Mark as lost" with confirmation → `/materials/mark-lost.php`; then the store allows re-buying. |
| **Transfer** (`/wallet/transfer`, button on wallet/dashboard) | Matric or email → lookup → show name for confirmation → amount + optional note → PIN → receipt. Generate a `request_token` per attempt to prevent double sends. |
| **Bulk payment** (`/bulk-payment`) | Pick material → enter rows (CSV upload or pasted text: matric, first name, last name) → preview table with per-row status and totals incl. 5% fee → PIN → result. Keep the web's local draft of pasted text. |
| **Bulk claims** (`AppShell.tsx`) | On load, fetch pending claims. Modal per claim: "[Payer] paid for [course code] for you" → Accept / Reject. Refresh orders on accept. |
| **Material requests** (`/material-requests`) | List with progress bars toward 40%, upvote button, create form with scope picker, share link, and a "this material is already in the store" redirect when the service finds a match. |
| **System alerts** (`AppShell.tsx`) | Banner stack at the top, coloured by `color`, dismissible for the session. |
| **Survey banner** (`AppShell.tsx`) | Non-blocking bottom-right card; CTA opens the survey; close → `dismiss`; hidden after 5 dismissals or once answered. |
| **Events** | None of this appears anywhere in the UI. |

---

## 6. Rollout Order

| # | Work | Depends on |
| :--- | :--- | :--- |
| 1 | Fix `API/wallet/transfer.php`; tighten PIN change on web and API | — |
| 2 | Semester schema + filter helper + `'closed'` checks in cart/details | — |
| 3 | cc_dashboard: semester field, school semester switch, awaiting-confirmation list, confirm/retire | 2 |
| 4 | Apply the semester filter across the API and PHP website (4.4) | 2 |
| 5 | New API endpoints: bulk, claims, material requests, surveys, alerts, `set_pin_direct` | 1 |
| 6 | white_label: PIN, checkout, store details, mark lost | 5 |
| 7 | white_label: transfer, bulk payment, claims, material requests, alerts, survey | 5 |
| 8 | Parity check vs mobile app and PHP site; school-by-school subdomain cutover | 6, 7 |

Steps 2–4 can ship before white_label: they immediately clean up the current PHP store and the mobile app store.

### Test checklist (minimum)
- Right after deploy, existing untagged materials are still visible; at the first switch they move to awaiting and cannot be confirmed without choosing a semester.
- cc_dashboard refuses to create, edit or open a material without a semester; "Duplicate for other semester" creates a separate material with a new code.
- A semester-2 material created during semester 1 is hidden in the API, the PHP store, cart-add, checkout, the bulk picker, and material-request matching; it appears after the switch.
- After the switch, semester-1 materials are `awaiting_confirmation`, hidden everywhere, but still visible in order history and receipts for buyers.
- Confirming with a new price reopens the material; old purchases keep the old price.
- Transfer fails with a wrong PIN, a recipient from another school, or insufficient balance; a double submit with the same `request_token` sends once.
- `set_pin_direct` is refused when a PIN already exists.
- The mobile app PIN OTP flow and checkout still work unchanged.
- Bulk: name mismatch, department mismatch and unregistered students (placeholder → later claim) all behave as on the PHP site.

---

## 7. Decisions
1. **Verification codes are not part of white_label.** They belong to material granting in cc_dashboard: the export verification code (`manual_export_audits`, checked via `manual-export-verify.php`) and `manuals.code` are used in **Material Grants** (`cc_dashboard/material_grants.php`) to grant a whole export in bulk, or a single purchase by student email/matric. Nothing changes there, except that grant lookups must never be semester-filtered (4.4). Student bulk payment uses no code (matric + names only).
2. **Every material needs a semester and confirmation.** Untagged materials are not always-on: they go to `awaiting_confirmation` at the first switch and need a semester to be confirmed (4.1.1). A material belongs to one semester only; use "Duplicate for other semester" for courses that need it in both.
3. **Gateway stays behind the payment-freeze flag**, exactly like the mobile app (5.2 Checkout).
