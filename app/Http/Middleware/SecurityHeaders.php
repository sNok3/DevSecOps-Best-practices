<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/** Browser hardening headers on every response (OWASP Secure Headers Project). */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // A fresh random nonce per response; only <script>/<link> tags rendered by Vite carry it,
        // so an injected inline script has no valid nonce and the browser refuses to run it.
        $nonce = Vite::useCspNonce();

        $response = $next($request);
        $headers = [
            'Content-Security-Policy' => self::csp($nonce),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }
        // PHP itself adds X-Powered-By when the response is sent; production also sets `expose_php = Off`.
        header_remove('X-Powered-By');

        return $response;
    }

    private static function csp(string $nonce): string
    {
        $script = "'self' 'nonce-{$nonce}'";
        $style = "'self' 'nonce-{$nonce}'";
        $connect = "'self'";

        // Local development only: the Vite dev server serves modules from its own origin, uses a websocket
        // for hot reload and injects CSS through <style> tags. Never active in a production build.
        if (app()->isLocal() && Vite::isRunningHot()) {
            $dev = rtrim((string) file_get_contents(Vite::hotFile()));
            $ws = preg_replace('#^http#', 'ws', $dev);
            $script .= " {$dev}";
            $style = "'self' 'unsafe-inline' {$dev}";
            $connect .= " {$dev} {$ws}";
        }

        return "default-src 'self'; script-src {$script}; style-src {$style}; connect-src {$connect}; img-src 'self' data:; font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";
    }
}
