# Registrar Queue Management System

A full-stack, mobile-friendly registrar queueing application with three access levels (**Administrator**, **Registrar Staff**, **Student**) and a public queue display. Developed with **React + Vite + Tailwind CSS**, **PHP REST APIs**, and **MySQL/MariaDB hosted by XAMPP**. Designed for a team of seven collaborators.

> This is a functional source-code implementation for local XAMPP development, **not a hosted service**. Install dependencies, import SQL, and create the first administrator to use it. Before using real student data, deploy with HTTPS, apply institution-specific identity policies, backups, monitoring, and a security review.

## Included features

| Role | Features |
|---|---|
| Administrator | Dashboard, staff account creation/editing/deactivation and temporary password reset, student verification and reset, service and counter management, counter assignments, queue monitoring and priority controls, announcements, reports and CSV exports, audit logs, password-reset requests, system settings |
| Staff | Assigned counter dashboard, call next, recall, start, complete, skip and requeue skipped tickets, remarks, service history and notifications |
| Student | Registration/login, browse services, generate ticket, live queue position and estimated wait, cancel pending ticket, history, notifications, announcements, profile |
| Public | Full-screen queue display showing serving tickets, counters and upcoming ticket numbers (no student names) |

Additional safeguards: CSRF protection, HttpOnly PHP sessions, role permissions enforced in PHP, PDO prepared statements, transaction-based ticket issuance and calling, password hashing, queue events and audit logs. Updated responsive UI using the official school seal and a blue-and-gold theme derived from the logo.

## Requirements

- **Windows + XAMPP** (PHP **8.2+**, Apache with `mod_rewrite`, MySQL or modern MariaDB, PHP PDO MySQL enabled)
- **Node.js 18.18+ or Node.js 20+** and npm
- Modern Chrome, Firefox, or Edge
- Optional: VS Code, Git, Postman

> **Important:** MySQL is **not** used directly by React. React calls PHP's `/api/*` routes, and PHP connects to the database using PDO.

## 1. Copy the project into XAMPP

Extract the ZIP. Copy the **entire `registrar` folder** to:

```text
C:\xampp\htdocs\registrar\
```

The result must contain:

```text
C:\xampp\htdocs\registrar\frontend
C:\xampp\htdocs\registrar\backend
C:\xampp\htdocs\registrar\database
```

## 2. Start Apache and MySQL

Open the **XAMPP Control Panel** and start **Apache** and **MySQL**.

Check: http://localhost/phpmyadmin

If Apache runs on a different port, update the Vite proxy in `frontend/vite.config.js` accordingly.

## 3. Create and populate the database

1. Open http://localhost/phpmyadmin
2. Click **New** and create database **`registrar_queue_db`**, choosing `utf8mb4_unicode_ci` collation.
3. Select this new database.
4. Use **Import** to import `registrar/database/schema.sql`.
5. Import `registrar/database/seed.sql` next.

The seed adds eight registrar services, three counters, service assignments and safe default settings. It does **not** create any user passwords.

## 4. Configure the PHP database connection

Copy `backend/.env.example` to `backend/.env` and edit it with your XAMPP credentials:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_TIMEZONE=Asia/Manila
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=registrar_queue_db
DB_USER=root
DB_PASS=
SESSION_NAME=registrar_sid
```

A default local XAMPP installation often has a blank MySQL root password. If yours differs, configure it here. Never commit `.env` or deploy with empty database passwords on a public server. Set `APP_DEBUG=false` for production.

Open the backend health endpoint:

http://localhost/registrar/backend/public/api/health

Expected response includes `"success":true` and `"status":"ok"`.

## 5. Create the initial administrator securely

Open **Command Prompt** or **PowerShell**:

```powershell
cd C:\xampp\htdocs\registrar
C:\xampp\php\php.exe backend\scripts\create_admin.php
```

Enter the requested full name, username, school email and a **unique password of at least 12 characters**. The script prompts for a password rather than shipping an insecure default account. **Note:** this simple console prompt may show password characters on your screen; run in a private terminal.

You can create additional administrators through the CLI only. Staff cannot register themselves.

## 6. Run the React app

In a **new terminal**:

```powershell
cd C:\xampp\htdocs\registrar\frontend
npm install
npm run dev
```

Open **http://localhost:5173/**.

The Vite development server forwards `/api` requests to `http://localhost/registrar/backend/public/api`. Keep **Apache, MySQL, and the Vite terminal** running.

### Test accounts and first workflow

1. Open http://localhost:5173/login and sign in with the administrator account you created.
2. Under **Staff Accounts**, create a staff account and assign it to **Counter 1**, **2** or **3**. Share its temporary password privately.
3. In another browser/private session, open http://localhost:5173/register and register a student. Newly created student accounts are auto-verified by default for local demos. For real use, disable auto-verification under Admin → Settings, then manually verify students.
4. Have the student open **Get Queue Ticket**, select a service supported by the assigned counter, and generate a ticket.
5. Sign in as staff in a separate browser/private session. Staff must change the temporary password on first login. Press **Call Next Student**, then **Start Service** and **Complete Service**.
6. Watch the ticket status automatically update on the student dashboard and the public board at http://localhost:5173/display.
7. View completed transactions and reports in the administrator portal.

