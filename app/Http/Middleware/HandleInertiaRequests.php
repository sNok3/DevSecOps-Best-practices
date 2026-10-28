<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * Props on every page. Only what the UI needs: never the whole user model (password hash,
     * remember token, role internals) — the frontend is public code and its props are visible in the page source.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? ['id' => $user->id, 'name' => $user->name, 'isAdmin' => $user->isAdmin()] : null,
            ],
            'flash' => ['status' => fn () => $request->session()->get('status')],
            'limits' => ['maxUploadKb' => config('vault.max_upload_kb'), 'types' => config('vault.allowed_mimes')],
        ];
    }
}
