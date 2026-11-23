# CLAUDE.md — Eco Metrics Project Rules & Architecture

## Architecture & Technology Stack
- **Pattern**: Custom modular MVC (Model-View-Controller) inspired architecture.
- **Language**: PHP 8.x
- **Database Access**: PDO (PHP Data Objects) exclusively. **No MySQLi**.
- **Framework Restriction**: **No Laravel or other full-stack frameworks**. Vanilla PHP with clean object-oriented design and Composer autoloading.
- **Frontend**: Bootstrap 5, HTML5, CSS3, JavaScript, jQuery.
- **Server Environment**: Laragon, Apache, `public/` folder as document root.

## Directory Structure
- `app/`
  - `controllers/`: Request handlers and page logic.
  - `models/`: Data access objects and business logic entities using PDO.
  - `services/`: Reusable domain services.
  - `middleware/`: HTTP request filters (auth, logging, etc.).
  - `helpers/`: Utility functions and classes.
  - `views/`: Layouts, partials, and view templates.
- `config/`: Configuration files (database, app settings).
- `database/`: Migrations and seeders.
- `public/`: Web document root (`index.php`, CSS, JS, images).
- `routes/`: Web and API routing definitions.
- `storage/`: Logs, cache, and file uploads.
- `vendor/`: Composer dependencies.

## Coding Standards
- Strict type hinting (`declare(strict_types=1);`) in PHP files where appropriate.
- PSR-4 autoloading via Composer namespace `App\`.
- Use prepared statements with PDO for all database queries to prevent SQL injection.
- Secure session handling, CSRF protection, and output escaping against XSS.
