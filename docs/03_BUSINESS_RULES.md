# Business Rules

- A free account may have at most one active shop.
- A shop slug is globally unique; a product slug is unique within its shop.
- Default limits: 100 products per shop, 3 images per product, 10 MB per file, and
  30 files per bulk upload batch.
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
