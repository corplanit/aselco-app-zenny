# Go-live checklist — Wallet + Tickets

## Pre-deploy config

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` set
- [ ] `QUEUE_CONNECTION=database` (or redis) — **not** `sync`
- [ ] Queue worker running: `php artisan queue:work --tries=5 --backoff=15,30,60,120`
- [ ] Cron: `* * * * * cd /path/to/aselcoph && php artisan schedule:run >> /dev/null 2>&1`
- [ ] Confirm `php artisan schedule:list` shows `tickets:check-sla` every 5 minutes (`withoutOverlapping`)
- [ ] `AST_PHP_RATE`, `AST_LOAD_APPROVAL_THRESHOLD`, `AST_MAX_LOAD_AMOUNT`, `AST_LOAD_WALLET_ROLES`
- [ ] `TICKET_SLA_SWEEP_MINUTES`, `TICKET_SECOND_TIER_AFTER_MINUTES`, category `sla_minutes` in DB
- [ ] FCM: `FIREBASE_PROJECT_ID` + readable `FIREBASE_CREDENTIALS` JSON (or accept in-app-only notifications)
- [ ] SMS: `SMS_WEBHOOK_URL` / `SMS_WEBHOOK_TOKEN` (or accept mail + in-app only)
- [ ] `failed_jobs` table present; watch `queue.job_failed` log events
- [ ] Ticket attachments disk is **not** the public disk (`TICKET_ATTACHMENT_DISK=local`)

## AuthZ (verified in code)

| Surface | Guard |
|---|---|
| `/api/v1/admin/wallet/*` | Sanctum + `can.load-wallet` (role list) |
| `/api/v1/admin/tickets/*` | Sanctum + `can.manage-tickets`; list/show scoped by department unless CSR/supervisor/admin |
| `/api/v1/customer/wallet/*` | Sanctum + verified; bills/accounts must belong to the member |
| `/api/v1/customer/tickets/*` | Sanctum + verified; `customer_id = auth id` |
| Attachment download | Sanctum + signed URL + ownership/department check |

## Idempotency

- Admin load: unique `idempotency_key` + `reference_no` on `wallet_transactions` / `wallet_load_requests`
- AST pay/load: unique `idempotency_key` on `ast_ledger_entries` + `lockForUpdate`
- Storm test: 50 identical POSTs → one ledger/wallet effect (`WalletIdempotencyStormTest`)

SQLite PHPUnit runs those 50 hits in-process (unique index still enforces one row). For a true parallel storm, run the same HTTP calls against production-like MySQL with a load tool.

## Rollback

1. Stop queue workers and disable the scheduler cron.
2. `php artisan down --retry=60 --secret=...`
3. Deploy previous release / `git revert` the go-live tag.
4. Restore DB from the pre-deploy dump if migrations or ledger rows are wrong. **Do not** delete `wallet_audit_logs`, `ast_audit_logs`, or `ticket_status_history` — they are append-only.
5. `php artisan migrate:rollback` only for the last batch if it is schema-only and unused.
6. `php artisan up`, restart workers, confirm `tickets:check-sla` and a test wallet load + ticket create.

## After go-live

- [ ] One admin AST load + one customer pay-bill with a reused Idempotency-Key (second call 200, no double credit)
- [ ] One customer ticket + attachment download via signed URL (direct `/storage/tickets/...` must 404)
- [ ] One SLA-overdue ticket escalates on the next `tickets:check-sla`
- [ ] Failed notification job appears in `failed_jobs` if FCM credentials are wrong (then fix env and `queue:retry`)
