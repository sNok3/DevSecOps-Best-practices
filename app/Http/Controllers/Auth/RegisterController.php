<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class RegisterController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterRequest $request)
    {
        // Only validated fields; role/is_active keep their secure defaults.
        $user = User::create($request->validated());
        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('notes.index');
    }
}
