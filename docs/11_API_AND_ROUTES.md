# API and Routes

The MVP is server-rendered with Blade and Livewire. Public routes operate under a
**Single-Seller Storefront Isolation model**:

Public routes:

- `GET /`: Platform landing page for seller acquisition (benefits, isolation guarantee, CTA).
- `GET /tienda/{shop:slug}`: Isolated storefront. Internal scoped search (`?q=...`) and store category filtering (`?categoria=...`).
- `GET /tienda/{shop:slug}/producto/{product:slug}`: Product detail view. Strictly scoped to `{shop}` via `Route::scopeBindings()`. Returns 404 on any shop mismatch.
- `GET /r/wa/tienda/{shop:slug}/producto/{product:slug}`: WhatsApp click tracking scoped to `{shop}`.
- `GET /r/wa/tienda/{shop:slug}`: Shop WhatsApp click tracking.
- `POST /reportar`: Content moderation reporting.
- `GET /sitemap.xml`: XML sitemap with home, active shops, and active products.

No public REST API, GraphQL API, or WhatsApp Business API is planned for the MVP.

Verified seller routes:

- `GET /panel`: seller dashboard with owned shops only.
- `GET /panel/tiendas/crear` and `POST /panel/tiendas`: create a shop.
- `GET /panel/tiendas/{shop}/editar`, `PUT /panel/tiendas/{shop}`, and
  `DELETE /panel/tiendas/{shop}`: edit or soft-delete an owned shop.
- `GET /panel/tiendas/{shop}/productos`: list owned shop products.
- `GET /panel/tiendas/{shop}/categorias`: manage store categories.
- `GET /panel/tiendas/{shop}/metricas`: shop performance dashboard.
- `GET /panel/tiendas/{shop}/inventario`: inventory and stock management.

Shop route binding uses its slug for public storefronts and ULID `public_id` for
seller/admin operations; update and deletion routes enforce policies through `can` middleware.

