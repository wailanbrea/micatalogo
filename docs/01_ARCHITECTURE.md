# Architecture

The approved local architecture is a modular Laravel 12 monolith with Blade, Livewire
4, Tailwind 4, Vite, MariaDB 11.4, and Cloudflare R2 for final media.

- Laravel owns authentication, authorization, catalog logic, moderation, search,
  metrics, and WhatsApp links.
- Public storefronts operate under a **Single-Seller SaaS Isolation model**: each store
  has its own isolated catalog (`/tienda/{shop}`), internal scoped search, categories,
  and product detail views. No cross-seller links or recommendations exist in storefronts.
- `Route::scopeBindings()` guarantees that product URLs `/tienda/{shop}/producto/{product}`
  are cryptographically and relationally bound to that store.
- The public root (`/`) serves as a seller acquisition and conversion landing page.
- MariaDB stores structured data and media metadata during development and production.
- Cloudflare R2 stores logos, WebP product images, and thumbnails.
- Cloudflare provides DNS, TLS, WAF, CDN, and Turnstile.

No microservices, Redis, external search engine, or frontend SPA is part of the MVP.

Catalog limits, DOP currency, media retention, discovery status, and advertising flags
are centralized in config/catalog.php.

