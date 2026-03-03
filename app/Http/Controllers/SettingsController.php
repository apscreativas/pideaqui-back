<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateGeneralSettingsRequest;
use App\Http\Requests\UpdateRestaurantScheduleRequest;
use App\Models\Restaurant;
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

    public function schedules(Request $request): Response
    {
        /** @var Restaurant $restaurant */
        $restaurant = $request->user()->load('restaurant')->restaurant;

        // Ensure all 7 days exist
        for ($day = 0; $day <= 6; $day++) {
            $restaurant->schedules()->firstOrCreate(
                ['day_of_week' => $day],
                ['opens_at' => '09:00', 'closes_at' => '21:00', 'is_closed' => true],
            );
        }

        $schedules = $restaurant->schedules()->orderBy('day_of_week')->get()
            ->map(fn ($s) => [
                'day_of_week' => $s->day_of_week,
                'opens_at' => $s->opens_at ? substr($s->opens_at, 0, 5) : '09:00',
                'closes_at' => $s->closes_at ? substr($s->closes_at, 0, 5) : '21:00',
                'is_closed' => $s->is_closed,
            ]);

        return Inertia::render('Settings/Schedules', [
            'schedules' => $schedules,
        ]);
    }

    public function updateSchedules(UpdateRestaurantScheduleRequest $request): RedirectResponse
    {
        /** @var Restaurant $restaurant */
        $restaurant = $request->user()->load('restaurant')->restaurant;

        foreach ($request->validated()['schedules'] as $scheduleData) {
            $restaurant->schedules()->updateOrCreate(
                ['day_of_week' => $scheduleData['day_of_week']],
                [
                    'opens_at' => $scheduleData['is_closed'] ? null : $scheduleData['opens_at'],
                    'closes_at' => $scheduleData['is_closed'] ? null : $scheduleData['closes_at'],
                    'is_closed' => $scheduleData['is_closed'] ?? false,
                ],
            );
        }

        return back()->with('success', 'Horarios actualizados.');
    }
}
