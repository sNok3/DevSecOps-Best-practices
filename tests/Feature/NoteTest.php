<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Misuse cases MC-04 (IDOR: read another user's note), MC-05 (stored XSS), MC-06 (database dump). */
class NoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_note_is_encrypted_in_the_database(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/notes', ['title' => 'Bank PIN', 'body' => 'my pin is 4821']);

        $row = DB::table('notes')->first();
        $this->assertStringNotContainsString('4821', $row->body);
        $this->assertStringNotContainsString('Bank PIN', $row->title);
        // Laravel's encrypted payload (base64 JSON with iv, value, mac/tag); GCM puts the auth tag in `tag`.
        $payload = json_decode(base64_decode($row->body), true);
        $this->assertNotEmpty($payload['tag']);
        $this->assertSame('my pin is 4821', Note::first()->body);
    }

    public function test_owner_sees_own_notes_only(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        Note::forceCreate(['user_id' => $alice->id, 'title' => 'alice note', 'body' => 'x']);
        Note::forceCreate(['user_id' => $bob->id, 'title' => 'bob note', 'body' => 'y']);

        $this->actingAs($alice)->get('/notes')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Notes/Index')
            ->has('notes', 1)
            ->where('notes.0.title', 'alice note'));
    }

    public function test_other_users_note_is_not_found_and_the_attempt_is_logged(): void
    {
        [$alice, $mallory] = User::factory()->count(2)->create();
        $note = Note::forceCreate(['user_id' => $alice->id, 'title' => 'secret', 'body' => 'x']);

        $this->actingAs($mallory)->get("/notes/{$note->id}")->assertNotFound();
        $this->actingAs($mallory)->put("/notes/{$note->id}", ['title' => 'hacked', 'body' => 'hacked'])->assertNotFound();
        $this->actingAs($mallory)->delete("/notes/{$note->id}")->assertNotFound();

        $this->assertSame('secret', $note->fresh()->title);
        $denied = $this->eventsNamed('authz.denied');
        $this->assertCount(3, $denied);
        $this->assertSame($mallory->id, $denied[0]['context']['user_id']);
        $this->assertSame($note->id, $denied[0]['context']['data']['resource_id']);
    }

    public function test_admin_cannot_read_user_notes(): void
    {
        $alice = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $note = Note::forceCreate(['user_id' => $alice->id, 'title' => 'private', 'body' => 'x']);

        $this->actingAs($admin)->get("/notes/{$note->id}")->assertNotFound();
    }

    public function test_script_in_a_note_is_never_returned_as_markup(): void
    {
        $user = User::factory()->create();
        $note = Note::forceCreate(['user_id' => $user->id, 'title' => 'xss', 'body' => '<script>alert(document.cookie)</script>']);

        $html = $this->actingAs($user)->get("/notes/{$note->id}")->assertOk()->getContent();
        // The page JSON is encoded with JSON_HEX_TAG, React renders it as text.
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('\\u003Cscript\\u003Ealert', $html);
    }

    public function test_html_in_the_title_and_control_characters_in_the_body_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notes', ['title' => '<img src=x onerror=alert(1)>', 'body' => 'ok'])->assertSessionHasErrors('title');
        $this->actingAs($user)->post('/notes', ['title' => 'ok', 'body' => "evil\0byte"])->assertSessionHasErrors('body');
        $this->actingAs($user)->post('/notes', ['title' => str_repeat('a', 121), 'body' => 'ok'])->assertSessionHasErrors('title');

        $this->assertDatabaseCount('notes', 0);
        $this->assertCount(3, $this->eventsNamed('input.rejected'));
    }

    public function test_user_id_cannot_be_injected_to_create_a_note_for_someone_else(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $this->actingAs($alice)->post('/notes', ['title' => 't', 'body' => 'b', 'user_id' => $bob->id]);

        $this->assertSame($alice->id, Note::first()->user_id);
    }

    public function test_sql_injection_in_the_id_is_harmless(): void
    {
        $user = User::factory()->create();
        Note::forceCreate(['user_id' => $user->id, 'title' => 't', 'body' => 'b']);

        $this->actingAs($user)->get('/notes/1%20OR%201=1')->assertNotFound();
    }
}
