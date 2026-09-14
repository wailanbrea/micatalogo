<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class Turnstile
{
    public function validate(?string $token, Request $request, string $action): void
    {
        if (! config('services.turnstile.enabled')) {
            return;
        }

        if (blank($token)) {
            $this->fail();
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $request->ip(),
                    'idempotency_key' => (string) str()->uuid(),
                ]);
        } catch (ConnectionException) {
            $this->fail();
        }

        if (! $response->successful() || ! $response->json('success') || $response->json('action') !== $action) {
            $this->fail();
        }
    }

    private function fail(): never
    {
        throw ValidationException::withMessages([
            'cf-turnstile-response' => 'No pudimos verificar que eres una persona. Intentalo de nuevo.',
        ]);
    }
}
