# API and Routes

The MVP is server-rendered with Blade and Livewire. Public route groups will cover the
home page, category browsing, shops, products, search, reporting, and WhatsApp click
tracking. Authenticated seller and admin routes will be defined after Laravel bootstrap.

No public REST API, GraphQL API, or WhatsApp Business API is planned for the MVP.

Verified seller routes:

- `GET /panel`: seller dashboard with owned shops only.
- `GET /panel/tiendas/crear` and `POST /panel/tiendas`: create a shop.
- `GET /panel/tiendas/{shop}/editar`, `PUT /panel/tiendas/{shop}`, and
  `DELETE /panel/tiendas/{shop}`: edit or soft-delete an owned shop.

Shop route binding uses its ULID `public_id`; update and deletion routes enforce the
corresponding Laravel policy through `can` middleware.
