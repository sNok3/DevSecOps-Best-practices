<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Only the owner may see or change a note. Admins are NOT allowed either (least privilege: admins manage
 * accounts, not content). Denied as 404 so an attacker cannot tell whether a note id exists.
 */
class NotePolicy
{
    public function access(User $user, Note $note): Response
    {
        return $user->id === $note->user_id ? Response::allow() : Response::denyAsNotFound();
    }
}
