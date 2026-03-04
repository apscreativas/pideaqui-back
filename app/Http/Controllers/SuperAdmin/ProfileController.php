<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateSuperAdminProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('SuperAdmin/Profile', [
            'user' => $request->user('superadmin')->only(['name', 'email']),
        ]);
    }

    public function update(UpdateSuperAdminProfileRequest $request): RedirectResponse
    {
        $user = $request->user('superadmin');
        $data = $request->validated();

        if (! isset($data['password']) || $data['password'] === null) {
            unset($data['password']);
        }

        unset($data['password_confirmation'], $data['current_password']);

        $user->update($data);

        return back()->with('success', 'Perfil actualizado.');
    }
}
