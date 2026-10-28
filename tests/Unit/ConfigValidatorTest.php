<?php

namespace Tests\Unit;

use App\Support\ConfigValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConfigValidatorTest extends TestCase
{
    private function valid(): array
    {
        return [
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'vault.file_key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://vault.example.com',
            'session.secure' => true,
        ];
    }

    public function test_secure_production_config_passes(): void
    {
        $this->assertSame([], ConfigValidator::problems($this->valid()));
    }

    public static function insecure(): array
    {
        return [
            'missing app key' => [['app.key' => null], 'APP_KEY'],
            'short app key' => [['app.key' => 'base64:'.base64_encode('too-short')], 'APP_KEY'],
            'missing file key' => [['vault.file_key' => ''], 'VAULT_FILE_KEY'],
            'debug on in production' => [['app.debug' => true], 'APP_DEBUG'],
            'debug unset (null) is not "off"' => [['app.debug' => null], 'APP_DEBUG'],
            'plain http' => [['app.url' => 'http://vault.example.com'], 'APP_URL'],
            'insecure cookie' => [['session.secure' => false], 'SESSION_SECURE_COOKIE'],
        ];
    }

    #[DataProvider('insecure')]
    public function test_insecure_values_are_reported(array $override, string $expected): void
    {
        $problems = ConfigValidator::problems(array_merge($this->valid(), $override));
        $this->assertNotEmpty($problems);
        $this->assertStringContainsString($expected, implode(' ', $problems));
    }

    public function test_same_key_for_db_and_files_is_rejected(): void
    {
        $config = $this->valid();
        $config['vault.file_key'] = $config['app.key'];
        $this->assertStringContainsString('must differ', implode(' ', ConfigValidator::problems($config)));
    }

    public function test_local_environment_may_use_debug_and_http(): void
    {
        $config = array_merge($this->valid(), ['app.env' => 'local', 'app.debug' => true, 'app.url' => 'http://localhost']);
        $this->assertSame([], ConfigValidator::problems($config));
    }
}
