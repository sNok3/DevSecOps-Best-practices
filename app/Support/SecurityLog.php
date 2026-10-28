<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;

/**
 * Writes security events to the dedicated `security` channel (JSON lines, storage/logs/security-*.log).
 *
 * Every entry answers who / what / when / where / outcome, so an incident can be reconstructed later
 * (forensic readiness). Never pass passwords, tokens, note contents or file contents in $data.
 */
class SecurityLog
{
    public const AUTH = 'authentication';

    public const AUTHZ = 'authorization';

    public const INPUT = 'input_validation';

    public const DATA = 'data_access';

    public const ADMIN = 'admin';

    public const CRYPTO = 'crypto';

    public const SYSTEM = 'system';

    /** Keys that must never reach a log, whatever a caller passes. */
    private const REDACT = ['password', 'password_confirmation', 'current_password', 'token', '_token', 'body', 'secret'];

    /**
     * @param  string  $event  dotted name, e.g. `auth.login.failed`
     * @param  string  $category  one of the class constants
     * @param  array<string, mixed>  $data  extra fields (ids, counts, field names)
     * @param  string  $level  PSR-3 level: info for normal, warning for suspicious, error/critical for failures
     */
    public static function event(string $event, string $category, array $data = [], string $level = 'info'): void
    {
        // Artisan commands have no real request; the test runner does (it simulates HTTP).
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        Log::channel('security')->log($level, $event, [
            'category' => $category,
            'outcome' => $data['outcome'] ?? 'success',
            'request_id' => Context::get('request_id'),
            'user_id' => Auth::id(),
            'ip' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 200) : null,
            'method' => $request?->method(),
            'path' => $request?->path(),
            'data' => self::redact($data),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function redact(array $data): array
    {
        unset($data['outcome']);
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACT, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = self::redact($value);
            }
        }

        return $data;
    }
}
