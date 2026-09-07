# ASELCO Zenny — Module Documentation

**Docs version:** `v1.0.3` (revised 2026-09-07 03:49:11)

Developer docs for every **active** web and mobile feature. Each module page lists purpose, MVC/Services/AI file map, routes, permissions, a context diagram, and a process flowchart.

## Browse in the browser (no server needed)

Double-open [`index.html`](index.html) — markdown is embedded in [`docs-data.js`](docs-data.js), so you do **not** need `npx serve`.

The viewer matches the staff web shell (dark sidebar, Bootstrap Icons, ASELCO brand, light header). Inside each page, a **second sidebar** shows Topics, Scenarios, Discussion, Related modules, and “Before you code” tips.

**After editing any `.md` file**, rebuild the embed:

```bash
node docs/build-docs-data.js
```

Then refresh `index.html`. (Diagrams/CDN scripts need internet once for Mermaid + Bootstrap Icons.)

## How to read these docs

1. Start with [Architecture overview](architecture/overview.md) if you are new to the monorepo.
2. Skim [MVC conventions](architecture/mvc-conventions.md) for where Controllers, Models, Views, and Services live.
3. Open the module you need under [Web](web/README.md) or [Mobile](mobile/README.md).
4. Use **Related modules** and **Backend link** sections to jump across web ↔ API ↔ mobile.

## Repository layout

| Path | Role |
|------|------|
| `aselcoph/` | Laravel 12 staff web portal + `/api/v1` backend |
| `mobile/` | Ionic React 8 + Capacitor customer app (`myASELCO`) |
| `aselcoph/docs/` | Deeper existing docs (AST architecture, OpenAPI, go-live) — linked from module pages |

## Indexes

- [Web modules](web/README.md)
- [Mobile modules](mobile/README.md)
- [Architecture overview](architecture/overview.md)
- [MVC conventions](architecture/mvc-conventions.md)

## Web ↔ Mobile cross map

| Domain | Web doc | Mobile doc |
|--------|---------|------------|
| Auth | [web/auth-profile.md](web/auth-profile.md) | [mobile/auth.md](mobile/auth.md) |
| Membership / accounts | [web/consumers-billing.md](web/consumers-billing.md) | [mobile/membership.md](mobile/membership.md) |
| Dashboard / Home | [web/dashboard.md](web/dashboard.md) | [mobile/home-dashboard.md](mobile/home-dashboard.md) |
| Ledger / billing history | [web/consumers-billing.md](web/consumers-billing.md) | [mobile/ledger.md](mobile/ledger.md) |
| AST Wallet | [web/ast-wallet.md](web/ast-wallet.md) | [mobile/ast-wallet.md](mobile/ast-wallet.md) |
| Pay with AST | [web/ast-wallet.md](web/ast-wallet.md) | [mobile/pay-with-ast.md](mobile/pay-with-ast.md) |
| Tickets | [web/tickets.md](web/tickets.md) | [mobile/tickets.md](mobile/tickets.md) |
| Create complaint | [web/tickets.md](web/tickets.md) | [mobile/complaints.md](mobile/complaints.md) |
| AI | [web/ai-assistant.md](web/ai-assistant.md) | [mobile/ai-assistant.md](mobile/ai-assistant.md) |
| Knowledge / RAG | [web/knowledge-rag.md](web/knowledge-rag.md) | (via AI answers) |
| Support chat | [web/support-chat.md](web/support-chat.md) | [mobile/support-chat.md](mobile/support-chat.md) |
| Notifications | [web/announcements.md](web/announcements.md) | [mobile/notifications.md](mobile/notifications.md) |
| Profile | [web/access-user-management.md](web/access-user-management.md) | [mobile/profile.md](mobile/profile.md) |

## Module doc template

Every module page follows the same structure:

1. Purpose  
2. Users / entry points  
3. Context diagram (Mermaid)  
4. Process flowchart (Mermaid)  
5. File map (MVC + Services + AI)  
6. Routes / API  
7. Permissions / feature flags  
8. Related modules  

Paths in file maps are relative to the repo root unless noted.
