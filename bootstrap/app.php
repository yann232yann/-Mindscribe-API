<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(HandleCors::class);
        $middleware->api(prepend: [HandleCors::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Toujours retourner du JSON sur les routes API (même sans Accept: application/json)
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Non authentifié.',
                ], 401);
            }
        });

        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Erreur de validation.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Ressource introuvable.',
                ], 404);
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Méthode non autorisée.',
                ], 405);
            }
        });

        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Erreur serveur. Réessayez dans un moment.',
                ], 500);
            }
        });

    })->create();