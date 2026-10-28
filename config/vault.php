<?php

return [
    // 32 random bytes, base64: `php artisan vault:key` prints a new one. Kept out of the repo (.env / secret store).
    'file_key' => env('VAULT_FILE_KEY'),

    // Upload limits (KB) and the only file types the vault accepts.
    'max_upload_kb' => (int) env('VAULT_MAX_UPLOAD_KB', 5120),
    'allowed_mimes' => ['pdf', 'png', 'jpg', 'txt'],

    // Brute force protection: attempts per minute per e-mail + IP before a lockout.
    'login_attempts' => 5,
];
