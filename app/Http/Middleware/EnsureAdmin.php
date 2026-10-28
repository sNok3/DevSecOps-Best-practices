<?php

namespace App\Http\Middleware;

use App\Support\SecurityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            SecurityLog::event('authz.admin.denied', SecurityLog::AUTHZ, ['outcome' => 'failure'], 'warning');
            abort(403);
        }

        return $next($request);
    }
}
