<?php

namespace App\Notifications;

use App\Models\Shop;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class SellerInvitationNotification extends Notification
{
    public function __construct(private readonly Shop $shop, private readonly string $accessType = 'vendedor')
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $activationUrl = URL::temporarySignedRoute(
            'seller.invitation.show',
            now()->addHours(24),
            [
                'user' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
                'shop' => $this->shop,
            ]
        );

        return (new MailMessage)
            ->subject("Te invitaron a {$this->accessType} en {$this->shop->name}")
            ->greeting('Hola, '.(str($notifiable->name)->explode(' ')->first() ?: 'Usuario').'!')
            ->line("Te invitaron como {$this->accessType} de {$this->shop->name} en MiCatalogo.")
            ->line('Confirma tu correo y crea tu contraseña para activar tu acceso.')
            ->action('Crear contraseña y activar acceso', $activationUrl)
            ->line('Después podrás iniciar sesión desde la web o la aplicación BSPOS.')
            ->line('Vitrina de la tienda: '.route('shops.show', $this->shop))
            ->line('Este enlace vence en 24 horas. Si no esperabas esta invitación, puedes descartar este mensaje.');
    }
}
