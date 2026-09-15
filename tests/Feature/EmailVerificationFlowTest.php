<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_accessing_panel_is_redirected_to_verify_page(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/panel');

        $response->assertRedirect('/email/verify');
    }

    public function test_guest_can_access_verification_notice_page(): void
    {
        $response = $this->get('/email/verify');

        $response->assertOk();
        $response->assertSee('Verifica tu correo');
        $response->assertSee('Código de 6 dígitos');
    }

    public function test_notification_contains_direct_signed_activation_link_and_numeric_code(): void
    {
        $user = User::factory()->unverified()->create();

        $notification = new VerifyEmailCodeNotification;
        $mail = $notification->toMail($user);

        // Check code was stored in DB
        $this->assertDatabaseHas('email_verification_codes', [
            'user_id' => $user->id,
        ]);

        $this->assertSame('Activa tu cuenta en MiCatalogo', $mail->subject);
        $this->assertNotNull($mail->actionUrl);
        $this->assertStringContainsString('/email/activar/', $mail->actionUrl);
        $this->assertStringContainsString('signature=', $mail->actionUrl);
    }

    public function test_guest_can_verify_account_via_direct_signed_link_and_is_logged_in(): void
    {
        $user = User::factory()->unverified()->create();

        $signedUrl = URL::temporarySignedRoute(
            'verification.verify-link',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $response = $this->get($signedUrl);

        $response->assertRedirect('/panel');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_expired_or_tampered_signed_link_redirects_gracefully_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $invalidUrl = route('verification.verify-link', [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]).'?signature=tampered-signature';

        $response = $this->get($invalidUrl);

        $response->assertRedirect('/email/verify');
        $response->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_authenticated_user_can_verify_with_valid_6_digit_code(): void
    {
        $user = User::factory()->unverified()->create();

        DB::table('email_verification_codes')->insert([
            'user_id' => $user->id,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->post('/email/verify-code', [
            'code' => '123456',
        ]);

        $response->assertRedirect('/panel');
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $user->id]);
    }

    public function test_guest_user_can_verify_with_email_and_valid_6_digit_code_and_is_logged_in(): void
    {
        $user = User::factory()->unverified()->create();

        DB::table('email_verification_codes')->insert([
            'user_id' => $user->id,
            'code_hash' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/email/verify-code', [
            'email' => $user->email,
            'code' => '654321',
        ]);

        $response->assertRedirect('/panel');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $user->id]);
    }

    public function test_verification_fails_with_invalid_code(): void
    {
        $user = User::factory()->unverified()->create();

        DB::table('email_verification_codes')->insert([
            'user_id' => $user->id,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->post('/email/verify-code', [
            'code' => '999999',
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_guest_can_request_code_resend_with_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $response = $this->post('/email/verification-code', [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status', 'verification-code-sent');
        Notification::assertSentTo($user, VerifyEmailCodeNotification::class);
    }

    public function test_auth_failed_translation_is_localized_in_spanish(): void
    {
        app()->setLocale('es');

        $this->assertSame(
            'Estas credenciales no coinciden con nuestros registros.',
            trans('auth.failed')
        );
    }
}
