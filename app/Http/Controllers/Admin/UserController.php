<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SecurityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Users', [
            'users' => User::orderBy('id')->get(['id', 'name', 'email', 'role', 'is_active'])->map(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role, 'active' => $u->is_active,
            ]),
        ]);
    }

    public function toggle(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            // An admin locking themselves out (or a hijacked admin session hiding by disabling itself) is refused.
            SecurityLog::event('admin.user.toggle_refused', SecurityLog::ADMIN, ['outcome' => 'failure', 'target_user_id' => $user->id], 'warning');

            return back()->withErrors(['user' => 'You cannot disable your own account.']);
        }

        $user->is_active = ! $user->is_active;
        $user->save();
        SecurityLog::event($user->is_active ? 'admin.user.enabled' : 'admin.user.disabled', SecurityLog::ADMIN, ['target_user_id' => $user->id], 'notice');

        return back()->with('status', 'Account updated.');
    }
}
