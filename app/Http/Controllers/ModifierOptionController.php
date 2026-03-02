<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreModifierOptionRequest;
use App\Http\Requests\UpdateModifierOptionRequest;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Illuminate\Http\RedirectResponse;

class ModifierOptionController extends Controller
{
    public function store(StoreModifierOptionRequest $request, ModifierGroup $modifierGroup): RedirectResponse
    {
        $this->authorize('update', $modifierGroup);

        $data = $request->validated();
        $data['modifier_group_id'] = $modifierGroup->id;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['price_adjustment'] = $data['price_adjustment'] ?? 0;

        ModifierOption::query()->create($data);

        return redirect()->back()->with('success', 'Opción creada correctamente.');
    }

    public function update(UpdateModifierOptionRequest $request, ModifierGroup $modifierGroup, ModifierOption $modifierOption): RedirectResponse
    {
        $this->authorize('update', $modifierOption);

        $modifierOption->update($request->validated());

        return redirect()->back()->with('success', 'Opción actualizada correctamente.');
    }

    public function destroy(ModifierGroup $modifierGroup, ModifierOption $modifierOption): RedirectResponse
    {
        $this->authorize('delete', $modifierOption);

        $modifierOption->delete();

        return redirect()->back()->with('success', 'Opción eliminada correctamente.');
    }
}
