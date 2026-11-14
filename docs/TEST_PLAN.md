# Integration and Acceptance Test Plan

Prepare a fresh XAMPP installation, import both SQL scripts, run the CLI admin setup, start Vite, then test in two or three separate browser profiles. Use `docs/API.md` for request shapes.

## Functional cases

| ID | Action | Expected outcome |
|---|---|---|
| AUTH-01 | Admin logs in with correct credentials | Admin dashboard displays database-backed stats |
| AUTH-02 | Wrong password | `401`; no session created |
| AUTH-03 | Student registers twice with same student ID | Second request returns `409` |
| AUTH-04 | Staff signs in with temporary password | Forced password-change page |
| AUTH-05 | Staff changes password | Can access staff workspace after successful change |
| AUTH-06 | Anonymous visitor visits `/admin` | Redirected to login; underlying API still protected |
| AUTH-07 | POST without valid CSRF token | `419`; no write |
| ADMIN-01 | Admin creates staff and assigns Counter 1 | Staff appears with assignment; credentials work |
| ADMIN-02 | Admin disables staff account | Staff loses access on next request |
| ADMIN-03 | Admin adds service and maps to counter | Student can select the service and staff can process it |
| ADMIN-04 | Admin disables service | Service disappears from student service list |
| ADMIN-05 | Admin enables verification required | New student cannot generate ticket until verified |
| QUEUE-01 | Student requests one service | Unique ticket persisted, shown on dashboard |
| QUEUE-02 | Student tries another active ticket | `409` instead of duplicate active ticket |
| QUEUE-03 | Two students create simultaneously | Unique monotonically increasing service sequence numbers |
| QUEUE-04 | Two staff counters try to call same waiting ticket | At most one counter owns the ticket |
| QUEUE-05 | Staff calls, starts, completes ticket | Valid status transitions and notifications; transaction persisted |
| QUEUE-06 | Staff attempts to complete a waiting ticket | `409` or `403`; no illegal transition |
| QUEUE-07 | Student cancels a waiting ticket | Ticket cancelled and removed from active queue |
| QUEUE-08 | Student attempts to cancel called ticket | `409`; no cancellation |
| QUEUE-09 | Staff skips called ticket and requeues | Ticket returns to waiting queue; status history preserved |
| QUEUE-10 | Admin sets waiting ticket priority | Next-queue ordering respects priority |
| QUEUE-11 | Admin suspends queue issuance | New ticket creation and next calls blocked |
| DISPLAY-01 | Staff calls ticket | Public display updates within 5 seconds |
| DISPLAY-02 | Open public board without login | Displays ticket/counter only; never student names |
| REPORT-01 | Complete a ticket | Reports aggregate completed ticket and staff metric |
| REPORT-02 | Export CSV | File downloads; dangerous spreadsheet cell prefixes escaped |
| SECURITY-01 | Student calls staff/admin mutation endpoints | `403` |
| SECURITY-02 | Staff edits another staff member | `403` |
| SECURITY-03 | Student reads another student's tickets | No cross-account history exposed |
| SECURITY-04 | Script attempts SQL injection in form inputs | Query parameterization prevents SQL execution |
| DESIGN-01 | Repeat flows on mobile/tablet | Navigation, dialogs, tables and forms remain usable |

## Local static checks

**PHP syntax:**

```powershell
Get-ChildItem backend -Recurse -Filter *.php | ForEach-Object { C:\xampp\php\php.exe -l $_.FullName }
```

**Frontend build:**

```powershell
cd frontend
npm install
npm run build
```

**Backend health smoke test:**

http://localhost/registrar/backend/public/api/health

**CSRF check:** POST to `/api/queues` without `X-CSRF-Token` should return HTTP 419 before executing queue logic.

## Important note

The code has been statically validated in the generation environment, but a complete end-to-end test needs a running XAMPP MySQL server and installed npm dependencies. This QA plan specifies what your team must verify locally rather than claiming those live flows were exercised here.
