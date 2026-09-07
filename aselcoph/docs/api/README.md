# ASELCO API v1 — QA / mobile handoff

Interactive docs: **`/api/docs`** (Swagger UI)  
Machine spec: **`/api/v1/openapi.json`** (source: `docs/api/openapi-v1.yaml`)  
Postman: import **`docs/api/Aselco-API-v1.postman_collection.json`**

The API is already versioned at **`/api/v1/...`**. A leftover `GET /api/user` exists for old clients; use `GET /api/v1/auth/user`.

## Groups

| Tag | Prefix | Who |
|---|---|---|
| Wallet | `/customer/wallet`, `/wallet`, `/admin/wallet` | Verified member; admin load roles |
| Tickets (complaints) | `/customer/tickets`, `/admin/tickets` | Member; staff with `canManageTickets()` |
| Notifications | `/notifications`, `/admin/notifications`, `/devices` | Same as tickets/member |
| Customer Service | `/membership`, `/dashboard`, `/ledger`, `/auth` | Member |

There is **no** `/api/v1` surface for the legacy `customer_complaints` Blade module.

## Conventions

**Success:** raw resource or Laravel paginator. Mutations often include `message`. Domain failures (wallet, ledger) already used `{ message, code }`. That is the contract — we did **not** wrap successes in `{ data: ... }` so the Ionic app does not break.

**Errors (all `/api/*` exceptions):**

```json
{ "message": "…", "code": "VALIDATION_ERROR", "errors": { "field": ["…"] } }
```

Codes: `VALIDATION_ERROR`, `UNAUTHENTICATED`, `FORBIDDEN`, `NOT_FOUND`, `RATE_LIMITED`, `UNPROCESSABLE`, plus domain codes (`AST_INSUFFICIENT_FUNDS`, `WALLET_LOAD_INVALID`, …).

**Auth:** `Authorization: Bearer {token}` from `POST /auth/login`. Wallet loads also send `Idempotency-Key`.

## Rate limits

| Limiter | Default |
|---|---|
| `api` (all v1) | 120 / min / user or IP |
| `api-login` | 5 / min |
| `api-register` | 3 / min |
| `api-wallet` / `admin-wallet-load` | 20 / min |
| `api-tickets` | 40 / min |
| `api-notifications` | 60 / min |

## Third-party status (no new vendors added in this pass)

| Integration | Code | Needs from you |
|---|---|---|
| **FCM** | `FcmPushService` + `POST /devices` | Confirm `FIREBASE_PROJECT_ID` + `FIREBASE_CREDENTIALS` are set in the deployed env. Pipeline is end-to-end in code. |
| **SMS** | `SmsDeliveryService` webhook (`SMS_WEBHOOK_URL`) | Confirm provider URL/token if OTP/SMS must actually send. Unconfigured calls are logged and skipped. |
| **Online payment platforms** | Not implemented | Flowchart “advise online payment” can be a **link list** or a full gateway. Say which. |
| **CIS ledger** | `LedgerController` HTTP proxy | Already used (`ASELCO_LEDGER_URL`). |
