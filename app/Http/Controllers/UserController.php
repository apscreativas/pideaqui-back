<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $restaurant = $request->user()->restaurant;

        $users = User::where('restaurant_id', $restaurant->id)
            ->with('branches:id,name')
            ->orderByRaw("role = 'admin' DESC, name ASC")
            ->get(['id', 'name', 'email', 'role', 'restaurant_id', 'created_at']);

        $operatorCount = $users->where('role', 'operator')->count();

        return Inertia::render('Settings/Users/Index', [
            'users' => $users,
            'operator_count' => $operatorCount,
            'max_operators' => $restaurant->max_branches,
        ]);
    }

    public function create(Request $request): Response
    {
        $restaurant = $request->user()->restaurant;
        $branches = Branch::where('restaurant_id', $restaurant->id)->get(['id', 'name']);

        return Inertia::render('Settings/Users/Create', [
            'branches' => $branches,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;

        // Check operator limit (max_branches = max operators).
        $currentOperators = User::where('restaurant_id', $restaurant->id)->where('role', 'operator')->count();
        if ($currentOperators >= $restaurant->max_branches) {
            return redirect()->route('settings.users')->with('error', "Has alcanzado el límite de {$restaurant->max_branches} usuarios adicionales.");
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['integer', Rule::exists('branches', 'id')->where('restaurant_id', $restaurant->id)],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.unique' => 'Este correo ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'branch_ids.required' => 'Selecciona al menos una sucursal.',
            'branch_ids.min' => 'Selecciona al menos una sucursal.',
        ]);

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        $user->role = 'operator';
        $user->restaurant_id = $restaurant->id;
        $user->save();

        $user->branches()->attach($data['branch_ids']);

        return redirect()->route('settings.users')->with('success', 'Usuario creado correctamente.');
    }

    public function edit(Request $request, User $user): Response
    {
        $restaurant = $request->user()->restaurant;

        if ($user->restaurant_id !== $restaurant->id || $user->isAdmin()) {
            abort(403);
        }

        $user->load('branches:id');
        $branches = Branch::where('restaurant_id', $restaurant->id)->get(['id', 'name']);

        return Inertia::render('Settings/Users/Edit', [
            'operator' => $user,
            'branches' => $branches,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;

        if ($user->restaurant_id !== $restaurant->id || $user->isAdmin()) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['integer', Rule::exists('branches', 'id')->where('restaurant_id', $restaurant->id)],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.unique' => 'Este correo ya está registrado.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'branch_ids.required' => 'Selecciona al menos una sucursal.',
            'branch_ids.min' => 'Selecciona al menos una sucursal.',
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            ...($data['password'] ? ['password' => $data['password']] : []),
        ]);

        $user->branches()->sync($data['branch_ids']);

        return redirect()->route('settings.users')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;

        if ($user->restaurant_id !== $restaurant->id || $user->isAdmin()) {
            abort(403);
        }

        $user->delete();

        return redirect()->route('settings.users')->with('success', 'Usuario eliminado correctamente.');
    }
}
