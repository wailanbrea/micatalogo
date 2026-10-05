<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Shop;
use App\Services\ExpenseService;
use App\Services\PlanLimitsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request, Shop $shop, PlanLimitsService $limits): JsonResponse
    {
        abort_unless($request->user()->canSellAtShop($shop), 403);
        $limits->assertFeature($shop->user, 'expenses');

        $expenses = $shop->expenses()
            ->with(['category', 'user:id,name', 'payments'])
            ->latest('occurred_at')
            ->paginate(25)
            ->through(fn ($expense) => [
                'id' => $expense->public_id,
                'description' => $expense->description,
                'amount' => (float) $expense->amount,
                'amount_paid' => (float) $expense->amount_paid,
                'unpaid_amount' => $expense->unpaidAmount(),
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

    public function categories(Request $request, Shop $shop, ExpenseService $service, PlanLimitsService $limits): JsonResponse
    {
        abort_unless($request->user()->canSellAtShop($shop), 403);
        $limits->assertFeature($shop->user, 'expenses');

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
            'paid_amount' => ['nullable', 'decimal:0,2', 'min:0'],
            'payment_status' => ['sometimes', 'in:paid,partial,pending'],
            'payment_method' => ['sometimes', 'in:cash,card,bank_transfer,other'],
            'reference' => ['nullable', 'string', 'max:120'],
            'occurred_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'client_operation_uuid' => ['nullable', 'uuid'],
        ]);

        if (! empty($validated['client_operation_uuid'])) {
            $validated['payload_sha256'] = hash('sha256', json_encode($validated, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        }

        try {
            $expense = $service->recordExpense($shop, $request->user(), $validated);
        } catch (\InvalidArgumentException $e) {
            $status = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 422;

            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json([
            'message' => 'Gasto registrado exitosamente.',
            'expense' => [
                'id' => $expense->public_id,
                'description' => $expense->description,
                'amount' => (float) $expense->amount,
                'amount_paid' => (float) $expense->amount_paid,
                'unpaid_amount' => $expense->unpaidAmount(),
                'payment_status' => $expense->payment_status,
                'payment_method' => $expense->payment_method,
                'occurred_at' => $expense->occurred_at->toIso8601String(),
                'category' => $expense->category?->name,
            ],
        ], 201);
    }

    public function pay(Request $request, Shop $shop, Expense $expense, ExpenseService $service, PlanLimitsService $limits): JsonResponse
    {
        abort_unless($request->user()->canSellAtShop($shop), 403);
        abort_unless($expense->shop_id === $shop->id, 404);
        $limits->assertFeature($shop->user, 'expenses');

        $validated = $request->validate([
            'amount' => ['required', 'decimal:0,2', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,card,bank_transfer,other'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'paid_at' => ['nullable', 'date'],
            'client_operation_uuid' => ['nullable', 'uuid'],
        ]);

        if (! empty($validated['client_operation_uuid'])) {
            $validated['payload_sha256'] = hash('sha256', json_encode($validated, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        }

        try {
            $payment = $service->recordExpensePayment($shop, $expense, $request->user(), $validated);
        } catch (\InvalidArgumentException $e) {
            $status = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 422;

            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json([
            'message' => 'Abono al gasto registrado exitosamente.',
            'payment' => [
                'id' => $payment->public_id,
                'amount' => (float) $payment->amount,
                'payment_method' => $payment->payment_method,
                'paid_at' => $payment->paid_at->toIso8601String(),
            ],
            'expense' => [
                'id' => $expense->public_id,
                'amount' => (float) $expense->amount,
                'amount_paid' => (float) $expense->amount_paid,
                'unpaid_amount' => $expense->unpaidAmount(),
                'payment_status' => $expense->payment_status,
            ],
        ], 201);
    }
}
