# MiCatalogo

MiCatalogo is a public product showcase for independent sellers. Visitors discover
products and contact sellers through WhatsApp; the MVP has no cart, checkout, payments,
or order processing.

## Stack

- Laravel 12, PHP 8.2, MariaDB 11.4
- Blade, Livewire 4, Tailwind 4, Vite
- Cloudflare Turnstile, optional Cloudflare R2 media storage

## Local Setup

1. Copy `.env.example` to `.env` and configure the local database.
2. Install dependencies with `php .tools/composer/composer.phar install` and `npm install`.
3. Run `php artisan migrate --seed`.
4. Start the app with `php artisan serve` and assets with `npm run dev`.

## Verification

```powershell
php artisan test
php .\vendor\bin\pint --test
npm run build
php .tools\composer\composer.phar audit
```

## Media Storage

Local development falls back to the `public` disk when R2 credentials are absent. For
production, configure `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`,
`R2_ENDPOINT`, and a custom `R2_URL`. Do not use an `r2.dev` domain in production.

See `docs/10_PROJECT_STATE.md` for the verified state and `docs/07_DEPLOYMENT.md` for
the release checklist.
