<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

class VerifyEmailNotification extends BaseVerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);
        $expiresIn = Config::get('auth.verification.expire', 60);

        return (new MailMessage)
            ->subject('Verifica tu correo — PideAqui')
            ->greeting('¡Bienvenido a PideAqui!')
            ->line('Gracias por registrar tu restaurante en PideAqui.')
            ->line('Para empezar a usar tu panel, necesitamos confirmar que este correo es tuyo. Haz clic en el botón de abajo:')
            ->action('Verificar mi correo', $url)
            ->line("Este enlace expira en {$expiresIn} minutos.")
            ->line('Si no creaste esta cuenta, puedes ignorar este mensaje.')
            ->salutation('Saludos, el equipo de PideAqui');
    }
}
