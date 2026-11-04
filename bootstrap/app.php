<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
        'role' => \App\Http\Middleware\RoleMiddleware::class,
    ]);

    // tamu yang buka halaman admin diarahkan ke login admin
    $middleware->redirectGuestsTo(fn (Request $request) =>
        $request->is('admin/*') ? route('admin.login') : route('login')
    );

    // user yang sudah login dan membuka halaman login diarahkan sesuai role
    $middleware->redirectUsersTo(fn (Request $request) =>
        $request->user()?->isAdmin() ? route('admin.dashboard') : '/home'
    );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
