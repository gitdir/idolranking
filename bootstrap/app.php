<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Console\Commands\CreateDummyMembers;
use App\Console\Commands\AutoVoteForDummyMembers;
use App\Console\Commands\BoostFanPostViews;
use App\Console\Commands\PublishQueuedFanPosts;
use App\Console\Commands\SettleQuizWeeklyRanking;
use App\Http\Middleware\ResolveMember;
use App\Http\Middleware\ResolveActivityMember;
use App\Http\Middleware\CaptureReferral;
use App\Http\Middleware\BlockRestrictedIp;
use App\Http\Middleware\TrackVisitor;
use App\Http\Middleware\SetPublicLocale;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([CreateDummyMembers::class, AutoVoteForDummyMembers::class, BoostFanPostViews::class, PublishQueuedFanPosts::class, SettleQuizWeeklyRanking::class])
    ->withMiddleware(function (Middleware $middleware): void {
        // Comment text is source content for later translation and must remain byte-for-byte as submitted.
        $middleware->trimStrings(except: [
            fn ($request) => $request->is('fan-post/*/comments'),
            fn ($request) => $request->isMethod('post') && $request->is('idol-registration/request'),
        ]);
        $middleware->prependToGroup('web', BlockRestrictedIp::class);
        $middleware->appendToGroup('web', ResolveMember::class);
        $middleware->appendToGroup('web', ResolveActivityMember::class);
        $middleware->appendToGroup('web', CaptureReferral::class);
        $middleware->appendToGroup('web', TrackVisitor::class);
        $middleware->appendToGroup('web', SetPublicLocale::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

$configurePublicPath = static function (Application $app, mixed $publicPath): void {
    if (is_string($publicPath) && ($publicPath = trim($publicPath)) !== '') {
        $app->usePublicPath($publicPath);
    }
};

$app->afterLoadingEnvironment(function (Application $app) use ($configurePublicPath): void {
    $configurePublicPath($app, env('APP_PUBLIC_PATH'));
});

$app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function (Application $app) use ($configurePublicPath): void {
    $configurePublicPath($app, $app['config']->get('app.public_path'));
});

return $app;
