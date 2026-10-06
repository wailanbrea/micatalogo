<?php

namespace App\Services;

use App\Enums\UserPlan;
use App\Enums\UserStatus;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;

class RegistrationService
{
    use \App\Actions\Fortify\PasswordValidationRules;

    public function __construct(
        private readonly BusinessProfileService $profiles,
        private readonly BusinessPresetService $presets,
        private readonly Turnstile $turnstile,
    ) {}

    public function create(array $input): User
    {
        $this->turnstile->validate($input['cf-turnstile-response'] ?? null, request(), 'register');
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            // Puntto asks for a WhatsApp number rather than a second phone field.
            // Keep `phone` accepted for older clients, but make the canonical value
            // the country code + WhatsApp number stored on the shop.
            'phone' => ['nullable', 'string', 'max:20'],
            'business_name' => ['required', 'string', 'min:2', 'max:120'],
            'business_type' => ['required', Rule::in(array_keys($this->profiles->types()))],
            'slug' => ['nullable', 'string', 'min:2', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'whatsapp_country_code' => ['required', 'regex:/^[1-9][0-9]{0,4}$/'],
            'whatsapp_number' => ['required', 'regex:/^[1-9][0-9]{5,14}$/'],
            'instagram' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9._]+$/'],
            'password' => $this->passwordRules(),
            'terms_accepted' => ['accepted'],
        ])->validate();

        $validated['email'] = Str::lower(trim($validated['email']));
        $requestedSlug = $validated['slug'] ?? $validated['business_name'];
        $validated['whatsapp_country_code'] = preg_replace('/\D/', '', $validated['whatsapp_country_code']);
        $validated['whatsapp_number'] = preg_replace('/\D/', '', $validated['whatsapp_number']);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $validated['slug'] = $this->uniqueSlug($requestedSlug);

            try {
                return DB::transaction(function () use ($validated): User {
                    $user = User::create([
                        'name' => trim($validated['name']),
                        'email' => $validated['email'],
                        'status' => UserStatus::Active,
                        'plan' => UserPlan::Free,
                        'password' => Hash::make($validated['password']),
                    ]);
                    $shop = $user->shops()->create([
                        'name' => trim($validated['business_name']),
                        'slug' => $validated['slug'],
                        'business_type' => $validated['business_type'],
                        'whatsapp_country_code' => $validated['whatsapp_country_code'],
                        'whatsapp_number' => $validated['whatsapp_number'],
                        'instagram' => isset($validated['instagram']) ? ltrim($validated['instagram'], '@') : null,
                        'status' => 'active',
                    ]);
                    $this->presets->apply($shop);

                    return $user;
                });
            } catch (QueryException $exception) {
                // The unique index is the final authority under concurrent signups.
                // Retry only a shop-slug race; email conflicts must remain errors.
                if (! str_contains((string) $exception->getMessage(), 'shops_slug_unique')) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('No se pudo reservar el enlace de la tienda. Inténtalo nuevamente.');
    }

    private function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: 'mi-negocio';
        if (in_array($base, config('business-types.reserved_slugs', []), true)) {
            $base .= '-tienda';
        }
        $slug = $base;
        $suffix = 2;
        while (Shop::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
