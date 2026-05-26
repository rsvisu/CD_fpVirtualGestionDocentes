<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;

class EsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || !Admin::where('email', auth()->user()->email)->exists()) {
            abort(403, 'Acceso restringido a administradores.');
        }

        return $next($request);
    }
}
