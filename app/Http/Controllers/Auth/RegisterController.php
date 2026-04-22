<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRestaurantRequest;
use App\Services\Onboarding\Dto\ProvisionRestaurantData;
use App\Services\Onboarding\RestaurantProvisioningService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(
        RegisterRestaurantRequest $request,
        RestaurantProvisioningService $provisioning,
    ): RedirectResponse {
        $data = $request->validated();

        $dto = new ProvisionRestaurantData(
            source: 'self_signup',
            restaurantName: $data['restaurant_name'],
            adminName: $data['admin_name'],
            adminEmail: $data['email'],
            adminPassword: $data['password'],
            billingMode: 'grace',
            actorId: null,
            ipAddress: $request->ip(),
            slug: $data['slug'] ?? null,
        );

        $restaurant = $provisioning->provision($dto);

        $admin = $restaurant->users()->where('email', $data['email'])->firstOrFail();

        event(new Registered($admin));

        Auth::guard('web')->login($admin);

        return redirect()->route('verification.notice');
    }
}
