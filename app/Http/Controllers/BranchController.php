<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use App\Models\BranchSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Branch::class);

        $branches = Branch::with('schedules')
            ->orderBy('name')
            ->get();

        $maxBranches = $request->user()->restaurant->max_branches;

        return Inertia::render('Branches/Index', [
            'branches' => $branches,
            'maxBranches' => $maxBranches,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Branch::class);

        return Inertia::render('Branches/Create');
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $this->authorize('create', Branch::class);

        $restaurant = $request->user()->restaurant;

        if ($restaurant->branches()->count() >= $restaurant->max_branches) {
            return redirect()->route('branches.index')->with('error', "Has alcanzado el límite de {$restaurant->max_branches} sucursales de tu plan.");
        }

        $data = $request->validated();
        $data['restaurant_id'] = $restaurant->id;
        $data['is_active'] = $data['is_active'] ?? true;

        $branch = Branch::query()->create($data);

        // Create default 7 schedules
        for ($day = 0; $day <= 6; $day++) {
            BranchSchedule::query()->create([
                'branch_id' => $branch->id,
                'day_of_week' => $day,
                'opens_at' => '09:00',
                'closes_at' => '21:00',
                'is_closed' => $day === 0, // Sunday closed by default
            ]);
        }

        return redirect()->route('branches.index')->with('success', 'Sucursal creada correctamente.');
    }

    public function edit(Branch $branch): Response
    {
        $this->authorize('update', $branch);

        return Inertia::render('Branches/Edit', [
            'branch' => $branch,
        ]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->authorize('update', $branch);

        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? $branch->is_active;

        $branch->update($data);

        return redirect()->route('branches.index')->with('success', 'Sucursal actualizada correctamente.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorize('delete', $branch);

        if ($branch->orders()->whereIn('status', ['pending', 'confirmed', 'preparing'])->exists()) {
            return redirect()->route('branches.index')->with('error', 'No puedes eliminar una sucursal con pedidos activos.');
        }

        $branch->delete();

        return redirect()->route('branches.index')->with('success', 'Sucursal eliminada correctamente.');
    }

    public function toggle(Branch $branch): RedirectResponse
    {
        $this->authorize('update', $branch);

        $branch->update(['is_active' => ! $branch->is_active]);

        return redirect()->route('branches.index');
    }
}
