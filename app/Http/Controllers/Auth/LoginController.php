<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Support\SecurityLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LoginController extends Controller
{
    private static ?string $dummyHash = null;

    public function create()
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request)
    {
        $email = mb_strtolower($request->validated('email'));
        $key = 'login:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, config('vault.login_attempts'))) {
            event(new Lockout($request));
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        $user = User::where('email', $email)->first();
        // Always run one hash verification, also for unknown e-mails, so response time does not reveal
        // which accounts exist (user enumeration by timing).
        $valid = Hash::check($request->validated('password'), $user?->password ?? self::dummyHash());

        if (! $user || ! $valid || ! $user->is_active) {
            RateLimiter::hit($key, 60);
            event(new Failed('web', $user, ['email' => $email]));
            // One generic message for every failure reason.
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $request->validated('password')])->save();
            SecurityLog::event('crypto.password.rehashed', SecurityLog::CRYPTO, ['target_user_id' => $user->id]);
        }

        RateLimiter::clear($key);
        Auth::login($user);
        $request->session()->regenerate(); // new session id after login: prevents session fixation

        return redirect()->intended(route('notes.index'));
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make('timing-equaliser-'.bin2hex(random_bytes(8)));
    }
}
