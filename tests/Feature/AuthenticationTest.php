<?php

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.turnstile.enabled', false);
});

test('authentication screens render', function () {
    $this->get('/login')->assertOk();
    $this->get('/register')->assertOk();
    $this->get(route('password.request'))->assertOk();
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
    $response = $this->post(route('register.store'), [
        'name' => 'Vendedor Demo',
        'email' => 'vendedor@example.com',
        'business_name' => 'Negocio Demo',
        'business_type' => 'general_retail',
        'whatsapp_country_code' => '1809',
        'whatsapp_number' => '8095550100',
        'terms_accepted' => '1',
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
        $this->post(route('register.store'), [
            'name' => "Vendedor {$attempt}",
            'email' => "vendedor{$attempt}@example.com",
            'business_name' => "Negocio {$attempt}",
            'business_type' => 'general_retail',
            'whatsapp_country_code' => '1809',
            'whatsapp_number' => "80955501{$attempt}0",
            'terms_accepted' => '1',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/panel');

        $this->post('/logout');
    }

    $this->post(route('register.store'), [
        'name' => 'Vendedor Bloqueado',
        'email' => 'bloqueado@example.com',
        'business_name' => 'Negocio Bloqueado',
        'business_type' => 'general_retail',
        'whatsapp_country_code' => '1809',
        'whatsapp_number' => '8095550199',
        'terms_accepted' => '1',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertStatus(429);
});

test('password reset requests are limited by IP address', function () {
    Mail::fake();
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect();
    }

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertStatus(429);
});

test('an expired password reset token cannot change the password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('old-password'),
    ]);
    $token = Password::broker()->createToken($user);
    $expiredAt = now()->subMinutes((int) config('auth.passwords.users.expire', 60) + 1);

    DB::table('password_reset_tokens')
        ->where('email', $user->email)
        ->update(['created_at' => $expiredAt]);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('email');

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

test('a password reset token is single use', function () {
    $user = User::factory()->create([
        'password' => Hash::make('old-password'),
    ]);
    $token = Password::broker()->createToken($user);
    $payload = [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ];

    $this->post(route('password.update'), $payload)->assertRedirect();
    expect(Hash::check('new-secure-password', $user->fresh()->password))->toBeTrue();

    $this->post(route('password.update'), $payload)
        ->assertRedirect()
        ->assertSessionHasErrors('email');
});

test('a suspended account cannot sign in', function () {
    $user = User::factory()->create([
        'email' => 'suspended@example.com',
        'status' => UserStatus::Suspended,
        'password' => Hash::make('password'),
    ]);

    $this->post(route('login.store'), [
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

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/panel');

    $this->assertAuthenticatedAs($user);
});

test('remember me persists a recaller cookie for web login', function () {
    $user = User::factory()->create([
        'status' => UserStatus::Active,
        'password' => Hash::make('password'),
        'remember_token' => null,
    ]);
    $recallerName = $this->app['auth']->guard()->getRecallerName();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => 'on',
    ])
        ->assertRedirect('/panel')
        ->assertCookie($recallerName);

    expect($user->fresh()->remember_token)->not->toBeNull();
});

test('an admin can sign in and is redirected to admin dashboard', function () {
    $user = User::factory()->admin()->create([
        'status' => UserStatus::Active,
        'password' => Hash::make('password'),
    ]);

    $this->post(route('login.store'), [
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

test('a seller does not see admin navigation', function () {
    $seller = User::factory()->create([
        'status' => UserStatus::Active,
    ]);

    $this->actingAs($seller)
        ->get('/panel')
        ->assertOk()
        ->assertSee('Guía para administradores de tienda')
        ->assertDontSee('Menú Administrativo')
        ->assertDontSee('Usuarios del sistema')
        ->assertSee('Vendedor');
});

test('an administrator sees the administrative navigation group', function () {
    $admin = User::factory()->admin()->create([
        'status' => UserStatus::Active,
    ]);

    $this->actingAs($admin)
        ->get('/panel')
        ->assertOk()
        ->assertSee('Menú Administrativo')
        ->assertSee('Panel administrativo')
        ->assertSee('Usuarios del sistema');
});
