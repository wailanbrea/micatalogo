<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\ProductImageProcessingStatus;
use App\Models\Shop;
use App\Services\BusinessProfileService;
use App\Services\ImageProcessingService;
use App\Services\MediaStorageService;
use App\Services\SellerMenuService;
use App\Services\ShopHoursService;
use App\Services\ShopOperationalSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ShopSettingsController extends Controller
{
    public function show(Request $request, Shop $shop, SellerMenuService $menus, BusinessProfileService $profiles, ShopOperationalSettingsService $operational): JsonResponse
    {
        abort_unless($menus->canManage($shop, $request->user()), 403);

        return response()->json($this->payload($shop, $profiles, $operational));
    }

    public function update(Request $request, Shop $shop, SellerMenuService $menus, BusinessProfileService $profiles, ShopOperationalSettingsService $operational): JsonResponse
    {
        abort_unless($menus->canManage($shop, $request->user()), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'business_type' => ['required', Rule::in(array_keys($profiles->types()))],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:255'],
            'maps_url' => ['nullable', 'url', 'max:500'],
            'instagram' => ['nullable', 'string', 'max:160'],
            'whatsapp_country_code' => ['required', 'regex:/^[0-9]{1,4}$/'],
            'whatsapp_number' => ['required', 'regex:/^[0-9 ()+.-]{7,30}$/'],
            'offers_shipping' => ['sometimes', 'boolean'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'business_hours' => ['nullable', 'array'],
            'operational_settings' => ['sometimes', 'array'],
        ]);

        $validated['business_hours'] = app(ShopHoursService::class)->normalize($validated['business_hours'] ?? $shop->business_hours);
        if (array_key_exists('operational_settings', $validated) && ! Schema::hasColumn('shops', 'operational_settings')) {
            return response()->json([
                'message' => 'La configuración avanzada requiere ejecutar la migración de ajustes operativos antes de guardarse.',
                'reason' => 'operational_settings_migration_required',
            ], 409);
        }
        $shop->update([
            ...$validated,
            'offers_shipping' => (bool) ($validated['offers_shipping'] ?? false),
        ]);
        if (array_key_exists('operational_settings', $validated)) {
            $operational->update($shop, $validated['operational_settings']);
        }

        return response()->json([
            'message' => 'Configuración guardada.',
            ...$this->payload($shop->fresh(), $profiles, $operational),
        ]);
    }

    public function uploadLogo(Request $request, Shop $shop, SellerMenuService $menus, ImageProcessingService $imageService, MediaStorageService $mediaStorage): JsonResponse
    {
        abort_unless($menus->canManage($shop, $request->user()), 403);

        $validated = $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,webp,avif', 'max:2048'],
        ]);

        $logoKey = $imageService->processAndStoreShopLogo($shop, $validated['logo'], $mediaStorage);
        $shop->update(['logo_object_key' => $logoKey]);

        return response()->json([
            'message' => 'Logo actualizado.',
            'logo_url' => $shop->fresh()->logo_url,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Shop $shop, BusinessProfileService $profiles, ShopOperationalSettingsService $operational): array
    {
        $types = collect($profiles->types())->map(fn (array $profile, string $key): array => [
            'key' => $key,
            'label' => $profile['label'],
        ])->values()->all();

        return [
            'id' => $shop->public_id,
            'slug' => $shop->slug,
            'name' => $shop->name,
            'business_type' => $shop->business_type ?: 'general_retail',
            'business_type_label' => $profiles->types()[$shop->business_type ?: 'general_retail']['label'] ?? 'Negocio independiente',
            'description' => $shop->description ?? '',
            'logo_url' => $shop->logo_url,
            'address' => $shop->address ?? '',
            'maps_url' => $shop->maps_url ?? '',
            'instagram' => $shop->instagram ?? '',
            'whatsapp_country_code' => $shop->whatsapp_country_code ?? '',
            'whatsapp_number' => $shop->whatsapp_number ?? '',
            'offers_shipping' => (bool) $shop->offers_shipping,
            'primary_color' => $shop->primary_color ?: '#1d4ed8',
            'secondary_color' => $shop->secondary_color ?: '#0f172a',
            'business_hours' => $shop->business_hours ?? [],
            'business_types' => $types,
            'google' => $this->googlePayload($shop),
            'operational_settings' => $operational->forShop($shop),
            'payment_accounts' => $shop->paymentAccounts()
                ->where('is_active', true)
                ->get()
                ->map(fn ($account): array => [
                    'id' => $account->public_id,
                    'name' => $account->name,
                    'bank_name' => $account->bank_name,
                    'account_number' => $account->account_number,
                    'account_holder' => $account->account_holder,
                    'instructions' => $account->instructions,
                ])
                ->values()
                ->all(),
            'available_payment_methods' => collect(config('catalog.payment_methods', []))->map(fn (array $method): array => [
                'key' => $method['key'],
                'label' => $method['label'],
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function googlePayload(Shop $shop): array
    {
        $productCount = $shop->products()->count();
        $readyPhoto = fn ($query) => $query->where('processing_status', ProductImageProcessingStatus::Ready);
        $withoutPhotoCount = $shop->products()->whereDoesntHave('images', $readyPhoto)->count();
        $withoutDescriptionCount = $shop->products()
            ->where(fn ($query) => $query->whereNull('description')->orWhereRaw("TRIM(description) = ''"))
            ->count();
        $unpublishedCount = $shop->products()->where('moderation_status', '!=', 'active')->count();

        $checks = [
            [
                'key' => 'logo',
                'label' => 'Sube tu logo',
                'description' => 'Ayuda a reconocer tu negocio desde el primer vistazo.',
                'done' => filled($shop->logo_url),
                'count' => null,
                'section' => 'Apariencia',
            ],
            [
                'key' => 'description',
                'label' => 'Completa la descripción de tu negocio',
                'description' => 'Explica qué vendes y en qué ciudad.',
                'done' => filled($shop->description),
                'count' => null,
                'section' => 'Apariencia',
            ],
            [
                'key' => 'photos',
                'label' => $withoutPhotoCount > 0 ? "{$withoutPhotoCount} producto(s) sin foto" : 'Fotos de productos completas',
                'description' => 'Los productos con foto se muestran mejor en búsquedas y en la vitrina.',
                'done' => $productCount > 0 && $withoutPhotoCount === 0,
                'count' => $withoutPhotoCount,
                'section' => 'Imágenes',
            ],
            [
                'key' => 'product_descriptions',
                'label' => $withoutDescriptionCount > 0 ? "{$withoutDescriptionCount} producto(s) sin descripción" : 'Descripciones de productos completas',
                'description' => 'Incluye tamaño, presentación o características relevantes.',
                'done' => $productCount > 0 && $withoutDescriptionCount === 0,
                'count' => $withoutDescriptionCount,
                'section' => 'Catálogo',
            ],
            [
                'key' => 'contact',
                'label' => 'Pon tu WhatsApp y dirección',
                'description' => 'Facilita que el cliente te contacte y te encuentre.',
                'done' => filled($shop->whatsapp_number) && filled($shop->address),
                'count' => null,
                'section' => 'Contacto y horario',
            ],
            [
                'key' => 'published',
                'label' => $unpublishedCount > 0 ? "{$unpublishedCount} producto(s) por publicar" : 'Productos publicados',
                'description' => 'Solo los productos activos pueden aparecer en Google Shopping.',
                'done' => $productCount > 0 && $unpublishedCount === 0,
                'count' => $unpublishedCount,
                'section' => 'Catálogo',
            ],
            [
                'key' => 'verification',
                'label' => 'Verifica tu tienda con Search Console',
                'description' => 'Pega la etiqueta HTML de Google para verificar tu sitio.',
                'done' => filled($shop->operational_settings['marketing']['google_site_verification'] ?? null),
                'count' => null,
                'section' => 'Google',
            ],
        ];
        $weights = ['logo' => 20, 'description' => 12, 'photos' => 20, 'product_descriptions' => 15, 'contact' => 15, 'published' => 10, 'verification' => 8];
        $score = collect($checks)->sum(fn (array $check): int => $check['done'] ? ($weights[$check['key']] ?? 0) : 0);

        return [
            'score' => $score,
            'target_score' => 90,
            'pending_count' => collect($checks)->where('done', false)->count(),
            'checks' => $checks,
        ];
    }
}
