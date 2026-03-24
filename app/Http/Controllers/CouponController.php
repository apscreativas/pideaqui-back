<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Coupon::class);

        $coupons = Coupon::query()
            ->withCount('uses')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Coupons/Index', [
            'coupons' => $coupons,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Coupon::class);

        return Inertia::render('Coupons/Create');
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $this->authorize('create', Coupon::class);

        $data = $request->validated();
        $data['restaurant_id'] = $request->user()->restaurant_id;
        $data['is_active'] = $data['is_active'] ?? true;
        $data['min_purchase'] = $data['min_purchase'] ?? 0;

        Coupon::query()->create($data);

        return redirect()->route('coupons.index')->with('success', 'Cupón creado correctamente.');
    }

    public function edit(Coupon $coupon): Response
    {
        $this->authorize('update', $coupon);

        $coupon->loadCount('uses');

        return Inertia::render('Coupons/Edit', [
            'coupon' => $coupon,
        ]);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $this->authorize('update', $coupon);

        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? $coupon->is_active;
        $data['min_purchase'] = $data['min_purchase'] ?? $coupon->min_purchase;

        $coupon->update($data);

        return redirect()->route('coupons.index')->with('success', 'Cupón actualizado correctamente.');
    }

    public function toggleActive(Coupon $coupon): RedirectResponse
    {
        $this->authorize('update', $coupon);

        $coupon->update(['is_active' => ! $coupon->is_active]);

        return redirect()->route('coupons.index');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $this->authorize('delete', $coupon);

        $coupon->delete();

        return redirect()->route('coupons.index')->with('success', 'Cupón eliminado correctamente.');
    }
}
