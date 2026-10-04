<?php

return [
    'seller_menu_options' => [
        'sales' => 'Ventas',
        'products' => 'Productos',
        'printers' => 'Impresoras (app)',
        'customers' => 'Clientes',
        'inventory' => 'Inventario',
        'collections' => 'Cobros',
        'cash' => 'Caja',
        'returns' => 'Devoluciones',
        'routes' => 'Rutas',
        'more' => 'Más herramientas',
        'settings' => 'Ajustes',
        'metrics' => 'Métricas y QR',
        'public_catalog' => 'Compartir catálogo',
        'shop_settings' => 'Configuración de tienda',
        'sellers' => 'Vendedores',
    ],
    'owner_only_menu_options' => [
        'settings',
        'shop_settings',
        'sellers',
    ],
    'android_update' => [
        'version_code' => (int) env('BSPOS_ANDROID_VERSION_CODE', 0),
        'version_name' => env('BSPOS_ANDROID_VERSION_NAME', ''),
        'minimum_supported_version_code' => (int) env('BSPOS_ANDROID_MINIMUM_SUPPORTED_VERSION_CODE', 0),
        'apk_url' => env('BSPOS_ANDROID_APK_URL', ''),
        'apk_sha256' => env('BSPOS_ANDROID_APK_SHA256', ''),
        'release_notes' => env('BSPOS_ANDROID_RELEASE_NOTES', ''),
        'release_date' => env('BSPOS_ANDROID_RELEASE_DATE', '2026-10-04'),
    ],
];
