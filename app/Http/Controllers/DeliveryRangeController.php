<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliveryRangeRequest;
use App\Http\Requests\UpdateDeliveryRangeRequest;
use App\Models\DeliveryRange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryRangeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DeliveryRange::class);

        return Inertia::render('Settings/ShippingRates', [
            'ranges' => DeliveryRange::orderBy('sort_order')->get(),
        ]);
    }

    public function store(StoreDeliveryRangeRequest $request): RedirectResponse
    {
        $this->authorize('create', DeliveryRange::class);

        $data = $request->validated();
        $data['restaurant_id'] = $request->user()->restaurant_id;
        $data['sort_order'] = (int) $data['min_km'];

        DeliveryRange::query()->create($data);

        return back()->with('success', 'Rango agregado.');
    }

    public function update(UpdateDeliveryRangeRequest $request, DeliveryRange $deliveryRange): RedirectResponse
    {
        $this->authorize('update', $deliveryRange);

        $data = $request->validated();
        $data['sort_order'] = (int) $data['min_km'];

        $deliveryRange->update($data);

        return back()->with('success', 'Rango actualizado.');
    }

    public function destroy(DeliveryRange $deliveryRange): RedirectResponse
    {
        $this->authorize('delete', $deliveryRange);

        $deliveryRange->delete();

        return back()->with('success', 'Rango eliminado.');
    }
}
