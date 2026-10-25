<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** @return void */
    public function test_csrf_cookie_endpoint_is_available(): void
    {
        $this->get('/sanctum/csrf-cookie')->assertNoContent();
    }

    /** @return void */
    public function test_login_succeeds_with_valid_credentials(): void
    {
        User::factory()->create([
            'email'    => 'household@foliotrak.test',
            'password' => 'correct-horse',
        ]);

        $this->postJson('/login', [
            'email'    => 'household@foliotrak.test',
            'password' => 'correct-horse',
        ])->assertOk();
    }

    /** @return void */
    public function test_login_is_rejected_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email'    => 'household@foliotrak.test',
            'password' => 'correct-horse',
        ]);

        $this->postJson('/login', [
            'email'    => 'household@foliotrak.test',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    /** @return void */
    public function test_api_user_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJson(['email' => $user->email]);
    }

    /** @return void */
    public function test_api_user_requires_authentication(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    /** @return void */
    public function test_logout_endpoint_succeeds(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/logout')
            ->assertOk();
    }

    /** @return void */
    public function test_login_with_remember_sets_remember_cookie(): void
    {
        User::factory()->create([
            'email'          => 'household@foliotrak.test',
            'password'       => 'correct-horse',
            'remember_token' => null,
        ]);

        $response = $this->postJson('/login', [
            'email'    => 'household@foliotrak.test',
            'password' => 'correct-horse',
            'remember' => true,
        ]);

        $response->assertOk();

        $user = User::where('email', 'household@foliotrak.test')->first();
        $this->assertNotNull($user->remember_token);
    }

    /** @return void */
    public function test_login_without_remember_does_not_set_remember_token(): void
    {
        User::factory()->create([
            'email'          => 'household@foliotrak.test',
            'password'       => 'correct-horse',
            'remember_token' => null,
        ]);

        $this->postJson('/login', [
            'email'    => 'household@foliotrak.test',
            'password' => 'correct-horse',
        ])->assertOk();

        $user = User::where('email', 'household@foliotrak.test')->first();
        $this->assertNull($user->remember_token);
    }

    /** @return void */
    public function test_login_with_remember_emits_recaller_cookie_with_five_day_expiry(): void
    {
        User::factory()->create([
            'email'    => 'household@foliotrak.test',
            'password' => 'correct-horse',
        ]);

        $response = $this->postJson('/login', [
            'email'    => 'household@foliotrak.test',
            'password' => 'correct-horse',
            'remember' => true,
        ]);

        $response->assertOk();

        $recallerCookie = collect($response->headers->getCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));

        $this->assertNotNull($recallerCookie, 'Login response must include a remember_web_* cookie');

        $fiveDaysFromNow = time() + (7200 * 60);
        $this->assertEqualsWithDelta(
            $fiveDaysFromNow,
            $recallerCookie->getExpiresTime(),
            60,
            'Recaller cookie expiry should be ~5 days from now',
        );
    }

    /** @return void */
    public function test_login_without_remember_does_not_emit_recaller_cookie(): void
    {
        User::factory()->create([
            'email'    => 'household@foliotrak.test',
            'password' => 'correct-horse',
        ]);

        $response = $this->postJson('/login', [
            'email'    => 'household@foliotrak.test',
            'password' => 'correct-horse',
        ]);

        $response->assertOk();

        $recallerCookie = collect($response->headers->getCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));

        $this->assertNull($recallerCookie, 'Login without remember must not set a recaller cookie');
    }

    /**
     * JSON test requests carry no cookies unless withCredentials() is set, and the
     * guard keeps the user in memory across requests in one process, so both are
     * reset here; otherwise this passes without the cookie ever being read.
     *
     * @return void
     */
    public function test_recaller_cookie_reauthenticates_after_session_destroyed(): void
    {
        User::factory()->create([
            'email'    => 'household@foliotrak.test',
            'password' => 'correct-horse',
        ]);

        $login = $this->postJson('/login', [
            'email'    => 'household@foliotrak.test',
            'password' => 'correct-horse',
            'remember' => true,
        ]);
        $login->assertOk();

        $recaller = collect($login->headers->getCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
        $this->assertNotNull($recaller, 'Login response must include a remember_web_* cookie');

        $this->app['session.store']->flush();
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->withCredentials()
            ->withUnencryptedCookie($recaller->getName(), $recaller->getValue())
            ->withHeader('Referer', config('app.url') . '/')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonFragment(['email' => 'household@foliotrak.test']);
    }

    /** @return void */
    public function test_fresh_request_without_recaller_cookie_is_unauthenticated(): void
    {
        User::factory()->create([
            'email'    => 'household@foliotrak.test',
            'password' => 'correct-horse',
        ]);

        $this->postJson('/login', [
            'email'    => 'household@foliotrak.test',
            'password' => 'correct-horse',
            'remember' => true,
        ])->assertOk();

        $this->app['session.store']->flush();
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->withCredentials()
            ->withHeader('Referer', config('app.url') . '/')
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    /** @return void */
    public function test_authenticated_request_with_recaller_cookie_reissues_it_for_five_days(): void
    {
        User::factory()->create([
            'email'    => 'household@foliotrak.test',
            'password' => 'correct-horse',
        ]);

        $login = $this->postJson('/login', [
            'email'    => 'household@foliotrak.test',
            'password' => 'correct-horse',
            'remember' => true,
        ]);
        $login->assertOk();

        $issued = collect($login->headers->getCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
        $this->assertNotNull($issued);

        // The cookie jar is a singleton here, so the login's queued cookie would be
        // re-attached to this response and pass as a reissue.
        $this->app['session.store']->flush();
        $this->app['auth']->forgetGuards();
        $this->app['cookie']->flushQueuedCookies();
        $this->flushSession();

        $response = $this->withCredentials()
            ->withUnencryptedCookie($issued->getName(), $issued->getValue())
            ->withHeader('Referer', config('app.url') . '/')
            ->getJson('/api/user');
        $response->assertOk();

        $reissued = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === $issued->getName());
        $this->assertNotNull($reissued, 'An authenticated request must reissue the remember cookie');

        $this->assertEqualsWithDelta(
            time() + (7200 * 60),
            $reissued->getExpiresTime(),
            60,
            'Reissued remember cookie should expire five days from this request',
        );

        $encrypter = $this->app['encrypter'];
        $this->assertSame(
            $encrypter->decrypt($issued->getValue(), false),
            $encrypter->decrypt($reissued->getValue(), false),
            'Reissuing must keep the same user, token, and password hash',
        );
    }

    /** @return void */
    public function test_authenticated_request_without_recaller_cookie_sets_none(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeader('Referer', config('app.url') . '/')
            ->getJson('/api/user');
        $response->assertOk();

        $recallerCookie = collect($response->headers->getCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));

        $this->assertNull($recallerCookie, 'A session without remember-me must not gain a remember cookie');
    }
}
