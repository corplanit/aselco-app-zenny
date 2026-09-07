# User Management and Authorization

Central access-control layer for customers, support accounts, departments, roles, and permissions.

## Principles

- One `users` table. Customers and staff are distinguished by `user_type` and role, not a second identity store.
- All module access goes through `AccessService` / Laravel Gates (`can('tickets.assign')`). Do not add module-local permission tables.
- Ticket routing stays authoritative in `TicketService::authoritativeDepartmentFor()`. AI suggestions are validated before assignment.
- `users.role` and `users.department_code` stay synced from `role_id` / `department_id` for existing ticket and AST code.

## Web

- `/access/users`, `/access/customers`, `/access/support`
- `/access/departments`, `/access/roles`, `/access/permissions`
- `/access/sessions`, `/access/activity`, `/access/availability`
- `/access/assignments`, `/access/settings`, `/access/reports`
- `/workspace/dashboard`, `/workspace/tickets`, `/workspace/department-queue`, `/workspace/sla`, `/workspace/notifications`

## API (`/api/v1`)

- `GET|POST /admin/users`, `GET|PUT|DELETE /admin/users/{id}`
- `GET|POST /admin/departments`, `GET|PUT /admin/departments/{id}`
- `GET|POST /admin/roles`, `PUT /admin/roles/{id}`
- `GET /admin/permissions`, `GET|PUT /admin/roles/{id}/permissions`, `PUT /admin/users/{id}/permissions`
- `GET /admin/tickets/{id}/assignment`, `POST .../assign`, `POST .../reassign`, `GET .../assignment-history`
- `GET /support/tickets`, `/support/notifications`, `/support/dashboard`, `/support/profile`

## Seed

`php artisan db:seed --class=AccessSeeder` maps existing role strings to configurable roles and departments.
