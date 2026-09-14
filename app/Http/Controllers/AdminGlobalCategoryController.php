<?php

namespace App\Http\Controllers;

use App\Http\Requests\GlobalCategoryRequest;
use App\Models\GlobalCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminGlobalCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.global-categories.index', [
            'categories' => GlobalCategory::query()
                ->with(['parent', 'children'])
                ->withCount(['products', 'children'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(GlobalCategoryRequest $request): RedirectResponse
    {
        GlobalCategory::create($this->attributes($request->validated()));

        return back()->with('status', 'Categoria global creada.');
    }

    public function update(GlobalCategoryRequest $request, GlobalCategory $category): RedirectResponse
    {
        $category->update($this->attributes($request->validated(), $category));

        return back()->with('status', 'Categoria global actualizada.');
    }

    public function destroy(GlobalCategory $category): RedirectResponse
    {
        $category->delete();

        return back()->with('status', 'Categoria global eliminada.');
    }

    private function attributes(array $input, ?GlobalCategory $category = null): array
    {
        $base = Str::slug($input['slug'] ?? $input['name']) ?: 'categoria';
        $slug = $base;
        $suffix = 2;

        while (GlobalCategory::query()->where('slug', $slug)->when($category, fn ($categories) => $categories->whereKeyNot($category))->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return [...$input, 'slug' => $slug, 'sort_order' => $input['sort_order'] ?? 0];
    }
}
