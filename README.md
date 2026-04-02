# Hinweisgeberportal — Backend API

[🇩🇪 Deutsch](#-deutsch) | [🇬🇧 English](#-english)

---

## 🇩🇪 Deutsch

Eine sichere, datenschutzorientierte Whistleblower-Meldeplattform, entwickelt mit **Laravel 12**, konzipiert zur Einhaltung des deutschen Hinweisgeberschutzgesetzes (**HinSchG**) und der **DSGVO**.

---

### Inhaltsverzeichnis

- [Übersicht](#übersicht)
- [Technologie-Stack](#technologie-stack)
- [Funktionen](#funktionen)
- [Sprachunterstützung](#sprachunterstützung)
- [Architektur](#architektur)
- [Datenbankschema](#datenbankschema)
- [API-Referenz](#api-referenz)
- [Authentifizierung](#authentifizierung)
- [Rollen & Berechtigungen](#rollen--berechtigungen)
- [Sicherheit](#sicherheit)
- [E-Mail-Benachrichtigungen](#e-mail-benachrichtigungen)
- [Hintergrundjobs](#hintergrundjobs)
- [Installation & Einrichtung](#installation--einrichtung)
- [Umgebungsvariablen](#umgebungsvariablen)
- [Tests ausführen](#tests-ausführen)

---

### Übersicht

Dies ist die Backend-REST-API für das Hinweisgeberportal. Sie betreibt ein vollständiges System zur Meldungseinreichung und Fallverwaltung, in dem:

- **Hinweisgeber** Meldungen anonym (ohne Konto) oder mit einem registrierten Konto einreichen können
- **Administratoren** eingereichte Meldungen einsehen, verwalten und kommentieren können
- **SuperAdministratoren** vollständige Kontrolle über Admins, Portalkonfiguration und Identitätsentschlüsselung haben

Das System unterstützt einen vollständigen Lebenszyklus: Meldungseinreichung → Admin-Überprüfung → Zweiwege-Kommunikation → Dateiaustausch → Fallabschluss.

---

### Technologie-Stack

| Schicht | Technologie |
|---|---|
| Sprache | PHP 8.2+ |
| Framework | Laravel 12 |
| Authentifizierung | Laravel Sanctum (zustandslose Bearer-Token) |
| Datenbank | MySQL oder PostgreSQL |
| E-Mail | SMTP (konfigurierbar über `.env`) |
| Warteschlange | Laravel Queue (Datenbanktreiber) |
| Tests | PHPUnit 11 |
| Code-Stil | Laravel Pint |

---

### Funktionen

#### Hinweisgeber-Funktionen
- **Anonyme Meldung** — keine E-Mail oder Konto erforderlich; System generiert UUID-Token + 6-stellige PIN, einmalig angezeigt
- **Registrierte Meldung** — vollständiges Konto mit E-Mail-Verifizierung
- **Meldungsverfolgung** — jederzeit einloggen, um Statusaktualisierungen zu prüfen
- **Zweiwege-Kommunikation** — direkt im Meldungsthread mit dem Fallbearbeiter kommunizieren
- **Dateianhänge** — unterstützende Dokumente hochladen (PDF, DOCX, Bilder, Audio, Video)
- **Referenznummern** — jede Meldung erhält eine eindeutige `HIN-JJJJ-XXXX`-Referenz

#### Administrator-Funktionen
- Alle Meldungen nach Status und Kategorie anzeigen und filtern
- Meldungsstatus aktualisieren (`eingegangen → in Bearbeitung → Klärungsbedarf → abgeschlossen`)
- Nachrichten an Hinweisgeber innerhalb eines Meldungsthreads senden
- Meldungsanhänge einsehen und herunterladen
- Tägliche E-Mail-Zusammenfassung ungelesener Hinweisgeber-Nachrichten erhalten

#### SuperAdministrator-Funktionen
- Admin-Konten erstellen, deaktivieren, reaktivieren und löschen
- Admin-Passwörter ändern
- Hinweisgeberidentität entsperren (entschlüsselt E-Mail für rechtliche/justizielle Nutzung — immer im Audit-Log erfasst)
- Portalweite Einstellungen konfigurieren (Rate-Limits, Dateigrößenbeschränkungen, Upload-Kontingente)

---

### Sprachunterstützung

Das Portal unterstützt **Deutsch (Standard)** und **Englisch**.

| Aspekt | Detail |
|---|---|
| Standardsprache | Deutsch (`de`) — alle UI-Zeichenketten, Fehlermeldungen und E-Mail-Benachrichtigungen sind auf Deutsch |
| Zweitsprache | Englisch (`en`) — vollständige Übersetzung über den Frontend-Sprachumschalter verfügbar |
| Sprachauswahl | In `localStorage` gespeichert (`lang: "de"` oder `"en"`); bleibt sitzungsübergreifend erhalten |
| Übersetzungsdateien | `js/lang/de.js` (Deutsch) und `js/lang/en.js` (Englisch) im Frontend |
| Backend | API-Antworten sind sprachunabhängig (nur JSON-Daten); alle nutzersichtigen Texte werden vom Frontend gerendert |
| E-Mail-Benachrichtigungen | Werden auf Deutsch versendet; Sprache wird durch die Standard-Locale-Einstellung des Portals bestimmt |

---

### Architektur

```
app/
├── Http/
│   ├── Controllers/Api/
│   │   ├── AuthController.php            # Registrierung, Login, anonymer Login, Passwort-Reset
│   │   ├── ReportController.php          # Meldungen einreichen und abrufen
│   │   ├── MessageController.php         # Hinweisgeber-seitige Nachrichten
│   │   ├── AttachmentController.php      # Datei-Upload und -Download
│   │   ├── AdminController.php           # Admin-Meldungsverwaltung und Nachrichten
│   │   ├── SuperAdminController.php      # Admin-Benutzerverwaltung und Identitätsentschlüsselung
│   │   └── SuperAdminSettingsController.php  # Portalkonfiguration
│   └── Middleware/
│       ├── EnsureUserIsAdmin.php         # Erfordert Rolle: admin oder superadmin
│       └── EnsureUserIsSuperAdmin.php    # Erfordert Rolle: superadmin
├── Models/
│   ├── User.php                          # Unterstützt registrierte + anonyme Nutzer
│   ├── Report.php                        # Kern-Meldungsentität
│   ├── Message.php                       # Meldungsthread-Nachrichten
│   ├── Attachment.php                    # Datei-Metadaten
│   ├── AuditLog.php                      # Unveränderliches Aktionsprotokoll
│   └── PortalSetting.php                 # Schlüssel-Wert-Konfigurationsspeicher
├── Services/
│   ├── ReportService.php                 # Referenzgenerierung, anonyme Nutzererstellung, Audit-Logging
│   └── PortalSettings.php                # Einstellungsabruf mit Standardwerten
├── Jobs/
│   ├── CleanupInactiveAnonymousAccounts.php  # Geplant: veraltete anonyme Nutzer entfernen
│   └── SendDailyUnreadDigest.php             # Geplant: tägliche Zusammenfassung ungelesener Nachrichten
└── Notifications/
    ├── NewReportNotification.php
    ├── StatusChangedNotification.php
    ├── NewMessageNotification.php
    ├── DailyUnreadDigestNotification.php
    └── ResetPasswordNotification.php
```

---

### Datenbankschema

#### `users`

| Spalte | Typ | Hinweise |
|---|---|---|
| `id` | UUID | Primärschlüssel |
| `email` | string (verschlüsselt) | AES-256 verschlüsselt im Ruhezustand; für anonyme Nutzer nullable |
| `email_hash` | string | SHA-256 der Kleinbuchstaben-E-Mail; für Lookups verwendet |
| `password` | string | bcrypt, 12 Runden |
| `role` | enum | `user`, `admin`, `superadmin`, `deactivated_admin` |
| `is_anonymous` | boolean | True für anonyme Hinweisgeber |
| `anon_token` | UUID | Anonymer Login-Identifikator (einmalig dem Nutzer angezeigt) |
| `anon_pin_hash` | string | bcrypt-gehashte 6-stellige PIN |
| `last_active_at` | timestamp | Vom Bereinigungsjob verwendet |
| `email_verified_at` | timestamp | Muss nicht null sein, um auf das Portal zuzugreifen |

#### `reports`

| Spalte | Typ | Hinweise |
|---|---|---|
| `id` | UUID | Primärschlüssel |
| `user_id` | UUID | FK → users |
| `reference_number` | string | Eindeutig, Format `HIN-JJJJ-XXXX` |
| `category` | enum | `fraud`, `harassment`, `safety`, `discrimination`, `other` |
| `status` | enum | `received`, `reviewing`, `clarification`, `closed` |
| `subject` | string | Max. 255 Zeichen |
| `description` | text | Vollständiger Meldungstext |
| `incident_date` | date | Optional |
| `incident_location` | string | Optional |
| `involved_persons` | text | Optional |
| `is_anonymous` | boolean | |
| `closed_at` | timestamp | Gesetzt wenn Status `closed` wird |

#### `messages`

| Spalte | Typ | Hinweise |
|---|---|---|
| `id` | UUID | Primärschlüssel |
| `report_id` | UUID | FK → reports |
| `sender_id` | UUID | FK → users |
| `sender_role` | enum | `whistleblower`, `admin` |
| `body` | text | |
| `read_at` | timestamp | NULL = ungelesen |

#### `attachments`

| Spalte | Typ | Hinweise |
|---|---|---|
| `id` | UUID | Primärschlüssel |
| `report_id` | UUID | FK → reports |
| `original_filename` | string | Dem Nutzer angezeigt |
| `stored_filename` | string | UUID-basierter Name auf dem Datenträger (vor API verborgen) |
| `mime_type` | string | Beim Upload validiert |
| `size` | bigint | Bytes |

#### `audit_logs`

| Spalte | Typ | Hinweise |
|---|---|---|
| `id` | UUID | Primärschlüssel |
| `report_id` | UUID | FK → reports (nullable) |
| `actor_id` | UUID | FK → users (nullable für Systemaktionen) |
| `action` | string | z.B. `report_submitted`, `status_changed`, `identity_unlocked` |
| `old_value` | JSON | Vorheriger Zustand (nullable) |
| `new_value` | JSON | Neuer Zustand (nullable) |
| `ip_address` | string | Gespeichert, aber in allen API-Antworten verborgen |

#### `portal_settings`

| Schlüssel | Standard | Beschreibung |
|---|---|---|
| `max_reports_per_hour_per_ip` | `5` | Rate-Limit für Meldungseinreichungen |
| `max_file_size_mb` | `10` | Maximale Größe pro hochgeladener Datei |
| `max_upload_per_week_mb` | `50` | Wöchentliches Upload-Kontingent pro Nutzer |

---

### API-Referenz

Alle Endpunkte haben das Präfix `/api`. JSON ist erforderlich (`Accept: application/json`).

#### Authentifizierung

| Methode | Endpunkt | Auth | Beschreibung |
|---|---|---|---|
| POST | `/auth/register` | Öffentlich | Registriertes Konto erstellen |
| POST | `/auth/login` | Öffentlich | Login mit E-Mail + Passwort |
| POST | `/auth/anonymous-login` | Öffentlich | Login mit `anon_token` + `pin` |
| POST | `/auth/forgot-password` | Öffentlich | Passwort-Reset-E-Mail anfordern |
| POST | `/auth/reset-password` | Öffentlich | Neues Passwort mit Reset-Token einreichen |
| POST | `/auth/logout` | Sanctum | Aktuelles Token ungültig machen |
| GET | `/auth/me` | Sanctum | Authentifizierten Nutzer abrufen |
| GET | `/email/verify/{id}/{hash}` | Signierte URL | E-Mail-Adresse verifizieren |
| POST | `/email/resend` | Sanctum | Verifizierungs-E-Mail erneut senden (Rate-Limit: 6/min) |

#### Hinweisgeber-Meldungen

| Methode | Endpunkt | Auth | Beschreibung |
|---|---|---|---|
| POST | `/reports` | Öffentlich + Rate-limitiert | Neue Meldung einreichen (5/Std/IP) |
| GET | `/reports` | Sanctum | Eigene Meldungen auflisten |
| GET | `/reports/{reference}` | Sanctum | Bestimmte Meldung anzeigen (nur Eigentümer) |

#### Nachrichten

| Methode | Endpunkt | Auth | Beschreibung |
|---|---|---|---|
| GET | `/reports/{reference}/messages` | Sanctum | Nachrichten einer Meldung abrufen |
| POST | `/reports/{reference}/messages` | Sanctum | Nachricht senden (bei abgeschlossenen Meldungen deaktiviert) |

#### Anhänge

| Methode | Endpunkt | Auth | Beschreibung |
|---|---|---|---|
| GET | `/reports/{reference}/attachments` | Sanctum | Anhänge auflisten |
| POST | `/reports/{reference}/attachments` | Sanctum | Datei hochladen |
| GET | `/attachments/{id}/download` | Sanctum | Datei herunterladen |

#### Administrator

> Erfordert Rolle: `admin` oder `superadmin`

| Methode | Endpunkt | Beschreibung |
|---|---|---|
| GET | `/admin/reports` | Alle Meldungen auflisten (Filter: `?status=` und `?category=`) |
| GET | `/admin/reports/{reference}` | Vollständige Meldung anzeigen |
| PATCH | `/admin/reports/{reference}/status` | Meldungsstatus aktualisieren |
| GET | `/admin/reports/{reference}/messages` | Meldungsthread anzeigen |
| POST | `/admin/reports/{reference}/messages` | Nachricht als Admin senden |
| GET | `/admin/reports/{reference}/attachments` | Anhänge anzeigen |

#### SuperAdministrator

> Erfordert Rolle: `superadmin`

| Methode | Endpunkt | Beschreibung |
|---|---|---|
| GET | `/superadmin/admins` | Alle Admin-Konten auflisten |
| POST | `/superadmin/admins` | Neuen Admin anlegen |
| PATCH | `/superadmin/admins/{id}/deactivate` | Admin deaktivieren |
| PATCH | `/superadmin/admins/{id}/reactivate` | Admin reaktivieren |
| DELETE | `/superadmin/admins/{id}` | Admin dauerhaft löschen |
| PATCH | `/superadmin/admins/{id}/password` | Admin-Passwort ändern |
| GET | `/superadmin/reports/{reference}/unlock-identity` | Hinweisgeberidentität entschlüsseln (immer im Audit-Log) |
| GET | `/superadmin/settings` | Portaleinstellungen anzeigen |
| PATCH | `/superadmin/settings` | Portaleinstellungen aktualisieren |

---

### Authentifizierung

Die API verwendet **Laravel Sanctum** für zustandslose, tokenbasierte Authentifizierung.

#### Registrierter Nutzer
1. `POST /auth/register` — Konto erstellt, Verifizierungs-E-Mail gesendet
2. Nutzer klickt auf signierten Verifizierungslink in der E-Mail
3. `POST /auth/login` — gibt Bearer-Token zurück
4. `Authorization: Bearer {token}` bei allen nachfolgenden Anfragen angeben

#### Anonymer Nutzer
1. `POST /reports` — Meldung ohne Konto einreichen
2. Antwort enthält `anon_token` (UUID) und `pin` (6-stellig) — **einmalig angezeigt, sorgfältig speichern**
3. `POST /auth/anonymous-login` mit `{ anon_token, pin }` — gibt Bearer-Token zurück
4. Ab hier gleicher API-Ablauf wie für registrierte Nutzer

#### E-Mail-Verschlüsselung & Lookup
Nutzer-E-Mails werden AES-256-verschlüsselt gespeichert. Ein SHA-256-Hash der Kleinbuchstaben-E-Mail wird separat für Login-Lookups gespeichert. Selbst ein vollständiger Datenbankdump gibt keine Klartextadressen preis.

---

### Rollen & Berechtigungen

| Rolle | Kann |
|---|---|
| `user` | Meldungen einreichen, eigene Meldungen ansehen, Admins schreiben, Anhänge hochladen |
| `admin` | Alles oben + alle Meldungen ansehen, Status ändern, Hinweisgebern schreiben |
| `superadmin` | Alles oben + Admins verwalten, Identitäten entsperren, Portal konfigurieren |
| `deactivated_admin` | Kann sich nicht einloggen (durch Middleware gesperrt) |

---

### Sicherheit

| Bereich | Umsetzung |
|---|---|
| Bot-Schutz | hCaptcha-Verifizierung bei anonymer Meldungseinreichung |
| Rate-Limiting | 5 Meldungen/Std/IP; 6 E-Mail-Wiederholungen/min (konfigurierbar) |
| E-Mail-Speicherung | AES-256-Verschlüsselung; SHA-256-Hash für Lookups |
| Passwort-Hashing | bcrypt, 12 Runden |
| Anonyme PIN | bcrypt-gehashte 6-stellige Code; einmalig angezeigt, nicht wiederherstellbar |
| Dateispeicherung | UUID-Dateinamen, außerhalb des Web-Roots gespeichert (`storage/app/attachments/`) |
| Dateivalidierung | MIME-Typ-Whitelist + Einzel- und wöchentliche Größenlimits |
| Zugriffskontrolle | Rollenbasierte Middleware + Meldungsbesitz-Durchsetzung |
| Identitätsentschlüsselung | Nur SuperAdmin; erstellt unveränderlichen Audit-Log-Eintrag mit IP |
| Audit-Logging | Jede sensible Aktion protokolliert: Wer, Was, Wann, Alt/Neu-Wert, IP |
| SQL-Injection | Eloquent ORM mit parametrisierten Abfragen überall |
| Timing-Angriffe | `hash_equals()` für E-Mail-Hash-Vergleiche verwendet |
| Sitzungssicherheit | Sanctum-zustandslose Token; keine Sitzungs-Cookies für API |

#### Erlaubte Datei-MIME-Typen

| Typ | Formate |
|---|---|
| Dokumente | `application/pdf`, `application/msword`, `application/vnd.openxmlformats-officedocument.wordprocessingml.document` |
| Bilder | `image/jpeg`, `image/png`, `image/gif` |
| Video | `video/mp4`, `video/mpeg` |
| Audio | `audio/mpeg`, `audio/wav`, `audio/ogg` |

---

### E-Mail-Benachrichtigungen

| Benachrichtigung | Auslöser | Empfänger |
|---|---|---|
| `NewReportNotification` | Meldung eingereicht | Alle Admins und SuperAdmins |
| `StatusChangedNotification` | Admin ändert Meldungsstatus | Hinweisgeber (wenn nicht anonym) |
| `NewMessageNotification` | Admin sendet Nachricht | Hinweisgeber (nur Login-Hinweis — kein Nachrichteninhalt aus Datenschutzgründen) |
| `DailyUnreadDigestNotification` | Täglicher geplanter Job | Alle Admins mit ungelesenen Hinweisgeber-Nachrichten |
| `ResetPasswordNotification` | Passwort-Reset angefordert | Nutzer (Link läuft nach 60 Minuten ab) |

---

### Hintergrundjobs

#### `CleanupInactiveAnonymousAccounts`
Läuft periodisch. Löscht anonyme Nutzerkonten, die:
- Seit **30+ Tagen** inaktiv sind, UND
- Alle zugehörigen Meldungen **abgeschlossen** sind, UND
- Alle abgeschlossenen Meldungen seit **360+ Tagen** abgeschlossen sind

Dies verhindert das versehentliche Löschen von Konten, die mit aktiven oder kürzlich abgeschlossenen Fällen verknüpft sind.

#### `SendDailyUnreadDigest`
Läuft täglich. Fragt alle offenen (nicht abgeschlossenen) Meldungen ab, die ungelesene Nachrichten von Hinweisgebern haben, und sendet eine Zusammenfassungs-E-Mail an jeden Admin mit diesen Meldungen.

---

### Installation & Einrichtung

#### Voraussetzungen
- PHP 8.2+
- Composer
- MySQL 8+ oder PostgreSQL 14+

#### Schritte

```bash
# 1. Repository klonen
git clone <repo-url>
cd hinweisgeberporal

# 2. PHP-Abhängigkeiten installieren
composer install

# 3. Umgebungsdatei kopieren und konfigurieren
cp .env.example .env
# .env mit Datenbank-, Mail- und hCaptcha-Zugangsdaten bearbeiten

# 4. Anwendungsschlüssel generieren
php artisan key:generate

# 5. Datenbankmigrationen ausführen und SuperAdmin anlegen
php artisan migrate
php artisan db:seed --class=SuperAdminSeeder

# 6. Entwicklungsserver starten
php artisan serve
```

Die API ist unter `http://127.0.0.1:8000` verfügbar.

---

### Umgebungsvariablen

```env
APP_NAME=Hinweisgeberportal
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://127.0.0.1:5500

# MySQL (Standard)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hinweisgeberporal
DB_USERNAME=root
DB_PASSWORD=

# PostgreSQL (Alternative — DB_CONNECTION und DB_PORT anpassen)
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=hinweisgeberporal
# DB_USERNAME=postgres
# DB_PASSWORD=

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

### Tests ausführen

```bash
# Alle Tests ausführen
php artisan test

# Mit Abdeckungsbericht ausführen
php artisan test --coverage
```

Tests befinden sich in `tests/Feature/` und `tests/Unit/` und verwenden PHPUnit 11.

---

### Lizenz

Dieses Projekt ist proprietär. Alle Rechte vorbehalten.

---

## 🇬🇧 English

A secure, privacy-first whistleblower reporting platform built with **Laravel 12**, designed to comply with the German Whistleblower Protection Act (**HinSchG**) and **GDPR (DSGVO)**.

---

### Table of Contents

- [Overview](#overview)
- [Tech Stack](#tech-stack)
- [Features](#features)
- [Language Support](#language-support)
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

### Overview

This is the backend REST API for the Hinweisgeberportal. It powers a complete whistleblower submission and case management system where:

- **Whistleblowers** can submit reports anonymously (no account required) or with a registered account
- **Admins** can view, manage, and communicate on submitted reports
- **SuperAdmins** have full control over admins, portal configuration, and identity unlocking

The system supports a full lifecycle: report submission → admin review → two-way messaging → file exchange → case closure.

---

### Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.2+ |
| Framework | Laravel 12 |
| Authentication | Laravel Sanctum (stateless Bearer tokens) |
| Database | MySQL or PostgreSQL |
| Mail | SMTP (configured via `.env`) |
| Queue | Laravel Queue (database driver) |
| Testing | PHPUnit 11 |
| Code Style | Laravel Pint |

---

### Features

#### Whistleblower Features
- **Anonymous submission** — no email or account required; system generates a UUID token + 6-digit PIN shown once
- **Registered submission** — full account with email verification
- **Report tracking** — log back in at any time to check status updates
- **Two-way messaging** — communicate with the case handler directly inside the report thread
- **File attachments** — upload supporting documents (PDF, DOCX, images, audio, video)
- **Reference numbers** — every report gets a unique `HIN-YYYY-XXXX` reference

#### Admin Features
- View and filter all reports by status and category
- Update report status (`received → reviewing → clarification → closed`)
- Send messages to whistleblowers within a report thread
- View and download report attachments
- Receive daily email digest of unread whistleblower messages

#### SuperAdmin Features
- Create, deactivate, reactivate, and delete admin accounts
- Change admin passwords
- Unlock whistleblower identity (decrypts email for legal/judicial use — always audit logged)
- Configure portal-wide settings (rate limits, file size caps, upload quotas)

---

### Language Support

The platform supports **German (default)** and **English**.

| Aspect | Detail |
|---|---|
| Default language | German (`de`) — all UI strings, error messages, and email notifications ship in German |
| Secondary language | English (`en`) — full translation available via the frontend language switcher |
| Language selection | Stored in `localStorage` (`lang: "de"` or `"en"`); persists across sessions |
| Translation files | `js/lang/de.js` (German) and `js/lang/en.js` (English) in the frontend |
| Backend | API responses are language-agnostic (JSON data only); all user-facing text is rendered by the frontend |
| Email notifications | Sent in German; language is determined by the portal's default locale setting |

---

### Architecture

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

### Database Schema

#### `users`

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

#### `reports`

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

#### `messages`

| Column | Type | Notes |
|---|---|---|
| `id` | UUID | Primary key |
| `report_id` | UUID | FK → reports |
| `sender_id` | UUID | FK → users |
| `sender_role` | enum | `whistleblower`, `admin` |
| `body` | text | |
| `read_at` | timestamp | NULL = unread |

#### `attachments`

| Column | Type | Notes |
|---|---|---|
| `id` | UUID | Primary key |
| `report_id` | UUID | FK → reports |
| `original_filename` | string | Displayed to user |
| `stored_filename` | string | UUID-based name on disk (hidden from API) |
| `mime_type` | string | Validated on upload |
| `size` | bigint | Bytes |

#### `audit_logs`

| Column | Type | Notes |
|---|---|---|
| `id` | UUID | Primary key |
| `report_id` | UUID | FK → reports (nullable) |
| `actor_id` | UUID | FK → users (nullable for system actions) |
| `action` | string | e.g. `report_submitted`, `status_changed`, `identity_unlocked` |
| `old_value` | JSON | Previous state (nullable) |
| `new_value` | JSON | New state (nullable) |
| `ip_address` | string | Stored but hidden from all API responses |

#### `portal_settings`

| Key | Default | Description |
|---|---|---|
| `max_reports_per_hour_per_ip` | `5` | Rate limit on report submissions |
| `max_file_size_mb` | `10` | Maximum size per uploaded file |
| `max_upload_per_week_mb` | `50` | Weekly upload quota per user |

---

### API Reference

All endpoints are prefixed with `/api`. JSON is required (`Accept: application/json`).

#### Authentication

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

#### Whistleblower Reports

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| POST | `/reports` | Public + Rate limited | Submit a new report (5/hr/IP) |
| GET | `/reports` | Sanctum | List own reports |
| GET | `/reports/{reference}` | Sanctum | View a specific report (owner only) |

#### Messaging

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/reports/{reference}/messages` | Sanctum | Get messages for a report |
| POST | `/reports/{reference}/messages` | Sanctum | Send a message (disabled on closed reports) |

#### Attachments

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/reports/{reference}/attachments` | Sanctum | List attachments |
| POST | `/reports/{reference}/attachments` | Sanctum | Upload a file |
| GET | `/attachments/{id}/download` | Sanctum | Download a file |

#### Admin

> Requires role: `admin` or `superadmin`

| Method | Endpoint | Description |
|---|---|---|
| GET | `/admin/reports` | List all reports (filter by `?status=` and `?category=`) |
| GET | `/admin/reports/{reference}` | View full report |
| PATCH | `/admin/reports/{reference}/status` | Update report status |
| GET | `/admin/reports/{reference}/messages` | View report thread |
| POST | `/admin/reports/{reference}/messages` | Send message as admin |
| GET | `/admin/reports/{reference}/attachments` | View attachments |

#### SuperAdmin

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

### Authentication

The API uses **Laravel Sanctum** for stateless, token-based authentication.

#### Registered User Flow
1. `POST /auth/register` — account created, verification email sent
2. User clicks signed verification link in email
3. `POST /auth/login` — returns Bearer token
4. Include `Authorization: Bearer {token}` on all subsequent requests

#### Anonymous User Flow
1. `POST /reports` — submit a report without an account
2. Response includes `anon_token` (UUID) and `pin` (6-digit) — **shown once, store them**
3. `POST /auth/anonymous-login` with `{ anon_token, pin }` — returns Bearer token
4. Same API flow as registered users from this point

#### Email Encryption & Lookup
User emails are stored AES-256 encrypted. A SHA-256 hash of the lowercase email is stored separately and used for login lookups. This means even a full database dump does not expose plaintext email addresses.

---

### Roles & Permissions

| Role | Can Do |
|---|---|
| `user` | Submit reports, view own reports, message admins, upload attachments |
| `admin` | Everything above + view all reports, change status, message whistleblowers |
| `superadmin` | Everything above + manage admins, unlock identities, configure portal |
| `deactivated_admin` | Cannot log in (blocked by middleware) |

---

### Security

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

#### Allowed File MIME Types

| Type | Formats |
|---|---|
| Documents | `application/pdf`, `application/msword`, `application/vnd.openxmlformats-officedocument.wordprocessingml.document` |
| Images | `image/jpeg`, `image/png`, `image/gif` |
| Video | `video/mp4`, `video/mpeg` |
| Audio | `audio/mpeg`, `audio/wav`, `audio/ogg` |

---

### Email Notifications

| Notification | Trigger | Recipient |
|---|---|---|
| `NewReportNotification` | Report submitted | All admins and superadmins |
| `StatusChangedNotification` | Admin changes report status | Whistleblower (if not anonymous) |
| `NewMessageNotification` | Admin sends a message | Whistleblower (login prompt only — message body not included for privacy) |
| `DailyUnreadDigestNotification` | Daily scheduled job | All admins with unread whistleblower messages |
| `ResetPasswordNotification` | Password reset requested | User (link expires in 60 minutes) |

---

### Background Jobs

#### `CleanupInactiveAnonymousAccounts`
Scheduled to run periodically. Deletes anonymous user accounts that are:
- Inactive for **30+ days**, AND
- All associated reports are **closed**, AND
- All closed reports have been closed for **360+ days**

This prevents accidental deletion of accounts tied to active or recently closed cases.

#### `SendDailyUnreadDigest`
Scheduled to run daily. Queries all open (non-closed) reports that have unread messages from the whistleblower side and sends a single digest email to each admin listing those reports.

---

### Installation & Setup

#### Prerequisites
- PHP 8.2+
- Composer
- MySQL 8+ or PostgreSQL 14+

#### Steps

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

# 6. Start the development server
php artisan serve
```

The API will be available at `http://127.0.0.1:8000`.

---

### Environment Variables

```env
APP_NAME=Hinweisgeberportal
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://127.0.0.1:5500

# MySQL (default)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hinweisgeberporal
DB_USERNAME=root
DB_PASSWORD=

# PostgreSQL (alternative — change DB_CONNECTION and DB_PORT)
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=hinweisgeberporal
# DB_USERNAME=postgres
# DB_PASSWORD=

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

### Running Tests

```bash
# Run all tests
php artisan test

# Run with coverage report
php artisan test --coverage
```

Tests are located in `tests/Feature/` and `tests/Unit/` and use PHPUnit 11.

---

### License

This project is proprietary. All rights reserved.
