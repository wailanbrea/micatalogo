<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use App\Services\ExpenseService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class SellerExpenseController extends Controller
{
    public function index(Request $request, Shop $shop, ExpenseService $expenseService): View
    {
        $from = $request->from ?: now()->startOfMonth()->toDateString();
        $to = $request->to ?: now()->toDateString();
        $categoryId = $request->category_id;

        $categories = $shop->expenseCategories()->where('is_active', true)->orderBy('name')->get();
        if ($categories->isEmpty()) {
            $expenseService->seedDefaultCategories($shop);
            $categories = $shop->expenseCategories()->where('is_active', true)->orderBy('name')->get();
        }

        $query = $shop->expenses()
            ->with(['category', 'user'])
            ->whereDate('occurred_at', '>=', $from)
            ->whereDate('occurred_at', '<=', $to)
            ->when($categoryId, fn ($q) => $q->where('expense_category_id', $categoryId))
            ->latest('occurred_at');

        $expenses = (clone $query)->paginate(15)->withQueryString();

        $totalPeriodCents = (int) (clone $query)->where('payment_status', 'paid')->sum('amount_cents');
        $totalPeriod = $totalPeriodCents / 100.0;

        $byCategory = (clone $query)
            ->where('payment_status', 'paid')
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->selectRaw('expense_categories.name as cat_name, SUM(expenses.amount_cents) as cat_cents')
            ->groupBy('expense_categories.name')
            ->orderByDesc('cat_cents')
            ->get();

        $paymentMethods = config('catalog.payment_methods', [
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'bank_transfer' => 'Transferencia',
            'credit' => 'Crédito',
            'other' => 'Otro',
        ]);

        return view('seller.expenses.index', compact(
            'shop',
            'expenses',
            'categories',
            'from',
            'to',
            'categoryId',
            'totalPeriod',
            'byCategory',
            'paymentMethods'
        ));
    }

    public function store(Request $request, Shop $shop, ExpenseService $expenseService): RedirectResponse
    {
        $validated = $request->validate([
            'expense_category_id' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,card,bank_transfer,credit,other'],
            'occurred_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $expense = $expenseService->recordExpense($shop, $request->user(), $validated);

            $msg = 'Gasto registrado correctamente por RD$ ' . number_format((float) $validated['amount'], 2);
            if ($expense->cash_register_session_id) {
                $msg .= ' (descontado de la caja abierta actual).';
            }

            return back()->with('status', $msg);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['expense' => $e->getMessage()]);
        }
    }

    public function storeCategory(Request $request, Shop $shop): RedirectResponse
    {
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
