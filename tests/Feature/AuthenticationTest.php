<?php

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.turnstile.enabled', false);
});

test('authentication screens render', function () {
    $this->get('/login')->assertOk();
    $this->get('/register')->assertOk();
    $this->get('/forgot-password')->assertOk();
});

test('turnstile validates the expected form action on the server', function () {
    config()->set('services.turnstile.enabled', true);
    config()->set('services.turnstile.secret_key', 'test-secret');

    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
            'success' => true,
            'action' => 'register',
        ]),
    ]);

    app(Turnstile::class)->validate(
        'test-token',
        Request::create('/register', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']),
        'register',
    );

    Http::assertSent(fn ($request) => $request['response'] === 'test-token');
});

test('a visitor can register an active account', function () {
    $response = $this->post('/register', [
        'name' => 'Vendedor Demo',
        'email' => 'vendedor@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect('/panel');

    $this->assertDatabaseHas('users', [
        'email' => 'vendedor@example.com',
        'status' => UserStatus::Active->value,
    ]);
});

test('registration is limited by IP address', function () {
    foreach (range(1, 3) as $attempt) {
        $this->post('/register', [
            'name' => "Vendedor {$attempt}",
            'email' => "vendedor{$attempt}@example.com",
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/panel');

        $this->post('/logout');
    }

    $this->post('/register', [
        'name' => 'Vendedor Bloqueado',
        'email' => 'bloqueado@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertStatus(429);
});

test('password reset requests are limited by IP address', function () {
    Mail::fake();
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect();
    }

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertStatus(429);
});

test('a suspended account cannot sign in', function () {
    $user = User::factory()->create([
        'email' => 'suspended@example.com',
        'status' => UserStatus::Suspended,
        'password' => Hash::make('password'),
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('an active account can sign in', function () {
    $user = User::factory()->create([
        'status' => UserStatus::Active,
        'password' => Hash::make('password'),
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/panel');

    $this->assertAuthenticatedAs($user);
});

test('an admin can sign in and is redirected to admin dashboard', function () {
    $user = User::factory()->admin()->create([
        'status' => UserStatus::Active,
        'password' => Hash::make('password'),
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);
});

test('the seller panel requires a verified account', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get('/panel')
        ->assertRedirect('/email/verify');
});
