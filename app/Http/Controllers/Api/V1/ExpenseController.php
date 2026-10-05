<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\ExpenseService;
use App\Services\PlanLimitsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request, Shop $shop): JsonResponse
    {
        abort_unless($request->user()->canSellAtShop($shop), 403);

        $expenses = $shop->expenses()
            ->with(['category', 'user:id,name'])
            ->latest('occurred_at')
            ->paginate(25)
            ->through(fn ($expense) => [
                'id' => $expense->public_id,
                'description' => $expense->description,
                'amount' => (float) $expense->amount,
                'payment_status' => $expense->payment_status,
                'payment_method' => $expense->payment_method,
                'occurred_at' => $expense->occurred_at?->toIso8601String(),
                'category_name' => $expense->category?->name,
                'reference' => $expense->reference,
                'notes' => $expense->notes,
                'user_name' => $expense->user?->name,
            ]);

        return response()->json($expenses);
    }

    public function categories(Request $request, Shop $shop, ExpenseService $service): JsonResponse
    {
        abort_unless($request->user()->canSellAtShop($shop), 403);

        if ($shop->expenseCategories()->count() === 0) {
            $service->seedDefaultCategories($shop);
        }

        $categories = $shop->expenseCategories()->get(['id', 'name', 'is_active']);

        return response()->json($categories);
    }

    public function store(Request $request, Shop $shop, ExpenseService $service, PlanLimitsService $limits): JsonResponse
    {
        abort_unless($request->user()->canSellAtShop($shop), 403);
        $limits->assertFeature($shop->user, 'expenses');

        $validated = $request->validate([
            'expense_category_id' => ['nullable', 'integer'],
            'category_name' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'decimal:0,2', 'min:0.01'],
            'payment_status' => ['sometimes', 'in:paid,partial,pending'],
            'payment_method' => ['sometimes', 'in:cash,card,bank_transfer,other'],
            'reference' => ['nullable', 'string', 'max:120'],
            'occurred_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $expense = $service->recordExpense($shop, $request->user(), $validated);

        return response()->json([
            'message' => 'Gasto registrado exitosamente.',
            'expense' => [
                'id' => $expense->public_id,
                'description' => $expense->description,
                'amount' => (float) $expense->amount,
                'payment_status' => $expense->payment_status,
                'payment_method' => $expense->payment_method,
                'occurred_at' => $expense->occurred_at->toIso8601String(),
                'category' => $expense->category?->name,
            ],
        ], 201);
    }
}
