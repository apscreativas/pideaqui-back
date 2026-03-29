<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePromotionRequest;
use App\Http\Requests\UpdatePromotionRequest;
use App\Models\ModifierGroupTemplate;
use App\Models\Promotion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    use Concerns\SyncsModifierGroups;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Promotion::class);

        $promotions = Promotion::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Promotions/Index', [
            'promotions' => $promotions,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Promotion::class);

        $catalogTemplates = ModifierGroupTemplate::with('options')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Promotions/Create', [
            'catalogTemplates' => $catalogTemplates,
        ]);
    }

    public function store(StorePromotionRequest $request): RedirectResponse
    {
        $this->authorize('create', Promotion::class);

        $data = $request->validated();
        $data['restaurant_id'] = $request->user()->restaurant_id;
        $data['sort_order'] = Promotion::where('restaurant_id', $data['restaurant_id'])->max('sort_order') + 1;
        $data['is_active'] = $data['is_active'] ?? false;
        $data['production_cost'] = $data['production_cost'] ?? 0;
        $data['active_days'] = array_values(array_unique(array_map('intval', $data['active_days'])));

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('promotions', config('filesystems.media_disk', 'public'));
        }

        $modifierGroups = $data['modifier_groups'] ?? [];
        $catalogTemplateIds = $data['catalog_template_ids'] ?? [];
        unset($data['image'], $data['modifier_groups'], $data['catalog_template_ids']);

        $promotion = Promotion::query()->create($data);

        $this->syncModifierGroups($promotion, $modifierGroups);
        $this->syncCatalogTemplates($promotion, $catalogTemplateIds);

        return redirect()->route('promotions.index')->with('success', 'Promoción creada correctamente.');
    }

    public function edit(Promotion $promotion): Response
    {
        $this->authorize('update', $promotion);

        $promotion->load(['modifierGroups.options', 'modifierGroupTemplates.options']);

        $catalogTemplates = ModifierGroupTemplate::with('options')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Promotions/Edit', [
            'promotion' => $promotion,
            'catalogTemplates' => $catalogTemplates,
        ]);
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): RedirectResponse
    {
        $this->authorize('update', $promotion);

        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? $promotion->is_active;
        $data['production_cost'] = $data['production_cost'] ?? $promotion->production_cost;
        $data['active_days'] = array_values(array_unique(array_map('intval', $data['active_days'])));

        if ($request->hasFile('image')) {
            if ($promotion->image_path) {
                Storage::disk(config('filesystems.media_disk', 'public'))->delete($promotion->image_path);
            }
            $data['image_path'] = $request->file('image')->store('promotions', config('filesystems.media_disk', 'public'));
        }

        $modifierGroups = $data['modifier_groups'] ?? [];
        $catalogTemplateIds = $data['catalog_template_ids'] ?? [];
        unset($data['image'], $data['modifier_groups'], $data['catalog_template_ids']);

        $promotion->update($data);

        $this->syncModifierGroups($promotion, $modifierGroups);
        $this->syncCatalogTemplates($promotion, $catalogTemplateIds);

        return redirect()->route('promotions.index')->with('success', 'Promoción actualizada correctamente.');
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        $this->authorize('delete', $promotion);

        if ($promotion->image_path) {
            Storage::disk(config('filesystems.media_disk', 'public'))->delete($promotion->image_path);
        }

        $promotion->delete();

        return redirect()->route('promotions.index')->with('success', 'Promoción eliminada correctamente.');
    }

    public function toggle(Promotion $promotion): RedirectResponse
    {
        $this->authorize('update', $promotion);

        $promotion->update(['is_active' => ! $promotion->is_active]);

        return redirect()->route('promotions.index');
    }

    /**
     * @param  array<int, int>  $templateIds
     */
    private function syncCatalogTemplates(Promotion $promotion, array $templateIds): void
    {
        $syncData = [];
        foreach ($templateIds as $sortOrder => $templateId) {
            $syncData[$templateId] = ['sort_order' => $sortOrder];
        }
        $promotion->modifierGroupTemplates()->sync($syncData);
    }

    public function reorder(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Promotion::class);

        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $restaurantId = $request->user()->restaurant_id;

        foreach ($request->input('ids') as $index => $id) {
            Promotion::query()
                ->where('id', $id)
                ->where('restaurant_id', $restaurantId)
                ->update(['sort_order' => $index]);
        }

        return redirect()->route('promotions.index');
    }
}
