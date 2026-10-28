<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VaultFile;
use Illuminate\Auth\Access\Response;

/** Same rule as NotePolicy: owner only, others get a 404. */
class VaultFilePolicy
{
    public function access(User $user, VaultFile $file): Response
    {
        return $user->id === $file->user_id ? Response::allow() : Response::denyAsNotFound();
    }
}
