<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateOwnerUserCommand extends Command
{
    protected $signature = 'catalog:create-owner 
                            {email=wailandkey@gmail.com : Correo electronico del owner} 
                            {--name=Wailan Brea : Nombre del owner} 
                             {--password= : Contrasena inicial; requerida en ejecucion no interactiva}';

    protected $description = 'Crea o actualiza el usuario Owner/Admin de la plataforma';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $name = (string) $this->option('name');
        $password = (string) $this->option('password');

        if ($password === '') {
            if (! $this->input->isInteractive()) {
                $this->error('Proporciona una contrasena segura mediante --password en ejecucion no interactiva.');

                return self::FAILURE;
            }

            $password = (string) $this->secret('Contrasena inicial');
        }

        if (strlen($password) < 12) {
            $this->error('La contrasena debe tener al menos 12 caracteres.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]
        );

        $this->info('Usuario Owner configurado exitosamente:');
        $this->line("  ID: {$user->id}");
        $this->line("  Nombre: {$user->name}");
        $this->line("  Email: {$user->email}");
        $this->line("  Rol: {$user->role->value}");
        $this->line("  Estado: {$user->status->value}");
        $this->line('  Contrasena: configurada de forma segura.');

        return self::SUCCESS;
    }
}
