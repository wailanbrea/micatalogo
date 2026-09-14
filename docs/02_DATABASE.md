# Database

The original target is MySQL 8.4 LTS with InnoDB and utf8mb4. The user approved
MariaDB 11.4.12 for local development; it uses InnoDB-compatible tables and utf8mb4.

Initial entities are users, shops, global_categories, shop_categories, products,
product_images, reports, shop_daily_metrics, and product_daily_metrics. Publicly
exposed shops, products, and reports use ULIDs in addition to internal bigint keys.

Local database: micatalogo. The application connects through a dedicated local account
with privileges restricted to that database. Initial Laravel migrations are applied.

Domain schema is applied: shops, global_categories, shop_categories, products,
product_images, reports, shop_daily_metrics, and product_daily_metrics. Shop and
product slugs are constrained at database level; public IDs are generated as ULIDs.
