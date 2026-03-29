<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreModifierGroupTemplateRequest;
use App\Http\Requests\UpdateModifierGroupTemplateRequest;
use App\Models\ModifierGroupTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModifierCatalogController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', ModifierGroupTemplate::class);

        $templates = ModifierGroupTemplate::with('options')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Modifiers/Index', [
            'templates' => $templates,
        ]);
    }

    public function store(StoreModifierGroupTemplateRequest $request): RedirectResponse
    {
        $this->authorize('create', ModifierGroupTemplate::class);

        $data = $request->validated();
        $restaurantId = $request->user()->restaurant_id;

        $template = ModifierGroupTemplate::create([
            'restaurant_id' => $restaurantId,
            'name' => $data['name'],
            'selection_type' => $data['selection_type'],
            'is_required' => $data['is_required'] ?? false,
            'max_selections' => $data['selection_type'] === 'multiple' ? ($data['max_selections'] ?? null) : null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => ModifierGroupTemplate::where('restaurant_id', $restaurantId)->max('sort_order') + 1,
        ]);

        foreach ($data['options'] as $sortOrder => $optData) {
            $template->options()->create([
                'name' => $optData['name'],
                'price_adjustment' => $optData['price_adjustment'] ?? 0,
                'production_cost' => $optData['production_cost'] ?? 0,
                'is_active' => $optData['is_active'] ?? true,
                'sort_order' => $sortOrder,
            ]);
        }

        return redirect()->route('modifier-catalog.index')->with('success', 'Grupo de catálogo creado correctamente.');
    }

    public function update(UpdateModifierGroupTemplateRequest $request, ModifierGroupTemplate $modifierGroupTemplate): RedirectResponse
    {
        $this->authorize('update', $modifierGroupTemplate);

        $data = $request->validated();

        $modifierGroupTemplate->update([
            'name' => $data['name'],
            'selection_type' => $data['selection_type'],
            'is_required' => $data['is_required'] ?? false,
            'max_selections' => $data['selection_type'] === 'multiple' ? ($data['max_selections'] ?? null) : null,
            'is_active' => $data['is_active'] ?? $modifierGroupTemplate->is_active,
        ]);

        $this->syncOptions($modifierGroupTemplate, $data['options']);

        return redirect()->route('modifier-catalog.index')->with('success', 'Grupo de catálogo actualizado correctamente.');
    }

    public function destroy(ModifierGroupTemplate $modifierGroupTemplate): RedirectResponse
    {
        $this->authorize('delete', $modifierGroupTemplate);

        $modifierGroupTemplate->delete();

        return redirect()->route('modifier-catalog.index')->with('success', 'Grupo de catálogo eliminado correctamente.');
    }

    public function toggle(ModifierGroupTemplate $modifierGroupTemplate): RedirectResponse
    {
        $this->authorize('update', $modifierGroupTemplate);

        $modifierGroupTemplate->update(['is_active' => ! $modifierGroupTemplate->is_active]);

        return redirect()->route('modifier-catalog.index');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', ModifierGroupTemplate::class);

        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $restaurantId = $request->user()->restaurant_id;

        foreach ($request->input('ids') as $index => $id) {
            ModifierGroupTemplate::query()
                ->where('id', $id)
                ->where('restaurant_id', $restaurantId)
                ->update(['sort_order' => $index]);
        }

        return redirect()->route('modifier-catalog.index');
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     */
    private function syncOptions(ModifierGroupTemplate $template, array $options): void
    {
        $existingOptionIds = $template->options()->pluck('id')->all();
        $incomingOptionIds = [];

        foreach ($options as $sortOrder => $optData) {
            if (! empty($optData['id']) && in_array($optData['id'], $existingOptionIds)) {
                $template->options()->where('id', $optData['id'])->update([
                    'name' => $optData['name'],
                    'price_adjustment' => $optData['price_adjustment'] ?? 0,
                    'production_cost' => $optData['production_cost'] ?? 0,
                    'is_active' => $optData['is_active'] ?? true,
                    'sort_order' => $sortOrder,
                ]);
                $incomingOptionIds[] = $optData['id'];
            } else {
                $newOpt = $template->options()->create([
                    'name' => $optData['name'],
                    'price_adjustment' => $optData['price_adjustment'] ?? 0,
                    'production_cost' => $optData['production_cost'] ?? 0,
                    'is_active' => $optData['is_active'] ?? true,
                    'sort_order' => $sortOrder,
                ]);
                $incomingOptionIds[] = $newOpt->id;
            }
        }

        $template->options()->whereNotIn('id', $incomingOptionIds)->delete();
    }
}
