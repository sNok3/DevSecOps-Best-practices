<?php

namespace Tests\Feature;

use App\Support\SecurityLog;
use Tests\TestCase;

/** Misuse case MC-12 (log forging) and the log format the forensics in docs/06-logging.md rely on. */
class SecurityLogTest extends TestCase
{
    public function test_newline_in_input_cannot_forge_a_second_entry(): void
    {
        SecurityLog::event('auth.login.failed', SecurityLog::AUTH, [
            'email' => "x@example.com\n{\"message\":\"auth.login.success\",\"context\":{\"user_id\":1}}",
        ], 'warning');

        $events = $this->securityEvents();
        $this->assertCount(1, $events);
        $this->assertSame('auth.login.failed', $events[0]['message']);
        $this->assertStringContainsString("\n", $events[0]['context']['data']['email']); // kept as data, inside one line
    }

    public function test_every_entry_has_the_forensic_fields(): void
    {
        $this->get('/login'); // a request so a request id exists
        SecurityLog::event('test.event', SecurityLog::SYSTEM, ['password' => 'never-logged']);

        $entry = $this->eventsNamed('test.event')[0];
        foreach (['category', 'outcome', 'request_id', 'user_id', 'ip', 'user_agent', 'method', 'path', 'data'] as $field) {
            $this->assertArrayHasKey($field, $entry['context']);
        }
        $this->assertNotEmpty($entry['datetime']);
        $this->assertSame('[REDACTED]', $entry['context']['data']['password']);
    }
}
