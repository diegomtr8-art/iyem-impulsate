<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\EnsureRolSeleccionado::class,
            \App\Http\Middleware\EnsureAvisoAceptado::class,
            \App\Http\Middleware\EnsureProfileComplete::class,
        ]);

        $middleware->alias([
            'admin'           => \App\Http\Middleware\EnsureIsAdmin::class,
            'super-admin'     => \App\Http\Middleware\EnsureIsSuperAdmin::class,
            'role'            => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'      => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'aviso.aceptado'  => \App\Http\Middleware\EnsureAvisoAceptado::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // La API nunca responde con redirecciones HTML: un 401 sin token debe
        // llegar a la app como JSON aunque el cliente olvide el Accept.
        $exceptions->shouldRenderJsonWhen(
            fn (\Illuminate\Http\Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        // Regla de negocio de los servicios: 422 con la forma de Laravel en la
        // API; en la web, el mismo back()->withErrors() que hacian los controladores.
        $exceptions->render(function (\App\Exceptions\ReglaDeNegocioException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors'  => [$e->campo => [$e->getMessage()]],
                ], 422);
            }

            return back()->withErrors([$e->campo => $e->getMessage()])->withInput();
        });
    })->create();
