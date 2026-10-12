<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{AccountMobileOperation, Shop, SupportConversation, User};
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountMobileOperationController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'actor_id' => ['required', 'string', 'max:40'],
            'client_operation_uuid' => ['required', 'uuid'],
            'type' => ['required', 'in:support_create,support_message,support_close,support_read,profile_update'],
            'request' => ['required', 'array'],
            'resource_id' => ['required_if:type,support_message,support_close', 'nullable', 'ulid'],
            'shop_id' => ['required_if:type,support_create', 'nullable', 'ulid'],
        ]);
        abort_unless($data['actor_id'] === (string) $request->user()->id, 403);
        $this->authorizeResource($request, $data);
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

        return DB::transaction(function () use ($request, $data, $hash) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $old = AccountMobileOperation::where('user_id', $request->user()->id)->where('client_operation_uuid', $data['client_operation_uuid'])->first();
            if ($old) {
                if (! hash_equals($old->payload_sha256, $hash)) return response()->json(['message' => 'El UUID ya identifica otro contenido.'], 409);
                return response()->json($old->result, 201);
            }
            $inner = Request::create('/api/v1/account-operation', 'POST', $data['request']);
            $inner->headers->set('Accept', 'application/json');
            $inner->setUserResolver(fn () => $request->user());
            $controller = $data['type'] === 'profile_update' ? AuthController::class : SupportChatController::class;
            $method = match ($data['type']) { 'support_create' => 'store', 'support_message' => 'storeMessage', 'support_close' => 'close', 'support_read' => 'markRead', 'profile_update' => 'update' };
            $parameters = ['request' => $inner];
            if ($data['type'] === 'support_create') $parameters['shop'] = Shop::where('public_id', $data['shop_id'])->firstOrFail();
            if (isset($data['resource_id'])) $parameters['conversation'] = SupportConversation::where('public_id', $data['resource_id'])->lockForUpdate()->firstOrFail();
            $response = app()->call([app($controller), $method], $parameters);
            if ($response->getStatusCode() >= 400) throw new HttpResponseException($response);
            $result = ['actor_id' => $data['actor_id'], 'client_operation_uuid' => $data['client_operation_uuid'], 'type' => $data['type'], 'result' => $response->getData(true)];
            AccountMobileOperation::create(['user_id' => $request->user()->id, 'client_operation_uuid' => $data['client_operation_uuid'],
                'type' => $data['type'], 'payload' => $data, 'payload_sha256' => $hash, 'result' => $result]);
            return response()->json($result, 201);
        });
    }

    public function show(Request $request, string $uuid)
    {
        $operation = AccountMobileOperation::where('user_id', $request->user()->id)->where('client_operation_uuid', $uuid)->firstOrFail();
        $this->authorizeResource($request, $operation->payload);
        if ($operation->type === 'profile_update') {
            $response = app(AuthController::class)->me($request);
        } else {
            $id = $operation->payload['resource_id'] ?? $operation->result['result']['conversation']['id'];
            $conversation = SupportConversation::where('public_id', $id)->firstOrFail();
            $response = app(SupportChatController::class)->show($request, $conversation);
        }
        return response()->json(['actor_id' => (string) $request->user()->id, 'client_operation_uuid' => $uuid,
            'type' => $operation->type, 'read_model' => $response->getData(true)]);
    }

    private function authorizeResource(Request $request, array $data): void
    {
        $user = $request->user();
        $supportOwner = strtolower($user->email) === strtolower((string) config('support.owner_email'));
        if ($data['type'] === 'support_create') {
            $shop = Shop::where('public_id', $data['shop_id'])->firstOrFail();
            abort_unless($supportOwner || $user->canSellAtShop($shop), 404);
        } elseif (in_array($data['type'], ['support_message', 'support_close', 'support_read'], true)) {
            $conversation = SupportConversation::where('public_id', $data['resource_id'])->firstOrFail();
            abort_unless($conversation->requester_id === $user->id || ($supportOwner && $conversation->assigned_to_id === $user->id), 404);
        }
    }
}
