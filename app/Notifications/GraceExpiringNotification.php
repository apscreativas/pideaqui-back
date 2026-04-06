<?php

namespace App\Notifications;

use App\Models\Restaurant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GraceExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Restaurant $restaurant,
        private readonly int $daysRemaining,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $days = $this->daysRemaining;
        $subject = $days === 1
            ? 'Tu periodo de prueba vence mañana'
            : "Tu periodo de prueba vence en {$days} días";

        return (new MailMessage)
            ->subject($subject.' — PideAqui')
            ->greeting("Hola, {$this->restaurant->name}")
            ->line($days === 1
                ? 'Tu periodo de prueba vence **mañana**.'
                : "Tu periodo de prueba vence en **{$days} días**.")
            ->line('Para seguir recibiendo pedidos, elige un plan y completa tu suscripción.')
            ->action('Elegir plan', url('/settings/subscription'))
            ->line('Si no eliges un plan antes de que venza el periodo, tu restaurante será suspendido temporalmente.');
    }
}
