<?php

namespace App\Support;

use App\Models\Restaurant;

class BillingMessages
{
    /**
     * Map an operational block reason to a user-facing Spanish message.
     * Period reasons embed the relevant date from the restaurant.
     */
    public static function operational(Restaurant $restaurant, ?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        return match ($reason) {
            'disabled' => 'Tu restaurante está desactivado. Contacta al administrador.',
            'suspended' => 'Tu plan está suspendido. Renueva para seguir operando.',
            'incomplete' => 'Completa el pago de tu plan para activar el restaurante.',
            'past_due' => 'Tu último cobro falló. Actualiza tu método de pago.',
            'period_expired' => 'Tu periodo expiró el '.optional($restaurant->orders_limit_end)->format('d/m/Y').'. Renueva tu plan.',
            'period_not_started' => 'Tu periodo inicia el '.optional($restaurant->orders_limit_start)->format('d/m/Y').'.',
            default => 'Tu restaurante no puede crear pedidos ni ventas en este momento.',
        };
    }
}
