<?php

namespace Tests\Unit;

use App\Support\FileCrypto;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FileCryptoTest extends TestCase
{
    private FileCrypto $crypto;

    protected function setUp(): void
    {
        $this->crypto = new FileCrypto(random_bytes(32));
    }

    public function test_round_trip_returns_the_original_bytes(): void
    {
        $data = random_bytes(5000);
        $this->assertSame($data, $this->crypto->decrypt($this->crypto->encrypt($data, 'id-1'), 'id-1'));
    }

    public function test_same_plaintext_gives_different_ciphertext_because_the_iv_is_random(): void
    {
        $this->assertNotSame($this->crypto->encrypt('secret', 'id'), $this->crypto->encrypt('secret', 'id'));
    }

    public function test_ciphertext_does_not_contain_the_plaintext(): void
    {
        $this->assertStringNotContainsString('TOP SECRET CONTENT', $this->crypto->encrypt('TOP SECRET CONTENT', 'id'));
    }

    public function test_one_flipped_bit_is_detected(): void
    {
        $blob = $this->crypto->encrypt('important contract', 'id');
        $blob[strlen($blob) - 1] = chr(ord($blob[strlen($blob) - 1]) ^ 1);

        $this->expectException(RuntimeException::class);
        $this->crypto->decrypt($blob, 'id');
    }

    public function test_ciphertext_moved_to_another_record_is_rejected(): void
    {
        $blob = $this->crypto->encrypt('alice file', 'alice-file-id');

        $this->expectException(RuntimeException::class);
        $this->crypto->decrypt($blob, 'mallory-file-id');
    }

    public function test_wrong_key_is_rejected(): void
    {
        $blob = $this->crypto->encrypt('data', 'id');

        $this->expectException(RuntimeException::class);
        (new FileCrypto(random_bytes(32)))->decrypt($blob, 'id');
    }

    public function test_truncated_blob_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->crypto->decrypt('VF1short', 'id');
    }

    public function test_key_must_be_256_bits(): void
    {
        $this->expectException(RuntimeException::class);
        new FileCrypto(random_bytes(16));
    }
}
