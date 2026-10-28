<?php

namespace App\Support;

use RuntimeException;

/**
 * Authenticated file encryption with AES-256-GCM.
 *
 * Blob layout: "VF1" (version) | 12-byte random IV | 16-byte GCM tag | ciphertext.
 * The record's stored name is the additional authenticated data (AAD), so a ciphertext copied onto
 * another record fails to decrypt. The key (VAULT_FILE_KEY) is separate from APP_KEY: leaking one
 * does not expose both the database fields and the files.
 */
class FileCrypto
{
    private const CIPHER = 'aes-256-gcm';

    private const VERSION = 'VF1';

    private const IV_BYTES = 12;

    private const TAG_BYTES = 16;

    public function __construct(private readonly string $key)
    {
        if (strlen($key) !== 32) {
            throw new RuntimeException('VAULT_FILE_KEY must be 32 bytes (base64 encoded in the environment).');
        }
    }

    public static function fromConfig(): self
    {
        $raw = (string) config('vault.file_key');
        $key = base64_decode(str_starts_with($raw, 'base64:') ? substr($raw, 7) : $raw, true);

        return new self($key === false ? '' : $key);
    }

    public function encrypt(string $plaintext, string $aad): string
    {
        $iv = random_bytes(self::IV_BYTES);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag, $aad, self::TAG_BYTES);
        if ($ciphertext === false) {
            throw new RuntimeException('File encryption failed.');
        }

        return self::VERSION.$iv.$tag.$ciphertext;
    }

    public function decrypt(string $blob, string $aad): string
    {
        $header = strlen(self::VERSION) + self::IV_BYTES + self::TAG_BYTES;
        if (strlen($blob) < $header || ! str_starts_with($blob, self::VERSION)) {
            throw new RuntimeException('Unknown or truncated file format.');
        }
        $offset = strlen(self::VERSION);
        $iv = substr($blob, $offset, self::IV_BYTES);
        $tag = substr($blob, $offset + self::IV_BYTES, self::TAG_BYTES);
        $plaintext = openssl_decrypt(substr($blob, $header), self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag, $aad);
        if ($plaintext === false) {
            // Wrong key, modified bytes or a file moved to another record: the GCM tag does not match.
            throw new RuntimeException('File integrity check failed.');
        }

        return $plaintext;
    }
}
