<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        foreach ($request->input('ids') as $index => $id) {
            Category::query()
                ->where('id', $id)
                ->where('restaurant_id', $restaurantId)
                ->update(['sort_order' => $index]);
        }

        return redirect()->route('menu.index');
    }
}
