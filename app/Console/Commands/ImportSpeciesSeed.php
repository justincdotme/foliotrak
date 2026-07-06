<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SpeciesCache;
use App\Support\Gbif\SpeciesRow;
use Illuminate\Console\Command;

/**
 * Loads a GBIF species seed dump so search works offline without per-query GBIF
 * calls. Pairs with the extraction step (init.sh / species:refresh-seed) that
 * produces the file. The dump is NDJSON (optionally gzipped), one species per
 * line; see ADR-0013 and docs/project for the field contract.
 */
class ImportSpeciesSeed extends Command
{
    /** @var string */
    protected $signature = 'species:import-seed {path=storage/app/seed/species.ndjson.gz} {--chunk=1000}';

    /** @var string */
    protected $description = 'Load a GBIF species seed dump (NDJSON) into the cache and search index';

    /**
     * @return integer
     */
    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        if (! is_file($path)) {
            $this->error("Seed file not found: {$path}");

            return self::FAILURE;
        }

        $gzipped = str_ends_with($path, '.gz');
        $handle  = $gzipped ? gzopen($path, 'rb') : fopen($path, 'rb');

        if ($handle === false) {
            $this->error("Could not open: {$path}");

            return self::FAILURE;
        }

        $chunkSize = max(1, (int) $this->option('chunk'));
        $buffer    = [];
        $imported  = 0;
        $skipped   = 0;

        while (! ($gzipped ? gzeof($handle) : feof($handle))) {
            $line = $gzipped ? gzgets($handle) : fgets($handle);

            if ($line === false) {
                break;
            }

            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $row = $this->toRow($line);

            if ($row === null) {
                $skipped++;

                continue;
            }

            $buffer[] = $row;

            if (count($buffer) >= $chunkSize) {
                $this->flush($buffer);
                $imported += count($buffer);
                $buffer = [];
                $this->output->write("\rImported {$imported}...");
            }
        }

        if ($buffer !== []) {
            $this->flush($buffer);
            $imported += count($buffer);
        }

        $gzipped ? gzclose($handle) : fclose($handle);

        $this->newLine();
        $this->info("Imported or updated {$imported} species; skipped {$skipped} invalid lines.");
        $this->info('Indexing into the search engine...');
        $this->call('scout:import', ['model' => SpeciesCache::class]);

        return self::SUCCESS;
    }

    /**
     * Parse a JSON line into a cache row.
     *
     * @param string $line
     *
     * @return array<string, mixed>|null
     */
    private function toRow(string $line): ?array
    {
        $data = json_decode($line, true);

        if (! is_array($data)) {
            return null;
        }

        $row = SpeciesRow::fromArray($data);

        if ($row === null) {
            return null;
        }

        $now = now()->toDateTimeString();

        return [
            'gbif_key'        => $row->gbifKey,
            'scientific_name' => $row->scientificName,
            'canonical_name'  => $row->canonicalName,
            'common_name'     => $row->commonName,
            'common_names'    => $row->commonNames !== null ? json_encode($row->commonNames) : null,
            'rank'            => $row->rank,
            'family'          => $row->family,
            'cached_at'       => $now,
            'created_at'      => $now,
            'updated_at'      => $now,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return void
     */
    private function flush(array $rows): void
    {
        SpeciesCache::upsert(
            $rows,
            ['gbif_key'],
            ['scientific_name', 'canonical_name', 'common_name', 'common_names', 'rank', 'family', 'cached_at', 'updated_at'],
        );
    }
}
