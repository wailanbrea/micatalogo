<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\SellerMenuService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SellerFeatureController extends Controller
{
    /** @var array<string, array{group: string, title: string, description: string, status: string}> */
    private const FEATURES = [
        'sales' => ['group' => 'Operación', 'title' => 'Ventas', 'description' => 'Consulta y administra las ventas registradas desde el Punto de venta.', 'status' => 'En preparación'],
        'quotes' => ['group' => 'Operación', 'title' => 'Cotizaciones', 'description' => 'Prepara cotizaciones, compártelas con tus clientes y conviértelas en ventas.', 'status' => 'En preparación'],
        'orders' => ['group' => 'Operación', 'title' => 'Pedidos', 'description' => 'Centraliza los pedidos recibidos desde tu catálogo público y su estado de atención.', 'status' => 'En preparación'],
        'encargos' => ['group' => 'Operación', 'title' => 'Encargos', 'description' => 'Organiza encargos pendientes, fechas de entrega y seguimiento al cliente.', 'status' => 'En preparación'],
        'shipments' => ['group' => 'Operación', 'title' => 'Envíos', 'description' => 'Gestiona entregas, transportistas y estados de envío desde un mismo lugar.', 'status' => 'En preparación'],
        'day_close' => ['group' => 'Operación', 'title' => 'Cierre de día', 'description' => 'Revisa ventas, cobros, gastos y diferencias antes de cerrar la jornada.', 'status' => 'En preparación'],
        'containers' => ['group' => 'Compras', 'title' => 'Contenedores', 'description' => 'Agrupa compras internacionales o recepciones grandes para controlar su costo total.', 'status' => 'En preparación'],
        'loads' => ['group' => 'Compras', 'title' => 'Cargas', 'description' => 'Registra mercancía en tránsito y sus costos asociados antes de recibirla.', 'status' => 'En preparación'],
        'suppliers' => ['group' => 'Compras', 'title' => 'Suplidores', 'description' => 'Administra tus suplidores, contactos, condiciones y compras históricas.', 'status' => 'En preparación'],
        'purchase_invoices' => ['group' => 'Compras', 'title' => 'Facturas de compra', 'description' => 'Relaciona facturas de compra con productos, lotes y costos FIFO.', 'status' => 'En preparación'],
        'photos' => ['group' => 'Catálogo', 'title' => 'Fotos', 'description' => 'Mejora las imágenes del catálogo y detecta productos que necesitan una foto.', 'status' => 'En preparación'],
        'services' => ['group' => 'Catálogo', 'title' => 'Servicios', 'description' => 'Crea servicios, precios y duración para negocios que venden experiencias o citas.', 'status' => 'En preparación'],
        'price_health' => ['group' => 'Catálogo', 'title' => 'Salud de precios', 'description' => 'Detecta precios sin margen, desactualizados o fuera de la regla de tu negocio.', 'status' => 'En preparación'],
        'decants' => ['group' => 'Catálogo', 'title' => 'Decants', 'description' => 'Controla fracciones, mililitros disponibles y costo compartido por lote.', 'status' => 'En preparación'],
        'attributes' => ['group' => 'Catálogo', 'title' => 'Marcas y atributos', 'description' => 'Organiza marcas, tallas, colores, concentraciones y otros atributos de tus productos.', 'status' => 'En preparación'],
        'credit' => ['group' => 'Cobros', 'title' => 'Crédito', 'description' => 'Da seguimiento a cuentas por cobrar, vencimientos y abonos de clientes.', 'status' => 'En preparación'],
        'inventory_adjustments' => ['group' => 'Finanzas', 'title' => 'Ajustes de inventario', 'description' => 'Audita ajustes, pérdidas y diferencias de inventario sin alterar el historial contable.', 'status' => 'En preparación'],
        'partners' => ['group' => 'Finanzas', 'title' => 'Socios', 'description' => 'Prepara el control de aportes, participaciones y retiros del negocio.', 'status' => 'En preparación'],
        'reports' => ['group' => 'Análisis', 'title' => 'Reportes', 'description' => 'Construye reportes operativos y financieros exportables para tomar mejores decisiones.', 'status' => 'En preparación'],
        'commissions' => ['group' => 'Equipo', 'title' => 'Comisiones', 'description' => 'Define reglas y consulta comisiones por vendedor y por venta.', 'status' => 'En preparación'],
        'authorizations' => ['group' => 'Equipo', 'title' => 'Autorizaciones', 'description' => 'Controla qué acciones requieren aprobación del propietario.', 'status' => 'En preparación'],
        'accountant' => ['group' => 'Ajustes', 'title' => 'Contador', 'description' => 'Prepara el acceso de tu contador a la información financiera necesaria.', 'status' => 'En preparación'],
        'account' => ['group' => 'Ajustes', 'title' => 'Mi cuenta', 'description' => 'Administra tus datos personales, seguridad, sesión y preferencias de MiCatalogo.', 'status' => 'En preparación'],
        'updates' => ['group' => 'Ajustes', 'title' => 'Novedades', 'description' => 'Conoce las mejoras y nuevas funciones de MiCatalogo.', 'status' => 'En preparación'],
        'help' => ['group' => 'Ajustes', 'title' => 'Ayuda', 'description' => 'Encuentra guías rápidas para aprender a usar cada parte de tu negocio.', 'status' => 'En preparación'],
        'practice' => ['group' => 'Ajustes', 'title' => 'Practicar sin miedo', 'description' => 'Aprende los flujos principales con datos de práctica sin tocar tu operación real.', 'status' => 'En preparación'],
        'support' => ['group' => 'Ajustes', 'title' => 'Soporte', 'description' => 'Envía una solicitud y consulta el estado de la ayuda de tu equipo.', 'status' => 'En preparación'],
    ];

    public function show(Request $request, Shop $shop, string $feature, SellerMenuService $menus): View
    {
        abort_unless(isset(self::FEATURES[$feature]), 404);
        abort_unless(in_array($feature, $menus->visibleForUser($shop, $request->user()), true), 403);

        $related = match ($feature) {
            'sales', 'quotes', 'orders', 'encargos', 'shipments', 'day_close' => [
                ['label' => 'Ir al Punto de venta', 'url' => route('seller.shops.pos', $shop)],
                ['label' => 'Ver ganancias y resumen', 'url' => route('seller.shops.business', $shop)],
            ],
            'containers', 'loads', 'suppliers', 'purchase_invoices' => [
                ['label' => 'Ver inventario', 'url' => route('seller.shops.inventory.index', $shop)],
                ['label' => 'Ver lotes y costos FIFO', 'url' => route('seller.shops.inventory.lots', $shop)],
            ],
            'photos', 'services', 'price_health', 'decants', 'attributes' => [
                ['label' => 'Ver productos', 'url' => route('seller.shops.products.index', $shop)],
                ['label' => 'Precios automáticos', 'url' => route('seller.shops.pricing.index', $shop)],
            ],
            'credit' => [
                ['label' => 'Ver clientes y cobros', 'url' => route('seller.shops.customers.index', $shop)],
                ['label' => 'Ir al Punto de venta', 'url' => route('seller.shops.pos', $shop)],
            ],
            'inventory_adjustments' => [
                ['label' => 'Ver inventario', 'url' => route('seller.shops.inventory.index', $shop)],
                ['label' => 'Ver lotes y costos FIFO', 'url' => route('seller.shops.inventory.lots', $shop)],
            ],
            'partners', 'reports' => [
                ['label' => 'Ver ganancias y resumen', 'url' => route('seller.shops.business', $shop)],
                ['label' => 'Ver estadísticas', 'url' => route('seller.shops.metrics.index', $shop)],
            ],
            'commissions', 'authorizations' => [
                ['label' => 'Administrar vendedores', 'url' => route('seller.shops.sellers.index', $shop)],
                ['label' => 'Configuración de tienda', 'url' => route('seller.shops.edit', $shop)],
            ],
            default => [
                ['label' => 'Configuración de tienda', 'url' => route('seller.shops.edit', $shop)],
                ['label' => 'Volver al resumen', 'url' => route('seller.dashboard')],
            ],
        };

        return view('seller.features.show', [
            'shop' => $shop,
            'featureKey' => $feature,
            'feature' => self::FEATURES[$feature],
            'related' => $related,
        ]);
    }
}
