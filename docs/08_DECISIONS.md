# Architecture Decisions

## 2026-09-13 - Original platform version targets

The original specification targets PHP 8.4 and MySQL 8.4. The shared XAMPP
installation serves other projects, so shared runtimes must not be replaced.

## 2026-09-13 - Local Laravel 12 exception

The user explicitly approved Laravel 12 on the available PHP 8.2.33 runtime. This
supersedes the Laravel 13/PHP 8.4 requirement for local development. Any production
version decision must be documented before deployment.

## 2026-09-13 - Local MariaDB exception

The user explicitly approved MariaDB 11.4.12 for local development. The application
uses the micatalogo database and a dedicated account restricted to that database.

## 2026-09-13 - Do not bootstrap with the installed Composer

Composer 2.8.11 reports known security advisories. Install dependencies only after a
current Composer executable is available; do not alter shared tooling without approval.

Composer 2.10.3 is installed locally at .tools/composer/composer.phar after official
installer signature verification. The shared Composer installation remains unchanged.

## 2026-09-13 - Compatible Pest version

Pest 3.8 and pest-plugin-laravel 3.2 are used because current Pest 5 requires PHP 8.4
and Laravel 13. This is aligned with the approved Laravel 12/PHP 8.2 local exception.

## 2026-09-13 - Fortify authentication foundation

The current official Livewire starter kit requires Laravel 13 and PHP 8.3, so it cannot
be used under the approved local exception. Laravel Fortify 1.39 provides compatible,
official authentication routes and actions; MiCatalogo supplies its own Blade views.
Only registration, password reset, and email verification are enabled for the MVP.

## 2026-09-13 - Turnstile server validation

The user created the Catalogo Web Turnstile widget. Login and registration use its
public Site Key only when TURNSTILE_ENABLED is true. Server validation calls Siteverify
with the private key from .env and rejects missing, invalid, or action-mismatched tokens.
