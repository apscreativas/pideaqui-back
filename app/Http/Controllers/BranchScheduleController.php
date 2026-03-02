<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBranchScheduleRequest;
use App\Models\Branch;
use App\Models\BranchSchedule;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BranchScheduleController extends Controller
{
    public function edit(Branch $branch): Response
    {
        $this->authorize('update', $branch);

        $branch->load('schedules');

        // Ensure all 7 days are present
        $existingDays = $branch->schedules->pluck('day_of_week')->toArray();
        for ($day = 0; $day <= 6; $day++) {
            if (! in_array($day, $existingDays)) {
                BranchSchedule::query()->create([
                    'branch_id' => $branch->id,
                    'day_of_week' => $day,
                    'opens_at' => '09:00',
                    'closes_at' => '21:00',
                    'is_closed' => true,
                ]);
            }
        }

        $branch->load('schedules');

        return Inertia::render('Branches/Schedules', [
            'branch' => $branch,
        ]);
    }

    public function update(UpdateBranchScheduleRequest $request, Branch $branch): RedirectResponse
    {
        $this->authorize('update', $branch);

        foreach ($request->validated()['schedules'] as $scheduleData) {
            BranchSchedule::query()->updateOrCreate(
                ['branch_id' => $branch->id, 'day_of_week' => $scheduleData['day_of_week']],
                [
                    'opens_at' => $scheduleData['is_closed'] ? null : ($scheduleData['opens_at'] ?? null),
                    'closes_at' => $scheduleData['is_closed'] ? null : ($scheduleData['closes_at'] ?? null),
                    'is_closed' => $scheduleData['is_closed'] ?? false,
                ]
            );
        }

        return redirect()->route('branches.index')->with('success', 'Horarios actualizados correctamente.');
    }
}
