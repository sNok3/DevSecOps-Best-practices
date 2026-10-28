<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Misuse case MC-10 (a normal user tries the admin functions). */
class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_user_is_refused_and_the_attempt_is_logged(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get('/admin/security-log')->assertForbidden();
        $this->actingAs($user)->patch("/admin/users/{$other->id}/toggle")->assertForbidden();

        $this->assertTrue($other->fresh()->is_active);
        $this->assertCount(3, $this->eventsNamed('authz.admin.denied'));
    }

    public function test_admin_can_disable_a_user_and_the_action_is_logged(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->patch("/admin/users/{$user->id}/toggle")->assertRedirect();

        $this->assertFalse($user->fresh()->is_active);
        $event = $this->eventsNamed('admin.user.disabled');
        $this->assertCount(1, $event);
        $this->assertSame($admin->id, $event[0]['context']['user_id']);
        $this->assertSame($user->id, $event[0]['context']['data']['target_user_id']);
    }

    public function test_admin_cannot_disable_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->patch("/admin/users/{$admin->id}/toggle");

        $this->assertTrue($admin->fresh()->is_active);
        $this->assertCount(1, $this->eventsNamed('admin.user.toggle_refused'));
    }

    public function test_user_list_exposes_no_password_hashes(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/users')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Users')
            ->has('users.0', fn (AssertableInertia $u) => $u->hasAll(['id', 'name', 'email', 'role', 'active'])->missing('password')));
    }

    public function test_security_log_page_shows_recent_events(): void
    {
        $admin = User::factory()->admin()->create();
        config(['logging.channels.security.path' => storage_path('logs/security.log')]);
        $this->actingAs($admin)->get('/admin/security-log')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/SecurityLog')
            ->where('events.0.message', 'admin.security_log.viewed'));
    }
}
