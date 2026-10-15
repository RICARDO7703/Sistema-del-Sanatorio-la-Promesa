<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['contrasena']);
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }
            $entrada = $request->except(['contrasena', 'password', 'password_confirmation', 'current_password']);
            foreach ($exception->validator->failed() as $campo => $reglas) {
                if (isset($reglas['Array']) || is_array(data_get($entrada, $campo))) {
                    Arr::forget($entrada, $campo);
                }
            }
            foreach (['_formulario', '_habitacion', 'form_context'] as $contexto) {
                if (isset($entrada[$contexto]) && ! is_string($entrada[$contexto])) {
                    unset($entrada[$contexto]);
                }
            }

            return redirect($exception->redirectTo ?? url()->previous())
                ->withInput($entrada)->withErrors($exception->errors(), $exception->errorBag);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
