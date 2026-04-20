<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use App\Services\LimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function __construct(private readonly LimitService $limitService) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Branch::class);

        $branches = Branch::query()
            ->orderBy('name')
            ->get();

        $maxBranches = $this->limitService->getMaxBranches($request->user()->restaurant);

        return Inertia::render('Branches/Index', [
            'branches' => $branches,
            'maxBranches' => $maxBranches,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Branch::class);

        return Inertia::render('Branches/Create', [
            'mapsKey' => config('services.google_maps.key', ''),
        ]);
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $this->authorize('create', Branch::class);

        $restaurant = $request->user()->restaurant;

        $maxBranches = $this->limitService->getMaxBranches($restaurant);

        if ($restaurant->branches()->count() >= $maxBranches) {
            return redirect()->route('branches.index')->with('error', "Has alcanzado el límite de {$maxBranches} sucursales de tu plan.");
        }

        $data = $request->validated();
        $data['restaurant_id'] = $restaurant->id;
        $data['is_active'] = $data['is_active'] ?? true;

        Branch::query()->create($data);

        return redirect()->route('branches.index')->with('success', 'Sucursal creada correctamente.');
    }

    public function edit(Branch $branch): Response
    {
        $this->authorize('update', $branch);

        return Inertia::render('Branches/Edit', [
            'branch' => $branch,
            'mapsKey' => config('services.google_maps.key', ''),
        ]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->authorize('update', $branch);

        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? $branch->is_active;

        if ($branch->is_active && ! ($data['is_active'] ?? true)) {
            if ($this->isLastActiveBranch($branch)) {
                return redirect()->route('branches.index')->with('error', 'No puedes desactivar la última sucursal activa.');
            }
        }

        $branch->update($data);

        return redirect()->route('branches.index')->with('success', 'Sucursal actualizada correctamente.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorize('delete', $branch);

        if ($branch->orders()->whereNotIn('status', ['delivered', 'cancelled'])->exists()) {
            return redirect()->route('branches.index')->with('error', 'No puedes eliminar una sucursal con pedidos activos.');
        }

        if ($branch->is_active && $this->isLastActiveBranch($branch)) {
            return redirect()->route('branches.index')->with('error', 'No puedes eliminar la última sucursal activa.');
        }

        if (\App\Models\Expense::where('branch_id', $branch->id)->exists()) {
            return redirect()->route('branches.index')->with('error', 'No puedes eliminar una sucursal con gastos registrados. Desactívala en su lugar.');
        }

        try {
            $branch->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23503') {
                return redirect()->route('branches.index')->with('error', 'No se puede eliminar esta sucursal porque tiene registros asociados.');
            }
            throw $e;
        }

        return redirect()->route('branches.index')->with('success', 'Sucursal eliminada correctamente.');
    }

    public function toggle(Branch $branch): RedirectResponse
    {
        $this->authorize('update', $branch);

        if ($branch->is_active && $this->isLastActiveBranch($branch)) {
            return redirect()->route('branches.index')->with('error', 'No puedes desactivar la última sucursal activa.');
        }

        $branch->update(['is_active' => ! $branch->is_active]);

        return redirect()->route('branches.index');
    }

    private function isLastActiveBranch(Branch $branch): bool
    {
        return Branch::query()
            ->where('restaurant_id', $branch->restaurant_id)
            ->where('is_active', true)
            ->where('id', '!=', $branch->id)
            ->doesntExist();
    }
}
