<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SpeciesCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportSpeciesSeedTest extends TestCase
{
    use RefreshDatabase;

    /** @return void */
    protected function setUp(): void
    {
        parent::setUp();

        // The seed importer must never reach GBIF (it reads a local file only).
        Http::preventStrayRequests();
    }

    /** @return void */
    public function test_imports_valid_lines_and_skips_invalid_ones(): void
    {
        $path = $this->writeSeed('test-seed.ndjson', implode("\n", [
            (string) json_encode(['gbif_key' => 2868241, 'scientific_name' => 'Monstera deliciosa Liebm.', 'canonical_name' => 'Monstera deliciosa', 'rank' => 'SPECIES', 'family' => 'Araceae']),
            'not valid json',
            (string) json_encode(['scientific_name' => 'No key, skipped']),
            (string) json_encode(['gbif_key' => 5414516, 'scientific_name' => 'Ficus lyrata Warb.']),
            '',
        ]));

        $this->artisan('species:import-seed', ['path' => $path])->assertSuccessful();

        $this->assertSame(2, SpeciesCache::query()->count());
        $this->assertDatabaseHas('species_cache', ['gbif_key' => '2868241', 'family' => 'Araceae']);
        $this->assertNotNull(SpeciesCache::query()->where('gbif_key', '2868241')->value('cached_at'));

        @unlink($path);
    }

    /** @return void */
    public function test_reads_gzip_and_is_idempotent(): void
    {
        $path = $this->writeSeed(
            'test-seed.ndjson.gz',
            (string) json_encode(['gbif_key' => 2868241, 'scientific_name' => 'Monstera deliciosa Liebm.']) . "\n",
            gzip: true,
        );

        $this->artisan('species:import-seed', ['path' => $path])->assertSuccessful();
        $this->artisan('species:import-seed', ['path' => $path])->assertSuccessful();

        // Upsert keyed on gbif_key: re-running does not duplicate.
        $this->assertSame(1, SpeciesCache::query()->where('gbif_key', '2868241')->count());

        @unlink($path);
    }

    /** @return void */
    public function test_fails_when_the_seed_file_is_missing(): void
    {
        $this->artisan('species:import-seed', ['path' => '/nonexistent/seed.ndjson.gz'])
            ->assertFailed();
    }

    /**
     * @param string  $name
     * @param string  $contents
     * @param boolean $gzip
     *
     * @return string
     */
    private function writeSeed(string $name, string $contents, bool $gzip = false): string
    {
        $path = storage_path('app/' . $name);
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $gzip ? (string) gzencode($contents) : $contents);

        return $path;
    }
}
