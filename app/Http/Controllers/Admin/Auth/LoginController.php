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
        if (Auth::guard('admin')->check()) {
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

        // Intentar login por nombre (remember=true para persistir entre sesiones de usuario)
        $loginByName = Auth::guard('admin')->attempt([
            'user' => $credentials['user'],
            'password' => $credentials['password']
        ], true);

        // Intentar login por email si el anterior falla
        $loginByEmail = false;
        if (!$loginByName && filter_var($credentials['user'], FILTER_VALIDATE_EMAIL)) {
            $loginByEmail = Auth::guard('admin')->attempt([
                'email' => $credentials['user'],
                'password' => $credentials['password']
            ], true);
        }

        if ($loginByName || $loginByEmail) {
            $request->session()->regenerate();
            return redirect()->intended(route('admin.dashboard'));
        }

        throw ValidationException::withMessages([
            'user' => __('auth.failed'),
        ]);
    }

    public function logout(Request $request)
    {
        // Elimina el remember_token del usuario actual
        if (Auth::guard('admin')->check()) {
            $admin = Auth::guard('admin')->user();
            $admin->setRememberToken(null);
            $admin->save();
        }

        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
}

}
