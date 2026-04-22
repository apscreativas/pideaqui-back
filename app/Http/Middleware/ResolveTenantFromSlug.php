<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Resolves the current tenant from the `{slug}` route parameter and injects
 * the Restaurant model into the request attributes, mirroring the behavior of
 * `AuthenticateRestaurantToken` so downstream controllers remain agnostic of
 * how the tenant was identified.
 *
 * Failure modes (distinct status codes to allow differentiated UX in the SPA):
 *  - 404 when slug does not exist
 *  - 410 when restaurant exists but cannot currently receive orders
 */
class ResolveTenantFromSlug
{
    public function handle(Request $request, Closure $next): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        $slug = $request->route('slug');

        if (! is_string($slug) || $slug === '') {
            return response()->json([
                'message' => 'Slug de restaurante requerido.',
                'code' => 'slug_missing',
            ], 400);
        }

        $restaurant = Restaurant::query()
            ->where('slug', $slug)
            ->first();

        if (! $restaurant) {
            return response()->json([
                'message' => 'Restaurante no encontrado.',
                'code' => 'tenant_not_found',
            ], 404);
        }

        if (! $restaurant->canReceiveOrders()) {
            return response()->json([
                'message' => 'Restaurante no disponible temporalmente.',
                'code' => 'tenant_unavailable',
                'is_active' => (bool) $restaurant->is_active,
            ], 410);
        }

        $request->attributes->set('restaurant', $restaurant);

        return $next($request);
    }
}
