# Development with 7 Collaborators

Assign **one module owner per team member**, but review API contracts together before code integration.

| Collaborator | Role | Owned files / responsibilities |
|---|---|---|
| 1 | Lead / Architect | `frontend/src/App.jsx`, authentication architecture, integration, pull request reviews, CI plan |
| 2 | UI/UX + Shared Frontend | `frontend/src/components/`, `frontend/src/styles/`, responsive UI consistency |
| 3 | Admin UI Developer | `frontend/src/pages/AdminPages.jsx`, admin user flows |
| 4 | Staff UI Developer | `frontend/src/pages/StaffPages.jsx`, queue calling and remarks workflow |
| 5 | Student UI Developer | `frontend/src/pages/StudentPages.jsx`, registration UX and ticket tracking |
| 6 | PHP + Database Developer | `backend/src/`, `backend/public/`, `database/`, database migrations and transactions |
| 7 | QA + Reports + Public Display | `frontend/src/pages/PublicDisplay.jsx`, report validation, `docs/TEST_PLAN.md`, cross-browser testing |

Collaborators 1 and 6 should agree on the response JSON shape and schema before independent UI work. Collaborator 2 supplies shared components before other developers introduce new UI elements.

## Suggested branches

```text
main
  develop
  feature/authentication
  feature/ui-components
  feature/admin-module
  feature/staff-module
  feature/student-module
  feature/backend-database
  feature/reports-testing
```

## Team procedure

1. Create a GitHub repository from the extracted folder, committing source files only. Do not commit `backend/.env` or `node_modules`.
2. Protect `main` and require at least one review for pull requests.
3. Each developer works in a separate `feature/...` branch from `develop`.
4. The backend developer documents endpoint changes in `docs/API.md` before UI consumption.
5. New database changes use migration scripts with up/down or forward-only notes; never silently edit the shared production schema.
6. Use small pull requests, including UI screenshots and tests where possible.
7. Integrate in this order: schema → authentication → shared UI → staff/admin/student APIs → queue engine → analytics/display → QA.
8. Review and fix authorization, duplicate-call races, and data privacy before demonstrations.

### Example commands

```bash
git checkout -b feature/staff-module develop
git add frontend/src/pages/StaffPages.jsx
git commit -m "feat(staff): add staff queue processing UI"
git push -u origin feature/staff-module
```

### Definition of done

A change is complete after PHP syntax validation, React build, API tests against a working XAMPP MySQL server, access control checks, a mobile layout check, and a reviewed pull request. Avoid merging UI screens that only display hardcoded data or buttons that do nothing.
