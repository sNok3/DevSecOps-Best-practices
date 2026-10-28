<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Misuse cases MC-01 (brute force / credential stuffing), MC-02 (user enumeration), MC-03 (privilege escalation at sign-up). */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $override = []): TestResponse
    {
        return $this->post('/register', array_merge([
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'password' => 'correct horse battery staple',
            'password_confirmation' => 'correct horse battery staple',
        ], $override));
    }

    public function test_password_is_stored_as_argon2id_hash(): void
    {
        $this->register()->assertRedirect('/notes');

        $hash = User::where('email', 'alice@example.com')->value('password');
        $this->assertStringStartsWith('$argon2id$', $hash);
        $this->assertStringNotContainsString('correct horse', $hash);
    }

    public function test_role_cannot_be_set_through_the_registration_form(): void
    {
        $this->register(['role' => 'admin', 'is_active' => 1]);

        $this->assertSame('user', User::where('email', 'alice@example.com')->value('role'));
    }

    public function test_short_password_is_rejected(): void
    {
        $this->register(['password' => 'Short1!', 'password_confirmation' => 'Short1!'])->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
        $this->assertNotEmpty($this->eventsNamed('input.rejected'));
    }

    public function test_breached_password_is_rejected(): void
    {
        // SHA-1("correct horse battery staple") = BFD36 + suffix; the fake API reports it as leaked.
        $suffix = strtoupper(substr(sha1('correct horse battery staple'), 5));
        $this->pwnedResponse = $suffix.':1500';

        $this->register()->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_wrong_password_gives_the_same_generic_error_as_an_unknown_account(): void
    {
        User::factory()->create(['email' => 'bob@example.com']);

        $wrongPassword = $this->post('/login', ['email' => 'bob@example.com', 'password' => 'not-the-password'])->assertSessionHasErrors('email');
        $msgKnown = session('errors')->first('email');
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'not-the-password'])->assertSessionHasErrors('email');
        $msgUnknown = session('errors')->first('email');

        $this->assertSame($msgKnown, $msgUnknown);
        $this->assertGuest();

        $failed = $this->eventsNamed('auth.login.failed');
        $this->assertCount(2, $failed);
        $this->assertSame('failure', $failed[0]['context']['outcome']);
        $this->assertTrue($failed[0]['context']['data']['known_user']);
        $this->assertFalse($failed[1]['context']['data']['known_user']);
    }

    public function test_account_locks_after_five_failed_attempts_and_lockout_is_logged(): void
    {
        $user = User::factory()->create(['email' => 'bob@example.com', 'password' => 'the real password 1']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'bob@example.com', 'password' => 'guess'.$i]);
        }
        // Even the correct password is refused while locked.
        $this->post('/login', ['email' => 'bob@example.com', 'password' => 'the real password 1'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
        $lockout = $this->eventsNamed('auth.lockout');
        $this->assertCount(1, $lockout);
        $this->assertSame('ALERT', $lockout[0]['level_name']);
    }

    public function test_successful_login_regenerates_the_session_and_is_logged(): void
    {
        $user = User::factory()->create(['email' => 'bob@example.com', 'password' => 'the real password 1']);
        $this->startSession();
        $before = session()->getId();

        $this->post('/login', ['email' => 'BOB@example.com', 'password' => 'the real password 1'])->assertRedirect('/notes');

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
        $this->assertCount(1, $this->eventsNamed('auth.login.success'));
    }

    public function test_disabled_account_cannot_log_in(): void
    {
        User::factory()->create(['email' => 'bob@example.com', 'password' => 'the real password 1', 'is_active' => false]);

        $this->post('/login', ['email' => 'bob@example.com', 'password' => 'the real password 1'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_disabled_account_loses_its_existing_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/notes')->assertOk();

        $user->forceFill(['is_active' => false])->save();
        $this->actingAs($user)->get('/notes')->assertRedirect('/login');
        $this->assertCount(1, $this->eventsNamed('auth.session.revoked'));
    }

    public function test_the_password_never_reaches_the_security_log(): void
    {
        User::factory()->create(['email' => 'bob@example.com']);
        $this->post('/login', ['email' => 'bob@example.com', 'password' => 'Pa55word-that-must-not-leak']);

        $raw = implode("\n", array_map('json_encode', $this->securityEvents()));
        $this->assertStringNotContainsString('Pa55word-that-must-not-leak', $raw);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/notes')->assertRedirect('/login');
        $this->get('/admin/users')->assertRedirect('/login');
    }
}
