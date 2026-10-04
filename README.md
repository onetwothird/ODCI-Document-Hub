# ODCI Document Hub

**Office of the Director for Curriculum and Instruction — Document Management System**

A role-based document management and collaboration platform built for CvSU Naic Campus. Designed to streamline academic document submissions, track compliance, and foster departmental communication through an integrated social feed.

---

## 🏗️ Architecture Overview

```
ODCI Document Hub
├── Authentication & Authorization
│   ├── Multi-role access control (Super Admin, Admin, User)
│   ├── Session management with rate limiting & lockout protection
│   ├── Google OAuth 2.0 integration
│   └── Password reset via secure email tokens
│
├── Document Management
│   ├── Hierarchical folder structure (Department → Category → Semester)
│   ├── File upload with validation, thumbnails, and versioning
│   ├── Academic period tracking (Year/Semester)
│   ├── Submission tracker with compliance reporting
│   └── Download audit trail
│
├── Social Collaboration
│   ├── Department-scoped social feed
│   ├── Posts, comments, reactions, mentions
│   ├── Real-time notifications
│   └── Visibility controls (public, department, private)
│
├── Administration
│   ├── User lifecycle (registration → approval → activation)
│   ├── Department & user management
│   ├── Activity logging & audit trails
│   └── System settings & configuration
│
└── Infrastructure
    ├── Self-healing database schema (auto-migration on bootstrap)
    ├── Environment-based configuration (.env)
    ├── SMTP / MailHog / Log mail drivers
    └── PSR-4 autoloading via Composer
```

---

## 🚀 Quick Start

### Prerequisites

- **PHP** ≥ 8.1 (tested on 8.4)
- **MariaDB/MySQL** ≥ 10.4
- **Composer** ≥ 2.0
- Web server (Apache/Nginx) or PHP built-in server

### Installation

```bash
# 1. Clone the repository
git clone https://github.com/onetwothird/ODCI-Document-Hub.git
cd ODCI-Document-Hub

# 2. Install PHP dependencies
composer install --no-dev --optimize-autoloader

# 3. Configure environment
cp .env.example .env
# Edit .env with your database, mail, and OAuth credentials

# 4. Create database and import schema
mysql -u root -p < database/odci_db.sql

# 5. Set permissions (Linux/macOS)
chmod -R 775 uploads/ storage/logs/

# 6. Serve the application
# Option A: PHP built-in server (development)
php -S localhost:8000 -t .

# Option B: Configure Apache/Nginx document root to project root
```

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_ENV` | Application environment (`local`, `production`) | `local` |
| `APP_DEBUG` | Enable debug output | `true` |
| `APP_URL` | Base URL of the application | `http://localhost/ODCI` |
| `DB_HOST` | Database host | `localhost` |
| `DB_USER` | Database username | `root` |
| `DB_PASS` | Database password | *(empty)* |
| `DB_NAME` | Database name | `odci_db` |
| `ODCI_MAIL_DRIVER` | Mail driver: `smtp`, `mail`, `sendmail`, `log` | `log` |
| `ODCI_SMTP_HOST` | SMTP server hostname | `localhost` |
| `ODCI_SMTP_PORT` | SMTP port | `587` |
| `ODCI_SMTP_USERNAME` | SMTP username | *(empty)* |
| `ODCI_SMTP_PASSWORD` | SMTP password / app password | *(empty)* |
| `ODCI_SMTP_ENCRYPTION` | Encryption: `tls`, `ssl`, or empty | `tls` |
| `ODCI_MAIL_FROM_ADDRESS` | Default from address | `noreply@cvsu.edu.ph` |
| `ODCI_MAIL_FROM_NAME` | Default from name | `ODCI Document Hub` |
| `GOOGLE_CLIENT_ID` | Google OAuth client ID | *(optional)* |
| `GOOGLE_CLIENT_SECRET` | Google OAuth client secret | *(optional)* |
| `GOOGLE_REDIRECT_URI` | OAuth callback URL | `http://localhost/ODCI/login/script/google_callback.php` |

> **Security Note:** Never commit `.env` to version control. Use `.env.example` as a template.

---

## 👥 Role-Based Access

