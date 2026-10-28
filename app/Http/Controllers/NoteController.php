<?php

namespace App\Http\Controllers;

use App\Http\Requests\NoteRequest;
use App\Models\Note;
use App\Support\SecurityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class NoteController extends Controller
{
    public function index(Request $request)
    {
        // Always scoped to the logged-in user: the query itself cannot return someone else's notes.
        return Inertia::render('Notes/Index', [
            'notes' => $request->user()->notes()->latest()->get()->map(fn (Note $n) => [
                'id' => $n->id, 'title' => $n->title, 'updatedAt' => $n->updated_at->toIso8601String(),
            ]),
        ]);
    }

    public function create()
    {
        return Inertia::render('Notes/Form', ['note' => null]);
    }

    public function store(NoteRequest $request)
    {
        $note = $request->user()->notes()->create($request->validated());
        SecurityLog::event('data.note.created', SecurityLog::DATA, ['note_id' => $note->id]);

        return redirect()->route('notes.show', $note);
    }

    public function show(Note $note)
    {
        Gate::authorize('access', $note);

        return Inertia::render('Notes/Show', ['note' => self::present($note)]);
    }

    public function edit(Note $note)
    {
        Gate::authorize('access', $note);

        return Inertia::render('Notes/Form', ['note' => self::present($note)]);
    }

    public function update(NoteRequest $request, Note $note)
    {
        Gate::authorize('access', $note);
        $note->update($request->validated());
        SecurityLog::event('data.note.updated', SecurityLog::DATA, ['note_id' => $note->id]);

        return redirect()->route('notes.show', $note);
    }

    public function destroy(Note $note)
    {
        Gate::authorize('access', $note);
        $note->delete();
        SecurityLog::event('data.note.deleted', SecurityLog::DATA, ['note_id' => $note->id]);

        return redirect()->route('notes.index')->with('status', 'Note deleted.');
    }

    /** Explicit allow-list of what the browser receives (no user_id, no raw ciphertext). */
    private static function present(Note $note): array
    {
        return ['id' => $note->id, 'title' => $note->title, 'body' => $note->body, 'updatedAt' => $note->updated_at->toIso8601String()];
    }
}
