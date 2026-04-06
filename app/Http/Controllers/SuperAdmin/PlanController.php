<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function index(): Response
    {
        $plans = Plan::query()
            ->withCount('restaurants')
            ->orderBy('sort_order')
            ->get();

        $hasPendingSync = Plan::query()
            ->where('is_default_grace', false)
            ->where(function ($q): void {
                $q->whereNull('stripe_product_id')
                    ->orWhereNull('stripe_monthly_price_id')
                    ->orWhereNull('stripe_yearly_price_id');
            })
            ->exists();

        return Inertia::render('SuperAdmin/Plans/Index', [
            'plans' => $plans,
            'hasPendingSync' => $hasPendingSync,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('SuperAdmin/Plans/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:plans,slug'],
            'description' => ['required', 'string', 'max:500'],
            'orders_limit' => ['required', 'integer', 'min:1'],
            'max_branches' => ['required', 'integer', 'min:1'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'yearly_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        Plan::query()->create($data);

        return redirect()->route('super.plans.index')->with('success', 'Plan creado.');
    }

    public function edit(Plan $plan): Response
    {
        $plan->loadCount('restaurants');

        return Inertia::render('SuperAdmin/Plans/Edit', [
            'plan' => $plan,
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:plans,slug,'.$plan->id],
            'description' => ['required', 'string', 'max:500'],
            'orders_limit' => ['required', 'integer', 'min:1'],
            'max_branches' => ['required', 'integer', 'min:1'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'yearly_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'stripe_product_id' => ['nullable', 'string', 'max:255'],
            'stripe_monthly_price_id' => ['nullable', 'string', 'max:255'],
            'stripe_yearly_price_id' => ['nullable', 'string', 'max:255'],
        ]);

        $plan->update($data);

        return redirect()->route('super.plans.index')->with('success', 'Plan actualizado.');
    }

    public function toggle(Plan $plan): RedirectResponse
    {
        if ($plan->is_default_grace) {
            return back()->with('error', 'No se puede desactivar el plan de gracia.');
        }

        $plan->update(['is_active' => ! $plan->is_active]);

        $status = $plan->is_active ? 'activado' : 'desactivado';

        return back()->with('success', "Plan {$status}.");
    }

    public function syncStripe(): RedirectResponse
    {
        $exitCode = Artisan::call('billing:sync-stripe');

        if ($exitCode !== 0) {
            return back()->with('error', 'Error al sincronizar con Stripe. Revisa que las credenciales estén configuradas.');
        }

        return back()->with('success', 'Planes sincronizados con Stripe correctamente.');
    }
}
