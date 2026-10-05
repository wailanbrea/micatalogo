<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminShopController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json(
            Shop::query()
                ->with('user:id,name,email')
                ->withCount('products')
                ->orderBy('name')
                ->get()
                ->map(fn (Shop $shop): array => $this->payload($shop))
                ->values()
        );
    }

    public function update(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeAdmin($request);

        $request->merge([
            'whatsapp_country_code' => preg_replace('/\D/', '', (string) $request->input('whatsapp_country_code')),
            'whatsapp_number' => preg_replace('/\D/', '', (string) $request->input('whatsapp_number')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'whatsapp_country_code' => ['required', 'regex:/^[1-9][0-9]{0,4}$/'],
            'whatsapp_number' => ['required', 'regex:/^[1-9][0-9]{5,14}$/'],
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ]);

        $shop->update([
            ...$validated,
        ]);

        return response()->json($this->payload($shop->fresh(['user'])->loadCount('products')));
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }

    private function payload(Shop $shop): array
    {
        return [
            'id' => $shop->public_id,
            'name' => $shop->name,
            'slug' => $shop->slug,
            'owner_name' => $shop->user->name,
            'owner_email' => $shop->user->email,
            'whatsapp_country_code' => $shop->whatsapp_country_code,
            'whatsapp_number' => $shop->whatsapp_number,
            'status' => $shop->status,
            'product_count' => $shop->products_count,
        ];
    }
}
