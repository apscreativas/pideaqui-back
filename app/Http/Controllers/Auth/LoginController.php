<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /**
     * Rutas a las que NUNCA debe redirigir un login exitoso aunque queden en
     * `url.intended`. Si el `intended` apunta a una de estas, lo descartamos y
     * usamos el fallback. Evita el bug en que un `url.intended` rezagado de un
     * flujo previo dejaba al usuario rebotando en `/login`.
     */
    private const INTENDED_BLOCKLIST = [
        '/login',
        '/logout',
        '/register',
        '/forgot-password',
        '/reset-password',
        '/email/verify',
    ];

    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        // Limpia residuo de sesión cruzada entre guards (web ↔ superadmin)
        // antes de intentar autenticar — previene estados ambiguos donde la
        // sesión queda con dos identidades y los middlewares posteriores
        // toman el guard equivocado.
        if (Auth::guard('superadmin')->check()) {
            Auth::guard('superadmin')->logout();
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        // Try SuperAdmin first
        if (Auth::guard('superadmin')->attempt($credentials, $remember)) {
            $request->session()->regenerate();

            return $this->safeIntended($request, route('super.dashboard'));
        }

        // Try restaurant admin
        if (Auth::guard('web')->attempt($credentials, $remember)) {
            $user = Auth::guard('web')->user();

            if (! $user->restaurant_id) {
                Auth::guard('web')->logout();

                return back()->withErrors([
                    'email' => 'Tu cuenta no está asociada a ningún restaurante.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();

            if (! $user->hasVerifiedEmail()) {
                return redirect()->route('verification.notice');
            }

            return $this->safeIntended($request, route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'Las credenciales no coinciden con nuestros registros.',
        ])->onlyInput('email');
    }

    /**
     * Variante segura de `redirect()->intended()`: si el `url.intended`
     * almacenado apunta a una ruta de auth/guest (login, logout, etc.) o a un
     * host externo, lo descarta y usa el `$default` para evitar loops y
     * recargas hacia `/login`.
     */
    private function safeIntended(Request $request, string $default): RedirectResponse
    {
        $intended = $request->session()->pull('url.intended');

        if (! is_string($intended) || $intended === '') {
            return redirect()->to($default);
        }

        $path = parse_url($intended, PHP_URL_PATH) ?: '/';
        $intendedHost = parse_url($intended, PHP_URL_HOST);
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);

        if ($intendedHost && $appHost && $intendedHost !== $appHost) {
            return redirect()->to($default);
        }

        foreach (self::INTENDED_BLOCKLIST as $blocked) {
            if ($path === $blocked || Str::startsWith($path, $blocked.'/')) {
                return redirect()->to($default);
            }
        }

        return redirect()->to($intended);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function destroySuperAdmin(Request $request): RedirectResponse
    {
        Auth::guard('superadmin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
