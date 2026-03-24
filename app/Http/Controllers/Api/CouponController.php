<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
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
                'reason' => 'Cupón no encontrado.',
            ]);
        }

        $result = $coupon->isValidForOrder(
            (float) $request->input('subtotal'),
            $request->input('customer_phone'),
        );

        if (! $result['valid']) {
            return response()->json([
                'valid' => false,
                'reason' => $result['reason'],
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
}
