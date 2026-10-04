# Business Rules

- A free account may have at most one active shop.
- A shop slug is globally unique; a product slug is unique within its shop.
- Plan limits are 250 products and 1 image per product for Gratis, 500 products and
  3 images per product for Básico, and 1,500 products and 3 images per product for Pro.
  Gratis and Básico allow 1 active shop; Pro allows 3 active shops managed from the
  seller's unified panel.
- Included access is 1 user and 1 seller for Gratis, 3 users and 3 sellers for Básico,
  and 5 users and 5 sellers for Pro. Additional users or sellers cost US$5/month.
- Upload limits are 10 MB per file and 30 files per bulk upload batch.
- Product availability is available or out_of_stock.
- Product moderation is draft, active, pending_review, or suspended.
- Suspended content is never public.
- The conversion action is an official wa.me link with a product-specific message.
- Account anti-abuse limits are three registrations and five password-reset requests
  per IP address every 15 minutes.
- Shops and products expose ULIDs publicly while retaining internal bigint primary keys.
- Every account has a persisted `seller` or `admin` role. Sellers can only manage
  resources that belong to their shops; administrators can manage catalog-wide resources.
- Shop contact details are normalized to numeric country code and WhatsApp number before
  persistence. Instagram handles are stored without a leading `@`.
- When a requested shop slug collides, the system deterministically appends a numeric
  suffix. Soft-deleted shops retain their slug to prevent accidental URL takeover.
- Storefront Isolation: A seller's catalog never promotes, suggests, searches, or displays
  products from other sellers. Store headers, footers, categories, and related products
  are strictly scoped to the active shop.
- Scoped Route Binding: Visiting `/tienda/{shop}/producto/{product}` strictly ensures the
  product belongs to the shop. Any mismatch or slug tampering returns HTTP 404 immediately.
- Search Scoping: Storefront search (`/tienda/{shop}?q=...`) queries only that shop's catalog.
- Public Landing Page: The root `/` is a SaaS marketing page for acquiring sellers, not
  a shared marketplace directory.
- Discovery Flag: `shops.discovery_enabled` defaults to `false`. External discovery is
  opt-in only.
