<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDeliveryMethodsRequest;
use App\Models\DeliveryRange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryMethodController extends Controller
{
    public function index(Request $request): Response
    {
        $restaurant = $request->user()->load('restaurant')->restaurant;

        $hasDeliveryRanges = DeliveryRange::where('restaurant_id', $restaurant->id)->exists();

        return Inertia::render('Settings/DeliveryMethods', [
            'restaurant' => $restaurant->only(['allows_delivery', 'allows_pickup', 'allows_dine_in']),
            'has_delivery_ranges' => $hasDeliveryRanges,
        ]);
    }

    public function update(UpdateDeliveryMethodsRequest $request): RedirectResponse
    {
        $restaurant = $request->user()->load('restaurant')->restaurant;

        $restaurant->update($request->validated());

        return back()->with('success', 'Métodos de entrega actualizados.');
    }
}
