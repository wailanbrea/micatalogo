<?php

// Presentation only. Keys continue to be authorized by SellerMenuService and policies.
$item = static fn (string $id, string $key, string $label, ?string $route, string $android, array $extra = []): array => ['id' => $id, 'key' => $key, 'label' => $label, 'route' => $route, 'android_route' => $android] + $extra;
$feature = static fn (string $key, string $label, ?string $android = null): array => $item($key, $key, $label, 'seller.shops.feature', $android ?? $key, ['feature' => $key]);

return [
    'sections' => [
        'home' => ['label' => 'Inicio', 'items' => [
            $item('summary', '', 'Resumen', 'seller.shops.summary', 'dashboard'),
        ]],
        'sales' => ['label' => 'Ventas', 'items' => [
            $item('pos', 'sales', 'Vender', 'seller.shops.pos', 'pos'),
            $item('sales_history', 'sales', 'Historial de ventas', 'seller.shops.feature', 'sales_history', ['feature' => 'sales']),
            $feature('quotes', 'Cotizaciones'),
            $item('returns', 'returns', 'Devoluciones', null, 'returns'),
            $item('cash', 'cash', 'Caja', 'seller.shops.cash.index', 'cash', ['active_routes' => ['seller.shops.cash.*']]),
            $feature('day_close', 'Cierre de día'),
        ]],
        'orders' => ['label' => 'Pedidos', 'items' => [
            $feature('orders', 'Pedidos'), $feature('encargos', 'Encargos'), $feature('shipments', 'Envíos'),
        ]],
        'catalog' => ['label' => 'Catálogo', 'items' => [
            $item('products', 'products', 'Productos', 'seller.shops.products.index', 'catalog', ['active_routes' => ['seller.shops.products.*'], 'term' => 'products']),
            $feature('services', 'Servicios'),
            $item('categories', 'products', 'Categorías y unidades', 'seller.shops.categories.index', 'categories', ['active_routes' => ['seller.shops.categories.*']]),
            $feature('photos', 'Fotos'), $feature('attributes', 'Marcas y atributos'),
            $feature('price_health', 'Diagnóstico de precios'),
            $item('pricing', 'pricing', 'Precios automáticos', 'seller.shops.pricing.index', 'pricing', ['active_routes' => ['seller.shops.pricing.*'], 'manage_route' => true, 'feature_fallback' => 'pricing']),
            $feature('decants', 'Decants'),
        ]],
        'inventory' => ['label' => 'Inventario', 'items' => [
            $item('inventory', 'inventory', 'Existencias y movimientos', 'seller.shops.inventory.index', 'inventory', ['active_routes' => ['seller.shops.inventory.*']]),
            $feature('inventory_adjustments', 'Ajustes y conteos'),
            $item('fifo', 'inventory', 'Lotes FIFO', 'seller.shops.inventory.lots', 'inventory'),
            $item('import', 'import', 'Importar', 'seller.shops.products.import.create', 'import', ['active_routes' => ['seller.shops.products.import.*', 'seller.shops.products.bulk.*'], 'manage_route' => true, 'feature_fallback' => 'import']),
        ]],
        'purchases' => ['label' => 'Compras', 'items' => [
            $feature('suppliers', 'Proveedores'), $feature('purchase_invoices', 'Facturas de compra'),
            $feature('loads', 'Cargas'), $feature('containers', 'Contenedores'),
        ]],
        'customers' => ['label' => 'Clientes y cobros', 'items' => [
            $item('customers', 'customers', 'Clientes', 'seller.shops.customers.index', 'customers', ['active_routes' => ['seller.shops.customers.*']]),
            $feature('credit', 'Cartera y crédito', 'credit_ledger'),
            $item('collections', 'collections', 'Cobros y abonos', 'seller.shops.customers.index', 'collections', ['query' => ['view' => 'collections'], 'preferred_query' => ['view' => 'collections']]),
        ]],
        'finance' => ['label' => 'Finanzas', 'items' => [
            $item('finance', 'finance', 'Ganancias', 'seller.shops.business', 'finance'),
            $item('expenses', 'expenses', 'Gastos', 'seller.shops.expenses.index', 'expenses', ['active_routes' => ['seller.shops.expenses.*']]),
            $feature('partners', 'Socios'),
        ]],
        'reports' => ['label' => 'Reportes', 'items' => [$feature('reports', 'Reportes operativos')]],
        'storefront' => ['label' => 'Mi tienda', 'items' => [
            $item('storefront', 'storefront', 'Vitrina y diseño', 'seller.shops.storefront', 'storefront'),
            $item('public_catalog', 'public_catalog', 'Compartir catálogo', 'shops.show', 'public_catalog', ['external' => true]),
            $item('metrics', 'metrics', 'Métricas y QR', 'seller.shops.metrics.index', 'metrics', ['active_routes' => ['seller.shops.metrics.*', 'seller.shops.qr.*']]),
        ]],
        'settings' => ['label' => 'Configuración', 'items' => [
            $item('shop_settings', 'shop_settings', 'Negocio', 'seller.shops.edit', 'shop_settings'),
            $item('shop_menus', 'shop_settings', 'Menús de la tienda', 'seller.shops.menus.edit', 'shop_settings?section=menus', ['owner_only' => true, 'active_routes' => ['seller.shops.menus.*']]),
            $item('sellers', 'sellers', 'Equipo', 'seller.shops.sellers.index', 'team', ['owner_only' => true, 'active_routes' => ['seller.shops.sellers.*']]),
            $feature('commissions', 'Comisiones'), $feature('authorizations', 'Autorizaciones'), $feature('accountant', 'Contador'),
            $item('settings', 'settings', 'Ajustes de la app', null, 'settings'),
            $item('printers', 'printers', 'Impresoras', null, 'printers'),
        ]],
    ],
    'secondary' => [
        $feature('account', 'Mi cuenta', 'profile') + ['authenticated' => true],
        $feature('help', 'Ayuda') + ['authenticated' => true],
        $feature('support', 'Contactar soporte'), $feature('updates', 'Actualizaciones'),
        $feature('practice', 'Guía de práctica'),
        $item('shop_selector', '', 'Mis tiendas', 'seller.dashboard', '', ['web_only' => true, 'manage_shops' => true, 'without_shop' => true]),
    ],
    'contextual' => [
        'distribution' => ['label' => 'Distribución', 'requires_local_setting' => 'routesEnabled', 'items' => [
            $item('routes', 'routes', 'Rutas', null, 'routes'),
            $item('route_loads', 'routes', 'Cargas de ruta', null, 'route_loads'),
        ]],
    ],
    // A persisted legacy key, intentionally never a gate for opening the drawer.
    'container_keys' => ['more'],
];
