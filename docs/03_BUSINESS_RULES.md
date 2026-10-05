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

## Bulk Inventory Import & Quotas

- **Supported File Formats**: CSV (comma or semicolon delimited, with or without UTF-8 BOM), TXT, and XLSX.
- **Monetary Precision & Parser**: Prices and costs are parsed using a robust parser that determines decimal vs thousand separators without binary float corruption. Supported patterns include `2500`, `2500.50`, `2500,50`, `2,500`, `2,500.50`, `2.500,50`, and currency prefixes (`RD$`, `DOP`, `$`, `USD`). Never interprets decimals as integers (e.g. `2500,50` evaluates to `2500.50`, never `250050`). Values are persisted as exact strings/DECIMAL(12,2).
- **Identifier Preservation**: Barcode and SKU (`product_code`) are preserved as verbatim strings with leading zeros retained (e.g., `'000123'`).
- **Internal Duplicate Detection**: The preview identifies duplicates within the file by normalized barcode, SKU, or identical name, flagging invalid duplicate rows to prevent accidental double-creation.
- **Multi-Tenant Shop Isolation**: Existing product matching (`barcode`, `product_code`, `name`) strictly queries within the seller's active shop (`shop_id`). SKUs in other shops never collide or link across tenants.
- **Explicit Duplicate Strategies**:
  - `skip`: Leaves existing product unchanged without adjusting stock or consuming quota.
  - `update`: Updates allowed fields (`name`, `price`, `cost_price`, `stock`, `brand`, `description`, `notes`, `category`, `attributes`) on the existing product and its `ProductInventory` (adjusting stock via `InventoryService` without creating duplicate inventory records). Does not consume product quota.
  - `create`: Creates a new product. Disallows creation if the product barcode already exists in the shop.
- **Quota Consumption**: Only rows resulting in newly created products (`effective_action === 'create'`) count towards plan product limits. Updates and skips do not consume quota. If the number of new products exceeds remaining plan quota, the transaction fails before any product is written (total rollback).
- **Category Resolution**:
  - Existing shop categories match insensitively (case/accents/spaces).
  - Unrecognized categories are reported in `missing_categories`.
  - With `create_missing_categories = true`, categories are automatically created and linked.
  - With `create_missing_categories = false`, the product is imported with `shop_category_id = null`.
- **Duplicate Attributes**: Attributes repeated on the same row (e.g. `Marca=Rasasi;Marca=Lattafa`) trigger warnings and safely retain the first definition.
- **Server-Side Import Sessions (`InventoryImportSession`)**:
  - Previews create a short-lived server session (`status = 'previewed'`, 2-hour expiration).
  - Confirmations reference the server `session_id`, eliminating client-side payload tampering.
  - Operations run inside `DB::transaction()` with `Shop::lockForUpdate()`.
  - Re-submitting a confirmed session returns the existing summary idempotently.
  - API provides `POST /shops/{shop}/inventory-import/{session}/confirm` alongside backward-compatible `POST /shops/{shop}/inventory-import`.

