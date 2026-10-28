<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Creates the first admin from environment variables. There is deliberately no default password:
     * a missing or short ADMIN_PASSWORD stops the seeder instead of creating a guessable admin.
     */
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL');
        $password = (string) env('ADMIN_PASSWORD');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 12) {
            throw new RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD (12+ characters) to seed the admin account.');
        }

        $admin = User::firstOrNew(['email' => mb_strtolower($email)]);
        $admin->name = 'Administrator';
        $admin->password = $password;
        $admin->role = User::ROLE_ADMIN;
        $admin->save();
    }
}
