<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class DebugAuthMiddleware
{
    public function handle($request, Closure $next)
    {
        if (Auth::check()) {
            info('Usuario autenticado:', ['user' => Auth::user()]);
        } else {
            info('Usuario no autenticado');
        }

        return $next($request);
    }
}
