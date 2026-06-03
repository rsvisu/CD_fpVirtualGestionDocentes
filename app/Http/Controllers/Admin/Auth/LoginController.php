<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        // Mismo criterio que exige el panel (guard web + is_admin): evita el bucle de redirección
        if (Auth::check() && Auth::user()->is_admin) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'user' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Autenticar contra el guard web (modelo Usuario) por nombre
        $ok = Auth::guard('web')->attempt([
            'nombre' => $credentials['user'],
            'password' => $credentials['password'],
        ], $request->boolean('remember'));

        // Fallback: intentar por email si el campo es un email válido
        if (!$ok && filter_var($credentials['user'], FILTER_VALIDATE_EMAIL)) {
            $ok = Auth::guard('web')->attempt([
                'email' => $credentials['user'],
                'password' => $credentials['password'],
            ], $request->boolean('remember'));
        }

        if ($ok && Auth::user()->is_admin) {
            $request->session()->regenerate();
            return redirect()->route('admin.dashboard');
        }

        // Credenciales correctas pero sin permisos de admin: cerrar sesión y rechazar
        if ($ok) {
            Auth::guard('web')->logout();
        }

        throw ValidationException::withMessages([
            'user' => __('auth.failed'),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
