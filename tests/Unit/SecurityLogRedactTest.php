<?php

namespace Tests\Unit;

use App\Support\SecurityLog;
use PHPUnit\Framework\TestCase;

class SecurityLogRedactTest extends TestCase
{
    public function test_secrets_are_redacted_at_any_depth(): void
    {
        $out = SecurityLog::redact([
            'email' => 'a@b.c',
            'Password' => 'hunter2hunter2',
            'nested' => ['token' => 'abc', 'id' => 5],
            'outcome' => 'failure',
        ]);

        $this->assertSame('a@b.c', $out['email']);
        $this->assertSame('[REDACTED]', $out['Password']);
        $this->assertSame(['token' => '[REDACTED]', 'id' => 5], $out['nested']);
        $this->assertArrayNotHasKey('outcome', $out); // moved to the top level of the entry
    }
}
