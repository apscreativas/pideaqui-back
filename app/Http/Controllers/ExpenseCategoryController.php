<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseCategoryRequest;
use App\Http\Requests\StoreExpenseSubcategoryRequest;
use App\Http\Requests\UpdateExpenseCategoryRequest;
use App\Http\Requests\UpdateExpenseSubcategoryRequest;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSubcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        $categories = ExpenseCategory::query()
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->with(['subcategories' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
            ->withCount('expenses')
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        return Inertia::render('Expenses/Categories', [
            'categories' => $categories,
        ]);
    }

    public function store(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', ExpenseCategory::class);

        $maxOrder = (int) ExpenseCategory::query()
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->max('sort_order');

        ExpenseCategory::create([
            'restaurant_id' => $request->user()->restaurant_id,
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $maxOrder + 1,
        ]);

        return back()->with('success', 'Categoría creada.');
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $category->update([
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active', $category->is_active),
        ]);

        return back()->with('success', 'Categoría actualizada.');
    }

    public function toggle(ExpenseCategory $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $category->update(['is_active' => ! $category->is_active]);

        return back();
    }

    public function destroy(ExpenseCategory $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        if ($category->expenses()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'No se puede eliminar: la categoría tiene gastos registrados. Desactívala en su lugar.',
            ]);
        }

        $category->delete();  // cascades to subcategories

        return back()->with('success', 'Categoría eliminada.');
    }

    // ── Subcategories ────────────────────────────────────────────────────

    public function storeSubcategory(StoreExpenseSubcategoryRequest $request, ExpenseCategory $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $maxOrder = (int) $category->subcategories()->max('sort_order');

        $category->subcategories()->create([
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $maxOrder + 1,
        ]);

        return back()->with('success', 'Subcategoría creada.');
    }

    public function updateSubcategory(UpdateExpenseSubcategoryRequest $request, ExpenseSubcategory $subcategory): RedirectResponse
    {
        $this->authorize('update', $subcategory->category);

        $subcategory->update([
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active', $subcategory->is_active),
        ]);

        return back()->with('success', 'Subcategoría actualizada.');
    }

    public function toggleSubcategory(ExpenseSubcategory $subcategory): RedirectResponse
    {
        $this->authorize('update', $subcategory->category);

        $subcategory->update(['is_active' => ! $subcategory->is_active]);

        return back();
    }

    public function destroySubcategory(ExpenseSubcategory $subcategory): RedirectResponse
    {
        $this->authorize('update', $subcategory->category);

        if ($subcategory->expenses()->exists()) {
            throw ValidationException::withMessages([
                'subcategory' => 'No se puede eliminar: la subcategoría tiene gastos registrados.',
            ]);
        }

        $subcategory->delete();

        return back()->with('success', 'Subcategoría eliminada.');
    }
}
