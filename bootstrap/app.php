<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureActive;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Support\SecurityLog;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->appendToGroup('web', [EnsureActive::class, HandleInertiaRequests::class]);
        $middleware->alias(['admin' => EnsureAdmin::class]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/notes');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Never echo request input back in validation error flashes for these fields.
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password']);

        // Security-relevant HTTP errors go to the security log; the normal error page is still rendered
        // (returning null). Unexpected exceptions are reported to the app log with the request id (Context)
        // and the user only sees a generic page with that id: crash safely, no stack trace, no data.
        $exceptions->render(function (HttpException $e, Request $request) {
            $events = [419 => 'input.csrf.rejected', 429 => 'abuse.rate_limited'];
            if (isset($events[$e->getStatusCode()])) {
                SecurityLog::event($events[$e->getStatusCode()], SecurityLog::INPUT, ['outcome' => 'failure', 'status' => $e->getStatusCode()], 'warning');
            }

            return null;
        });

        // Inertia (XHR) requests get the error as a React page instead of a raw HTML dump.
        // With APP_DEBUG=true the developer still sees the detailed error.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();
            if ($status === 419) {
                return back()->with('status', 'Your session expired. Please try again.');
            }
            if (! app()->hasDebugModeEnabled() && $request->header('X-Inertia') && in_array($status, [403, 404, 429, 500, 503], true)) {
                return Inertia::render('Error', ['status' => $status, 'requestId' => Context::get('request_id')])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
