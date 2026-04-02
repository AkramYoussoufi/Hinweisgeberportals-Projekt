# Hinweisgeberportal — Backend API

A secure, privacy-first whistleblower reporting platform built with **Laravel 12**, designed to comply with the German Whistleblower Protection Act (**HinSchG**) and **GDPR (DSGVO)**.

---

## Table of Contents

- [Overview](#overview)
- [Tech Stack](#tech-stack)
- [Features](#features)
- [Architecture](#architecture)
- [Database Schema](#database-schema)
- [API Reference](#api-reference)
- [Authentication](#authentication)
- [Roles & Permissions](#roles--permissions)
- [Security](#security)
- [Email Notifications](#email-notifications)
- [Background Jobs](#background-jobs)
- [Installation & Setup](#installation--setup)
- [Environment Variables](#environment-variables)
- [Running Tests](#running-tests)

---

## Overview

This is the backend REST API for the Hinweisgeberportal. It powers a complete whistleblower submission and case management system where:

- **Whistleblowers** can submit reports anonymously (no account required) or with a registered account
- **Admins** can view, manage, and communicate on submitted reports
- **SuperAdmins** have full control over admins, portal configuration, and identity unlocking

The system supports a full lifecycle: report submission → admin review → two-way messaging → file exchange → case closure.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.2+ |
| Framework | Laravel 12 |
| Authentication | Laravel Sanctum (stateless Bearer tokens) |
| Database | MySQL |
| Mail | SMTP (configured via `.env`) |
| Queue | Laravel Queue (database driver) |
| Testing | PHPUnit 11 |
| Code Style | Laravel Pint |

---

## Features

### Whistleblower Features
- **Anonymous submission** — no email or account required; system generates a UUID token + 6-digit PIN shown once
- **Registered submission** — full account with email verification
- **Report tracking** — log back in at any time to check status updates
- **Two-way messaging** — communicate with the case handler directly inside the report thread
- **File attachments** — upload supporting documents (PDF, DOCX, images, audio, video)
- **Reference numbers** — every report gets a unique `HIN-YYYY-XXXX` reference

### Admin Features
- View and filter all reports by status and category
- Update report status (`received → reviewing → clarification → closed`)
- Send messages to whistleblowers within a report thread
- View and download report attachments
- Receive daily email digest of unread whistleblower messages

### SuperAdmin Features
- Create, deactivate, reactivate, and delete admin accounts
- Change admin passwords
- Unlock whistleblower identity (decrypts email for legal/judicial use — always audit logged)
- Configure portal-wide settings (rate limits, file size caps, upload quotas)

---

## Architecture

```
app/
├── Http/
│   ├── Controllers/Api/
│   │   ├── AuthController.php            # Registration, login, anonymous login, password reset
│   │   ├── ReportController.php          # Submit and retrieve whistleblower reports
│   │   ├── MessageController.php         # Whistleblower-side messaging
│   │   ├── AttachmentController.php      # File upload and download
│   │   ├── AdminController.php           # Admin report management and messaging
│   │   ├── SuperAdminController.php      # Admin user management and identity unlock
│   │   └── SuperAdminSettingsController.php  # Portal configuration
│   └── Middleware/
│       ├── EnsureUserIsAdmin.php         # Requires role: admin or superadmin
│       └── EnsureUserIsSuperAdmin.php    # Requires role: superadmin
├── Models/
│   ├── User.php                          # Supports registered + anonymous users
│   ├── Report.php                        # Core report entity
│   ├── Message.php                       # Report thread messages
│   ├── Attachment.php                    # File metadata
│   ├── AuditLog.php                      # Immutable action log
│   └── PortalSetting.php                 # Key-value config store
├── Services/
│   ├── ReportService.php                 # Reference generation, anonymous user creation, audit logging
│   └── PortalSettings.php                # Settings retrieval with defaults
├── Jobs/
│   ├── CleanupInactiveAnonymousAccounts.php  # Scheduled: remove stale anonymous users
│   └── SendDailyUnreadDigest.php             # Scheduled: daily unread message digest
└── Notifications/
    ├── NewReportNotification.php
    ├── StatusChangedNotification.php
    ├── NewMessageNotification.php
    ├── DailyUnreadDigestNotification.php
    └── ResetPasswordNotification.php
```

---

## Database Schema

### `users`

| Column | Type | Notes |
|---|---|---|
| `id` | UUID | Primary key |
| `email` | string (encrypted) | AES-256 encrypted at rest; nullable for anonymous |
| `email_hash` | string | SHA-256 of lowercase email; used for lookups |
| `password` | string | bcrypt, 12 rounds |
| `role` | enum | `user`, `admin`, `superadmin`, `deactivated_admin` |
| `is_anonymous` | boolean | True for anonymous whistleblowers |
| `anon_token` | UUID | Anonymous login identifier (shown to user once) |
| `anon_pin_hash` | string | bcrypt-hashed 6-digit PIN |
| `last_active_at` | timestamp | Used by cleanup job |
| `email_verified_at` | timestamp | Must be non-null to access the portal |

### `reports`

| Column | Type | Notes |
|---|---|---|
| `id` | UUID | Primary key |
| `user_id` | UUID | FK → users |
| `reference_number` | string | Unique, format `HIN-YYYY-XXXX` |
| `category` | enum | `fraud`, `harassment`, `safety`, `discrimination`, `other` |
| `status` | enum | `received`, `reviewing`, `clarification`, `closed` |
| `subject` | string | Max 255 characters |
| `description` | text | Full report body |
| `incident_date` | date | Optional |
| `incident_location` | string | Optional |
| `involved_persons` | text | Optional |
| `is_anonymous` | boolean | |
| `closed_at` | timestamp | Set when status becomes `closed` |

### `messages`

| Column | Type | Notes |
|---|---|---|
| `id` | UUID | Primary key |
| `report_id` | UUID | FK → reports |
| `sender_id` | UUID | FK → users |
| `sender_role` | enum | `whistleblower`, `admin` |
| `body` | text | |
| `read_at` | timestamp | NULL = unread |

### `attachments`

| Column | Type | Notes |
|---|---|---|
| `id` | UUID | Primary key |
| `report_id` | UUID | FK → reports |
| `original_filename` | string | Displayed to user |
| `stored_filename` | string | UUID-based name on disk (hidden from API) |
| `mime_type` | string | Validated on upload |
| `size` | bigint | Bytes |

### `audit_logs`

| Column | Type | Notes |
|---|---|---|
| `id` | UUID | Primary key |
| `report_id` | UUID | FK → reports (nullable) |
| `actor_id` | UUID | FK → users (nullable for system actions) |
| `action` | string | e.g. `report_submitted`, `status_changed`, `identity_unlocked` |
| `old_value` | JSON | Previous state (nullable) |
| `new_value` | JSON | New state (nullable) |
| `ip_address` | string | Stored but hidden from all API responses |

### `portal_settings`

| Key | Default | Description |
|---|---|---|
| `max_reports_per_hour_per_ip` | `5` | Rate limit on report submissions |
| `max_file_size_mb` | `10` | Maximum size per uploaded file |
| `max_upload_per_week_mb` | `50` | Weekly upload quota per user |

---

## API Reference

All endpoints are prefixed with `/api`. JSON is required (`Accept: application/json`).

### Authentication

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| POST | `/auth/register` | Public | Create a registered account |
| POST | `/auth/login` | Public | Login with email + password |
| POST | `/auth/anonymous-login` | Public | Login with `anon_token` + `pin` |
| POST | `/auth/forgot-password` | Public | Request a password reset email |
| POST | `/auth/reset-password` | Public | Submit new password with reset token |
| POST | `/auth/logout` | Sanctum | Invalidate current token |
| GET | `/auth/me` | Sanctum | Get authenticated user info |
| GET | `/email/verify/{id}/{hash}` | Signed URL | Verify email address |
| POST | `/email/resend` | Sanctum | Resend verification email (rate limited: 6/min) |

### Whistleblower Reports

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| POST | `/reports` | Public + Rate limited | Submit a new report (5/hr/IP) |
| GET | `/reports` | Sanctum | List own reports |
| GET | `/reports/{reference}` | Sanctum | View a specific report (owner only) |

### Messaging

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/reports/{reference}/messages` | Sanctum | Get messages for a report |
| POST | `/reports/{reference}/messages` | Sanctum | Send a message (disabled on closed reports) |

### Attachments

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/reports/{reference}/attachments` | Sanctum | List attachments |
| POST | `/reports/{reference}/attachments` | Sanctum | Upload a file |
| GET | `/attachments/{id}/download` | Sanctum | Download a file (inline for images, attachment for others) |

### Admin

> Requires role: `admin` or `superadmin`

| Method | Endpoint | Description |
|---|---|---|
| GET | `/admin/reports` | List all reports (filter by `?status=` and `?category=`) |
| GET | `/admin/reports/{reference}` | View full report |
| PATCH | `/admin/reports/{reference}/status` | Update report status |
| GET | `/admin/reports/{reference}/messages` | View report thread |
| POST | `/admin/reports/{reference}/messages` | Send message as admin |
| GET | `/admin/reports/{reference}/attachments` | View attachments |

### SuperAdmin

> Requires role: `superadmin`

| Method | Endpoint | Description |
|---|---|---|
| GET | `/superadmin/admins` | List all admin accounts |
| POST | `/superadmin/admins` | Create a new admin |
| PATCH | `/superadmin/admins/{id}/deactivate` | Deactivate an admin |
| PATCH | `/superadmin/admins/{id}/reactivate` | Reactivate an admin |
| DELETE | `/superadmin/admins/{id}` | Permanently delete an admin |
| PATCH | `/superadmin/admins/{id}/password` | Change admin password |
| GET | `/superadmin/reports/{reference}/unlock-identity` | Decrypt whistleblower email (always audit logged) |
| GET | `/superadmin/settings` | View portal settings |
| PATCH | `/superadmin/settings` | Update portal settings |

---

## Authentication

The API uses **Laravel Sanctum** for stateless, token-based authentication.

### Registered User Flow
1. `POST /auth/register` — account created, verification email sent
2. User clicks signed verification link in email
3. `POST /auth/login` — returns Bearer token
4. Include `Authorization: Bearer {token}` on all subsequent requests

### Anonymous User Flow
1. `POST /reports` — submit a report without an account
2. Response includes `anon_token` (UUID) and `pin` (6-digit) — **shown once, store them**
3. `POST /auth/anonymous-login` with `{ anon_token, pin }` — returns Bearer token
4. Same API flow as registered users from this point

### Email Encryption & Lookup
User emails are stored AES-256 encrypted. A SHA-256 hash of the lowercase email is stored separately and used for login lookups. This means even a full database dump does not expose plaintext email addresses.

---

## Roles & Permissions

| Role | Can Do |
|---|---|
| `user` | Submit reports, view own reports, message admins, upload attachments |
| `admin` | Everything above + view all reports, change status, message whistleblowers |
| `superadmin` | Everything above + manage admins, unlock identities, configure portal |
| `deactivated_admin` | Cannot log in (blocked by middleware) |

---

## Security

| Concern | Implementation |
|---|---|
| Bot protection | hCaptcha verification on anonymous report submission |
| Rate limiting | 5 reports/hr/IP; 6 email resends/min (configurable) |
| Email storage | AES-256 encryption; SHA-256 hash for lookups |
| Password hashing | bcrypt, 12 rounds |
| Anonymous PIN | bcrypt-hashed 6-digit code; shown once, never retrievable |
| File storage | UUID filenames, stored outside web root (`storage/app/attachments/`) |
| File validation | MIME type whitelist + individual and weekly size limits |
| Access control | Role-based middleware + report ownership enforcement |
| Identity unlock | SuperAdmin only; creates immutable audit log entry with IP |
| Audit logging | Every sensitive action logged: who, what, when, old/new value, IP |
| SQL injection | Eloquent ORM with parameterized queries throughout |
| Timing attacks | `hash_equals()` used for email hash comparisons |
| Session security | Sanctum stateless tokens; no session cookies for API |

### Allowed File MIME Types

| Type | Formats |
|---|---|
| Documents | `application/pdf`, `application/msword`, `application/vnd.openxmlformats-officedocument.wordprocessingml.document` |
| Images | `image/jpeg`, `image/png`, `image/gif` |
| Video | `video/mp4`, `video/mpeg` |
| Audio | `audio/mpeg`, `audio/wav`, `audio/ogg` |

---

## Email Notifications

| Notification | Trigger | Recipient |
|---|---|---|
| `NewReportNotification` | Report submitted | All admins and superadmins |
| `StatusChangedNotification` | Admin changes report status | Whistleblower (if not anonymous) |
| `NewMessageNotification` | Admin sends a message | Whistleblower (login prompt only — message body not included for privacy) |
| `DailyUnreadDigestNotification` | Daily scheduled job | All admins with unread whistleblower messages |
| `ResetPasswordNotification` | Password reset requested | User (link expires in 60 minutes) |

---

## Background Jobs

### `CleanupInactiveAnonymousAccounts`
Scheduled to run periodically. Deletes anonymous user accounts that are:
- Inactive for **30+ days**, AND
- All associated reports are **closed**, AND
- All closed reports have been closed for **360+ days**

This prevents accidental deletion of accounts tied to active or recently closed cases.

### `SendDailyUnreadDigest`
Scheduled to run daily. Queries all open (non-closed) reports that have unread messages from the whistleblower side and sends a single digest email to each admin listing those reports.

---

## Installation & Setup

### Prerequisites
- PHP 8.2+
- Composer
- MySQL
- Node.js + npm (for Vite asset bundling)

### Steps

```bash
# 1. Clone the repository
git clone <repo-url>
cd hinweisgeberporal

# 2. Install PHP dependencies
composer install

# 3. Copy environment file and configure it
cp .env.example .env
# Edit .env with your database, mail, and hCaptcha credentials

# 4. Generate application key
php artisan key:generate

# 5. Run database migrations and seed the superadmin
php artisan migrate
php artisan db:seed --class=SuperAdminSeeder

# 6. Install Node dependencies and build assets
npm install
npm run build

# 7. Start the development server
php artisan serve
```

The API will be available at `http://127.0.0.1:8000`.

---

## Environment Variables

```env
APP_NAME=Hinweisgeberportal
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://127.0.0.1:5500

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hinweisgeberporal
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="noreply@hinweisgeberporal.de"
MAIL_FROM_NAME="Hinweisgeberportal"

BCRYPT_ROUNDS=12

HCAPTCHA_SECRET=
HCAPTCHA_SITEKEY=

QUEUE_CONNECTION=database
SESSION_DRIVER=database
```

---

## Running Tests

```bash
# Run all tests
php artisan test

# Run with coverage report
php artisan test --coverage
```

Tests are located in `tests/Feature/` and `tests/Unit/` and use PHPUnit 11.

---

## License

This project is proprietary. All rights reserved.
