<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateGeneralSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function general(Request $request): Response
    {
        $restaurant = $request->user()->load('restaurant')->restaurant;

        return Inertia::render('Settings/General', [
            'restaurant' => array_merge(
                $restaurant->only(['name', 'logo_path', 'instagram', 'facebook', 'tiktok']),
                ['logo_url' => $restaurant->logo_url],
            ),
        ]);
    }

    public function updateGeneral(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        $restaurant = $request->user()->load('restaurant')->restaurant;

        $data = $request->validated();

        if ($request->hasFile('logo')) {
            if ($restaurant->logo_path) {
                Storage::disk(config('filesystems.media_disk', 'public'))->delete($restaurant->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', config('filesystems.media_disk', 'public'));
        }

        unset($data['logo']);

        $restaurant->update($data);

        return back()->with('success', 'Configuración guardada.');
    }
}
