<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RestaurantResource;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var Restaurant $restaurant */
        $restaurant = $request->attributes->get('restaurant');
        $restaurant->load(['paymentMethods', 'branches' => fn ($q) => $q->where('is_active', true)]);

        return (new RestaurantResource($restaurant))->response();
    }
}
