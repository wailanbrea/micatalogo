<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Shop;
use App\Services\ExpenseService;
use App\Services\PlanLimitsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class SellerExpenseController extends Controller
{
    public function index(Request $request, Shop $shop, ExpenseService $expenseService, PlanLimitsService $limits): View
    {
        $limits->assertFeature($shop->user, 'expenses');

        $from = $request->from ?: now()->startOfMonth()->toDateString();
        $to = $request->to ?: now()->toDateString();
        $categoryId = $request->category_id;
        $status = $request->status;

        $categories = $shop->expenseCategories()->where('is_active', true)->orderBy('name')->get();
        if ($categories->isEmpty()) {
            $expenseService->seedDefaultCategories($shop);
            $categories = $shop->expenseCategories()->where('is_active', true)->orderBy('name')->get();
        }

        $query = $shop->expenses()
            ->with(['category', 'user', 'payments'])
            ->whereDate('occurred_at', '>=', $from)
            ->whereDate('occurred_at', '<=', $to)
            ->when($categoryId, fn ($q) => $q->where('expense_category_id', $categoryId))
            ->when($status, fn ($q) => $q->where('payment_status', $status))
            ->latest('occurred_at');

        $expenses = (clone $query)->paginate(15)->withQueryString();

        $totalIncurredCents = (int) (clone $query)->sum('amount_cents');
        $totalIncurred = $totalIncurredCents / 100.0;
        $totalPeriod = $totalIncurred;

        $totalPaidCents = (int) (clone $query)->sum('amount_paid_cents');
        $totalPaid = $totalPaidCents / 100.0;

        $totalPendingCents = max(0, $totalIncurredCents - $totalPaidCents);
        $totalPending = $totalPendingCents / 100.0;

        $byCategory = (clone $query)
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->selectRaw('expense_categories.name as cat_name, SUM(expenses.amount_cents) as cat_cents')
            ->groupBy('expense_categories.name')
            ->orderByDesc('cat_cents')
            ->get();

        $rawPaymentMethods = config('catalog.payment_methods', []);
        $paymentMethods = [];
        foreach ($rawPaymentMethods as $key => $conf) {
            $paymentMethods[$key] = is_array($conf) ? ($conf['label'] ?? ucfirst($key)) : (string) $conf;
        }
        if (empty($paymentMethods)) {
            $paymentMethods = [
                'cash' => 'Efectivo',
                'card' => 'Tarjeta',
                'bank_transfer' => 'Transferencia',
                'credit' => 'Crédito',
                'other' => 'Otro',
            ];
        }

        return view('seller.expenses.index', compact(
            'shop',
            'expenses',
            'categories',
            'from',
            'to',
            'categoryId',
            'status',
            'totalPeriod',
            'totalIncurred',
            'totalPaid',
            'totalPending',
            'byCategory',
            'paymentMethods'
        ));
    }

    public function store(Request $request, Shop $shop, ExpenseService $expenseService, PlanLimitsService $limits): RedirectResponse
    {
        $limits->assertFeature($shop->user, 'expenses');

        $validated = $request->validate([
            'expense_category_id' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_status' => ['nullable', 'in:paid,partial,pending'],
            'payment_method' => ['required', 'in:cash,card,bank_transfer,credit,other'],
            'occurred_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $expense = $expenseService->recordExpense($shop, $request->user(), $validated);

            $msg = 'Gasto registrado correctamente por RD$ '.number_format((float) $validated['amount'], 2);
            if ($expense->amount_paid_cents < $expense->amount_cents) {
                $msg .= ' (pagado: RD$ '.number_format((float) $expense->amount_paid, 2).', pendiente: RD$ '.number_format($expense->unpaidAmount(), 2).').';
            } elseif ($expense->cash_register_session_id) {
                $msg .= ' (descontado de la caja abierta actual).';
            }

            return back()->with('status', $msg);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['expense' => $e->getMessage()]);
        }
    }

    public function storePayment(Request $request, Shop $shop, Expense $expense, ExpenseService $expenseService, PlanLimitsService $limits): RedirectResponse
    {
        abort_unless($expense->shop_id === $shop->id, 404);
        $limits->assertFeature($shop->user, 'expenses');

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,card,bank_transfer,other'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'paid_at' => ['nullable', 'date'],
        ]);

        try {
            $payment = $expenseService->recordExpensePayment($shop, $expense, $request->user(), $validated);

            $msg = 'Abono registrado correctamente por RD$ '.number_format((float) $validated['amount'], 2);
            if ($payment->cash_register_session_id) {
                $msg .= ' (descontado de la caja abierta actual).';
            }

            return back()->with('status', $msg);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['expense_payment' => $e->getMessage()]);
        }
    }

    public function storeCategory(Request $request, Shop $shop, PlanLimitsService $limits): RedirectResponse
    {
        $limits->assertFeature($shop->user, 'expenses');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $name = trim($validated['name']);
        $exists = $shop->expenseCategories()->where('name', $name)->exists();
        if ($exists) {
            return back()->withErrors(['category' => 'Ya existe una categoría con ese nombre.']);
        }

        $shop->expenseCategories()->create([
            'name' => $name,
            'is_active' => true,
        ]);

        return back()->with('status', "Categoría \"{$name}\" creada.");
    }
}
