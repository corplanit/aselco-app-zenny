# Web modules (Laravel staff portal)

Staff UI lives in `aselcoph/` (Blade). Customer mobile consumes the same backend via `/api/v1`.

## Active modules

| Module | Doc | Primary routes |
|--------|-----|----------------|
| AST Wallet | [ast-wallet.md](ast-wallet.md) | `/ast/admin/*`, `/ast/wallet`, `/ast/cis-queue` |
| Tickets | [tickets.md](tickets.md) | `/tickets/*` |
| AI Assistant + Ticket AI | [ai-assistant.md](ai-assistant.md) | `/tickets/ai`, ticket AI actions |
| Knowledge / RAG | [knowledge-rag.md](knowledge-rag.md) | `/knowledge/*` |
| Support Chat | [support-chat.md](support-chat.md) | `/chats/support/*` |
| Access / User Management | [access-user-management.md](access-user-management.md) | `/access/*` |
| Support Workspace | [support-workspace.md](support-workspace.md) | `/workspace/*` |
| Announcements | [announcements.md](announcements.md) | `/announcements/*` |
| Consumers / Billing | [consumers-billing.md](consumers-billing.md) | `/consumer/*`, `/validation`, `/billing-upload` |
| Calendar | [calendar.md](calendar.md) | `/calendar` |
| Auth & Profile | [auth-profile.md](auth-profile.md) | Jetstream + `/auth/google` |
| Dashboard | [dashboard.md](dashboard.md) | `/dashboard`, `/u/dashboard`, `/t/dashboard` |

## Legacy / ops (still routed)

| Module | Doc | Notes |
|--------|-----|-------|
| CMS / Blog / Pages | [cms-blog.md](cms-blog.md) | Content Manager menu |
| File Manager / Drive | [file-manager.md](file-manager.md) | Internal ops |
| Satisfaction Survey | [survey.md](survey.md) | Survey link update |

## Activation cheat sheet

- Permissions: `aselcoph/config/access.php`
- Ticket / knowledge / AI admin: middleware `can.manage-tickets`
- AST load writes: middleware `can.load-wallet` (API) + controller checks (web)
- Menus: `aselcoph/resources/views/components/menu/`
