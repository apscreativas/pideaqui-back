<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Settings/Profile', [
            'user' => $request->user()->only(['name', 'email']),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (! isset($data['password']) || $data['password'] === null) {
            unset($data['password']);
        }

        unset($data['password_confirmation'], $data['current_password']);

        $user->update($data);

        return back()->with('success', 'Perfil actualizado.');
    }
}
