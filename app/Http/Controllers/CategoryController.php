<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', Category::class);

        $data = $request->validated();
        $data['restaurant_id'] = $request->user()->restaurant_id;
        $data['sort_order'] = Category::where('restaurant_id', $data['restaurant_id'])->max('sort_order') + 1;
        $data['is_active'] = $data['is_active'] ?? true;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('categories', config('filesystems.media_disk', 'public'));
        }

        unset($data['image']);

        // Ensure schedule fields are present (FormData omits null/empty values).
        $this->normalizeScheduleFields($data);

        Category::query()->create($data);

        return redirect()->route('menu.index')->with('success', 'Categoría creada correctamente.');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? $category->is_active;

        if ($request->hasFile('image')) {
            if ($category->image_path) {
                Storage::disk(config('filesystems.media_disk', 'public'))->delete($category->image_path);
            }
            $data['image_path'] = $request->file('image')->store('categories', config('filesystems.media_disk', 'public'));
        }

        unset($data['image']);

        // Ensure schedule fields are present (FormData omits null/empty values).
        $this->normalizeScheduleFields($data);

        $category->update($data);

        return redirect()->route('menu.index')->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        if ($category->products()->where('is_active', true)->exists()) {
            return redirect()->route('menu.index')->with('error', 'No puedes eliminar una categoría con productos activos.');
        }

        if ($category->image_path) {
            Storage::disk(config('filesystems.media_disk', 'public'))->delete($category->image_path);
        }

        $category->delete();

        return redirect()->route('menu.index')->with('success', 'Categoría eliminada correctamente.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Category::class);

        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $restaurantId = $request->user()->restaurant_id;
        $ids = $request->input('ids');

        // Lock the affected tenant rows and renumber atomically so concurrent
        // reorder requests serialize instead of interleaving writes. Cross-
        // tenant ids are silently skipped by the `where restaurant_id` clause
        // — they cannot corrupt another tenant's order.
        DB::transaction(function () use ($ids, $restaurantId): void {
            Category::query()
                ->where('restaurant_id', $restaurantId)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get(['id']);

            foreach ($ids as $index => $id) {
                Category::query()
                    ->where('id', $id)
                    ->where('restaurant_id', $restaurantId)
                    ->update(['sort_order' => $index]);
            }
        });

        return redirect()->route('menu.index');
    }

    /**
     * Ensure schedule fields are always present in the data array.
     * FormData drops null and empty array values, so they must be
     * explicitly set to null when the schedule toggle is off.
     *
     * @param  array<string, mixed>  $data
     */
    private function normalizeScheduleFields(array &$data): void
    {
        if (! array_key_exists('available_days', $data)) {
            $data['available_days'] = null;
        }

        // Clear time fields if no days are configured.
        if (empty($data['available_days'])) {
            $data['available_days'] = null;
            $data['available_from'] = null;
            $data['available_until'] = null;

            return;
        }

        // Cast day values to integers (FormData sends strings).
        $data['available_days'] = array_values(array_unique(array_map('intval', $data['available_days'])));

        // Normalize empty time strings to null.
        $data['available_from'] = ! empty($data['available_from']) ? $data['available_from'] : null;
        $data['available_until'] = ! empty($data['available_until']) ? $data['available_until'] : null;
    }
}
