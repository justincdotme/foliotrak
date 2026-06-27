<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private const SAMPLE_KEY = 'uQiRzpo4DXghDmr9QzzfQu27cmVRsG';

    public function test_settings_require_authentication(): void
    {
        $this->getJson('/api/settings')->assertUnauthorized();
        $this->patchJson('/api/settings', ['pushover_user_key' => self::SAMPLE_KEY])->assertUnauthorized();
    }

    public function test_get_returns_the_users_pushover_key(): void
    {
        Sanctum::actingAs(User::factory()->create(['pushover_user_key' => self::SAMPLE_KEY]));

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertExactJson(['data' => ['pushover_user_key' => self::SAMPLE_KEY, 'temperature_unit' => 'F']]);
    }

    public function test_patch_sets_and_persists_the_pushover_key(): void
    {
        $user = User::factory()->create(['pushover_user_key' => null]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/settings', ['pushover_user_key' => self::SAMPLE_KEY])
            ->assertOk()
            ->assertJsonPath('data.pushover_user_key', self::SAMPLE_KEY);

        $this->assertSame(self::SAMPLE_KEY, $user->refresh()->pushover_user_key);
    }

    public function test_patch_clears_the_pushover_key_with_null(): void
    {
        $user = User::factory()->create(['pushover_user_key' => self::SAMPLE_KEY]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/settings', ['pushover_user_key' => null])
            ->assertOk()
            ->assertJsonPath('data.pushover_user_key', null);

        $this->assertNull($user->refresh()->pushover_user_key);
    }

    #[DataProvider('malformedKeyCases')]
    public function test_patch_rejects_a_malformed_pushover_key(string $key): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/settings', ['pushover_user_key' => $key])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('pushover_user_key');
    }

    /**
     * The Pushover channel rejects any key that is not exactly 30 alphanumeric
     * characters, so the API must reject the same before it reaches the queue.
     *
     * @return iterable<string, array{string}>
     */
    public static function malformedKeyCases(): iterable
    {
        yield 'too long' => [str_repeat('a', 65)];
        yield 'one short of thirty' => [str_repeat('a', 29)];
        yield 'one over thirty' => [str_repeat('a', 31)];
        yield 'thirty chars but non-alphanumeric' => [str_repeat('a', 29).'-'];
    }
}
