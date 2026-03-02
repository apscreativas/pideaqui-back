<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDeliveryMethodsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryMethodController extends Controller
{
    public function index(Request $request): Response
    {
        $restaurant = $request->user()->load('restaurant')->restaurant;

        return Inertia::render('Settings/DeliveryMethods', [
            'restaurant' => $restaurant->only(['allows_delivery', 'allows_pickup', 'allows_dine_in']),
        ]);
    }

    public function update(UpdateDeliveryMethodsRequest $request): RedirectResponse
    {
        $restaurant = $request->user()->load('restaurant')->restaurant;

        $restaurant->update($request->validated());

        return back()->with('success', 'Métodos de entrega actualizados.');
    }
}
