<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        // Devuelve una respuesta JSON para solicitudes de API no autenticadas
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'Es necesario autenticarse para realizar esta acción.'
            ], 401);
        }

        // Para otras solicitudes, redirige al login (solo si usas web)
        return redirect()->guest(route('login'));
    }

    public function render($request, Throwable $exception)
    {
        if ($exception instanceof \Symfony\Component\Routing\Exception\RouteNotFoundException) {
            \Log::error('Route Not Found Exception:', ['message' => $exception->getMessage()]);
        }

        return parent::render($request, $exception);
    }

}
