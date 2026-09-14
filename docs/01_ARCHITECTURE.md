# Architecture

The approved local architecture is a modular Laravel 12 monolith with Blade, Livewire
4, Tailwind 4, Vite, MariaDB 11.4, and Cloudflare R2 for final media.

- Laravel owns authentication, authorization, catalog logic, moderation, search,
  metrics, and WhatsApp links.
- MariaDB stores structured data and media metadata only during local development.
- Cloudflare R2 stores logos, WebP product images, and thumbnails.
- Cloudflare provides DNS, TLS, WAF, CDN, and Turnstile.

No microservices, Redis, external search engine, or frontend SPA is part of the MVP.

Catalog limits, DOP currency, media retention, and advertising flags are centralized
in config/catalog.php.
