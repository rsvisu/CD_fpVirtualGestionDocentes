<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || !auth()->user()->is_admin) {
            abort(403, 'Acceso restringido a administradores.');
        }

        return $next($request);
    }
}