| Role | Capabilities |
|------|--------------|
| **Super Admin** | System-wide administration: user/department management, activity logs, global settings, all documents |
| **Admin** | Department-scoped: manage faculty, track submissions, moderate social feed, view department documents |
| **User (Faculty)** | Submit documents, manage personal files, participate in social feed, view announcements |

---

## 📁 Project Structure

```
ODCI/
├── assets/                    # Shared CSS, JS, images
├── database/                  # SQL schema, migrations, seed data
├── document_tracker/          # Document messaging & session endpoints
├── includes/                  # Core bootstrap: config, auth, mailer, schema
├── login/                     # Authentication pages & scripts
│   ├── script/               # Login, register, forgot/reset password, OAuth
│   └── img/                  # Login page assets
├── roles/                     # Role-specific applications
│   ├── admin/                # Department admin dashboard
│   ├── superadmin/           # System admin dashboard
│   └── user/                 # Faculty portal (dashboard, folders, submissions)
├── social_feed/              # Social feed feature (API, managers, UI)
├── uploads/                  # User uploads (gitignored)
├── storage/logs/             # Application logs (gitignored)
├── vendor/                   # Composer dependencies (gitignored)
├── .env.example              # Environment template
├── .gitignore                # Git ignore rules
├── composer.json             # PHP dependencies
└── index.php                 # Entry point (routes to role dashboards)
```

---

## 🔧 Key Technical Decisions

### Self-Healing Schema (`includes/schema.php`)
The application defines required columns in code. On every bootstrap, it checks `information_schema` and adds missing columns automatically. A marker file (`/tmp/odci_schema_checked`) caches the result for 5 minutes to avoid per-request overhead.

### Password Reset Security
- Tokens are **SHA-256 hashed** before storage
- Raw token sent via email; only hash persisted
- 1-hour expiry with automatic cleanup on email failure
- Constant-time comparison via `hash_equals()`

### Mail Abstraction (`includes/Mailer.php`)
Wrapper around **PHPMailer** supporting multiple drivers:
- `smtp` — Production (Gmail, Outlook, SendGrid, etc.)
- `log` — Development (writes to `storage/logs/mail.log`)
- `mail` — PHP `mail()` function
- HTML + plain-text multipart emails

### Session & Rate Limiting
- 8-hour session lifetime with sliding expiry
- 5 failed login attempts → 30-minute lockout
- CSRF tokens on all state-changing forms

---

## 🧪 Development Workflow

```bash
# Run with MailHog (catches emails locally)
# 1. Start MailHog: mailhog
# 2. Set in .env:
#    ODCI_MAIL_DRIVER=smtp
#    ODCI_SMTP_HOST=localhost
#    ODCI_SMTP_PORT=1025
#    ODCI_SMTP_ENCRYPTION=

# View caught emails at http://localhost:8025
```

### Database Migrations
```bash
# After pulling schema changes, clear the schema cache:
php -r "require 'includes/config.php'; odci_reset_schema_cache();"
# Next request will re-run schema repair
```

---

## 📦 Deployment Checklist

- [ ] Set `APP_ENV=production` and `APP_DEBUG=false`
- [ ] Configure production SMTP credentials
- [ ] Set strong `DB_PASS` and restrict database user privileges
- [ ] Enable HTTPS and set `APP_URL` to production domain
- [ ] Configure Google OAuth authorized redirect URIs
- [ ] Set up automated database backups
- [ ] Configure log rotation for `storage/logs/`
- [ ] Run `composer install --no-dev --optimize-autoloader`
- [ ] Verify file upload permissions (`www-data` ownership)

---

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feat/your-feature`
3. Follow conventional commits: `feat:`, `fix:`, `chore:`, `docs:`, `refactor:`
4. Ensure code passes linting and tests
5. Submit a pull request with a clear description

---

## 📄 License

This project is proprietary software developed for **Cavite State University - Naic Campus**. All rights reserved.

---

## 👨‍💻 Maintainers

| Role | Name | Contact |
|------|------|---------|
| **Senior Software Engineer** | ANGELITO P. DECATORIA III | nc.angelitoiii.decatoria@cvsu.edu.ph |
| **System Administrator** | ITD Department | itd@cvsu.edu.ph |

---

> **Built with** PHP, MariaDB, vanilla JS/CSS, and ❤️ for academic excellence at CvSU Naic.
