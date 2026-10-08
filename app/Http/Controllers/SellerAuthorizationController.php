<?php

namespace App\Http\Controllers;

use App\Models\AuthorizationRequest;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SellerAuthorizationController extends Controller
{
    public function storeApi(Request $request, Shop $shop): JsonResponse
    {
        abort_unless($request->user()->canSellAtShop($shop), 404);

        $data = $request->validate([
            'action' => ['required', 'string', 'max:120'],
            'context' => ['nullable', 'array', 'max:20'],
        ]);

        $pending = AuthorizationRequest::query()
            ->where('shop_id', $shop->id)
            ->where('requested_by', $request->user()->id)
            ->where('action', $data['action'])
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        $authorization = $pending ?: AuthorizationRequest::create([
            'shop_id' => $shop->id,
            'requested_by' => $request->user()->id,
            'action' => $data['action'],
            'context' => $data['context'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'request' => $this->serialize($authorization->load('requester')),
            'created' => ! $pending,
        ], $pending ? 200 : 201);
    }

    public function approve(Request $request, Shop $shop, AuthorizationRequest $authorizationRequest): RedirectResponse
    {
        $this->applyDecision($request, $shop, $authorizationRequest, 'approved');

        return back()->with('status', 'Solicitud aprobada.');
    }

    public function reject(Request $request, Shop $shop, AuthorizationRequest $authorizationRequest): RedirectResponse
    {
        $this->applyDecision($request, $shop, $authorizationRequest, 'rejected');

        return back()->with('status', 'Solicitud rechazada.');
    }

    public function approveApi(Request $request, Shop $shop, AuthorizationRequest $authorizationRequest): JsonResponse
    {
        return $this->decideApi($request, $shop, $authorizationRequest, 'approved');
    }

    public function rejectApi(Request $request, Shop $shop, AuthorizationRequest $authorizationRequest): JsonResponse
    {
        return $this->decideApi($request, $shop, $authorizationRequest, 'rejected');
    }

    private function decideApi(Request $request, Shop $shop, AuthorizationRequest $authorizationRequest, string $status): JsonResponse
    {
        $authorization = $this->applyDecision($request, $shop, $authorizationRequest, $status);

        return response()->json(['message' => $status === 'approved' ? 'Solicitud aprobada.' : 'Solicitud rechazada.', 'request' => $this->serialize($authorization->load('requester'))]);
    }

    private function applyDecision(Request $request, Shop $shop, AuthorizationRequest $authorizationRequest, string $status): AuthorizationRequest
    {
        abort_unless($authorizationRequest->shop_id === $shop->id, 404);
        abort_unless($request->user()->can('update', $shop), 403);

        if ($authorizationRequest->status !== 'pending') {
            abort(409, 'Esta solicitud ya fue resuelta.');
        }

        $authorizationRequest->update([
            'status' => $status,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        return $authorizationRequest->fresh();
    }

    private function serialize(AuthorizationRequest $request): array
    {
        return [
            'id' => $request->public_id,
            'action' => $request->action,
            'context' => $request->context ?: [],
            'status' => $request->status,
            'requested_at' => $request->created_at?->toIso8601String(),
            'requested_by' => [
                'id' => $request->requester?->public_id,
                'name' => $request->requester?->name ?: 'Vendedor',
                'email' => $request->requester?->email,
            ],
        ];
    }
}
