<?php

namespace App\Http\Controllers;

use App\Models\AttributeDefinition;
use App\Models\AuthorizationRequest;
use App\Models\BusinessPartner;
use App\Models\CommercialQuote;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\Expense;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\SupportRequest;
use App\Services\BusinessDashboardService;
use App\Services\DailyCloseService;
use App\Services\InventoryService;
use App\Services\SellerMenuService;
use App\Services\ShopAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        'reports' => ['group' => 'Análisis', 'title' => 'Reportes', 'description' => 'Construye reportes operativos y financieros exportables para tomar mejores decisiones.', 'status' => 'Operativo'],
        'commissions' => ['group' => 'Equipo', 'title' => 'Comisiones', 'description' => 'Define reglas y consulta comisiones por vendedor y por venta.', 'status' => 'En preparación'],
        'authorizations' => ['group' => 'Equipo', 'title' => 'Autorizaciones', 'description' => 'Controla qué acciones requieren aprobación del propietario.', 'status' => 'En preparación'],
        'accountant' => ['group' => 'Ajustes', 'title' => 'Contador', 'description' => 'Prepara el acceso de tu contador a la información financiera necesaria.', 'status' => 'En preparación'],
        'account' => ['group' => 'Ajustes', 'title' => 'Mi cuenta', 'description' => 'Administra tus datos personales, seguridad, sesión y preferencias de MiCatalogo.', 'status' => 'En preparación'],
        'updates' => ['group' => 'Ajustes', 'title' => 'Novedades', 'description' => 'Conoce las mejoras y nuevas funciones de MiCatalogo.', 'status' => 'En preparación'],
        'help' => ['group' => 'Ajustes', 'title' => 'Ayuda', 'description' => 'Encuentra guías rápidas para aprender a usar cada parte de tu negocio.', 'status' => 'En preparación'],
        'practice' => ['group' => 'Ajustes', 'title' => 'Practicar sin miedo', 'description' => 'Aprende los flujos principales con datos de práctica sin tocar tu operación real.', 'status' => 'En preparación'],
        'support' => ['group' => 'Ajustes', 'title' => 'Soporte', 'description' => 'Envía una solicitud y consulta el estado de la ayuda de tu equipo.', 'status' => 'En preparación'],
        'storefront' => ['group' => 'Catálogo', 'title' => 'Mi tienda', 'description' => 'Revisa la vitrina pública y la experiencia que ven tus clientes.', 'status' => 'Operativo'],
        'metrics' => ['group' => 'Catálogo', 'title' => 'Métricas y QR', 'description' => 'Consulta visitas, contactos de WhatsApp y el código QR de tu catálogo.', 'status' => 'Operativo'],
        'public_catalog' => ['group' => 'Catálogo', 'title' => 'Compartir catálogo', 'description' => 'Comparte el enlace de tu catálogo público con tus clientes.', 'status' => 'Operativo'],
        'pricing' => ['group' => 'Catálogo', 'title' => 'Precios automáticos', 'description' => 'Define margen y redondeo para actualizar precios al recibir nuevos costos.', 'status' => 'Operativo'],
        'import' => ['group' => 'Catálogo', 'title' => 'Importar', 'description' => 'Carga productos desde Excel, CSV o PDF y revisa la previsualización antes de guardar.', 'status' => 'Operativo'],
        'expenses' => ['group' => 'Finanzas', 'title' => 'Gastos', 'description' => 'Registra y consulta los gastos que afectan la caja y la rentabilidad.', 'status' => 'Operativo'],
        'shop_settings' => ['group' => 'Ajustes', 'title' => 'Configuración de tienda', 'description' => 'Personaliza identidad, catálogo, pedidos y reglas operativas de la tienda.', 'status' => 'Operativo'],
        'sellers' => ['group' => 'Ajustes', 'title' => 'Equipo', 'description' => 'Administra vendedores, permisos y comisiones sin exponer funciones administrativas.', 'status' => 'Operativo'],
    ];

    public function show(
        Request $request,
        Shop $shop,
        string $feature,
        SellerMenuService $menus,
        ?BusinessDashboardService $dashboard = null
    ): View {
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

        $related = array_values(array_filter($related, function ($item) use ($request, $shop, $menus): bool {
            $path = parse_url($item['url'], PHP_URL_PATH);
            $route = app('router')->getRoutes()->match(Request::create($path));
            $menu = match ($route->getName()) {
                'seller.shops.pos' => 'sales',
                'seller.shops.business' => 'finance',
                'seller.shops.inventory.index', 'seller.shops.inventory.lots' => 'inventory',
                'seller.shops.products.index' => 'products',
                'seller.shops.pricing.index' => 'pricing',
                'seller.shops.customers.index' => 'customers',
                'seller.shops.metrics.index' => 'metrics',
                'seller.shops.sellers.index' => 'sellers',
                'seller.shops.edit' => 'shop_settings',
                default => null,
            };

            return $menu === null || in_array($menu, $menus->visibleForUser($shop, $request->user()), true);
        }));

        return view('seller.features.show', [
            'shop' => $shop,
            'featureKey' => $feature,
            'feature' => self::FEATURES[$feature],
            'related' => $related,
            'module' => $this->moduleData(
                $feature,
                $shop,
                $dashboard ?: app(BusinessDashboardService::class),
                $request->query('period'),
                $request->query('q'),
                $request->query('status')
            ),
        ]);
    }

    /**
     * Mobile read model for the same feature modules exposed by the web panel.
     * Mutations continue to use their dedicated API resources so this endpoint
     * cannot accidentally create or alter accounting data.
     */
    public function api(
        Request $request,
        Shop $shop,
        string $feature,
        SellerMenuService $menus,
        ?BusinessDashboardService $dashboard = null
    ): JsonResponse {
        abort_unless(isset(self::FEATURES[$feature]), 404);
        abort_unless(in_array($feature, $menus->visibleForUser($shop, $request->user()), true), 403);

        return response()->json([
            'feature_key' => $feature,
            'feature' => self::FEATURES[$feature],
            'module' => $this->moduleData(
                $feature,
                $shop,
                $dashboard ?: app(BusinessDashboardService::class),
                $request->query('period'),
                $request->query('q'),
                $request->query('status')
            ),
        ]);
    }

    public function updateAttribute(
        Request $request,
        Shop $shop,
        AttributeDefinition $attribute,
        SellerMenuService $menus
    ): RedirectResponse {
        abort_unless($menus->canManage($shop, $request->user()), 403);
        abort_unless($attribute->shop_id === $shop->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'filterable' => ['sometimes', 'boolean'],
            'required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $name = trim((string) $data['name']);
        abort_if($name === '', 422, 'El nombre del atributo no puede estar vacío.');

        DB::transaction(function () use ($shop, $attribute, $data, $name): void {
            $locked = AttributeDefinition::query()->whereKey($attribute->getKey())->lockForUpdate()->firstOrFail();
            $duplicate = AttributeDefinition::query()
                ->where('shop_id', $shop->id)
                ->whereKeyNot($locked->getKey())
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->exists();
            abort_if($duplicate, 422, 'Ya existe otro atributo con ese nombre.');

            $locked->update([
                'name' => $name,
                'slug' => Str::slug($name),
                'filterable' => array_key_exists('filterable', $data) ? (bool) $data['filterable'] : $locked->filterable,
                'required' => array_key_exists('required', $data) ? (bool) $data['required'] : $locked->required,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $locked->is_active,
            ]);
        });

        $active = (bool) ($data['is_active'] ?? true);

        return back()->with('status', $active ? 'Atributo actualizado.' : 'Atributo retirado. Sus valores históricos se conservaron.');
    }

    public function exportReports(
        Request $request,
        Shop $shop,
        BusinessDashboardService $dashboard
    ): StreamedResponse {
        $range = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'format' => ['nullable', 'string', 'in:csv,xlsx'],
        ]);
        $from = $range['from'] ?? now()->startOfMonth()->toDateString();
        $to = $range['to'] ?? now()->toDateString();
        $format = $range['format'] ?? 'csv';
        $summary = $dashboard->getSummary($shop, $from, $to, 'profit', 'desc');
        $rows = collect($summary['profitability'])->map(fn (array $product): array => [
            $product['product_name'],
            (int) $product['units'],
            round((float) $product['revenue'], 2),
            round((float) $product['cost'], 2),
            round((float) $product['gross_profit'], 2),
            round((float) $product['margin_percent'], 1),
            $product['has_unknown_cost'] ? 'Costo pendiente' : 'Calculada',
        ])->values()->all();
        $filename = 'micatalogo-reportes-'.$shop->slug.'-'.$from.'-'.$to.'.'.$format;

        if ($format === 'xlsx') {
            return response()->streamDownload(function () use ($summary, $rows): void {
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Rentabilidad');
                $sheet->fromArray([
                    ['Producto', 'Unidades', 'Ventas netas', 'Costo FIFO', 'Ganancia bruta', 'Margen %', 'Estado'],
                    ...$rows,
                ]);
                $sheet->fromArray([
                    ['Resumen del período', null, null, null, null, null, null],
                    ['Desde', $summary['from'], 'Hasta', $summary['to'], null, null, null],
                    ['Ventas netas', $summary['period']['net_sales'], 'Costo FIFO', $summary['period']['fifo_cogs'], 'Ganancia bruta', $summary['period']['gross_profit'], null],
                ], null, 'A'.(count($rows) + 3));
                foreach (range('A', 'G') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }
                (new Xlsx($spreadsheet))->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        return response()->streamDownload(function () use ($summary, $rows): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Producto', 'Unidades', 'Ventas netas', 'Costo FIFO', 'Ganancia bruta', 'Margen %', 'Estado']);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fputcsv($handle, []);
            fputcsv($handle, ['Resumen del período']);
            fputcsv($handle, ['Desde', $summary['from'], 'Hasta', $summary['to']]);
            fputcsv($handle, ['Ventas netas', $summary['period']['net_sales'], 'Costo FIFO', $summary['period']['fifo_cogs'], 'Ganancia bruta', $summary['period']['gross_profit']]);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Build read models from the existing accounting/catalog domains.
     * Read models deliberately reuse existing accounting/catalog domains. This
     * keeps every menu useful without inventing transactions or demo records.
     *
     * @return array{kind: string, kpis: array<int, array{label: string, value: string, tone: string}>, rows: array<int, array<string, mixed>>, note: ?string}
     */
    private function moduleData(
        string $feature,
        Shop $shop,
        BusinessDashboardService $dashboard,
        ?string $period = null,
        ?string $search = null,
        ?string $status = null
    ): array
    {
        return match ($feature) {
            'sales' => $this->salesData($shop),
            'quotes' => $this->quotesData($shop),
            'orders' => $this->ordersData($shop, $status, false, $search),
            'encargos' => $this->encargosData($shop, $status, $search),
            'shipments' => $this->ordersData($shop, $status, true, $search),
            'day_close' => $this->dayCloseData($shop, $period),
            'containers', 'loads', 'suppliers', 'purchase_invoices' => $this->purchasingData($shop, $feature),
            'photos' => $this->photosData($shop),
            'services' => $this->servicesData($shop, $search),
            'price_health' => $this->priceHealthData($shop, $status, $search),
            'decants' => $this->decantsData($shop),
            'attributes' => $this->attributesData($shop, $search),
            'credit' => $this->creditData($shop),
            'inventory_adjustments' => $this->inventoryAdjustmentsData($shop),
            'partners' => $this->partnersData($shop),
            'reports' => $this->reportsData($shop, $dashboard),
            'commissions' => $this->commissionsData($shop, $period),
            'authorizations' => $this->authorizationsData($shop),
            'accountant' => $this->accountantData($shop, $dashboard),
            'account' => $this->accountData($shop),
            'updates' => $this->updatesData(),
            'help' => $this->helpData(),
            'practice' => $this->practiceData($shop),
            'support' => $this->supportData($shop),
            'storefront' => $this->storefrontData($shop),
            'metrics' => $this->metricsData($shop),
            'public_catalog' => $this->publicCatalogData($shop),
            'pricing' => $this->pricingData($shop),
            'import' => $this->importData($shop),
            'expenses' => $this->expensesData($shop),
            'shop_settings' => $this->shopSettingsData($shop),
            'sellers' => $this->sellersData($shop),
            default => [
                'kind' => 'prepared',
                'kpis' => [],
                'rows' => [],
                'note' => null,
            ],
        };
    }

    private function storefrontData(Shop $shop): array
    {
        $products = $shop->products();
        $published = (clone $products)->where('availability_status', 'available')->where('moderation_status', 'active')->count();
        $withPhoto = (clone $products)->whereHas('images')->count();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Productos publicados', 'value' => number_format($published), 'tone' => 'blue'],
                ['label' => 'Con fotografía', 'value' => number_format($withPhoto), 'tone' => 'emerald'],
                ['label' => 'Pedidos recibidos', 'value' => number_format($shop->orders()->count()), 'tone' => 'amber'],
            ],
            'rows' => [
                ['primary' => 'Vitrina pública', 'secondary' => route('shops.show', $shop), 'value' => 'Abrir', 'status' => 'Disponible'],
                ['primary' => 'Identidad de la tienda', 'secondary' => filled($shop->logo_url) && filled($shop->cover_url) ? 'Logo y portada configurados' : 'Faltan elementos de presentación', 'value' => filled($shop->description) ? 'Completa' : 'Revisar', 'status' => 'Configuración'],
                ['primary' => 'Pedidos por WhatsApp', 'secondary' => filled($shop->whatsapp_number) ? $shop->whatsapp_number : 'Número no configurado', 'value' => filled($shop->whatsapp_number) ? 'Activo' : 'Revisar', 'status' => 'Canal de venta'],
            ],
            'note' => 'La vitrina usa los mismos productos, precios, imágenes y disponibilidad del catálogo operativo; no mantiene una copia separada.',
            'actions' => [
                ['label' => 'Abrir mi tienda', 'url' => route('seller.shops.storefront', $shop), 'tone' => 'primary'],
                ['label' => 'Configurar apariencia', 'url' => route('seller.shops.edit', $shop), 'tone' => 'secondary'],
            ],
        ];
    }

    private function metricsData(Shop $shop): array
    {
        $metrics = app(ShopAnalyticsService::class)->getMetricsSummary($shop);

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Visitas · 30 días', 'value' => number_format($metrics['views_30d']), 'tone' => 'blue'],
                ['label' => 'Contactos WhatsApp · 30 días', 'value' => number_format($metrics['clicks_30d']), 'tone' => 'emerald'],
                ['label' => 'Conversión a WhatsApp', 'value' => number_format($metrics['conversion_rate_30d'], 1).'%', 'tone' => 'amber'],
            ],
            'rows' => collect($metrics['top_by_clicks'])->map(fn ($product) => [
                'primary' => $product->name,
                'secondary' => number_format($product->views_count).' visita(s) en catálogo',
                'value' => number_format($product->clicks_count).' contacto(s)',
                'status' => 'Más consultado',
            ])->all(),
            'note' => 'Las métricas se agregan por día y por producto. El QR y el enlace público llevan a la misma vitrina de la tienda.',
            'actions' => [
                ['label' => 'Abrir métricas completas', 'url' => route('seller.shops.metrics.index', $shop), 'tone' => 'primary'],
                ['label' => 'Descargar QR', 'url' => route('seller.shops.qr.download', $shop), 'tone' => 'secondary'],
            ],
        ];
    }

    private function publicCatalogData(Shop $shop): array
    {
        $url = route('shops.show', $shop);
        $active = $shop->products()->where('availability_status', 'available')->where('moderation_status', 'active')->count();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Estado del catálogo', 'value' => $shop->status === 'active' ? 'Activo' : ucfirst((string) $shop->status), 'tone' => $shop->status === 'active' ? 'emerald' : 'amber'],
                ['label' => 'Productos visibles', 'value' => number_format($active), 'tone' => 'blue'],
                ['label' => 'Enlace', 'value' => 'Listo para compartir', 'tone' => 'slate'],
            ],
            'rows' => [
                ['primary' => 'Enlace público', 'secondary' => $url, 'value' => 'Copiar/abrir', 'status' => 'Disponible'],
                ['primary' => 'Pedidos online', 'secondary' => filled($shop->whatsapp_number) ? 'WhatsApp configurado' : 'Configura el WhatsApp para recibir pedidos', 'value' => filled($shop->whatsapp_number) ? 'Activo' : 'Pendiente', 'status' => 'Canal de venta'],
            ],
            'note' => 'El enlace público siempre refleja los cambios de inventario, precios e imágenes sin republicar manualmente.',
            'actions' => [
                ['label' => 'Abrir catálogo público', 'url' => $url, 'tone' => 'primary'],
                ['label' => 'Ver QR y métricas', 'url' => route('seller.shops.metrics.index', $shop), 'tone' => 'secondary'],
            ],
        ];
    }

    private function pricingData(Shop $shop): array
    {
        $products = $shop->products()->where('sale_unit', '!=', 'decant')->with('inventory')->orderBy('name')->limit(100)->get();
        $rules = DB::table('product_price_rules')->whereIn('product_id', $products->pluck('id'))->get()->keyBy('product_id');
        $pending = $rules->filter(fn ($rule) => $rule->pending_price !== null)->count();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Productos evaluados', 'value' => number_format($products->count()), 'tone' => 'blue'],
                ['label' => 'Reglas configuradas', 'value' => number_format($rules->count()), 'tone' => 'emerald'],
                ['label' => 'Bajadas por aprobar', 'value' => number_format($pending), 'tone' => $pending ? 'amber' : 'slate'],
            ],
            'rows' => $products->map(fn (Product $product) => [
                'product_id' => (string) $product->public_id,
                'primary' => $product->name,
                'secondary' => $product->inventory?->cost_price !== null ? 'Costo actual RD$ '.number_format((float) $product->inventory->cost_price, 2) : 'Sin costo registrado',
                'value' => 'Venta RD$ '.number_format($product->currentPrice(), 2),
                'status' => isset($rules[$product->id]) ? ($rules[$product->id]->pending_price !== null ? 'Aprobación pendiente' : 'Regla activa') : 'Sin regla',
                'margin_percent' => isset($rules[$product->id]) ? (string) $rules[$product->id]->margin_percent : '40',
                'round_step' => isset($rules[$product->id]) ? number_format(((int) $rules[$product->id]->round_step_cents) / 100, 2, '.', '') : '1.00',
                'auto_increase' => (bool) ($rules[$product->id]->auto_increase ?? false),
                'pending_price' => isset($rules[$product->id]) && $rules[$product->id]->pending_price !== null ? number_format((float) $rules[$product->id]->pending_price, 2, '.', '') : null,
            ])->all(),
            'note' => 'Cada lote conserva su costo. Las subidas automáticas pueden aplicarse según la regla y las bajadas quedan pendientes de aprobación.',
            'actions' => [
                ['label' => 'Administrar reglas', 'url' => route('seller.shops.pricing.index', $shop), 'tone' => 'primary'],
                ['label' => 'Ver ganancias', 'url' => route('seller.shops.business', $shop), 'tone' => 'secondary'],
            ],
        ];
    }

    private function importData(Shop $shop): array
    {
        $sessions = $shop->importSessions()->with('user')->limit(10)->get();
        $records = DB::table('product_import_records')->where('shop_id', $shop->id);

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Importaciones recientes', 'value' => number_format($sessions->count()), 'tone' => 'blue'],
                ['label' => 'Filas importadas', 'value' => number_format((clone $records)->where('data_status', 'imported')->count()), 'tone' => 'emerald'],
                ['label' => 'Con advertencias/error', 'value' => number_format((clone $records)->whereIn('data_status', ['warning', 'error', 'invalid'])->count()), 'tone' => 'amber'],
            ],
            'rows' => $sessions->map(fn ($session) => [
                'primary' => $session->original_filename,
                'secondary' => ($session->user?->name ?: 'Sistema').' · '.($session->created_at?->format('d/m/Y H:i') ?: 'Sin fecha'),
                'value' => number_format($session->valid_rows).' válidas / '.number_format($session->total_rows).' filas',
                'status' => $session->status === 'confirmed' ? 'Confirmada' : ($session->isExpired() ? 'Expirada' : 'Previsualizada'),
            ])->all(),
            'note' => 'El importador acepta Excel, CSV y PDF con previsualización, mapeo de columnas, detección de categorías y carga opcional de imágenes. Confirmar es el único paso que escribe en el catálogo.',
            'actions' => [
                ['label' => 'Abrir importador', 'url' => route('seller.shops.products.import.create', $shop), 'tone' => 'primary'],
                ['label' => 'Ver productos', 'url' => route('seller.shops.products.index', $shop), 'tone' => 'secondary'],
            ],
        ];
    }

    private function expensesData(Shop $shop): array
    {
        $expenses = $shop->expenses()->with('category')->latest('occurred_at')->limit(30)->get();
        $incurred = (float) $shop->expenses()->sum('amount');
        $paid = (float) $shop->expenses()->sum('amount_paid');

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Gastos registrados', 'value' => number_format($shop->expenses()->count()), 'tone' => 'blue'],
                ['label' => 'Total incurrido', 'value' => $this->money($incurred), 'tone' => 'rose'],
                ['label' => 'Pendiente de pago', 'value' => $this->money(max(0, $incurred - $paid)), 'tone' => 'amber'],
            ],
            'rows' => $expenses->map(fn (Expense $expense) => [
                'primary' => $expense->description,
                'secondary' => ($expense->category?->name ?: 'Sin categoría').' · '.($expense->occurred_at?->format('d/m/Y H:i') ?: 'Sin fecha'),
                'value' => $this->money($expense->amount),
                'status' => $expense->statusLabel(),
            ])->all(),
            'note' => 'Los gastos afectan la rentabilidad al registrarse y afectan caja solo cuando se paga el importe correspondiente.',
            'actions' => [
                ['label' => 'Registrar gasto', 'url' => route('seller.shops.expenses.index', $shop), 'tone' => 'primary'],
                ['label' => 'Ver caja', 'url' => route('seller.shops.cash.index', $shop), 'tone' => 'secondary'],
            ],
        ];
    }

    private function shopSettingsData(Shop $shop): array
    {
        $checks = [
            ['label' => 'Identidad visual', 'value' => filled($shop->logo_url) && filled($shop->cover_url) ? 'Logo y portada listos' : 'Falta logo o portada', 'status' => filled($shop->logo_url) && filled($shop->cover_url) ? 'Completo' : 'Revisar'],
            ['label' => 'Datos de contacto', 'value' => filled($shop->whatsapp_number) ? $shop->whatsapp_number : 'WhatsApp no configurado', 'status' => filled($shop->whatsapp_number) ? 'Completo' : 'Revisar'],
            ['label' => 'Entrega', 'value' => $shop->offers_shipping ? 'Ofrece envíos' : 'Retiro/entrega por coordinar', 'status' => 'Configurado'],
            ['label' => 'Perfil de negocio', 'value' => $shop->business_type ?: 'General', 'status' => 'Activo'],
        ];

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Estado', 'value' => $shop->status === 'active' ? 'Activo' : ucfirst((string) $shop->status), 'tone' => $shop->status === 'active' ? 'emerald' : 'amber'],
                ['label' => 'Catálogo', 'value' => number_format($shop->products()->count()).' productos', 'tone' => 'blue'],
                ['label' => 'Plan', 'value' => $shop->planLabel(), 'tone' => 'slate'],
            ],
            'rows' => array_map(fn (array $check) => ['primary' => $check['label'], 'secondary' => $check['value'], 'value' => $check['status'], 'status' => 'Configuración'], $checks),
            'note' => 'Los cambios de configuración se aplican sobre la tienda activa y se reflejan en la vitrina pública y en la aplicación.',
            'actions' => [
                ['label' => 'Editar configuración', 'url' => route('seller.shops.edit', $shop), 'tone' => 'primary'],
                ['label' => 'Ver mi tienda', 'url' => route('seller.shops.storefront', $shop), 'tone' => 'secondary'],
            ],
        ];
    }

    private function sellersData(Shop $shop): array
    {
        $sellers = $shop->sellers()->with('user')->where('is_active', true)->get();
        $stats = Invoice::query()
            ->where('shop_id', $shop->id)
            ->whereNotNull('salesperson_id')
            ->selectRaw('salesperson_id, COUNT(*) as sales_count, COALESCE(SUM(total), 0) as sales_total, COALESCE(SUM(commission_amount), 0) as commission_total')
            ->groupBy('salesperson_id')
            ->get()
            ->keyBy('salesperson_id');

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Vendedores activos', 'value' => number_format($sellers->count()), 'tone' => 'blue'],
                ['label' => 'Ventas asignadas', 'value' => number_format($stats->sum('sales_count')), 'tone' => 'emerald'],
                ['label' => 'Comisiones calculadas', 'value' => $this->money($stats->sum('commission_total')), 'tone' => 'amber'],
            ],
            'rows' => $sellers->map(function ($seller) use ($stats): array {
                $stat = $stats->get($seller->user_id);

                return [
                    'primary' => $seller->user?->name ?: 'Vendedor',
                    'secondary' => ($seller->user?->email ?: 'Sin correo').' · '.($seller->commission_type === 'fixed' ? 'Monto fijo' : 'Porcentaje').' '.number_format((float) $seller->commission_value, 2),
                    'value' => number_format((int) ($stat?->sales_count ?? 0)).' venta(s) · '.$this->money($stat?->commission_total ?? 0),
                    'status' => 'Activo',
                ];
            })->all(),
            'note' => 'Los permisos se asignan por tienda. Cada venta conserva el tipo y valor de comisión vigente al momento de cobrar.',
            'actions' => [
                ['label' => 'Administrar equipo', 'url' => route('seller.shops.sellers.index', $shop), 'tone' => 'primary'],
                ['label' => 'Ver comisiones', 'url' => route('seller.shops.feature', [$shop, 'feature' => 'commissions']), 'tone' => 'secondary'],
            ],
        ];
    }

    private function quotesData(Shop $shop): array
    {
        $today = now()->toDateString();
        $openQuotes = fn () => $shop->quotes()->whereIn('status', ['draft', 'sent']);
        $activeQuotes = fn () => $openQuotes()
            ->where(function ($query) use ($today): void {
                $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today);
            });
        $expiredQuotes = fn () => $openQuotes()
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', $today);
        $quotes = $shop->quotes()->with('items')->latest()->limit(30)->get();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Cotizaciones', 'value' => number_format($shop->quotes()->count()), 'tone' => 'blue'],
                ['label' => 'Vigentes', 'value' => number_format($activeQuotes()->count()), 'tone' => 'emerald'],
                ['label' => 'Monto por convertir', 'value' => $this->money($activeQuotes()->sum('total')), 'tone' => 'blue'],
                ['label' => 'Vencidas', 'value' => number_format($expiredQuotes()->count()), 'tone' => 'rose'],
                ['label' => 'Convertidas a venta', 'value' => number_format($shop->quotes()->where('status', 'converted')->count()), 'tone' => 'emerald'],
            ],
            'rows' => $quotes->map(fn (CommercialQuote $quote) => [
                'primary' => $quote->quote_number,
                'secondary' => ($quote->customer_name ?: 'Cliente sin nombre').' · '.number_format($quote->items->sum('quantity')).' artículo(s)',
                'value' => $this->money($quote->total),
                'status' => $quote->status !== 'converted' && $quote->status !== 'cancelled' && $quote->valid_until?->isBefore(today())
                    ? 'Vencida'
                    : match ($quote->status) {
                        'converted' => 'Convertida', 'sent' => 'Enviada', default => 'Borrador'
                    },
                'can_convert' => $quote->converted_invoice_id === null
                    && $quote->status !== 'cancelled'
                    && ! ($quote->valid_until && $quote->valid_until->isBefore(today())),
                'id' => $quote->public_id,
            ])->all(),
            'quoteProducts' => $shop->products()->with(['shopCategory', 'globalCategory', 'inventory', 'images', 'primaryImage'])->whereIn('availability_status', ['available', 'out_of_stock'])->orderBy('name')->limit(1000)->get()->map(fn (Product $product) => [
                'id' => $product->public_id,
                'name' => $product->name,
                'price' => number_format($product->currentPrice(), 2, '.', ''),
                'category' => $product->shopCategory?->name ?: ($product->globalCategory?->name ?: 'Sin categoría'),
                'brand' => $product->brand ?: '',
                'code' => $product->product_code ?: '',
                'stock' => $product->inventory?->track_inventory ? (int) $product->inventory->stock_quantity : null,
                'image_url' => $product->primaryImage?->url,
                'sale_unit_label' => $product->saleUnitLabel(),
            ])->all(),
            'note' => 'Cada cotización queda separada de la contabilidad hasta que la conviertas. Al convertirla se valida stock, se consume FIFO y se crea una venta única.',
            'actions' => [
                ['label' => 'Ir a Terminal', 'url' => route('seller.shops.pos', $shop), 'tone' => 'primary'],
                ['label' => 'Ver pedidos', 'url' => route('seller.shops.feature', [$shop, 'feature' => 'orders']), 'tone' => 'secondary'],
            ],
        ];
    }

    private function purchasingData(Shop $shop, string $feature): array
    {
        $documents = $shop->purchaseDocuments()->with(['supplier', 'items.product', 'parentDocument'])->latest()->limit(30)->get();
        $suppliers = $shop->suppliers()->get();
        $products = $shop->products()->whereHas('inventory', fn ($query) => $query->where('track_inventory', true))->orderBy('name')->limit(300)->get();

        $labels = [
            'containers' => ['Contenedores', 'Agrupaciones de recepción', 'Compras agrupadas'],
            'loads' => ['Cargas', 'Recepciones en inventario', 'Movimientos de reposición'],
            'suppliers' => ['Suplidores', 'Fuentes de compra', 'Productos con costo'],
            'purchase_invoices' => ['Facturas de compra', 'Recepciones valorizadas', 'Lotes con costo'],
        ];
        [$title, $primaryLabel, $secondaryLabel] = $labels[$feature];
        $rows = $documents->filter(fn (PurchaseDocument $document) => match ($feature) {
            'containers' => $document->type === 'container',
            'loads' => $document->type === 'load',
            'purchase_invoices' => $document->type === 'purchase_invoice',
            default => true,
        })->map(fn (PurchaseDocument $document) => [
            'id' => $document->public_id,
            'primary' => $document->document_number,
            'secondary' => ($document->supplier?->name ?: 'Sin suplidor').' · '.($document->status === 'debt' ? 'Factura '.($document->invoice_date?->format('d/m/Y') ?: 'sin fecha') : ($document->received_at?->format('d/m/Y H:i') ?: ($document->expected_at?->format('d/m/Y') ?: 'Sin fecha'))).($document->due_at ? ' · vence '.$document->due_at->format('d/m/Y') : ''),
            'value' => $this->money($document->total),
            'status' => $document->status === 'draft' ? 'Borrador' : ($document->status === 'debt' ? 'Pendiente de pago' : 'Recibida'),
            'can_receive' => $document->status === 'draft',
            'parent_document_id' => $document->parentDocument?->public_id,
            'payment_status' => $document->payment_status,
            'invoice_date' => $document->invoice_date?->format('d/m/Y'),
            'due_at' => $document->due_at?->format('d/m/Y'),
            'is_debt' => $document->status === 'debt',
        ])->values()->all();
        if ($feature === 'suppliers') {
            $rows = $suppliers->map(fn (Supplier $supplier) => [
                'primary' => $supplier->name,
                'secondary' => ($supplier->invoice_currency ? 'Factura en '.$supplier->invoice_currency.' · ' : '').($supplier->phone ?: ($supplier->email ?: 'Sin contacto')),
                'value' => number_format($supplier->purchaseDocuments()->count()).' compra(s)',
                'status' => 'Activo',
            ])->all();
        }

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => $primaryLabel, 'value' => number_format(count($rows)), 'tone' => 'blue'],
                ['label' => $secondaryLabel, 'value' => number_format($feature === 'suppliers' ? $suppliers->count() : $documents->where('status', 'received')->count()), 'tone' => 'emerald'],
                ['label' => 'Productos controlados', 'value' => number_format($products->count()), 'tone' => 'slate'],
            ],
            'rows' => $rows,
            'suppliers' => $suppliers->map(fn (Supplier $supplier) => ['id' => $supplier->public_id, 'name' => $supplier->name, 'invoice_currency' => $supplier->invoice_currency])->all(),
            'purchaseProducts' => $products->map(fn (Product $product) => [
                'id' => $product->public_id,
                'name' => $product->name,
                'code' => $product->product_code ?: ($product->barcode ?: ''),
                'cost' => number_format((float) ($product->inventory?->cost_price ?? 0), 2, '.', ''),
            ])->all(),
            'note' => "{$title} registra recepciones reales, crea un lote con el costo indicado y mantiene el historial FIFO. No se modifica ningún lote anterior.",
            'actions' => [
                ['label' => 'Ver inventario', 'url' => route('seller.shops.inventory.index', $shop), 'tone' => 'primary'],
                ['label' => 'Ver lotes y costos FIFO', 'url' => route('seller.shops.inventory.lots', $shop), 'tone' => 'secondary'],
            ],
        ];
    }

    private function servicesData(Shop $shop, ?string $search = null): array
    {
        $search = trim((string) $search);
        $products = $shop->products()
            ->where('sale_unit', 'service')
            ->with('inventory')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('product_code', 'like', "%{$search}%");
            }))
            ->latest()
            ->limit(100)
            ->get();
        $serviceIds = $products->pluck('id');
        $periodItems = $serviceIds->isEmpty()
            ? collect()
            : InvoiceItem::query()
                ->whereIn('product_id', $serviceIds)
                ->whereHas('invoice', fn ($query) => $query->where('shop_id', $shop->id)->where('issued_at', '>=', now()->subDays(30))->where('status', '!=', 'void'))
                ->get(['line_total', 'total_cost_cents']);
        $revenue = $periodItems->sum(fn (InvoiceItem $item): float => (float) $item->line_total);
        $cost = $periodItems->sum(fn (InvoiceItem $item): float => ((int) ($item->total_cost_cents ?? 0)) / 100);

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Servicios', 'value' => number_format($products->count()), 'tone' => 'blue'],
                ['label' => 'Cobrado · 30 días', 'value' => $this->money($revenue), 'tone' => 'emerald'],
                ['label' => 'Ganancia · 30 días', 'value' => $this->money($revenue - $cost), 'tone' => 'amber'],
            ],
            'rows' => $products->map(fn (Product $product) => [
                'id' => (string) $product->id,
                'primary' => $product->name,
                'secondary' => 'Precio RD$ '.number_format($product->currentPrice(), 2).' · '.($product->description ?: 'Sin descripción'),
                'value' => $product->inventory?->cost_price !== null ? 'Insumos RD$ '.number_format((float) $product->inventory->cost_price, 2) : 'Sin costo de insumos',
                'status' => $product->moderation_status->value === 'active' ? 'Publicado · cobrable' : 'Borrador · oculto en Terminal',
                'edit_url' => route('seller.shops.products.edit', [$shop, $product]),
            ])->all(),
            'filters' => [
                'search' => $search,
                'count' => $products->count(),
            ],
            'note' => 'Los servicios se cobran desde Terminal, factura y caja sin descontar inventario. El costo de insumos es opcional y se usa para calcular la ganancia real.',
            'actions' => [
                ['label' => 'Nuevo servicio', 'url' => route('seller.shops.products.create', $shop).'?sale_unit=service', 'tone' => 'primary'],
                ['label' => 'Ir a Terminal', 'url' => route('seller.shops.pos', $shop), 'tone' => 'secondary'],
            ],
        ];
    }

    private function inventoryAdjustmentsData(Shop $shop): array
    {
        $movements = InventoryMovement::query()->whereIn('product_id', $shop->products()->select('id'))->where('type', 'adjustment')->latest()->limit(30)->with(['product', 'user'])->get();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Ajustes registrados', 'value' => number_format($movements->count()), 'tone' => 'blue'],
                ['label' => 'Unidades afectadas', 'value' => number_format($movements->sum(fn (InventoryMovement $movement) => abs((int) $movement->quantity))), 'tone' => 'amber'],
                ['label' => 'Productos', 'value' => number_format($movements->pluck('product_id')->unique()->count()), 'tone' => 'slate'],
            ],
            'rows' => $movements->map(fn (InventoryMovement $movement) => [
                'primary' => $movement->product?->name ?: 'Producto eliminado',
                'secondary' => ($movement->notes ?: 'Ajuste manual').' · '.($movement->user?->name ?: 'Sistema'),
                'value' => sprintf('%+d unidades', (int) $movement->quantity),
                'status' => 'Auditado',
            ])->all(),
            'note' => 'Cada ajuste conserva stock anterior, stock nuevo, usuario y motivo; no se reescribe el historial de ventas.',
            'actions' => [['label' => 'Abrir inventario', 'url' => route('seller.shops.inventory.index', $shop), 'tone' => 'primary']],
        ];
    }

    private function partnersData(Shop $shop): array
    {
        $partners = $shop->partners()->with('transactions')->get();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Socios activos', 'value' => number_format($partners->count()), 'tone' => 'blue'],
                ['label' => 'Aportes', 'value' => $this->money($partners->flatMap->transactions->where('type', 'contribution')->sum('amount')), 'tone' => 'emerald'],
                ['label' => 'Retiros', 'value' => $this->money($partners->flatMap->transactions->whereIn('type', ['withdrawal', 'distribution'])->sum('amount')), 'tone' => 'rose'],
            ],
            'rows' => $partners->map(fn (BusinessPartner $partner) => [
                'primary' => $partner->name,
                'secondary' => number_format((float) $partner->ownership_percent, 2).'% de participación · '.($partner->email ?: ($partner->phone ?: 'Sin contacto')),
                'value' => $this->money($partner->transactions->where('type', 'contribution')->sum('amount') - $partner->transactions->whereIn('type', ['withdrawal', 'distribution'])->sum('amount')),
                'status' => 'Activo',
                'id' => $partner->public_id,
            ])->all(),
            'partners' => $partners->map(fn (BusinessPartner $partner) => ['id' => $partner->public_id, 'name' => $partner->name])->all(),
            'note' => 'Los aportes y retiros se registran mediante la sesión de caja abierta y quedan enlazados al movimiento contable correspondiente.',
            'actions' => [['label' => 'Ver caja', 'url' => route('seller.shops.cash.index', $shop), 'tone' => 'primary']],
        ];
    }

    private function authorizationsData(Shop $shop): array
    {
        $sellers = $shop->sellers()->with('user')->where('is_active', true)->get();
        $pendingRequests = $shop->authorizationRequests()
            ->with('requester')
            ->where('status', 'pending')
            ->latest('created_at')
            ->get();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Usuarios con acceso', 'value' => number_format($sellers->count()), 'tone' => 'blue'],
                ['label' => 'Con menú personalizado', 'value' => number_format($sellers->filter(fn ($seller) => is_array($seller->menu_permissions) && count($seller->menu_permissions))->count()), 'tone' => 'emerald'],
                ['label' => 'Pendientes de revisar', 'value' => number_format($pendingRequests->count()), 'tone' => $pendingRequests->count() ? 'amber' : 'slate'],
            ],
            'rows' => $sellers->map(fn ($seller) => [
                'primary' => $seller->user?->name ?: 'Vendedor',
                'secondary' => $seller->user?->email ?: 'Sin correo',
                'value' => is_array($seller->menu_permissions) && count($seller->menu_permissions) ? count($seller->menu_permissions).' menús' : 'Permisos estándar',
                'status' => 'Activo',
            ])->all(),
            'pendingRequests' => $pendingRequests->map(fn (AuthorizationRequest $authorization) => [
                'id' => $authorization->public_id,
                'action' => $authorization->action,
                'context' => $authorization->context ?: [],
                'requester' => $authorization->requester?->name ?: 'Vendedor',
                'requester_email' => $authorization->requester?->email ?: 'Sin correo',
                'created_at' => $authorization->created_at?->format('d/m/Y H:i'),
                'approve_url' => route('seller.shops.authorizations.approve', [$shop, $authorization]),
                'reject_url' => route('seller.shops.authorizations.reject', [$shop, $authorization]),
            ])->values()->all(),
            'note' => 'Las acciones sensibles pueden esperar aprobación del propietario. Las solicitudes conservan quién pidió permiso, el contexto y la decisión registrada.',
            'actions' => [['label' => 'Administrar equipo', 'url' => route('seller.shops.sellers.index', $shop), 'tone' => 'primary']],
        ];
    }

    private function accountantData(Shop $shop, BusinessDashboardService $dashboard): array
    {
        $summary = $dashboard->getSummary($shop, now()->startOfMonth()->toDateString(), now()->toDateString(), 'profit', 'desc');
        $accountants = $shop->members()->with('user')->where('role', 'accountant')->where('is_active', true)->get();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Ventas netas', 'value' => $this->money($summary['period']['net_sales']), 'tone' => 'blue'],
                ['label' => 'Ganancia bruta', 'value' => $this->money($summary['period']['gross_profit']), 'tone' => 'emerald'],
                ['label' => 'Gastos operativos', 'value' => $this->money($summary['period']['operating_expenses']), 'tone' => 'rose'],
            ],
            'rows' => [
                ['primary' => 'Estado financiero del mes', 'secondary' => 'Ventas, costos, gastos y comisiones integrados', 'value' => $this->money($summary['period']['operating_profit']), 'status' => 'Disponible'],
                ['primary' => 'Costos FIFO', 'secondary' => 'Costo real capturado por lote', 'value' => $this->money($summary['current_state']['inventory_cost_value'] ?? 0), 'status' => 'Disponible'],
                ...$accountants->map(fn ($member) => [
                    'primary' => $member->user?->name ?: 'Contador',
                    'secondary' => $member->user?->email ?: 'Sin correo',
                    'value' => 'Solo lectura',
                    'status' => 'Con acceso',
                ])->all(),
            ],
            'accountantMembers' => $accountants->map(fn ($member) => [
                'id' => (string) $member->id,
                'name' => $member->user?->name ?: 'Contador',
                'email' => $member->user?->email ?: 'Sin correo',
            ])->values()->all(),
            'note' => 'El contador consulta la misma fuente financiera que Ganancias; no se crean asientos paralelos.',
            'actions' => [
                ['label' => 'Ver ganancias', 'url' => route('seller.shops.business', $shop), 'tone' => 'primary'],
                ['label' => 'Ver reportes', 'url' => route('seller.shops.feature', [$shop, 'feature' => 'reports']), 'tone' => 'secondary'],
            ],
        ];
    }

    private function accountData(Shop $shop): array
    {
        $user = request()->user();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Cuenta', 'value' => $user->planLabel(), 'tone' => 'blue'],
                ['label' => 'Tiendas', 'value' => number_format($user->shops()->count()), 'tone' => 'emerald'],
                ['label' => 'Correo', 'value' => $user->email, 'tone' => 'slate'],
            ],
            'rows' => [
                ['primary' => $user->name, 'secondary' => $user->email, 'value' => $user->email_verified_at ? 'Verificado' : 'Pendiente', 'status' => 'Mi cuenta'],
                ['primary' => $shop->name, 'secondary' => 'Tienda activa', 'value' => $shop->planLabel(), 'status' => 'Plan vigente'],
            ],
            'note' => 'La seguridad, el plan y los datos de tu tienda se administran en sus pantallas protegidas.',
            'actions' => [['label' => 'Configuración de tienda', 'url' => route('seller.shops.edit', $shop), 'tone' => 'primary']],
        ];
    }

    private function updatesData(): array
    {
        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Versión web', 'value' => config('app.version', 'Actual'), 'tone' => 'blue'],
                ['label' => 'Módulos activos', 'value' => '28', 'tone' => 'emerald'],
                ['label' => 'Estado', 'value' => 'Operativo', 'tone' => 'slate'],
            ],
            'rows' => [
                ['primary' => 'Menú organizado por operación', 'secondary' => 'Operación, Compras, Catálogo, Cobros, Finanzas, Análisis, Equipo y Ajustes', 'value' => 'Publicado', 'status' => 'Listo'],
                ['primary' => 'Cierre de día y caja', 'secondary' => 'Arqueo conectado a la sesión financiera existente', 'value' => 'Publicado', 'status' => 'Listo'],
                ['primary' => 'Costos FIFO y precios automáticos', 'secondary' => 'Cada lote conserva su costo y las bajadas esperan aprobación', 'value' => 'Publicado', 'status' => 'Listo'],
            ],
            'note' => 'Las novedades se muestran aquí para que el equipo conozca qué cambió sin interrumpir la operación.',
            'actions' => [],
        ];
    }

    private function helpData(): array
    {
        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Guías rápidas', 'value' => '5', 'tone' => 'blue'],
                ['label' => 'Flujos principales', 'value' => '7', 'tone' => 'emerald'],
                ['label' => 'Soporte', 'value' => 'Disponible', 'tone' => 'slate'],
            ],
            'rows' => [
                ['primary' => 'Vender', 'secondary' => 'Selecciona productos, define pago y confirma la operación', 'value' => 'Terminal', 'status' => 'Guía'],
                ['primary' => 'Controlar inventario', 'secondary' => 'Repón, ajusta y consulta lotes con costo FIFO', 'value' => 'Inventario', 'status' => 'Guía'],
                ['primary' => 'Cobrar crédito', 'secondary' => 'Registra cuentas por cobrar y abonos desde Clientes', 'value' => 'Cobros', 'status' => 'Guía'],
            ],
            'note' => 'Cada guía enlaza a la pantalla operativa correspondiente para aprender haciendo.',
            'actions' => [['label' => 'Contactar soporte', 'url' => route('support.create'), 'tone' => 'primary']],
        ];
    }

    private function practiceData(Shop $shop): array
    {
        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Modo práctica', 'value' => 'Seguro', 'tone' => 'blue'],
                ['label' => 'Datos reales', 'value' => 'Sin cambios', 'tone' => 'emerald'],
                ['label' => 'Tienda', 'value' => $shop->name, 'tone' => 'slate'],
            ],
            'rows' => [
                ['primary' => 'Explora Terminal', 'secondary' => 'Revisa productos y carrito sin enviar la venta', 'value' => 'Abrir', 'status' => 'Seguro'],
                ['primary' => 'Explora Inventario', 'secondary' => 'Consulta stock, lotes y costos antes de operar', 'value' => 'Abrir', 'status' => 'Seguro'],
                ['primary' => 'Explora Caja', 'secondary' => 'Consulta el estado de la sesión actual', 'value' => 'Abrir', 'status' => 'Seguro'],
            ],
            'note' => 'Las acciones contables requieren confirmación en sus pantallas reales. Practicar sin miedo no crea ventas ni modifica existencias.',
            'actions' => [
                ['label' => 'Explorar Terminal', 'url' => route('seller.shops.pos', $shop), 'tone' => 'primary'],
                ['label' => 'Explorar inventario', 'url' => route('seller.shops.inventory.index', $shop), 'tone' => 'secondary'],
            ],
        ];
    }

    private function supportData(Shop $shop): array
    {
        $requests = SupportRequest::query()->where('shop_id', $shop->id)->latest()->limit(20)->get();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Solicitudes', 'value' => number_format($requests->count()), 'tone' => 'blue'],
                ['label' => 'Abiertas', 'value' => number_format($requests->where('status', 'open')->count()), 'tone' => 'amber'],
                ['label' => 'Resueltas', 'value' => number_format($requests->where('status', 'resolved')->count()), 'tone' => 'emerald'],
            ],
            'rows' => $requests->map(fn (SupportRequest $request) => [
                'primary' => $request->subject,
                'secondary' => ($request->category ?: 'Soporte').' · '.($request->created_at?->format('d/m/Y H:i') ?: 'Sin fecha'),
                'value' => ucfirst((string) $request->status),
                'status' => $request->status === 'resolved' ? 'Resuelta' : 'En seguimiento',
            ])->all(),
            'note' => 'Las solicitudes quedan asociadas a la tienda para que el equipo pueda dar seguimiento sin perder contexto.',
            'actions' => [['label' => 'Nueva solicitud', 'url' => route('support.create'), 'tone' => 'primary']],
        ];
    }

    private function salesData(Shop $shop): array
    {
        $period = (string) request()->query('period', 'today');
        $period = in_array($period, ['today', 'month', 'last_7', 'all'], true) ? $period : 'today';
        $status = (string) request()->query('status', 'all');
        $status = in_array($status, ['all', 'paid', 'credit', 'partial', 'void'], true) ? $status : 'all';
        $search = trim((string) request()->query('q', ''));

        $filtered = $shop->invoices()->with(['customer', 'user', 'items']);
        if ($status === 'all') {
            $filtered->where('status', '!=', 'void');
        } else {
            $filtered->where('status', $status);
        }
        if ($period === 'today') {
            $filtered->whereDate('issued_at', today());
        } elseif ($period === 'month') {
            $filtered->whereBetween('issued_at', [now()->startOfMonth(), now()->endOfDay()]);
        } elseif ($period === 'last_7') {
            $filtered->whereBetween('issued_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()]);
        }
        if ($search !== '') {
            $filtered->where(function ($query) use ($search): void {
                $query->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
            });
        }

        $today = $shop->invoices()->whereDate('issued_at', today())->where('status', '!=', 'void');
        $rows = (clone $filtered)->latest('issued_at')->limit(100)->get();
        $operationCount = (clone $filtered)->count();
        $totalSold = (float) (clone $filtered)->sum('total');
        $creditCount = (clone $filtered)->whereIn('status', ['credit', 'partial'])->count();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => $period === 'today' ? 'Ventas de hoy' : 'Ventas del período', 'value' => number_format($operationCount), 'tone' => 'blue'],
                ['label' => 'Total vendido', 'value' => $this->money($totalSold), 'tone' => 'blue'],
                ['label' => 'Promedio por venta', 'value' => $this->money($operationCount > 0 ? $totalSold / $operationCount : 0), 'tone' => 'slate'],
                ['label' => 'A crédito', 'value' => number_format($operationCount > 0 ? ($creditCount / $operationCount) * 100 : 0, 0).'% ', 'tone' => $creditCount > 0 ? 'amber' : 'emerald'],
            ],
            'filters' => [
                'period' => $period,
                'status' => $status,
                'search' => $search,
                'count' => $rows->count(),
            ],
            'rows' => $rows->map(fn (Invoice $invoice) => [
                'primary' => $invoice->invoice_number,
                'secondary' => ($invoice->customer?->name ?: 'Venta general').' · '.($invoice->issued_at?->format('d/m/Y H:i') ?: 'Sin fecha').' · '.number_format((int) $invoice->items->sum('quantity')).' artículo(s)',
                'value' => $this->money($invoice->total),
                'status' => $this->invoiceStatus($invoice),
            ])->all(),
            'note' => 'Las ventas se registran desde Terminal y alimentan inventario, caja, crédito y ganancias. Las anuladas se mantienen fuera del resumen general, pero pueden consultarse desde el filtro de estado.',
        ];
    }

    private function ordersData(Shop $shop, ?string $status = null, bool $shipping = false, ?string $search = null): array
    {
        $search = trim((string) $search);
        $query = $shop->orders()->with('items')->latest();
        if ($status !== null && $status !== 'all') {
            $query->where('status', $status);
        }
        if ($shipping) {
            $query->whereIn('delivery_type', ['delivery', 'shipping', 'envio']);
        }
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }
        $orders = $query->limit(30)->get();

        return [
            'kind' => 'orders',
            'kpis' => [
                ['label' => 'Pendientes', 'value' => number_format($shop->orders()->where('status', 'pending')->count()), 'tone' => 'amber'],
                ['label' => 'Confirmados', 'value' => number_format($shop->orders()->where('status', 'confirmed')->count()), 'tone' => 'emerald'],
                ['label' => 'Valor recibido', 'value' => $this->money($shop->orders()->sum('total')), 'tone' => 'blue'],
            ],
            'rows' => $orders->map(fn (Order $order) => [
                'id' => (string) $order->id,
                'primary' => $order->order_number,
                'secondary' => ($order->customer_name ?: 'Cliente sin nombre').' · '.($order->delivery_type ?: 'Retiro'),
                'value' => $this->money($order->total),
                'status' => $this->orderStatus($order),
                'can_confirm' => $order->invoice_id === null && $order->status !== 'cancelled',
                'confirm_url' => $order->invoice_id === null && $order->status !== 'cancelled'
                    ? URL::temporarySignedRoute('seller.shops.orders.confirm.show', now()->addDays(7), [$shop, $order])
                    : null,
            ])->all(),
            'filters' => [
                'search' => $search,
                'status' => $status ?: 'all',
                'count' => $orders->count(),
            ],
            'actions' => [
                ['label' => 'Compartir mi tienda', 'url' => 'https://wa.me/?text='.rawurlencode('Mira mi catálogo: '.route('shops.show', $shop)), 'tone' => 'primary'],
                ['label' => 'Abrir mi tienda', 'url' => route('seller.shops.storefront', $shop), 'tone' => 'secondary'],
                ['label' => 'Ir a Terminal', 'url' => route('seller.shops.pos', $shop), 'tone' => 'secondary'],
            ],
            'note' => $shipping ? 'Los envíos se filtran desde los pedidos que tienen entrega configurada.' : 'Confirma un pedido únicamente cuando la mercancía esté lista para descontar inventario.',
        ];
    }

    private function encargosData(Shop $shop, ?string $status = null, ?string $search = null): array
    {
        $status = in_array($status, ['all', 'today', 'tomorrow', 'overdue', 'no_date'], true) ? $status : 'all';
        $search = trim((string) $search);
        $open = $shop->orders()
            ->with('items')
            ->whereNull('invoice_id')
            ->whereNotIn('status', ['cancelled', 'confirmed'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            }))
            ->when($status === 'today', fn ($query) => $query->whereDate('delivery_at', today()))
            ->when($status === 'tomorrow', fn ($query) => $query->whereDate('delivery_at', today()->addDay()))
            ->when($status === 'overdue', fn ($query) => $query->whereNotNull('delivery_at')->whereDate('delivery_at', '<', today()))
            ->when($status === 'no_date', fn ($query) => $query->whereNull('delivery_at'))
            ->latest('delivery_at')
            ->latest()
            ->limit(30)
            ->get();
        $scheduled = $shop->orders()->whereNull('invoice_id')->whereNotIn('status', ['cancelled', 'confirmed']);

        $row = fn (Order $order): array => [
            'id' => (string) $order->id,
            'primary' => $order->order_number,
            'secondary' => ($order->customer_name ?: 'Cliente sin nombre').' · '.($order->delivery_type ?: 'Por coordinar').' · '.($order->delivery_at?->format('d/m/Y') ?: 'Sin fecha'),
            'value' => $this->money($order->total),
            'status' => $this->orderStatus($order),
            'can_confirm' => true,
            'confirm_url' => URL::temporarySignedRoute('seller.shops.orders.confirm.show', now()->addDays(7), [$shop, $order]),
        ];

        return [
            'kind' => 'orders',
            'kpis' => [
                ['label' => 'Hoy', 'value' => number_format((clone $scheduled)->whereDate('delivery_at', today())->count()), 'tone' => 'blue'],
                ['label' => 'Mañana', 'value' => number_format((clone $scheduled)->whereDate('delivery_at', today()->addDay())->count()), 'tone' => 'emerald'],
                ['label' => 'Atrasados', 'value' => number_format((clone $scheduled)->whereNotNull('delivery_at')->whereDate('delivery_at', '<', today())->count()), 'tone' => 'rose'],
            ],
            'rows' => $open->map($row)->all(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'count' => $open->count(),
            ],
            'actions' => [
                ['label' => 'Compartir mi tienda', 'url' => 'https://wa.me/?text='.rawurlencode('Mira mi catálogo: '.route('shops.show', $shop)), 'tone' => 'primary'],
                ['label' => 'Ir a Terminal', 'url' => route('seller.shops.pos', $shop), 'tone' => 'secondary'],
            ],
            'note' => 'Los encargos con fecha aparecen organizados por entrega. Confirma el encargo al convertirlo en venta para descontar inventario y registrar el cobro.',
        ];
    }

    private function dayCloseData(Shop $shop, ?string $requestedDate = null): array
    {
        $date = $requestedDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate)
            ? $requestedDate
            : today()->toDateString();
        $invoices = $shop->invoices()->whereDate('issued_at', $date)->where('status', '!=', 'void');
        $expenses = $shop->expenses()->whereDate('occurred_at', $date);
        $payments = InvoicePayment::query()
            ->where('shop_id', $shop->id)
            ->whereDate('received_at', $date);
        $salePayments = (clone $payments)->whereNull('customer_account_entry_id');
        $debtPayments = (clone $payments)->whereNotNull('customer_account_entry_id');
        $paymentBreakdown = (clone $salePayments)
            ->selectRaw('payment_method, SUM(amount_cents) as amount_cents, COUNT(*) as payment_count')
            ->groupBy('payment_method')
            ->orderByDesc('amount_cents')
            ->get()
            ->map(fn (InvoicePayment $payment): array => [
                'label' => config("catalog.payment_methods.{$payment->payment_method}.label", ucfirst((string) $payment->payment_method)),
                'value' => $this->money(((int) $payment->amount_cents) / 100),
                'count' => (int) $payment->payment_count,
            ])
            ->values()
            ->all();
        $refunds = DB::table('invoice_returns')
            ->join('invoices', 'invoices.id', '=', 'invoice_returns.invoice_id')
            ->where('invoices.shop_id', $shop->id)
            ->whereDate('invoice_returns.created_at', $date);
        $dailyClose = app(DailyCloseService::class)->calculate($shop, $date);
        $paidSalesCents = (int) (clone $salePayments)->sum('amount_cents');
        $debtPaymentsCents = (int) (clone $debtPayments)->sum('amount_cents');
        $expenseCents = (int) (clone $expenses)->sum('amount_cents');
        $refundCents = (int) $refunds->sum(DB::raw('ROUND(invoice_returns.total * 100)'));

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Ventas cobradas', 'value' => $this->money($paidSalesCents / 100), 'tone' => 'blue'],
                ['label' => 'Abonos recibidos', 'value' => $this->money($debtPaymentsCents / 100), 'tone' => 'emerald'],
                ['label' => 'Gastos del día', 'value' => $this->money($expenseCents / 100), 'tone' => 'rose'],
                ['label' => 'Efectivo esperado', 'value' => $this->money($dailyClose['expected_cash']), 'tone' => 'emerald'],
            ],
            'rows' => [
                ['primary' => 'Ventas registradas', 'secondary' => 'Facturas no anuladas de hoy', 'value' => number_format((clone $invoices)->count()), 'status' => 'Listo'],
                ['primary' => 'Gastos registrados', 'secondary' => 'Egresos con fecha de hoy', 'value' => number_format((clone $expenses)->count()), 'status' => 'Listo'],
                ['primary' => 'Cierre diario', 'secondary' => $dailyClose['closure'] ? 'Guardado por '.$dailyClose['closure']['closed_at'] : 'Todavía no guardado', 'value' => $dailyClose['closure'] ? 'Cerrado' : 'Pendiente', 'status' => $dailyClose['closure'] ? 'Listo' : 'Revisar'],
            ],
            'day_close' => [
                'sales_total' => $this->money($paidSalesCents / 100),
                'sales_count' => (int) (clone $salePayments)->count(),
                'payments' => $paymentBreakdown,
                'collections_total' => $this->money($debtPaymentsCents / 100),
                'collections_count' => (int) (clone $debtPayments)->count(),
                'expenses_total' => $this->money($expenseCents / 100),
                'refunds_total' => $this->money($refundCents / 100),
                'date' => $date,
                'cash' => [
                    'in' => $this->money($dailyClose['sales_cash'] + $dailyClose['debt_collections_cash'] + $dailyClose['other_inflows_cash']),
                    'out' => $this->money($dailyClose['expenses_cash'] + $dailyClose['cash_out']),
                    'expected' => $this->money($dailyClose['expected_cash']),
                    'sales' => $this->money($dailyClose['sales_cash']),
                    'collections' => $this->money($dailyClose['debt_collections_cash']),
                    'expenses' => $this->money($dailyClose['expenses_cash']),
                    'cash_out' => $this->money($dailyClose['cash_out']),
                ],
            ],
            'date' => $date,
            'closure' => $dailyClose['closure'],
            'note' => 'El cierre diario funciona sin abrir una sesión. Solo el efectivo entra en el esperado; tarjetas y transferencias se revisan aparte.',
        ];
    }

    private function photosData(Shop $shop): array
    {
        $products = $shop->products()->with(['primaryImage'])->withCount('images')->latest()->limit(100)->get();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Productos', 'value' => number_format($shop->products()->count()), 'tone' => 'blue'],
                ['label' => 'Con fotos', 'value' => number_format($products->where('images_count', '>', 0)->count()), 'tone' => 'emerald'],
                ['label' => 'Pendientes', 'value' => number_format($products->where('images_count', 0)->count()), 'tone' => 'amber'],
            ],
            'rows' => $products->map(fn (Product $product) => [
                'primary' => $product->name,
                'secondary' => $product->product_code ?: 'Sin código',
                'value' => number_format($product->images_count).' foto(s)',
                'status' => $product->images_count ? 'Completo' : 'Pendiente',
            ])->all(),
            'photoProducts' => $products->map(fn (Product $product) => [
                'id' => $product->public_id,
                'name' => $product->name,
                'code' => $product->product_code ?: 'Sin código',
                'image_count' => (int) $product->images_count,
                'image_url' => $product->primaryImage?->url,
                'edit_url' => route('seller.shops.products.edit', [$shop, 'product' => $product]),
                'image_store_url' => route('seller.shops.products.images.web.store', [$shop, 'product' => $product]),
            ])->values()->all(),
            'note' => 'Busca sugerencias por producto y decide cuál usar. El servidor valida el dominio, respeta el límite del plan y procesa la imagen sin borrar las anteriores.',
        ];
    }

    private function priceHealthData(Shop $shop, ?string $statusFilter = null, ?string $search = null): array
    {
        $products = $shop->products()->with('inventory')->where('sale_unit', '!=', 'decant')->limit(100)->get();
        $rules = \DB::table('product_price_rules')->whereIn('product_id', $products->pluck('id'))->get()->keyBy('product_id');
        $targetMargin = 40.0;
        $rows = $products->map(function (Product $product) use ($rules, $targetMargin): array {
            $cost = $product->inventory?->cost_price;
            $price = (float) $product->currentPrice();
            $rule = $rules[$product->id] ?? null;
            $margin = $cost !== null && $price > 0
                ? (($price - (float) $cost) / $price) * 100
                : null;
            $target = $rule?->margin_percent !== null ? (float) $rule->margin_percent : $targetMargin;
            $status = match (true) {
                $price <= 0 => 'Sin precio',
                $cost === null => 'Sin costo',
                (float) $cost <= 0 => 'Costo dudoso',
                $price < (float) $cost => 'Bajo costo',
                $margin !== null && $margin < $target => 'Margen bajo',
                $rule?->pending_price !== null => 'Sugerencia',
                default => 'Bien',
            };

            return [
                'primary' => $product->name,
                'secondary' => $cost !== null ? 'Costo RD$ '.number_format((float) $cost, 2) : 'Costo pendiente',
                'value' => $price > 0 ? 'Precio RD$ '.number_format($price, 2) : 'Sin precio',
                'status' => $status,
                'margin_percent' => $margin === null ? null : number_format($margin, 1, '.', '').' %',
                'target_margin_percent' => number_format($target, 1, '.', '').' %',
                'suggested_price' => $rule?->pending_price === null ? null : number_format((float) $rule->pending_price, 2, '.', ''),
            ];
        })->values();

        $allowedFilters = ['all', 'low_cost', 'low_margin', 'no_price', 'no_cost', 'cost_doubtful', 'suggestions'];
        $statusFilter = in_array($statusFilter, $allowedFilters, true) ? $statusFilter : 'all';
        $search = trim((string) $search);
        $statusMap = [
            'low_cost' => 'Bajo costo',
            'low_margin' => 'Margen bajo',
            'no_price' => 'Sin precio',
            'no_cost' => 'Sin costo',
            'cost_doubtful' => 'Costo dudoso',
            'suggestions' => 'Sugerencia',
        ];
        $filteredRows = $rows
            ->when($statusFilter !== 'all', fn ($collection) => $collection->where('status', $statusMap[$statusFilter]))
            ->when($search !== '', fn ($collection) => $collection->filter(fn (array $row): bool => collect([$row['primary'], $row['secondary'], $row['value'], $row['status']])->contains(fn ($value): bool => str_contains(mb_strtolower((string) $value), mb_strtolower($search)))))
            ->values();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Bajo costo', 'value' => number_format($rows->where('status', 'Bajo costo')->count()), 'tone' => 'rose'],
                ['label' => 'Margen bajo', 'value' => number_format($rows->where('status', 'Margen bajo')->count()), 'tone' => 'amber'],
                ['label' => 'Sin precio', 'value' => number_format($rows->where('status', 'Sin precio')->count()), 'tone' => 'rose'],
                ['label' => 'Sugerencias', 'value' => number_format($rows->where('status', 'Sugerencia')->count()), 'tone' => 'blue'],
            ],
            'rows' => $filteredRows->all(),
            'filters' => [
                'search' => $search,
                'status' => $statusFilter,
                'count' => $filteredRows->count(),
            ],
            'note' => 'Las bajadas de precio siguen esperando aprobación y las ventas conservan el costo real del lote.',
        ];
    }

    private function decantsData(Shop $shop): array
    {
        $products = $shop->products()->with(['inventory', 'sourceProduct.inventory'])->where('sale_unit', 'decant')->get();
        $bottles = $shop->products()
            ->with(['inventory', 'decantProducts'])
            ->whereIn('sale_unit', ['bottle', 'ml'])
            ->orderBy('name')
            ->get();
        $recovery = app(InventoryService::class)->getCostRecoveryForBottles($bottles);
        $bottleSources = $bottles->map(function (Product $bottle) use ($recovery, $shop): array {
            $inventory = $bottle->inventory;
            $availableMl = $inventory?->available_ml;
            if ($availableMl === null && $inventory?->track_inventory) {
                $availableMl = $bottle->sale_unit === 'bottle'
                    ? (int) $inventory->stock_quantity * (int) $bottle->volume_ml
                    : (int) $inventory->stock_quantity;
            }
            $sourceRecovery = $recovery[$bottle->id] ?? null;

            return [
                'id' => $bottle->public_id,
                'name' => $bottle->name,
                'volume_ml' => (int) ($bottle->volume_ml ?? 0),
                'stock_quantity' => (int) ($inventory?->stock_quantity ?? 0),
                'opened_bottles' => (int) ($inventory?->opened_bottles ?? 0),
                'available_ml' => $availableMl === null ? null : (int) $availableMl,
                'decants_count' => $bottle->decantProducts->count(),
                'cost' => $sourceRecovery['cost'] ?? null,
                'revenue' => $sourceRecovery['revenue'] ?? 0,
                'difference' => $sourceRecovery['difference'] ?? null,
                'percent' => $sourceRecovery['percent'] ?? null,
                'covered' => $sourceRecovery['covered'] ?? false,
                'message' => $sourceRecovery['message'] ?? 'Registra el costo para medir cuándo se recupera la botella.',
                'url' => route('seller.shops.products.edit', [$shop, 'product' => $bottle]),
                'open_url' => route('seller.shops.inventory.open-bottle', [$shop, 'product' => $bottle]),
            ];
        })->values()->all();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Decants', 'value' => number_format($products->count()), 'tone' => 'blue'],
                ['label' => 'Botellas fuente', 'value' => number_format($bottles->count()), 'tone' => 'emerald'],
                ['label' => 'Ml disponibles', 'value' => number_format($bottleSources ? collect($bottleSources)->sum(fn (array $bottle) => $bottle['available_ml'] ?? 0) : 0), 'tone' => 'blue'],
                ['label' => 'Costo recuperado', 'value' => number_format(collect($bottleSources)->where('covered', true)->count()).' botella(s)', 'tone' => 'amber'],
            ],
            'rows' => $products->map(function (Product $product): array {
                $source = $product->sourceProduct;
                $sourceInventory = $source?->inventory;
                $availableMl = $sourceInventory?->available_ml;
                if ($availableMl === null && $sourceInventory?->track_inventory) {
                    $availableMl = $source->sale_unit === 'bottle'
                        ? (int) $sourceInventory->stock_quantity * (int) $source->volume_ml
                        : (int) $sourceInventory->stock_quantity;
                }

                return [
                    'primary' => $product->name,
                    'secondary' => $source?->name
                        ? 'Botella fuente: '.$source->name
                            .' · '.($source->volume_ml ? $source->volume_ml.' ml de origen' : 'volumen pendiente')
                            .' · costo '.($sourceInventory?->cost_price !== null ? 'RD$ '.number_format((float) $sourceInventory->cost_price, 2) : 'pendiente')
                            .' · '.($availableMl !== null ? number_format($availableMl).' ml disponibles' : 'ml no controlados')
                        : 'Botella de origen no configurada',
                    'value' => $product->volume_ml
                        ? $product->volume_ml.' ml · '.number_format((int) ($product->inventory?->stock_quantity ?? 0)).' listos'
                        : 'Volumen pendiente',
                    'status' => !$source
                        ? 'Sin botella'
                        : (($sourceInventory?->available_ml !== null
                            && $product->volume_ml
                            && (int) $sourceInventory->available_ml <= (int) $product->volume_ml)
                            ? 'Se agota'
                            : ((int) ($product->inventory?->stock_quantity ?? 0) > 0 ? 'Listos' : 'A pedido')),
                ];
            })->all(),
            'note' => 'Los decants comparten el inventario de su producto de origen; no se duplica la valoración de la botella.',
            'bottleSources' => $bottleSources,
            'actions' => [
                ['label' => 'Abrir botella', 'url' => '#abrir-botella', 'tone' => 'secondary', 'modal' => true],
                ['label' => 'Preparar decant', 'url' => route('seller.shops.products.create', $shop).'?sale_unit=decant', 'tone' => 'primary'],
                ['label' => 'Ver inventario compartido', 'url' => route('seller.shops.inventory.index', $shop), 'tone' => 'secondary'],
                ['label' => 'Ver lotes y costos FIFO', 'url' => route('seller.shops.inventory.lots', $shop), 'tone' => 'secondary'],
            ],
        ];
    }

    private function attributesData(Shop $shop, ?string $search = null): array
    {
        $search = trim((string) $search);
        $attributes = $shop->attributeDefinitions()
            ->where('is_active', true)
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            }))
            ->with('shopCategory')
            ->get();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Atributos', 'value' => number_format($attributes->count()), 'tone' => 'blue'],
                ['label' => 'Obligatorios', 'value' => number_format($attributes->where('required', true)->count()), 'tone' => 'amber'],
                ['label' => 'Filtrables', 'value' => number_format($attributes->where('filterable', true)->count()), 'tone' => 'emerald'],
            ],
            'rows' => $attributes->map(fn (AttributeDefinition $attribute) => [
                'id' => (string) $attribute->id,
                'primary' => $attribute->name,
                'secondary' => $attribute->shopCategory?->name ?: 'Todas las categorías',
                'value' => ucfirst($attribute->type),
                'status' => $attribute->required ? 'Obligatorio' : 'Opcional',
                'filterable' => (bool) $attribute->filterable,
                'edit_url' => route('seller.shops.attributes.update', [$shop, 'attribute' => $attribute]),
                'retire_url' => route('seller.shops.attributes.update', [$shop, 'attribute' => $attribute]),
            ])->all(),
            'filters' => ['search' => $search, 'count' => $attributes->count()],
            'note' => 'La definición de atributos ya está centralizada en el catálogo y se puede reutilizar para marcas, tallas, colores o concentraciones.',
        ];
    }

    private function creditData(Shop $shop): array
    {
        $customers = $shop->customers()->where('balance', '>', 0)->orderByDesc('balance')->limit(30)->get();
        $payments = CustomerAccountEntry::query()
            ->where('shop_id', $shop->id)
            ->where('type', 'payment')
            ->with('customer')
            ->latest('created_at')
            ->limit(30)
            ->get();

        $receivableRows = $customers->map(fn (Customer $customer): array => [
            'primary' => $customer->name,
            'secondary' => $customer->phone ?: 'Sin teléfono',
            'value' => $this->money($customer->balance),
            'status' => (float) $customer->balance > (float) $customer->credit_limit ? 'Excedido' : 'Pendiente',
        ])->values()->all();
        $paidRows = $payments->map(fn (CustomerAccountEntry $entry): array => [
            'primary' => $entry->customer?->name ?: 'Cliente eliminado',
            'secondary' => ($entry->created_at?->format('d/m/Y H:i') ?: 'Sin fecha').' · '.($entry->notes ?: 'Abono registrado'),
            'value' => $this->money(abs((float) $entry->amount)),
            'status' => 'Pagado',
        ])->values()->all();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Clientes con saldo', 'value' => number_format($shop->customers()->where('balance', '>', 0)->count()), 'tone' => 'amber'],
                ['label' => 'Por cobrar', 'value' => $this->money($shop->customers()->sum('balance')), 'tone' => 'rose'],
                ['label' => 'Límite asignado', 'value' => $this->money($shop->customers()->sum('credit_limit')), 'tone' => 'blue'],
            ],
            'rows' => $receivableRows,
            'creditTabs' => [
                'receivable' => $receivableRows,
                'paid' => $paidRows,
            ],
            'note' => 'Los créditos y abonos se registran desde Clientes para conservar la asignación contable y evitar saldos manuales.',
        ];
    }

    private function reportsData(Shop $shop, BusinessDashboardService $dashboard): array
    {
        $summary = $dashboard->getSummary($shop, now()->startOfMonth()->toDateString(), now()->toDateString(), 'profit', 'desc');
        $currentState = $summary['current_state'] ?? [];
        $period = $summary['period'] ?? [];
        $metrics = app(ShopAnalyticsService::class)->getMetricsSummary($shop);
        $rows = collect($summary['profitability'])->take(20)->map(fn (array $product) => [
            'primary' => $product['product_name'],
            'secondary' => number_format($product['units']).' unidad(es) · Ventas '.$this->money($product['revenue']),
            'value' => $this->money($product['gross_profit']),
            'status' => $product['has_unknown_cost'] ? 'Costo pendiente' : 'Calculada',
        ]);
        $rows->prepend([
            'primary' => 'Inventario al costo',
            'secondary' => number_format((int) ($currentState['active_products_count'] ?? 0)).' productos activos · Valor FIFO actual',
            'value' => $this->money($currentState['inventory_cost_value'] ?? 0),
            'status' => 'Actual',
        ]);
        $serviceData = $this->servicesData($shop);
        $sections = [
            [
                'key' => 'sales',
                'label' => 'Ventas',
                'kpis' => [
                    ['label' => 'Ventas netas', 'value' => $this->money($period['net_sales'] ?? 0), 'tone' => 'blue'],
                    ['label' => 'Ganancia bruta', 'value' => $this->money($period['gross_profit'] ?? 0), 'tone' => 'emerald'],
                    ['label' => 'Transacciones', 'value' => number_format((int) ($period['sales_count'] ?? 0)), 'tone' => 'slate'],
                ],
                'rows' => $rows->take(10)->values()->all(),
                'note' => 'Ventas netas, costo FIFO y ganancia se calculan con las mismas reglas contables del módulo Finanzas.',
            ],
            [
                'key' => 'services',
                'label' => 'Servicios',
                'kpis' => $serviceData['kpis'],
                'rows' => $serviceData['rows'],
                'note' => 'Los servicios y productos sin control de inventario se mantienen en el mismo catálogo y se pueden vender desde Terminal.',
            ],
            [
                'key' => 'visits',
                'label' => 'Visitas',
                'kpis' => [
                    ['label' => 'Visitas · 30 días', 'value' => number_format($metrics['views_30d']), 'tone' => 'blue'],
                    ['label' => 'Contactos WhatsApp', 'value' => number_format($metrics['clicks_30d']), 'tone' => 'emerald'],
                    ['label' => 'Conversión', 'value' => number_format($metrics['conversion_rate_30d'], 1).'%', 'tone' => 'amber'],
                ],
                'rows' => collect($metrics['top_by_views'])->map(fn ($product) => [
                    'primary' => $product->name,
                    'secondary' => number_format($product->views_count).' visita(s) en catálogo',
                    'value' => number_format($product->clicks_count).' contacto(s)',
                    'status' => 'Más visto',
                ])->all(),
                'note' => 'Las visitas y contactos se agregan por día y por producto, sin modificar inventario ni ventas.',
            ],
            [
                'key' => 'links',
                'label' => 'Enlaces',
                'kpis' => [
                    ['label' => 'Visitas acumuladas', 'value' => number_format($metrics['total_views']), 'tone' => 'blue'],
                    ['label' => 'Contactos acumulados', 'value' => number_format($metrics['total_clicks']), 'tone' => 'emerald'],
                    ['label' => 'Productos activos', 'value' => number_format($metrics['active_products_count']), 'tone' => 'slate'],
                ],
                'rows' => [
                    ['primary' => 'Catálogo público', 'secondary' => route('shops.show', $shop), 'value' => 'Disponible', 'status' => 'Enlace público'],
                    ['primary' => 'Pedidos por WhatsApp', 'secondary' => filled($shop->whatsapp_number) ? $shop->whatsapp_number : 'Número no configurado', 'value' => filled($shop->whatsapp_number) ? 'Activo' : 'Revisar', 'status' => 'Canal de venta'],
                ],
                'note' => 'Ambos enlaces usan la misma vitrina pública y sus métricas se reflejan aquí en tiempo real.',
            ],
        ];

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Ventas netas', 'value' => $this->money($summary['period']['net_sales']), 'tone' => 'blue'],
                ['label' => 'Ganancia bruta', 'value' => $this->money($summary['period']['gross_profit']), 'tone' => 'emerald'],
                ['label' => 'Margen', 'value' => number_format((float) ($summary['period']['gross_margin_percent'] ?? 0), 1).'% ', 'tone' => 'slate'],
            ],
            'rows' => $rows->all(),
            'sections' => $sections,
            'actions' => [
                ['label' => 'Exportar CSV', 'url' => route('seller.shops.reports.export', [$shop, 'format' => 'csv']), 'tone' => 'secondary'],
                ['label' => 'Excel', 'url' => route('seller.shops.reports.export', [$shop, 'format' => 'xlsx']), 'tone' => 'secondary'],
                ['label' => 'Ver ganancias y resumen', 'url' => route('seller.shops.business', $shop), 'tone' => 'primary'],
                ['label' => 'Abrir métricas', 'url' => route('seller.shops.metrics.index', $shop), 'tone' => 'secondary'],
            ],
            'note' => 'Este reporte usa el costo capturado por lote y distingue ventas, costos, devoluciones y descuentos. El inventario al costo refleja los lotes FIFO que aún quedan disponibles.',
        ];
    }

    private function commissionsData(Shop $shop, ?string $period = null): array
    {
        $period = in_array($period, ['current_fortnight', 'previous_fortnight', 'month'], true)
            ? $period
            : 'current_fortnight';
        [$from, $to] = $this->commissionPeriod($period);
        $invoices = $shop->invoices()
            ->with('salesperson')
            ->whereNotNull('salesperson_id')
            ->where('status', '!=', 'void')
            ->whereBetween('issued_at', [$from, $to])
            ->latest('issued_at')
            ->limit(100)
            ->get();
        $returnsBySeller = DB::table('invoice_returns')
            ->join('invoices', 'invoices.id', '=', 'invoice_returns.invoice_id')
            ->where('invoices.shop_id', $shop->id)
            ->whereNotNull('invoices.salesperson_id')
            ->whereBetween('invoice_returns.created_at', [$from, $to])
            ->selectRaw('invoices.salesperson_id, COALESCE(SUM(invoice_returns.total), 0) as total')
            ->groupBy('invoices.salesperson_id')
            ->pluck('total', 'salesperson_id');
        $summaryRows = $invoices->groupBy('salesperson_id')->map(function ($sellerInvoices, $sellerId) use ($returnsBySeller): array {
            $first = $sellerInvoices->first();
            $returns = (float) ($returnsBySeller->get($sellerId) ?? 0);
            $type = $first?->commission_type;
            $rate = $type === 'percentage' ? number_format((float) $first->commission_value, 2).'%' : ($type === 'fixed' ? 'Fijo' : '—');

            return [
                'primary' => $first?->salesperson?->name ?: 'Vendedor no asignado',
                'secondary' => number_format($sellerInvoices->count()).' venta(s)',
                'rate' => $rate,
                'sales' => $this->money($sellerInvoices->sum('total')),
                'returns' => $returns > 0 ? $this->money($returns) : '—',
                'base' => $this->money($sellerInvoices->sum('total') - $returns),
                'commission' => $this->money($sellerInvoices->sum('commission_amount')),
            ];
        })->values()->all();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Ventas comisionables', 'value' => number_format($invoices->count()), 'tone' => 'blue'],
                ['label' => 'Comisiones', 'value' => $this->money($invoices->sum('commission_amount')), 'tone' => 'amber'],
                ['label' => 'Vendedores', 'value' => number_format($invoices->pluck('salesperson_id')->filter()->unique()->count()), 'tone' => 'emerald'],
            ],
            'rows' => $invoices->map(fn (Invoice $invoice) => [
                'primary' => $invoice->invoice_number,
                'secondary' => ($invoice->salesperson?->name ?: 'Vendedor no asignado').' · '.$this->money($invoice->total),
                'value' => $this->money($invoice->commission_amount),
                'status' => 'Calculada',
            ])->all(),
            'commissionRows' => $summaryRows,
            'commissionPeriods' => [
                'current_fortnight' => 'Esta quincena',
                'previous_fortnight' => 'Quincena pasada',
                'month' => 'Este mes',
            ],
            'commissionPeriod' => $period,
            'commissionDateLabel' => $from->format('Y-m-d').' a '.$to->format('Y-m-d'),
            'commissionPayable' => $this->money($invoices->sum('commission_amount')),
            'note' => 'Período: '.$this->commissionPeriodLabel($period).'. Las comisiones se leen desde la fotografía guardada en cada venta; cambiar una regla no altera ventas históricas.',
        ];
    }

    /** @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon} */
    private function commissionPeriod(string $period): array
    {
        $today = now();

        return match ($period) {
            'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfDay()],
            'previous_fortnight' => $today->day <= 15
                ? [$today->copy()->subMonthNoOverflow()->day(16)->startOfDay(), $today->copy()->subMonthNoOverflow()->endOfMonth()]
                : [$today->copy()->startOfMonth()->startOfDay(), $today->copy()->day(15)->endOfDay()],
            default => $today->day <= 15
                ? [$today->copy()->startOfMonth()->startOfDay(), $today->copy()->day(15)->endOfDay()]
                : [$today->copy()->day(16)->startOfDay(), $today->copy()->endOfMonth()],
        };
    }

    private function commissionPeriodLabel(string $period): string
    {
        return match ($period) {
            'month' => 'este mes',
            'previous_fortnight' => 'quincena pasada',
            default => 'quincena actual',
        };
    }

    private function money(float|int|string|null $amount): string
    {
        return 'RD$ '.number_format((float) $amount, 2);
    }

    private function invoiceStatus(Invoice $invoice): string
    {
        return match ($invoice->status) {
            'paid' => 'Pagada',
            'credit' => 'A crédito',
            'partial' => 'Parcial',
            'void' => 'Anulada',
            default => ucfirst((string) $invoice->status),
        };
    }

    private function orderStatus(Order $order): string
    {
        return match ($order->status) {
            'confirmed' => 'Confirmado',
            'cancelled' => 'Cancelado',
            default => 'Pendiente',
        };
    }
}
