# HTTP API Reference

Base development URL: `http://localhost:5173/api` (through Vite), or `http://localhost/registrar/backend/public/api` in XAMPP.

All JSON endpoints return:

```json
{"success":true,"data":{},"message":""}
```

Errors return `{ "success": false, "message": "..." }` with suitable HTTP status. On mutations, send `Content-Type: application/json`, session cookie and `X-CSRF-Token` from `GET /auth/csrf`. CSRF tokens are automatically handled by the included Axios client.

## Authentication

| Method | Endpoint | Role | Body / Notes |
|---|---|---|---|
| GET | `/health` | Public | Server health (does not check DB) |
| GET | `/auth/csrf` | Public | Returns session CSRF token |
| GET | `/auth/me` | Public | Current user or `null` |
| POST | `/auth/login` | Public | `{identity,password}`; returns user+fresh CSRF |
| POST | `/auth/register-student` | Public | `{student_number,full_name,email,username,program,year_level,password}` |
| POST | `/auth/password-reset-request` | Public | `{identity}` |
| POST | `/auth/logout` | Logged in | Clears session |
| POST | `/auth/change-password` | Logged in | `{current_password,new_password}` |
| PUT | `/profile` | Logged in | `{full_name,email,program?,year_level?}` |

## Admin

| Method | Endpoint | Action |
|---|---|---|
| GET | `/dashboard/admin` | Summary stats, trend, recent activity |
| GET, POST | `/staff` | List / create staff |
| PUT | `/staff/{id}` | Edit staff + counter assignment |
| PATCH | `/staff/{id}/status` | `{status:"active"|"inactive"}` |
| POST | `/staff/{id}/reset-password` | `{new_password}` |
| GET | `/students` | Student profiles |
| PATCH | `/students/{id}/verification` | `{verification_status:"verified"|"pending"}` |
| POST | `/students/{id}/reset-password` | `{new_password}` |
| GET | `/password-reset-requests` | Pending requests |
| GET, POST | `/admin/services` | List all / create service |
| PUT, DELETE | `/admin/services/{id}` | Update / disable service |
| GET, POST | `/counters` | List / create counter |
| PUT, DELETE | `/counters/{id}` | Update / disable counter |
| GET | `/queues` | Paged queue listing; `status`, `date`, `search`, `page`, `limit` |
| PATCH | `/queues/{id}/priority` | `{priority_level:0|1|2}` on waiting ticket |
| GET | `/reports/overview?days=14` | Metrics and trend |
| GET | `/reports/export?days=14` | CSV download |
| GET | `/audit-logs` | Recent audit activity |
| GET, PUT | `/settings` | Settings object; approved keys only |
| GET | `/announcements/manage` | All announcements |
| POST | `/announcements` | `{title,message,is_active}` |
| PUT, DELETE | `/announcements/{id}` | Edit / delete announcement |

## Staff

| Method | Endpoint | Action |
|---|---|---|
| GET | `/dashboard/staff` | Current, waiting, stats, recent |
| GET | `/queues` | Staff's handled tickets only |
| POST | `/queues/next` | Call next eligible waiting ticket |
| POST | `/queues/{id}/recall` | Repeat a call |
| POST | `/queues/{id}/start` | Start serving called ticket |
| POST | `/queues/{id}/complete` | `{remarks?}`; close ticket |
| POST | `/queues/{id}/skip` | `{remarks?}`; skip ticket |
| POST | `/queues/{id}/reinstate` | Return own skipped ticket to today's waiting queue |

## Student

| Method | Endpoint | Action |
|---|---|---|
| GET | `/services` | Active registrar services |
| POST | `/queues` | `{service_id}`; create own ticket |
| GET | `/queues/my-active` | Active ticket(s), queue position and wait estimate |
| GET | `/queues/my-history` | Own 100 most recent tickets |
| POST | `/queues/{id}/cancel` | Cancel own waiting ticket |

## Shared / public

| Method | Endpoint | Action |
|---|---|---|
| GET | `/display/current` | Current counter calls, next waiting, counts (public) |
| GET | `/announcements` | Published announcements (public) |
| GET | `/notifications` | Own notifications |
| PATCH | `/notifications/{id}/read` | Mark own notification as read |

### Common HTTP errors

- `400`: missing or invalid input
- `401`: not signed in/inactive session
- `403`: role or permission denied
- `404`: resource or ticket not found
- `409`: duplicate ticket or invalid queue transition
- `419`: missing/expired CSRF token
- `503`: database connection unavailable
