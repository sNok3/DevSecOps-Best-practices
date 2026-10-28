<?php

use App\Support\ConfigValidator;
use Illuminate\Support\Facades\Artisan;

Artisan::command('vault:key', function () {
    // Printed, never written: you put it in .env or your secret store yourself.
    $this->line('VAULT_FILE_KEY=base64:'.base64_encode(random_bytes(32)));
})->purpose('Generate a new 256-bit key for file encryption');

Artisan::command('vault:check-config', function () {
    $problems = ConfigValidator::problems(ConfigValidator::fromApp());
    foreach ($problems as $problem) {
        $this->error($problem);
    }
    if ($problems === []) {
        $this->info('Configuration OK.');
    }

    return $problems === [] ? 0 : 1;
})->purpose('Validate security-relevant configuration (exit 1 when insecure)');
