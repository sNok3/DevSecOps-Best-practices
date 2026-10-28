<?php

namespace App\Support;

use RuntimeException;

/**
 * Validates configuration at boot. Config is input too: a production deploy with debug on, a missing key
 * or a plain-http URL must refuse to start (fail closed) instead of running insecurely.
 */
class ConfigValidator
{
    /**
     * @param  array<string, mixed>  $config  flattened values (see fromApp)
     * @return list<string> problems found, empty when valid
     */
    public static function problems(array $config): array
    {
        $problems = [];
        if (! self::isKey($config['app.key'] ?? null)) {
            $problems[] = 'APP_KEY must be a base64 encoded 32-byte key.';
        }
        if (! self::isKey($config['vault.file_key'] ?? null)) {
            $problems[] = 'VAULT_FILE_KEY must be a base64 encoded 32-byte key.';
        }
        if (($config['app.key'] ?? null) !== null && ($config['app.key'] ?? null) === ($config['vault.file_key'] ?? null)) {
            $problems[] = 'VAULT_FILE_KEY must differ from APP_KEY.';
        }
        if (($config['app.env'] ?? null) === 'production') {
            if (($config['app.debug'] ?? true) !== false) {
                $problems[] = 'APP_DEBUG must be false in production.';
            }
            if (! str_starts_with((string) ($config['app.url'] ?? ''), 'https://')) {
                $problems[] = 'APP_URL must use https:// in production.';
            }
            if (($config['session.secure'] ?? false) !== true) {
                $problems[] = 'SESSION_SECURE_COOKIE must be true in production.';
            }
        }

        return $problems;
    }

    /** @return array<string, mixed> */
    public static function fromApp(): array
    {
        $keys = ['app.key', 'app.env', 'app.debug', 'app.url', 'session.secure', 'vault.file_key'];

        return array_combine($keys, array_map(fn ($k) => config($k), $keys));
    }

    public static function assertValid(): void
    {
        $problems = self::problems(self::fromApp());
        if ($problems !== []) {
            throw new RuntimeException('Insecure configuration: '.implode(' ', $problems));
        }
    }

    private static function isKey(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('/^base64:[A-Za-z0-9+\/]{43}=$/', $value)) {
            return false;
        }

        return strlen((string) base64_decode(substr($value, 7), true)) === 32;
    }
}
