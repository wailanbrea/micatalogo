<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\RegistrationService;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     */
    public function create(array $input): User
    {
        return app(RegistrationService::class)->create($input);
    }
}
