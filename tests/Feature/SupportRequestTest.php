<?php

use App\Models\Shop;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.turnstile.enabled', false);
});

test('a visitor support request is identified as a visitor without an account', function () {
    $this->post(route('support.store'), supportPayload())
        ->assertRedirect(route('support.create'));

    $this->assertDatabaseHas('support_requests', [
        'requester_type' => 'visitor',
        'requester_email' => 'visitor@example.com',
        'category' => 'problem',
    ]);
});

test('a signed in user with a catalog is identified as a catalog owner', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('support.store'), supportPayload(['email' => 'other@example.com']))
        ->assertRedirect(route('support.create'));

    $this->assertDatabaseHas('support_requests', [
        'user_id' => $user->id,
        'shop_id' => $shop->id,
        'requester_type' => 'catalog_owner',
        'requester_email' => $user->email,
    ]);
});

test('a signed in user without a catalog is identified separately', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('support.store'), supportPayload())->assertRedirect(route('support.create'));

    $this->assertDatabaseHas('support_requests', [
        'user_id' => $user->id,
        'shop_id' => null,
        'requester_type' => 'registered_user',
    ]);
});

test('an admin can review and resolve a support request', function () {
    $admin = User::factory()->admin()->create();
    $support = SupportRequest::create([
        'requester_type' => 'visitor',
        'category' => 'suggestion',
        'requester_email' => 'visitor@example.com',
        'subject' => 'Agregar exportación',
        'message' => 'Sería útil exportar las ventas.',
        'status' => 'open',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.support.show', $support))
        ->assertOk()
        ->assertSee('Visitante sin cuenta');

    $this->actingAs($admin)
        ->post(route('admin.support.resolve', $support), ['resolution_notes' => 'Registrado para revisión.'])
        ->assertRedirect(route('admin.support.index'));

    $this->assertDatabaseHas('support_requests', [
        'id' => $support->id,
        'status' => 'resolved',
        'resolved_by' => $admin->id,
        'resolution_notes' => 'Registrado para revisión.',
    ]);
});

/** @return array<string, string> */
function supportPayload(array $overrides = []): array
{
    return [...[
        'category' => 'problem',
        'name' => 'Visitante',
        'email' => 'visitor@example.com',
        'subject' => 'No puedo entrar',
        'message' => 'Al intentar iniciar sesión aparece un mensaje de error.',
    ], ...$overrides];
}
