<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SecurityLog;
use Inertia\Inertia;

/** Read-only view of today's security log for admins (monitoring). The file itself stays the source of truth. */
class SecurityLogController extends Controller
{
    private const MAX_LINES = 200;

    public function index()
    {
        SecurityLog::event('admin.security_log.viewed', SecurityLog::ADMIN);

        $path = storage_path('logs/security-'.now()->format('Y-m-d').'.log');
        $lines = is_readable($path) ? array_slice(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -self::MAX_LINES) : [];
        $events = array_reverse(array_values(array_filter(array_map(
            fn (string $line) => json_decode($line, true),
            $lines,
        ), 'is_array')));

        return Inertia::render('Admin/SecurityLog', ['events' => $events]);
    }
}
