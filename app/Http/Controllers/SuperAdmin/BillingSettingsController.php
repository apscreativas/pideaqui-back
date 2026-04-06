<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BillingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('SuperAdmin/BillingSettings', [
            'settings' => [
                'initial_grace_period_days' => BillingSetting::getInt('initial_grace_period_days', 14),
                'payment_grace_period_days' => BillingSetting::getInt('payment_grace_period_days', 7),
                'reminder_days_before_expiry' => BillingSetting::get('reminder_days_before_expiry', '3,1'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'initial_grace_period_days' => ['required', 'integer', 'min:1', 'max:90'],
            'payment_grace_period_days' => ['required', 'integer', 'min:1', 'max:30'],
            'reminder_days_before_expiry' => ['required', 'string', 'max:50'],
        ]);

        foreach ($data as $key => $value) {
            BillingSetting::set($key, (string) $value);
        }

        return back()->with('success', 'Configuración de billing actualizada.');
    }
}
