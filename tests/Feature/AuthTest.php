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
}
