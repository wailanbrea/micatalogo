<?php

use App\Models\Shop;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('support chat delivers requests to the configured owner in arrival order', function () {
    $owner = User::factory()->create([
        'name' => 'BSolutions Support',
        'email' => 'wailandkey@gmail.com',
    ]);
    $requester = User::factory()->create(['name' => 'Cliente de prueba']);
    $shop = Shop::factory()->for($requester)->create(['name' => 'Tienda de prueba']);
    $requestToken = $requester->createToken('android-support')->plainTextToken;
    $ownerToken = $owner->createToken('android-support-owner')->plainTextToken;

    $first = $this->withToken($requestToken)
        ->postJson('/api/v1/shops/'.$shop->public_id.'/support/conversations', [
            'subject' => 'No puedo cargar una imagen',
            'message' => 'La imagen no termina de subir.',
        ])
        ->assertCreated()
        ->assertJsonPath('conversation.requester.email', $requester->email)
        ->assertJsonPath('conversation.assigned_to.email', $owner->email)
        ->assertJsonPath('messages.0.body', 'La imagen no termina de subir.');

    $second = $this->withToken($requestToken)
        ->postJson('/api/v1/shops/'.$shop->public_id.'/support/conversations', [
            'message' => 'También necesito ayuda con el inventario.',
        ])
        ->assertCreated();

    app('auth')->forgetGuards();
    $inbox = $this->withToken($ownerToken)
        ->getJson('/api/v1/support/conversations');
    $inbox
        ->assertOk()
        ->assertJsonPath('is_inbox', true)
        ->assertJsonPath('conversations.0.id', $second->json('conversation.id'))
        ->assertJsonPath('conversations.0.requester.email', $requester->email)
        ->assertJsonPath('conversations.1.id', $first->json('conversation.id'));

    app('auth')->forgetGuards();
    $this->withToken($ownerToken)
        ->getJson('/api/v1/support/conversations/'.$first->json('conversation.id'))
        ->assertOk()
        ->assertJsonPath('messages.0.sender.email', $requester->email)
        ->assertJsonPath('messages.0.body', 'La imagen no termina de subir.');

    app('auth')->forgetGuards();
    $this->withToken($ownerToken)
        ->postJson('/api/v1/support/conversations/'.$first->json('conversation.id').'/messages', [
            'message' => 'Ya estoy revisando tu solicitud.',
        ])
        ->assertCreated()
        ->assertJsonPath('chat_message.body', 'Ya estoy revisando tu solicitud.');

    expect(SupportConversation::query()->count())->toBe(2)
        ->and(SupportMessage::query()->count())->toBe(3);
});

test('support chat keeps conversations private to the requester and owner', function () {
    $owner = User::factory()->create(['email' => 'wailandkey@gmail.com']);
    $requester = User::factory()->create();
    $other = User::factory()->create();
    $shop = Shop::factory()->for($requester)->create();
    $conversation = SupportConversation::create([
        'shop_id' => $shop->id,
        'requester_id' => $requester->id,
        'assigned_to_id' => $owner->id,
        'subject' => 'Privado',
        'last_message_at' => now(),
    ]);

    $this->withToken($other->createToken('android-support-other')->plainTextToken)
        ->getJson('/api/v1/support/conversations/'.$conversation->public_id)
        ->assertNotFound();
});
