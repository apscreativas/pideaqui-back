<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BillingAudit;
use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('SuperAdmin/PlatformSettings', [
            'settings' => [
                'public_menu_base_url' => PlatformSetting::get('public_menu_base_url', ''),
            ],
            'defaults' => [
                'public_menu_base_url' => config('app.url', ''),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'public_menu_base_url' => ['nullable', 'url', 'max:500'],
        ], [
            'public_menu_base_url.url' => 'La URL base debe ser una URL válida (con http:// o https://).',
            'public_menu_base_url.max' => 'La URL base no puede exceder 500 caracteres.',
        ]);

        $value = $data['public_menu_base_url'] ?? null;
        if (is_string($value)) {
            $value = rtrim(trim($value), '/');
            if ($value === '') {
                $value = null;
            }
        }

        PlatformSetting::set('public_menu_base_url', $value);

        BillingAudit::log(
            action: 'platform_setting_updated',
            restaurantId: null,
            actorType: 'super_admin',
            actorId: $request->user('superadmin')?->id,
            payload: ['key' => 'public_menu_base_url', 'value' => $value],
            ipAddress: $request->ip(),
        );

        return back()->with('success', 'URL base del menú actualizada.');
    }
}
