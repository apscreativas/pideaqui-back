<?php

namespace App\Services;

use App\Models\PosSale;

class PosTicketNumberService
{
    /**
     * Generate a sequential ticket number for the restaurant.
     * Must be called inside a transaction; uses lockForUpdate against the
     * latest pos_sales row to avoid concurrent collisions.
     */
    public function next(int $restaurantId): string
    {
        $last = PosSale::query()
            ->where('restaurant_id', $restaurantId)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('ticket_number');

        $lastNumber = 0;
        if ($last && preg_match('/POS-(\d+)/', $last, $matches)) {
            $lastNumber = (int) $matches[1];
        }

        return 'POS-'.str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
    }
}
