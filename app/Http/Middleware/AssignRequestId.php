<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request a random id, adds it to every log line (Context) and to the response, so a user's
 * error page, the app log and the security log can be correlated. A client-supplied id is ignored.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = (string) Str::uuid();
        Context::add('request_id', $id);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
