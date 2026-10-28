<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/** Misuse cases MC-11 (clickjacking / script injection via the browser), MC-12 (information leak through errors), MC-13 (CSRF). */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_set(): void
    {
        $response = $this->get('/login');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9]+'/", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $response->headers->get('X-Request-Id'));
    }

    public function test_hsts_on_https(): void
    {
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_client_supplied_request_id_is_ignored(): void
    {
        $response = $this->get('/login', ['X-Request-Id' => 'attacker-chosen']);
        $this->assertNotSame('attacker-chosen', $response->headers->get('X-Request-Id'));
    }

    public function test_session_cookie_is_secure_httponly_and_strict(): void
    {
        $cookie = collect($this->get('/login')->headers->getCookies())->first(fn ($c) => str_ends_with($c->getName(), 'session'));

        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('strict', $cookie->getSameSite());
    }

    public function test_unexpected_exception_shows_a_generic_page_with_a_reference_only(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/_boom', fn () => throw new RuntimeException('DB password is hunter2'));

        $response = $this->get('/_boom')->assertStatus(500);
        $html = $response->getContent();

        $this->assertStringNotContainsString('hunter2', $html);
        $this->assertStringNotContainsString('RuntimeException', $html);
        $this->assertStringContainsString($response->headers->get('X-Request-Id'), $html);
    }

    public function test_inertia_request_gets_the_error_page_component(): void
    {
        config(['app.debug' => false]);
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/notes/999', ['X-Inertia' => 'true', 'X-Inertia-Version' => '']);

        $response->assertStatus(404);
        $this->assertSame('Error', $response->json('component'));
        $this->assertSame(404, $response->json('props.status'));
    }

    public function test_request_without_csrf_token_is_rejected_and_logged(): void
    {
        $this->app->instance('env', 'production'); // the CSRF middleware is skipped in unit tests by default
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notes', ['title' => 't', 'body' => 'b'])->assertRedirect();

        $this->assertDatabaseCount('notes', 0);
        $this->assertCount(1, $this->eventsNamed('input.csrf.rejected'));
    }
}
