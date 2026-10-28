<?php

namespace App\Http\Middleware;

use App\Support\SecurityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** A deactivated account loses its existing sessions on the next request, not only at the next login. */
class EnsureActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->is_active) {
            SecurityLog::event('auth.session.revoked', SecurityLog::AUTH, ['outcome' => 'failure', 'reason' => 'account_disabled'], 'warning');
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Your account is disabled.']);
        }

        return $next($request);
    }
}
