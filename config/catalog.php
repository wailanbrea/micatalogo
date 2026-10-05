<?php

return [
    'currency' => env('CATALOG_CURRENCY', 'DOP'),

    'plans' => [
        'free' => [
            'display_name' => 'Gratis',
            'price_usd' => 0,
            'max_active_shops' => 1,
            'max_products_per_shop' => 250,
            'max_images_per_product' => 1,
            'max_users' => 1,
            'max_sellers' => 1,
            'features' => ['catalog', 'whatsapp_orders', 'basic_inventory', 'bulk_import'],
        ],
        'premium' => [
            'display_name' => 'Básico',
            'price_usd' => 8,
            'max_active_shops' => 1,
            'max_products_per_shop' => 500,
            'max_images_per_product' => 3,
            'max_users' => 3,
            'max_sellers' => 3,
            'features' => ['catalog', 'whatsapp_orders', 'inventory', 'sales', 'invoicing', 'reports', 'quotes', 'customers', 'android', 'bulk_import', 'cash_registers'],
        ],
        'pro' => [
            'display_name' => 'Pro',
            'price_usd' => 15,
            'max_active_shops' => 3,
            'max_products_per_shop' => 1500,
            'max_images_per_product' => 3,
            'max_users' => 5,
            'max_sellers' => 5,
            'features' => ['catalog', 'whatsapp_orders', 'inventory', 'sales', 'invoicing', 'reports', 'quotes', 'customers', 'android', 'credit_interest', 'wholesale_pricing', 'bulk_import', 'expenses', 'profit_sharing', 'automatic_pricing', 'cash_registers'],
        ],
        'custom' => [
            'display_name' => 'Personalizado',
            'price_usd' => null,
            'max_active_shops' => 10,
            'max_products_per_shop' => 1000000,
            'max_images_per_product' => 3,
            'max_users' => 100,
            'max_sellers' => 100,
            'features' => ['custom_domain', 'multiple_shops', 'priority_support'],
        ],
    ],

    'payment_methods' => [
        'cash' => [
            'key' => 'cash',
            'label' => 'Efectivo',
            'requires_reference' => false,
            'affects_cash_register' => true,
        ],
        'card' => [
            'key' => 'card',
            'label' => 'Tarjeta',
            'requires_reference' => false,
            'affects_cash_register' => false,
        ],
        'bank_transfer' => [
            'key' => 'bank_transfer',
            'label' => 'Transferencia',
            'requires_reference' => false,
            'affects_cash_register' => false,
        ],
        'credit' => [
            'key' => 'credit',
            'label' => 'Crédito',
            'requires_reference' => false,
            'affects_cash_register' => false,
        ],
        'other' => [
            'key' => 'other',
            'label' => 'Otro',
            'requires_reference' => false,
            'affects_cash_register' => false,
        ],
    ],

    'default_expense_categories' => [
        'Alquiler',
        'Electricidad',
        'Internet',
        'Transporte',
        'Publicidad',
        'Nómina',
        'Comisiones',
        'Mantenimiento',
        'Materiales',
        'Impuestos',
        'Otros',
    ],

    // Legacy keys remain available for existing tests and deployments.
    'free' => [
        'max_active_shops' => 1,
        'max_products_per_shop' => 250,
        'max_images_per_product' => 1,
        'max_users' => 1,
        'max_sellers' => 1,
    ],

    'additional_seat_price_usd' => 5,

    'inventory' => [
        'low_ml_alert_threshold' => 200,
    ],

    'uploads' => [
        'max_file_size_mb' => 10,
        'max_batch_files' => 30,
        'max_input_pixels' => 60_000_000,
    ],

    'images' => [
        'main_max_width' => 1600,
        'main_max_height' => 1600,
        'thumbnail_width' => 480,
        'thumbnail_height' => 480,
        'webp_quality' => 80,
        'thumbnail_quality' => 75,
    ],

    'open_beauty_facts' => [
        'endpoint' => env('OPEN_BEAUTY_FACTS_ENDPOINT', 'https://world.openbeautyfacts.org/api/v2/product'),
        'cache_seconds' => (int) env('OPEN_BEAUTY_FACTS_CACHE_SECONDS', 86400),
        'connect_timeout' => (int) env('OPEN_BEAUTY_FACTS_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('OPEN_BEAUTY_FACTS_TIMEOUT', 10),
        'image_timeout' => (int) env('OPEN_BEAUTY_FACTS_IMAGE_TIMEOUT', 20),
        'max_image_bytes' => (int) env('OPEN_BEAUTY_FACTS_MAX_IMAGE_BYTES', 10485760),
        'allowed_image_hosts' => [
            'images.openfoodfacts.org',
            'images.openbeautyfacts.org',
            'world.openbeautyfacts.org',
        ],
    ],

    'media' => [
        'soft_delete_retention_days' => 30,
    ],

    'ads' => [
        'enabled' => env('ADS_ENABLED', false),
    ],

    'rate_limits' => [
        'account' => [
            'registration' => [
                'max_attempts' => 3,
                'decay_seconds' => 900,
            ],
            'password_reset' => [
                'max_attempts' => 5,
                'decay_seconds' => 900,
            ],
            'reports' => [
                'max_attempts' => 5,
                'decay_seconds' => 900,
            ],
            'catalog_media' => [
                'max_attempts' => 30,
                'decay_seconds' => 60,
            ],
        ],
    ],
];
