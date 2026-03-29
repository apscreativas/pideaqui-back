<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSpecialDateRequest;
use App\Http\Requests\UpdateSpecialDateRequest;
use App\Models\RestaurantSpecialDate;
use Illuminate\Http\RedirectResponse;

class SpecialDateController extends Controller
{
    public function store(StoreSpecialDateRequest $request): RedirectResponse
    {
        $this->authorize('create', RestaurantSpecialDate::class);

        $data = $request->validated();
        $data['restaurant_id'] = $request->user()->restaurant_id;

        if ($data['type'] === 'closed') {
            $data['opens_at'] = null;
            $data['closes_at'] = null;
        }

        RestaurantSpecialDate::create($data);

        return back()->with('success', 'Fecha especial agregada.');
    }

    public function update(UpdateSpecialDateRequest $request, RestaurantSpecialDate $specialDate): RedirectResponse
    {
        $this->authorize('update', $specialDate);

        $data = $request->validated();

        if ($data['type'] === 'closed') {
            $data['opens_at'] = null;
            $data['closes_at'] = null;
        }

        $specialDate->update($data);

        return back()->with('success', 'Fecha especial actualizada.');
    }

    public function destroy(RestaurantSpecialDate $specialDate): RedirectResponse
    {
        $this->authorize('delete', $specialDate);

        $specialDate->delete();

        return back()->with('success', 'Fecha especial eliminada.');
    }
}
