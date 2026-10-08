<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\ResolveMember;
use App\Http\Middleware\CaptureReferral;
use App\Http\Middleware\BlockRestrictedIp;
use App\Http\Middleware\TrackVisitor;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('web', BlockRestrictedIp::class);
        $middleware->appendToGroup('web', ResolveMember::class);
        $middleware->appendToGroup('web', CaptureReferral::class);
        $middleware->appendToGroup('web', TrackVisitor::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
