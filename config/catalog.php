<?php

return [
    'currency' => env('CATALOG_CURRENCY', 'DOP'),

    'plans' => [
        'free' => [
            'max_active_shops' => 1,
            'max_products_per_shop' => 100,
            'max_images_per_product' => 3,
        ],
        'premium' => [
            'max_active_shops' => 1,
            'max_products_per_shop' => 500,
            'max_images_per_product' => 3,
        ],
        'pro' => [
            'max_active_shops' => 1,
            'max_products_per_shop' => 1500,
            'max_images_per_product' => 8,
        ],
    ],

    // Legacy keys remain available for existing tests and deployments.
    'free' => [
        'max_active_shops' => 1,
        'max_products_per_shop' => 100,
        'max_images_per_product' => 3,
    ],

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