**Why separate browser sessions?** All users share a session cookie on `localhost`. Use normal and Incognito windows (or separate browser profiles) to test multiple accounts without replacing one another's session.

## Local build for Apache (optional)

By default, **Vite development mode is recommended for XAMPP**. To serve a compiled React app through Apache:

1. Copy `frontend/.env.production.example` to `frontend/.env.production`.
2. In the `frontend` directory, run `npm run build`.
3. Confirm the output is in `frontend/dist/` and copy `frontend/public/.htaccess` into `frontend/dist/.htaccess` if it did not copy automatically.
4. Open http://localhost/registrar/frontend/dist/

This build configuration assumes the project folder name stays `registrar` and is under the default Apache `htdocs`. If you move it, update `base` in `frontend/vite.config.js`, `.htaccess` RewriteBase, the production API base URL, and any Apache path configuration.

## Project structure

```text
registrar/
├── frontend/
│   ├── src/
│   │   ├── components/       # Shell and reusable UI components
│   │   ├── contexts/         # Auth + toast providers
│   │   ├── lib/              # Axios/CSRF API client + hooks
│   │   ├── pages/            # Admin, Staff, Student, Auth, Public Display
│   │   ├── styles/           # Tailwind + application CSS
│   │   ├── App.jsx           # React Router routes and access gates
│   │   └── main.jsx
│   ├── public/.htaccess
│   ├── package.json
│   ├── vite.config.js
│   ├── tailwind.config.js
│   └── .env.production.example
├── backend/
│   ├── public/index.php      # Front controller / REST API routes
│   ├── public/.htaccess
│   ├── src/                  # Core, AuthApi, AdminApi, QueueApi, CommonApi
│   ├── scripts/create_admin.php
│   └── .env.example
├── database/
│   ├── schema.sql
│   └── seed.sql
├── docs/
│   ├── ARCHITECTURE.md
│   ├── API.md
│   ├── COLLABORATION.md
│   └── TEST_PLAN.md
├── .gitignore
└── README.md
```

## Important notes / limitations

- This implementation uses **efficient HTTP polling** (5 seconds for the public display, 6–9 seconds for dashboards), not WebSockets or text-to-speech.
- Wait times are **heuristic estimates** based on the current queue and the number of capable active counters. They are not guaranteed.
- Password reset is **administrator-assisted**: the public request form records a request; it does **not** send email. Staff and student resets require administrator identity verification.
- CSV export is implemented; use the browser **Print / PDF** action for a printable report.
- Student email addresses are validated syntactically, not automatically verified through email. Configure campus identity verification for real-world use.
- A queue is scoped to the Philippine date/time (Asia/Manila). Queues from prior days should be reviewed/closed manually when appropriate.
- Intended for local/school project demonstration. Real deployment requires automated integration tests against MySQL, rate limiting at the web-server layer, HTTPS, secure backup policies, accessibility testing, and an institutional privacy assessment.
- The full `npm install` and MySQL integration flow requires a workstation with Node registry access and a running XAMPP MySQL server.

## Collaboration

See `docs/COLLABORATION.md` for 7-member assignments and Git branch workflow. See `docs/API.md` for route contracts and `docs/TEST_PLAN.md` for acceptance tests.

## Troubleshooting

| Problem | Check |
|---|---|
| `404` calling `/api/*` | Apache `mod_rewrite`, `AllowOverride All`, backend `.htaccess`, Vite proxy path |
| `503` database connection failed | Start XAMPP MySQL, correct `.env`, enable PHP `pdo_mysql`, import schema |
| Login shows `419` | Refresh page to obtain a new CSRF token; same origin and cookies required |
| `Cannot find module` on React | Run `npm install` inside `frontend/` |
| Student cannot join queue | Verify account, active service, active counter supports the service, no other active ticket, queue not suspended |
| Staff cannot call tickets | Admin must assign staff to active counter; counter must support relevant services |
| Blank page on compiled build | Check `.env.production` and Vite base/Apache RewriteBase paths |
| Network issues on port 5173 | Ensure Vite is running; check that port is not in use |


## Branding update

This package now includes the uploaded **Our Lady of the Pillar College of San Manuel** seal as the default system logo.

### Applied theme colors

- Primary Blue: `#0E144C`
- Secondary Blue: `#1E2D76`
- Accent Gold: `#F9F90C`
- Soft Gold: `#FFF9C5`
- Surface Background: `#F4F7FE`

### Where the branding was applied

- Sidebar and topbar branding
- Login and registration screens
- Landing page hero card
- Public queue display
- Shared buttons, cards, table headers, form focus states, and highlights

If you want to change the logo later, replace:

- `frontend/src/assets/olpc-logo.jpg`
- `frontend/public/olpc-logo.jpg`
