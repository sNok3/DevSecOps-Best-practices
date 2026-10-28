<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected string $securityLogBase;

    /** Body the fake Pwned Passwords API answers with ("SUFFIX:count" lines); empty = not breached. */
    protected string $pwnedResponse = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Throw-away keys per test run: no real secret is ever needed (or stored) for the suite.
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'vault.file_key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);

        $this->securityLogBase = storage_path('logs/test-security-'.getmypid());
        config(['logging.channels.security.path' => $this->securityLogBase.'.log']);
        array_map('unlink', glob($this->securityLogBase.'*') ?: []);

        // The breached-password check calls api.pwnedpasswords.com; tests never leave the machine.
        Http::fake(['api.pwnedpasswords.com/*' => fn () => Http::response($this->pwnedResponse, 200)]);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->securityLogBase.'*') ?: []);
        parent::tearDown();
    }

    /** @return list<array<string, mixed>> the JSON lines written to the security log during this test */
    protected function securityEvents(): array
    {
        $events = [];
        foreach (glob($this->securityLogBase.'*') ?: [] as $file) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $events[] = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            }
        }

        return $events;
    }

    /** @return list<array<string, mixed>> */
    protected function eventsNamed(string $name): array
    {
        return array_values(array_filter($this->securityEvents(), fn ($e) => $e['message'] === $name));
    }
}
