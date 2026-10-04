# Open Beauty Facts Catalog Media

MiCatalogo integrates Open Beauty Facts as a backend-only enrichment provider. The
integration is additive: it does not delete or replace existing products, SKUs, manual
images, inventory, prices, descriptions, or notes.

## Data flow

1. A product may store an optional `barcode` independently from its internal `product_code`.
2. Product creation, update, and inventory imports enqueue `ResolveProductCatalogMediaJob`.
3. `CatalogMediaService` resolves the barcode through the Open Beauty Facts client, using
   the Laravel cache and a local `catalog_products` library for persistence.
4. Provider metadata and image provenance are stored in `catalog_products` and
   `catalog_product_images`.
5. External images are downloaded only from the configured allowlist, validated, converted
   to WebP, and stored in the existing media disk/R2 pipeline.
6. A seller explicitly chooses **Agregar sin reemplazar** before a library image is linked
   to `product_images`.

## API

`GET /api/v1/catalog/media/{barcode}` requires a verified Sanctum token with the
`catalog:read` ability. It returns provider status, product metadata, local image URLs,
license, and attribution. Requests are rate limited to 30 per minute by user/IP.

## Data safety

- Existing rows receive nullable columns only; the migration does not rewrite or delete
  existing product/image data.
- Existing product images default to `source=manual`.
- Deleting a linked catalog image removes only the local link; shared catalog objects are
  not deleted from storage.
- Provider payloads are retained for auditability and future reprocessing.

## Operations

The queue worker must be running for automatic product/import resolution. The panel also
offers a manual **Consultar ahora** action. Failed provider requests and image processing
are recorded without blocking the product transaction.
