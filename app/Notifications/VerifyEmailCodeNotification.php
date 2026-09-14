<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

class VerifyEmailCodeNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $code = (string) random_int(100000, 999999);

        DB::table('email_verification_codes')->where('user_id', $notifiable->getKey())->delete();
        DB::table('email_verification_codes')->insert([
            'user_id' => $notifiable->getKey(),
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $directUrl = URL::temporarySignedRoute(
            'verification.verify-link',
            now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        $manualUrl = route('verification.notice', [
            'email' => $notifiable->getEmailForVerification(),
        ]);

        $firstName = str($notifiable->name)->explode(' ')->first() ?: 'Vendedor';

        return (new MailMessage)
            ->subject('Activa tu cuenta en MiCatalogo')
            ->greeting('¡Hola, '.$firstName.'!')
            ->line('Gracias por registrarte en MiCatalogo. Para activar tu catálogo comercial y acceder de inmediato a tu panel, haz clic en el siguiente botón:')
            ->action('Activar mi cuenta y vitrina', $directUrl)
            ->line('---')
            ->line('**¿Prefieres usar tu código de 6 dígitos?**')
            ->line('Tu código de activación es:')
            ->line('**'.$code.'**')
            ->line('Puedes ingresar este código directamente en la página web: '.$manualUrl)
            ->line('Este código vence en 15 minutos (el enlace directo de activación es válido por 60 minutos). Si tú no creaste esta cuenta, puedes descartar este mensaje de forma segura.')
            ->salutation('El equipo de MiCatalogo');
    }
}
