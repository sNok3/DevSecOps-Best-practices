<?php

// Tests get their own throw-away storage, so `php artisan test` inside the running app container never
// touches its encrypted files or its real security log.
$storage = sys_get_temp_dir().'/vault-test-storage-'.getmypid();
foreach (['app/private', 'app/public', 'framework/cache/data', 'framework/sessions', 'framework/testing', 'framework/views', 'logs'] as $dir) {
    is_dir("$storage/$dir") || mkdir("$storage/$dir", 0700, true);
}
$_ENV['LARAVEL_STORAGE_PATH'] = $storage;
// PHPUnit's <env force="true"> sets getenv() and $_ENV but not $_SERVER, which Laravel reads first: copy them over,
// otherwise the container's real DB_CONNECTION=mysql wins and RefreshDatabase wipes the real database.
foreach ($_ENV as $name => $value) {
    $_SERVER[$name] = $value;
}
register_shutdown_function(function () use ($storage) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storage, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($storage);
});

require __DIR__.'/../vendor/autoload.php';
