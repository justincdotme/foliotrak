<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CareEventRuleSets;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CareEventRuleSetsTest extends TestCase
{
    /**
     * @param  list<string>  $occurredAtCreateRules
     */
    #[DataProvider('typeCases')]
    public function test_rule_sets_compose_correctly_per_type(
        string $type,
        array $occurredAtCreateRules,
        ?string $requiredField,
        string $boundField,
        string $boundToken,
    ): void {
        $createRules = CareEventRuleSets::create($type);
        $updateRules = CareEventRuleSets::update($type);

        $this->assertEqualsCanonicalizing($occurredAtCreateRules, $createRules['occurred_at']);
        $this->assertContains('sometimes', $updateRules['occurred_at']);

        if ($requiredField !== null) {
            $this->assertContains('required', $createRules[$requiredField]);
        }

        $this->assertContains($boundToken, $createRules[$boundField]);
        $this->assertContains($boundToken, $updateRules[$boundField]);
    }

    /**
     * @return iterable<string, array{string, list<string>, string|null, string, string}>
     */
    public static function typeCases(): iterable
    {
        // Every type but relocation requires occurred_at on create; relocation defaults
        // it to now server-side, so it stays nullable instead.
        yield 'watering' => ['watering', ['required', 'date'], null, 'amount_ml', 'max:4294967295'];
        yield 'fertilizing' => ['fertilizing', ['required', 'date'], 'fertilizer_form_id', 'npk_n', 'max:999.99'];
        yield 'repotting' => ['repotting', ['required', 'date'], null, 'pot_size_value', 'max:9999.9'];
        yield 'observation' => ['observation', ['required', 'date'], null, 'leaf_size_mm', 'max:99999.9'];
        yield 'relocation' => ['relocation', ['nullable', 'date'], 'to_location_id', 'to_location_id', 'integer'];
    }

    public function test_update_relocation_includes_from_location_id(): void
    {
        $rules = CareEventRuleSets::update('relocation');

        $this->assertArrayHasKey('from_location_id', $rules);
        $this->assertContains('sometimes', $rules['from_location_id']);
    }
}
