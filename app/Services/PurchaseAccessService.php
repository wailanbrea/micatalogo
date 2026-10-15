<?php

namespace App\Services;

use App\Models\PurchaseDocument;
use App\Models\Shop;
use App\Models\User;

class PurchaseAccessService
{
    private readonly SellerMenuService $menus;

    public function __construct(SellerMenuService $menus)
    {
        $this->menus = $menus;
    }

    public function keyForType(?string $type): ?string
    {
        return match ($type) {
            'container' => 'containers', 'load' => 'loads', 'purchase_invoice' => 'purchase_invoices',
            'supplier_debt' => 'suppliers', default => null,
        };
    }

    public function keyForDocument(PurchaseDocument $document): ?string
    {
        return $document->status === 'debt' ? 'suppliers' : $this->keyForType($document->type);
    }

    public function assertType(Shop $shop, User $user, mixed $type): void
    {
        abort_unless($user->canSellAtShop($shop), 404);
        $visible = $this->menus->visibleForUser($shop, $user);
        $key = is_string($type) ? $this->keyForType($type) : null;
        if ($type === 'supplier_debt') {
            abort_unless(array_intersect(['suppliers', 'purchase_invoices'], $visible) !== [], 403);

            return;
        }
        abort_unless($key ? in_array($key, $visible, true) : array_intersect(['containers', 'loads', 'purchase_invoices', 'suppliers'], $visible) !== [], 403);
    }

    public function assertDocument(Shop $shop, User $user, PurchaseDocument $document): void
    {
        abort_unless($document->shop_id === $shop->id && $user->canSellAtShop($shop), 404);
        if ($document->status === 'debt') {
            $this->assertType($shop, $user, 'supplier_debt');

            return;
        }
        abort_unless(in_array($this->keyForDocument($document), $this->menus->visibleForUser($shop, $user), true), 403);
    }

    public function assertPreview(Shop $shop, User $user): void
    {
        abort_unless($user->canSellAtShop($shop), 404);
        abort_unless(array_intersect(['containers', 'loads', 'purchase_invoices'], $this->menus->visibleForUser($shop, $user)) !== [], 403);
    }
}
