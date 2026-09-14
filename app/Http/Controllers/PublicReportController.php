<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Report;
use App\Models\Shop;
use App\Services\Turnstile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicReportController extends Controller
{
    public function store(Request $request, Turnstile $turnstile): RedirectResponse
    {
        $turnstile->validate($request->input('cf-turnstile-response'), $request, 'report');

        $validated = $request->validate([
            'type' => ['required', Rule::in(['shop', 'product'])],
            'id' => ['required', 'string'],
            'reason' => [
                'required',
                Rule::in([
                    'illegal_content',
                    'counterfeit',
                    'fraud',
                    'adult_content',
                    'prohibited_product',
                    'spam',
                    'copyright',
                    'other',
                ]),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $model = match ($validated['type']) {
            'shop' => Shop::where('public_id', $validated['id'])->firstOrFail(),
            'product' => Product::where('public_id', $validated['id'])->firstOrFail(),
        };

        $report = new Report([
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'status' => 'open',
        ]);
        $report->reportable()->associate($model);
        $report->save();

        return back()->with('status', 'Tu reporte ha sido recibido y será evaluado por nuestro equipo de moderación.');
    }
}
