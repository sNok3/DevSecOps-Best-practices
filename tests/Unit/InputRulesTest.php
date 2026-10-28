<?php

namespace Tests\Unit;

use App\Support\InputRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InputRulesTest extends TestCase
{
    public static function cases(): array
    {
        return [
            // [pattern, input, accepted?]
            'name plain' => [InputRules::NAME, 'Jan de Vries', true],
            'name accents' => [InputRules::NAME, 'Zoë O\'Brien-Ąłę', true],
            'name script tag' => [InputRules::NAME, '<script>', false],
            'name digits' => [InputRules::NAME, 'admin123', false],
            'name single char' => [InputRules::NAME, 'J', false],
            'name sql' => [InputRules::NAME, "x' OR 1=1 --", false],
            'title normal' => [InputRules::TITLE, 'Meeting notes 28/10 (draft)', true],
            'title emoji ok' => [InputRules::TITLE, 'Ideas ✨', true],
            'title html' => [InputRules::TITLE, '<img src=x onerror=alert(1)>', false],
            'title newline' => [InputRules::TITLE, "two\nlines", false],
            'title null byte' => [InputRules::TITLE, "abc\0def", false],
            'body multiline' => [InputRules::MULTILINE, "line 1\r\nline 2\ttab", true],
            'body escape char' => [InputRules::MULTILINE, "colour \e[31mred", false],
            'body null byte' => [InputRules::MULTILINE, "a\0b", false],
            'file pdf' => [InputRules::FILE_NAME, 'Report 2026 (final).pdf', true],
            'file jpeg upper' => [InputRules::FILE_NAME, 'PHOTO.JPEG', true],
            'file php' => [InputRules::FILE_NAME, 'shell.php', false],
            'file double ext' => [InputRules::FILE_NAME, 'shell.php.png', true], // name is fine; content is checked by `mimes`
            'file traversal' => [InputRules::FILE_NAME, '../../etc/passwd.txt', false],
            'file null byte' => [InputRules::FILE_NAME, "a.php\0.png", false],
            'file html' => [InputRules::FILE_NAME, '<b>.txt', false],
        ];
    }

    #[DataProvider('cases')]
    public function test_pattern(string $pattern, string $input, bool $accepted): void
    {
        $this->assertSame($accepted, preg_match($pattern, $input) === 1);
    }
}
