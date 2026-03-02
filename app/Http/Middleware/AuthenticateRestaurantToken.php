<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticateRestaurantToken
{
    public function handle(Request $request, Closure $next): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        $token = $request->header('X-Restaurant-Token') ?: $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Token de acceso requerido.'], 401);
        }

        $restaurant = Restaurant::query()
            ->where('access_token', $token)
            ->first();

        if (! $restaurant || ! $restaurant->is_active) {
            return response()->json(['message' => 'Token inválido o restaurante inactivo.'], 401);
        }

        $request->attributes->set('restaurant', $restaurant);

        return $next($request);
    }
}
