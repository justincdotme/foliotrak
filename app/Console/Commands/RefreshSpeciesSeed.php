<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Gbif\BackboneExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Builds the species seed from the GBIF backbone: download, filter to accepted
 * plant taxa, and write the gzipped NDJSON that species:import-seed loads. Runs
 * in the app container so the appliance needs no toolchain beyond PHP (ADR-0013).
 */
class RefreshSpeciesSeed extends Command
{
    /**
     * A seed younger than this is reused rather than re-downloaded, matching the
     * species cache TTL (ADR-0014). --fresh overrides.
     */
    private const SEED_MAX_AGE_DAYS = 90;

    /** @var string */
    protected $signature = 'species:refresh-seed
        {--source= : Backbone archive URL or a local .zip path (default: the GBIF backbone)}
        {--output= : Destination for the gzipped NDJSON (default: storage/app/seed/species.ndjson.gz)}
        {--fresh : Rebuild even if a recent seed file already exists}';

    /** @var string */
    protected $description = 'Download the GBIF backbone and build the species seed (gzipped NDJSON)';

    /**
     * @param BackboneExtractor $extractor
     *
     * @return integer
     */
    public function handle(BackboneExtractor $extractor): int
    {
        // The vernacular join holds millions of taxon keys in memory at once.
        ini_set('memory_limit', '1024M');

        $output = $this->resolveOutput();

        if (! $this->option('fresh') && $this->isFresh($output)) {
            $this->info("Seed at {$output} is recent; reusing it. Pass --fresh to rebuild.");

            return self::SUCCESS;
        }

        $directory = dirname($output);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            $this->error("Could not create output directory: {$directory}");

            return self::FAILURE;
        }

        $source = (string) ($this->option('source') ?: config('services.gbif.backbone_url'));
        $isUrl  = str_starts_with($source, 'http://') || str_starts_with($source, 'https://');

        $download = null;

        try {
            if ($isUrl) {
                $download = $directory . '/.backbone-download.zip';
                $this->downloadArchive($source, $download);
                $archive = $download;
            } else {
                if (! is_file($source)) {
                    $this->error("Source archive not found: {$source}");

                    return self::FAILURE;
                }
                $archive = $source;
            }

            $startedAt = microtime(true);
            $written   = $extractor->extract($archive, $output, function (string $message): void {
                $this->output->write("\r  {$message}");
            });
            $this->newLine();
            $this->info(sprintf('Wrote %d species to %s in %.1fs.', $written, $output, microtime(true) - $startedAt));
        } catch (Throwable $error) {
            $this->error("Seed build failed: {$error->getMessage()}");

            return self::FAILURE;
        } finally {
            if ($download !== null && is_file($download)) {
                @unlink($download);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return string
     */
    private function resolveOutput(): string
    {
        $output = (string) ($this->option('output') ?: storage_path('app/seed/species.ndjson.gz'));

        if (! str_starts_with($output, '/')) {
            $output = base_path($output);
        }

        return $output;
    }

    /**
     * @param string $output
     *
     * @return boolean
     */
    private function isFresh(string $output): bool
    {
        if (! is_file($output)) {
            return false;
        }

        return (time() - (int) filemtime($output)) < self::SEED_MAX_AGE_DAYS * 86400;
    }

    /**
     * @param string $url
     * @param string $destination
     *
     * @return void
     */
    private function downloadArchive(string $url, string $destination): void
    {
        $this->info("Downloading {$url}");

        $lastPercent = -1;
        Http::withOptions([
            'sink'     => $destination,
            'progress' => function (int $downloadTotal, int $downloadedBytes) use (&$lastPercent): void {
                if ($downloadTotal <= 0) {
                    return;
                }
                $percent = (int) ($downloadedBytes * 100 / $downloadTotal);

                if ($percent !== $lastPercent && $percent % 5 === 0) {
                    $lastPercent = $percent;
                    $this->output->write("\r  {$percent}%");
                }
            },
        ])
            ->timeout(0)
            ->withHeaders(['User-Agent' => (string) config('services.gbif.user_agent')])
            ->get($url)
            ->throw();

        $this->newLine();
    }
}
