<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    /**
     * Generic response used for any case that would reveal whether a specific
     * code exists or its lifecycle state (active/inactive, not-yet-valid,
     * expired, exhausted). Prevents enumeration of coupon codes across
     * restaurants while still allowing the cases below through with their
     * specific message (min_purchase and max_uses_per_customer reveal nothing
     * an attacker doesn't already know: the user's own phone and the total).
     */
    private const GENERIC_INVALID_REASON = 'Cupón no válido o no vigente.';

    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'customer_phone' => ['required', 'string', 'regex:/^\d{10}$/'],
        ]);

        /** @var Restaurant $restaurant */
        $restaurant = $request->attributes->get('restaurant');

        $coupon = Coupon::query()
            ->withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->whereRaw('UPPER(code) = ?', [strtoupper($request->input('code'))])
            ->first();

        if (! $coupon) {
            return response()->json([
                'valid' => false,
                'reason' => self::GENERIC_INVALID_REASON,
            ]);
        }

        $result = $coupon->isValidForOrder(
            (float) $request->input('subtotal'),
            $request->input('customer_phone'),
        );

        if (! $result['valid']) {
            return response()->json([
                'valid' => false,
                'reason' => $this->maskEnumerationReason($result['reason']),
            ]);
        }

        $calculatedDiscount = $coupon->calculateDiscount((float) $request->input('subtotal'));

        return response()->json([
            'valid' => true,
            'discount_type' => $coupon->discount_type,
            'discount_value' => (float) $coupon->discount_value,
            'max_discount' => $coupon->max_discount ? (float) $coupon->max_discount : null,
            'calculated_discount' => $calculatedDiscount,
        ]);
    }

    /**
     * Collapse enumeration-revealing reasons (lifecycle / exhaustion) into a
     * single generic message. Keep min_purchase and per-customer limits as-is:
     * both are useful UX feedback and require context the attacker already has.
     */
    private function maskEnumerationReason(?string $reason): string
    {
        if ($reason === null) {
            return self::GENERIC_INVALID_REASON;
        }

        // Preserve UX-useful messages: min_purchase mentions "pedido mínimo",
        // per-customer mentions "máximo de veces permitido".
        if (str_contains($reason, 'pedido mínimo') || str_contains($reason, 'máximo de veces')) {
            return $reason;
        }

        return self::GENERIC_INVALID_REASON;
    }
}
