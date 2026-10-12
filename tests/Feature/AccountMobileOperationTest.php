<?php

use App\Models\AccountMobileOperation;
use App\Models\Shop;
use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function accountCommand(User $user, string $type, array $request, array $extra = []): array
{
    return [
        'actor_id' => (string) $user->id,
        'client_operation_uuid' => (string) Str::uuid(),
        'type' => $type,
        'request' => $request,
    ] + $extra;
}

test('account profile and support envelopes replay exactly once', function () {
    User::factory()->create(['email' => 'wailandkey@gmail.com']);
    $user = User::factory()->create(['name' => 'Cuenta original']);
    $shop = Shop::factory()->for($user)->create();
    $token = $user->createToken('android-account-operations')->plainTextToken;

    $profile = accountCommand($user, 'profile_update', [
        'name' => 'Cuenta actualizada',
        'email' => $user->email,
    ]);
    $this->withToken($token)
        ->postJson('/api/v1/me/mobile-operations', $profile)
        ->assertCreated()
        ->assertJsonPath('type', 'profile_update');
    $this->withToken($token)
        ->postJson('/api/v1/me/mobile-operations', $profile)
        ->assertCreated()
        ->assertJsonPath('client_operation_uuid', $profile['client_operation_uuid']);

    $support = accountCommand($user, 'support_create', [
        'subject' => 'Sincronización',
        'message' => 'La operación debe conservarse si se pierde el ACK.',
    ], ['shop_id' => $shop->public_id]);
    $created = $this->withToken($token)
        ->postJson('/api/v1/me/mobile-operations', $support)
        ->assertCreated();
    $conversationId = $created->json('result.conversation.id');
    expect($conversationId)->not->toBeNull();

    $this->withToken($token)
        ->postJson('/api/v1/me/mobile-operations', $support)
        ->assertCreated()
        ->assertJsonPath('result.conversation.id', $conversationId);

    expect(User::find($user->id)->name)->toBe('Cuenta actualizada')
        ->and(SupportConversation::where('requester_id', $user->id)->count())->toBe(1)
        ->and(AccountMobileOperation::where('user_id', $user->id)->count())->toBe(2);
});

test('account support envelopes keep conversations private', function () {
    User::factory()->create(['email' => 'wailandkey@gmail.com']);
    $user = User::factory()->create();
    $other = User::factory()->create();
    $shop = Shop::factory()->for($user)->create();
    $token = $user->createToken('android-account-operations')->plainTextToken;
    $otherToken = $other->createToken('android-account-operations-other')->plainTextToken;

    $created = $this->withToken($token)
        ->postJson('/api/v1/me/mobile-operations', accountCommand($user, 'support_create', [
            'message' => 'Conversación privada.',
        ], ['shop_id' => $shop->public_id]))
        ->assertCreated();

    $message = accountCommand($other, 'support_message', ['message' => 'No debo entrar.'], [
        'resource_id' => $created->json('result.conversation.id'),
    ]);
    $this->withToken($otherToken)
        ->postJson('/api/v1/me/mobile-operations', $message)
        ->assertStatus(403);
});
