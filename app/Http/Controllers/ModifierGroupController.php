<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreModifierGroupRequest;
use App\Http\Requests\UpdateModifierGroupRequest;
use App\Models\ModifierGroup;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ModifierGroupController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', ModifierGroup::class);

        $modifierGroups = ModifierGroup::with(['options', 'products:id,name'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Modifiers/Index', [
            'modifierGroups' => $modifierGroups,
        ]);
    }

    public function store(StoreModifierGroupRequest $request): RedirectResponse
    {
        $this->authorize('create', ModifierGroup::class);

        $data = $request->validated();
        $data['restaurant_id'] = $request->user()->restaurant_id;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_required'] = $data['is_required'] ?? false;

        ModifierGroup::query()->create($data);

        return redirect()->back()->with('success', 'Grupo de modificadores creado correctamente.');
    }

    public function update(UpdateModifierGroupRequest $request, ModifierGroup $modifierGroup): RedirectResponse
    {
        $this->authorize('update', $modifierGroup);

        $data = $request->validated();
        $data['is_required'] = $data['is_required'] ?? $modifierGroup->is_required;

        $modifierGroup->update($data);

        return redirect()->back()->with('success', 'Grupo de modificadores actualizado correctamente.');
    }

    public function destroy(ModifierGroup $modifierGroup): RedirectResponse
    {
        $this->authorize('delete', $modifierGroup);

        $modifierGroup->delete();

        return redirect()->back()->with('success', 'Grupo de modificadores eliminado correctamente.');
    }
}
