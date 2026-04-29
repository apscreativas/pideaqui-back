<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reemplaza al middleware `guest` por defecto de Laravel para que reconozca
 * los dos guards de la aplicación (`web` y `superadmin`). Si el visitante ya
 * está autenticado en cualquiera de ellos, lo redirige a su dashboard
 * correspondiente en vez de mostrarle el login.
 */
class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        if (Auth::guard('superadmin')->check()) {
            return redirect()->route('super.dashboard');
        }

        if (Auth::guard('web')->check()) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
