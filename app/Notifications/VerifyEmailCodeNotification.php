<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (new MailMessage)
            ->subject('Tu código de verificación de MiCatalogo')
            ->greeting('Hola, '.str($notifiable->name)->explode(' ')->first())
            ->line('Usa este código para confirmar tu correo y activar tu catálogo:')
            ->line('**'.$code.'**')
            ->line('El código vence en 10 minutos. Si no solicitaste esta cuenta, puedes ignorar este correo.')
            ->salutation('El equipo de MiCatalogo');
    }
}
