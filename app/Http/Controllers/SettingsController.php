<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBrandingRequest;
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
                $restaurant->only(['name', 'slug', 'logo_path', 'notify_new_orders']),
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

        $specialDates = $restaurant->specialDates()->orderBy('date')->get()
            ->map(fn ($sd) => [
                'id' => $sd->id,
                'date' => $sd->date->toDateString(),
                'type' => $sd->type,
                'opens_at' => $sd->opens_at ? substr($sd->opens_at, 0, 5) : null,
                'closes_at' => $sd->closes_at ? substr($sd->closes_at, 0, 5) : null,
                'label' => $sd->label,
                'is_recurring' => $sd->is_recurring,
            ]);

        return Inertia::render('Settings/Schedules', [
            'schedules' => $schedules,
            'specialDates' => $specialDates,
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

    public function branding(Request $request): Response
    {
        $restaurant = $request->user()->load('restaurant')->restaurant;

        return Inertia::render('Settings/Branding', [
            'restaurant' => array_merge(
                $restaurant->only(['primary_color', 'secondary_color', 'default_product_image', 'text_color']),
                ['default_product_image_url' => $restaurant->default_product_image_url],
            ),
        ]);
    }

    public function updateBranding(UpdateBrandingRequest $request): RedirectResponse
    {
        $restaurant = $request->user()->load('restaurant')->restaurant;

        $data = $request->validated();

        if ($request->boolean('remove_default_image')) {
            if ($restaurant->default_product_image) {
                Storage::disk(config('filesystems.media_disk', 'public'))->delete($restaurant->default_product_image);
            }
            $data['default_product_image'] = null;
        } elseif ($request->hasFile('default_product_image')) {
            if ($restaurant->default_product_image) {
                Storage::disk(config('filesystems.media_disk', 'public'))->delete($restaurant->default_product_image);
            }
            $data['default_product_image'] = $request->file('default_product_image')
                ->store("restaurants/{$restaurant->id}", config('filesystems.media_disk', 'public'));
        } else {
            unset($data['default_product_image']);
        }

        unset($data['remove_default_image']);

        $restaurant->update($data);

        return back()->with('success', 'Personalización guardada.');
    }
}
