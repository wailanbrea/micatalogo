<?php

return [
    'currency' => env('CATALOG_CURRENCY', 'DOP'),

    'free' => [
        'max_active_shops' => 1,
        'max_products_per_shop' => 100,
        'max_images_per_product' => 3,
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
        ],
    ],
];
