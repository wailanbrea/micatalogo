<?php

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::post('/login', fn () => throw new TokenMismatchException())->middleware('web');
    Route::post('/test-expired-form', fn () => throw new TokenMismatchException())->middleware('web');
});

it('returns an expired login to a fresh GET without flashing passwords', function () {
    $this->post('/login', ['email' => 'qa@example.test', 'password' => 'private-test-value'])
        ->assertStatus(303)
        ->assertRedirect('/login')
        ->assertSessionHas('status')
        ->assertSessionHas('_old_input.email', 'qa@example.test')
        ->assertSessionMissing('_old_input.password');
});

it('shows a recoverable page for expired business forms without replaying them', function () {
    $this->post('/test-expired-form')->assertStatus(419)
        ->assertSee('Tu sesión ha caducado')->assertSee('Iniciar sesión');
});

it('keeps JSON expiration responses as 419', function () {
    $this->postJson('/test-expired-form')->assertStatus(419)->assertJsonStructure(['message']);
});
