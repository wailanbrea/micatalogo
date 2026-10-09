<?php

return [
    'implemented' => [
        'products', 'catalog', 'inventory', 'sales', 'customers', 'credit', 'cash',
        'expenses', 'finance', 'services', 'decants', 'perfume_fields', 'wholesale', 'brand',
        'sku', 'barcode', 'shipping', 'public_catalog',
    ],

    // Presentation is UX context only. Capabilities remain the authorization
    // boundary and are resolved by BusinessProfileService.
    'type_archetypes' => [
        'perfume_store' => 'fragrance',
        'clothing' => 'fashion',
        'boutique' => 'fashion',
        'caps_store' => 'fashion',
        'accessories_jewelry' => 'fashion',
        'footwear' => 'footwear',
        'beauty_cosmetics' => 'beauty_retail',
        'barbershop' => 'service_retail',
        'beauty_salon' => 'service_retail',
        'tattoo_studio' => 'service_retail',
        'professional_services' => 'service_retail',
        'electronics' => 'electronics',
        'appliance_store' => 'appliances',
        'food_restaurant' => 'food',
        'pastry' => 'food',
        'grocery' => 'grocery',
        'hardware' => 'hardware',
        'auto_parts' => 'automotive',
        'home_furniture' => 'home',
        'pharmacy_personal_care' => 'pharmacy',
        'vapes' => 'vape_retail',
        'general_retail' => 'general_retail',
        'other' => 'general_retail',
    ],

    'presentation_defaults' => [
        'terminology' => [
            'catalog' => 'Catálogo',
            'product' => 'producto',
            'products' => 'Productos',
            'new_product' => 'Nuevo producto',
            'inventory' => 'Inventario',
            'customers' => 'Clientes',
        ],
        'dashboard' => [
            'widgets' => ['sales_today', 'collections_today', 'low_stock', 'receivables', 'active_customers'],
            'quick_actions' => ['new_sale', 'new_product', 'inventory', 'new_customer'],
            'title' => 'Resumen de tu negocio',
        ],
        'pos' => [
            'search_placeholder' => 'Buscar producto o código',
            'show_wholesale' => false,
            'show_credit' => false,
            'show_inventory' => true,
        ],
        'catalog' => [
            'search_placeholder' => 'Buscar productos',
            'empty_message' => 'Crea tu primer producto para empezar',
            'show_stock' => true,
        ],
        'inventory' => [
            'title' => 'Existencias y movimientos',
        ],
        'customers' => [
            'show_credit' => false,
        ],
    ],

    'presentation_archetypes' => [
        'general_retail' => [],
        'fragrance' => [
            'terminology' => ['catalog' => 'Catálogo de perfumes', 'product' => 'Perfume', 'products' => 'Perfumes', 'new_product' => 'Nuevo perfume'],
            'pos' => ['search_placeholder' => 'Buscar perfume, marca o código'],
        ],
        'fashion' => [
            'terminology' => ['catalog' => 'Catálogo de moda', 'product' => 'Artículo', 'products' => 'Artículos', 'new_product' => 'Nuevo artículo'],
            'pos' => ['search_placeholder' => 'Buscar artículo, marca o código'],
        ],
        'footwear' => [
            'terminology' => ['catalog' => 'Catálogo de calzado', 'product' => 'Calzado', 'products' => 'Calzados', 'new_product' => 'Nuevo calzado'],
            'pos' => ['search_placeholder' => 'Buscar calzado, modelo o código'],
        ],
        'beauty_retail' => [
            'terminology' => ['catalog' => 'Catálogo de belleza', 'product' => 'Producto de belleza', 'products' => 'Productos de belleza', 'new_product' => 'Nuevo producto de belleza'],
        ],
        'service_retail' => [
            'terminology' => ['catalog' => 'Catálogo', 'product' => 'Producto', 'products' => 'Productos'],
            'dashboard' => ['widgets' => ['sales_today', 'collections_today', 'active_customers']],
            'catalog' => ['show_stock' => false, 'empty_message' => 'Agrega tu primer producto'],
            'pos' => ['show_inventory' => false],
        ],
        'electronics' => [
            'terminology' => ['catalog' => 'Catálogo de tecnología', 'product' => 'Equipo', 'products' => 'Equipos', 'new_product' => 'Nuevo equipo'],
            'pos' => ['search_placeholder' => 'Buscar equipo, marca, modelo o código'],
        ],
        'appliances' => [
            'terminology' => ['catalog' => 'Catálogo de electrodomésticos', 'product' => 'Electrodoméstico', 'products' => 'Electrodomésticos', 'new_product' => 'Nuevo electrodoméstico'],
            'pos' => ['search_placeholder' => 'Buscar electrodoméstico, marca, modelo o código'],
        ],
        'food' => [
            'terminology' => ['catalog' => 'Menú', 'product' => 'Artículo del menú', 'products' => 'Artículos del menú', 'new_product' => 'Nuevo artículo del menú'],
            'dashboard' => ['widgets' => ['sales_today', 'collections_today', 'active_customers']],
            'catalog' => ['show_stock' => false, 'empty_message' => 'Agrega el primer artículo del menú'],
            'pos' => ['search_placeholder' => 'Buscar en el menú', 'show_inventory' => false],
        ],
        'grocery' => [
            'terminology' => ['catalog' => 'Catálogo del colmado', 'product' => 'Producto', 'products' => 'Productos', 'new_product' => 'Nuevo producto'],
        ],
        'hardware' => [
            'terminology' => ['catalog' => 'Catálogo de ferretería', 'product' => 'Artículo', 'products' => 'Artículos', 'new_product' => 'Nuevo artículo'],
            'pos' => ['search_placeholder' => 'Buscar artículo, marca o código'],
        ],
        'automotive' => [
            'terminology' => ['catalog' => 'Catálogo automotriz', 'product' => 'Repuesto', 'products' => 'Repuestos y productos', 'new_product' => 'Nuevo repuesto'],
            'pos' => ['search_placeholder' => 'Buscar repuesto, modelo o código'],
        ],
        'home' => [
            'terminology' => ['catalog' => 'Catálogo del hogar', 'product' => 'Artículo', 'products' => 'Artículos', 'new_product' => 'Nuevo artículo'],
        ],
        'pharmacy' => [
            'terminology' => ['catalog' => 'Catálogo de cuidado personal', 'product' => 'Producto', 'products' => 'Productos', 'new_product' => 'Nuevo producto'],
        ],
        'vape_retail' => [
            'terminology' => ['catalog' => 'Catálogo de vapes', 'product' => 'Producto', 'products' => 'Productos', 'new_product' => 'Nuevo producto'],
        ],
    ],

    'types' => [
        'perfume_store' => [
            'label' => 'Perfumes y fragancias',
            'categories' => ['Perfumes', 'Sets', 'Body Splash', 'Decants', 'Accesorios'],
            'capabilities' => ['products' => true, 'catalog' => true, 'inventory' => true, 'sales' => true, 'customers' => true, 'credit' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'brand' => true, 'sku' => true, 'barcode' => true, 'perfume_fields' => true, 'decants' => true, 'wholesale' => true, 'public_catalog' => true],
            'product_fields' => ['brand', 'volume_ml', 'concentration', 'gender', 'sku', 'barcode', 'cost_price', 'price', 'stock', 'decants'],
        ],
        'clothing' => [
            'label' => 'Ropa y moda',
            'categories' => ['Camisetas', 'Camisas', 'Pantalones', 'Vestidos', 'Conjuntos', 'Accesorios'],
            'capabilities' => ['products' => true, 'catalog' => true, 'inventory' => true, 'sales' => true, 'customers' => true, 'credit' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'brand' => true, 'sku' => true, 'barcode' => true, 'variants' => 'unsupported', 'sizes' => 'unsupported', 'colors' => 'unsupported', 'public_catalog' => true],
            'product_fields' => ['brand', 'size', 'color', 'sku', 'barcode', 'cost_price', 'price', 'stock'],
        ],
        'footwear' => [
            'label' => 'Calzado y tenis',
            'categories' => ['Tenis', 'Zapatos', 'Sandalias', 'Botas', 'Accesorios'],
            'capabilities' => ['products' => true, 'catalog' => true, 'inventory' => true, 'sales' => true, 'customers' => true, 'credit' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'brand' => true, 'sku' => true, 'barcode' => true, 'variants' => 'unsupported', 'sizes' => 'unsupported', 'colors' => 'unsupported', 'public_catalog' => true],
            'product_fields' => ['brand', 'model', 'size', 'color', 'sku', 'barcode', 'cost_price', 'price', 'stock'],
        ],
        'beauty_cosmetics' => ['label' => 'Belleza y cosméticos', 'categories' => ['Maquillaje', 'Cuidado facial', 'Cuidado capilar', 'Cuidado corporal', 'Accesorios'], 'capabilities' => ['products' => true, 'catalog' => true, 'inventory' => true, 'sales' => true, 'customers' => true, 'credit' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'brand' => true, 'sku' => true, 'barcode' => true, 'public_catalog' => true], 'product_fields' => ['brand', 'sku', 'barcode', 'cost_price', 'price', 'stock']],
        'barbershop' => ['label' => 'Barbería', 'categories' => ['Cortes', 'Barba', 'Tratamientos', 'Cuidado capilar', 'Cuidado de barba', 'Accesorios'], 'capabilities' => ['products' => true, 'catalog' => true, 'inventory' => true, 'sales' => true, 'customers' => true, 'credit' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'public_catalog' => true, 'services' => true, 'appointments' => 'unsupported'], 'product_fields' => ['brand', 'size', 'cost_price', 'price', 'stock']],
        'beauty_salon' => ['label' => 'Salón de belleza y uñas', 'categories' => ['Cabello', 'Uñas', 'Maquillaje', 'Tratamientos', 'Productos'], 'capabilities' => ['products' => true, 'catalog' => true, 'inventory' => true, 'sales' => true, 'customers' => true, 'credit' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'public_catalog' => true, 'services' => true, 'appointments' => 'unsupported'], 'product_fields' => ['brand', 'size', 'cost_price', 'price', 'stock']],
        'electronics' => ['label' => 'Tecnología y electrónica', 'categories' => ['Celulares', 'Computadoras', 'Accesorios', 'Audio', 'Redes', 'Otros'], 'capabilities' => ['products' => true, 'catalog' => true, 'inventory' => true, 'sales' => true, 'customers' => true, 'credit' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'brand' => true, 'model' => true, 'sku' => true, 'barcode' => true, 'wholesale' => true, 'public_catalog' => true, 'serial_tracking' => 'unsupported'], 'product_fields' => ['brand', 'model', 'sku', 'barcode', 'cost_price', 'price', 'stock']],
        'appliance_store' => ['label' => 'Electrodomésticos', 'categories' => ['Neveras y refrigeradores', 'Estufas y hornos', 'Lavadoras y secadoras', 'Aires acondicionados', 'Microondas', 'Pequeños electrodomésticos', 'Televisores y entretenimiento', 'Otros'], 'capabilities' => ['products' => true, 'catalog' => true, 'inventory' => true, 'sales' => true, 'customers' => true, 'credit' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'brand' => true, 'model' => true, 'sku' => true, 'barcode' => true, 'wholesale' => true, 'public_catalog' => true, 'shipping' => true, 'serial_tracking' => 'unsupported'], 'product_fields' => ['brand', 'model', 'sku', 'barcode', 'cost_price', 'price', 'stock']],
        'accessories_jewelry' => ['label' => 'Accesorios y joyería', 'categories' => ['Relojes', 'Cadenas', 'Pulseras', 'Aretes', 'Bolsos', 'Otros'], 'capabilities' => ['products' => true, 'catalog' => true, 'inventory' => true, 'sales' => true, 'customers' => true, 'credit' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'brand' => true, 'sku' => true, 'barcode' => true, 'public_catalog' => true], 'product_fields' => ['brand', 'model', 'color', 'sku', 'barcode', 'cost_price', 'price', 'stock']],
        'food_restaurant' => ['label' => 'Restaurante y alimentos', 'categories' => ['Entradas', 'Platos', 'Bebidas', 'Postres', 'Combos'], 'capabilities' => ['products' => true, 'catalog' => true, 'sales' => true, 'customers' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'public_catalog' => true, 'inventory' => 'unsupported', 'recipes_or_ingredients' => 'unsupported', 'weighted_products' => 'unsupported'], 'product_fields' => ['description', 'price', 'stock']],
        'grocery' => ['label' => 'Colmado y minimarket', 'categories' => ['Alimentos', 'Bebidas', 'Higiene', 'Limpieza', 'Hogar', 'Otros'], 'capabilities' => ['products' => true, 'catalog' => true, 'inventory' => true, 'sales' => true, 'customers' => true, 'credit' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'sku' => true, 'barcode' => true, 'public_catalog' => true, 'weighted_products' => 'unsupported'], 'product_fields' => ['sku', 'barcode', 'cost_price', 'price', 'stock']],
        'hardware' => ['label' => 'Ferretería', 'categories' => ['Herramientas', 'Electricidad', 'Plomería', 'Pintura', 'Construcción', 'Tornillería'], 'capabilities' => ['products' => true, 'catalog' => true, 'inventory' => true, 'sales' => true, 'customers' => true, 'credit' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'brand' => true, 'sku' => true, 'barcode' => true, 'wholesale' => true, 'public_catalog' => true], 'product_fields' => ['brand', 'sku', 'barcode', 'cost_price', 'price', 'stock']],
        'auto_parts' => ['label' => 'Repuestos y automotriz', 'categories' => ['Motor', 'Frenos', 'Suspensión', 'Electricidad', 'Filtros', 'Accesorios'], 'capabilities' => ['products' => true, 'catalog' => true, 'inventory' => true, 'sales' => true, 'customers' => true, 'credit' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'brand' => true, 'model' => true, 'sku' => true, 'barcode' => true, 'wholesale' => true, 'public_catalog' => true], 'product_fields' => ['brand', 'model', 'sku', 'barcode', 'cost_price', 'price', 'stock']],
        'home_furniture' => ['label' => 'Hogar y muebles', 'categories' => ['Sala', 'Comedor', 'Dormitorio', 'Cocina', 'Decoración', 'Otros'], 'capabilities' => ['products' => true, 'catalog' => true, 'inventory' => true, 'sales' => true, 'customers' => true, 'credit' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'sku' => true, 'barcode' => true, 'public_catalog' => true], 'product_fields' => ['color', 'sku', 'barcode', 'cost_price', 'price', 'stock']],
        'pharmacy_personal_care' => ['label' => 'Farmacia y cuidado personal', 'categories' => ['Cuidado personal', 'Higiene', 'Belleza', 'Accesorios', 'Otros'], 'capabilities' => ['products' => true, 'catalog' => true, 'inventory' => true, 'sales' => true, 'customers' => true, 'credit' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'brand' => true, 'sku' => true, 'barcode' => true, 'public_catalog' => true], 'product_fields' => ['brand', 'sku', 'barcode', 'cost_price', 'price', 'stock']],
        'professional_services' => ['label' => 'Servicios profesionales', 'categories' => ['Servicios', 'Paquetes', 'Otros'], 'capabilities' => ['sales' => true, 'customers' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'catalog' => true, 'public_catalog' => true, 'services' => true, 'products' => 'unsupported', 'inventory' => false], 'product_fields' => ['description', 'price']],
        'general_retail' => ['label' => 'Tienda general', 'categories' => ['Productos', 'Ofertas', 'Otros'], 'capabilities' => ['products' => true, 'catalog' => true, 'inventory' => true, 'sales' => true, 'customers' => true, 'credit' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'decants' => true, 'sku' => true, 'barcode' => true, 'public_catalog' => true], 'product_fields' => ['sku', 'barcode', 'cost_price', 'price', 'stock']],
        'other' => ['label' => 'Otro tipo de negocio', 'categories' => ['Productos', 'Servicios', 'Otros'], 'capabilities' => ['products' => true, 'catalog' => true, 'inventory' => true, 'sales' => true, 'customers' => true, 'credit' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'decants' => true, 'public_catalog' => true], 'product_fields' => ['description', 'sku', 'barcode', 'cost_price', 'price', 'stock']],
        // Additional stable keys observed in Puntto's public registration.
        'vapes' => ['label' => 'Vapes', 'categories' => ['Dispositivos', 'Líquidos', 'Accesorios', 'Otros'], 'capabilities' => ['products' => true, 'catalog' => true, 'inventory' => true, 'sales' => true, 'customers' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'sku' => true, 'barcode' => true, 'public_catalog' => true], 'product_fields' => ['brand', 'model', 'sku', 'barcode', 'cost_price', 'price', 'stock']],
        'pastry' => ['label' => 'Repostería', 'categories' => ['Pasteles', 'Postres', 'Panadería', 'Combos'], 'capabilities' => ['products' => true, 'catalog' => true, 'sales' => true, 'customers' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'public_catalog' => true, 'inventory' => 'unsupported'], 'product_fields' => ['description', 'price']],
        'tattoo_studio' => ['label' => 'Tatuajes', 'categories' => ['Tatuajes', 'Diseños', 'Accesorios'], 'capabilities' => ['sales' => true, 'customers' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'catalog' => true, 'public_catalog' => true, 'services' => true, 'products' => 'unsupported'], 'product_fields' => ['description', 'price']],
        'caps_store' => ['label' => 'Gorras', 'categories' => ['Gorras', 'Accesorios', 'Otros'], 'capabilities' => ['products' => true, 'catalog' => true, 'inventory' => true, 'sales' => true, 'customers' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'brand' => true, 'sku' => true, 'barcode' => true, 'public_catalog' => true], 'product_fields' => ['brand', 'size', 'color', 'sku', 'barcode', 'cost_price', 'price', 'stock']],
        'boutique' => ['label' => 'Boutique', 'categories' => ['Ropa', 'Vestidos', 'Accesorios', 'Otros'], 'capabilities' => ['products' => true, 'catalog' => true, 'inventory' => true, 'sales' => true, 'customers' => true, 'credit' => true, 'cash' => true, 'expenses' => true, 'finance' => true, 'brand' => true, 'sku' => true, 'barcode' => true, 'public_catalog' => true], 'product_fields' => ['brand', 'size', 'color', 'sku', 'barcode', 'cost_price', 'price', 'stock']],
    ],

    'reserved_slugs' => ['admin', 'api', 'login', 'register', 'panel', 'tienda', 'tiendas', 'logout', 'forgot-password'],
];
