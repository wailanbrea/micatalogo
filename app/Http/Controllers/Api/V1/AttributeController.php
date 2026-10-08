<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttributeDefinition;
use App\Models\Shop;
use App\Services\SellerMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AttributeController extends Controller
{
    public function update(
        Request $request,
        Shop $shop,
        AttributeDefinition $attribute,
        SellerMenuService $menus
    ): JsonResponse {
        abort_unless($menus->canManage($shop, $request->user()), 403);
        abort_unless($attribute->shop_id === $shop->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'filterable' => ['sometimes', 'boolean'],
            'required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $name = trim($data['name']);
        abort_if($name === '', 422, 'El nombre del atributo no puede estar vacío.');

        DB::transaction(function () use ($shop, $attribute, $data, $name): void {
            $locked = AttributeDefinition::query()
                ->whereKey($attribute->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $duplicate = AttributeDefinition::query()
                ->where('shop_id', $shop->id)
                ->whereKeyNot($locked->getKey())
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->exists();
            abort_if($duplicate, 422, 'Ya existe otro atributo con ese nombre. Cambia el nombre para conservar sus valores separados.');

            $locked->update([
                'name' => $name,
                'slug' => Str::slug($name),
                'filterable' => array_key_exists('filterable', $data) ? (bool) $data['filterable'] : $locked->filterable,
                'required' => array_key_exists('required', $data) ? (bool) $data['required'] : $locked->required,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $locked->is_active,
            ]);
        });

        return response()->json(['message' => 'Atributo actualizado.']);
    }
}
