<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShopCategoryRequest;
use App\Models\Shop;
use App\Models\ShopCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShopCategoryController extends Controller
{
    public function index(Shop $shop): View
    {
        return view('seller.categories.index', ['shop' => $shop, 'categories' => $shop->categories()->orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function store(ShopCategoryRequest $request, Shop $shop): RedirectResponse
    {
        $shop->categories()->create($this->attributes($request->validated(), $shop));

        return back()->with('status', 'Categoria creada.');
    }

    public function update(ShopCategoryRequest $request, Shop $shop, ShopCategory $category): RedirectResponse
    {
        $category->update($this->attributes($request->validated(), $shop, $category));

        return back()->with('status', 'Categoria actualizada.');
    }

    public function destroy(Shop $shop, ShopCategory $category): RedirectResponse
    {
        $category->delete();

        return back()->with('status', 'Categoria eliminada.');
    }

    private function attributes(array $input, Shop $shop, ?ShopCategory $category = null): array
    {
        $base = Str::slug($input['slug'] ?? $input['name']) ?: 'categoria';
        $slug = $base;
        $suffix = 2;

        while ($shop->categories()->where('slug', $slug)->when($category, fn ($categories) => $categories->whereKeyNot($category))->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return [...$input, 'slug' => $slug, 'sort_order' => $input['sort_order'] ?? 0];
    }
}
