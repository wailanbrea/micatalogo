<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ExpenseService
{
    public function __construct(
        protected CashRegisterService $cashRegisterService
    ) {}

    public function seedDefaultCategories(Shop $shop): void
    {
        $defaults = config('catalog.default_expense_categories', [
            'Alquiler', 'Electricidad', 'Internet', 'Transporte',
            'Publicidad', 'Nómina', 'Comisiones', 'Mantenimiento',
            'Materiales', 'Impuestos', 'Otros',
        ]);

        foreach ($defaults as $name) {
            ExpenseCategory::firstOrCreate(
                ['shop_id' => $shop->id, 'name' => $name],
                ['is_active' => true]
            );
        }
    }

    public function recordExpense(Shop $shop, User $user, array $data): Expense
    {
        return DB::transaction(function () use ($shop, $user, $data): Expense {
            $categoryId = (int) ($data['expense_category_id'] ?? 0);
            $category = ExpenseCategory::query()
                ->where('shop_id', $shop->id)
                ->where('id', $categoryId)
                ->where('is_active', true)
                ->first();

            if (! $category) {
                // Check if a category name was provided to find or create
                if (! empty($data['category_name'])) {
                    $category = ExpenseCategory::firstOrCreate(
                        ['shop_id' => $shop->id, 'name' => trim((string) $data['category_name'])],
                        ['is_active' => true]
                    );
                } else {
                    throw new InvalidArgumentException('Categoría de gasto inválida o no pertenece a esta tienda.', 422);
                }
            }

            $amountCents = $this->toCents($data['amount'] ?? 0);
            if ($amountCents <= 0) {
                throw new InvalidArgumentException('El monto del gasto debe ser mayor a cero.');
            }

            $paymentMethod = $data['payment_method'] ?? 'cash';
            $paymentStatus = $data['payment_status'] ?? 'paid';

            $activeCashSession = $this->cashRegisterService->getCurrentSession($shop, $user);
            $cashSessionId = null;

            if ($paymentMethod === 'cash' && $paymentStatus === 'paid' && $activeCashSession) {
                $cashSessionId = $activeCashSession->id;
            }

            $expense = Expense::create([
                'public_id' => (string) Str::ulid(),
                'shop_id' => $shop->id,
                'user_id' => $user->id,
                'expense_category_id' => $category->id,
                'cash_register_session_id' => $cashSessionId,
                'description' => trim((string) ($data['description'] ?? 'Gasto operativo')),
                'amount' => $this->toDecimal($amountCents),
                'amount_cents' => $amountCents,
                'payment_status' => $paymentStatus,
                'payment_method' => $paymentMethod,
                'reference' => $data['reference'] ?? null,
                'occurred_at' => ! empty($data['occurred_at']) ? $data['occurred_at'] : now(),
                'notes' => $data['notes'] ?? null,
            ]);

            // Record cash movement if paid in cash with active session
            if ($paymentMethod === 'cash' && $paymentStatus === 'paid' && $activeCashSession) {
                $this->cashRegisterService->recordMovement(
                    $activeCashSession,
                    $user,
                    'expense',
                    $this->toDecimal($amountCents),
                    "Gasto: {$expense->description}",
                    'expense',
                    $expense->id
                );
            }

            return $expense;
        });
    }

    public function toCents(mixed $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) round((float) $amount * 100);
    }

    public function toDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
