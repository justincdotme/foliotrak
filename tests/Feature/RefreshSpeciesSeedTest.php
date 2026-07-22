<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class RefreshSpeciesSeedTest extends TestCase
{
    /** @var string */
    private string $workdir;

    /** @return void */
    protected function setUp(): void
    {
        parent::setUp();

        // The build downloads from a URL; a local --source must never touch the network.
        Http::preventStrayRequests();

        $this->workdir = sys_get_temp_dir() . '/refresh-seed-' . bin2hex(random_bytes(6));
        mkdir($this->workdir, 0775, true);
    }

    /** @return void */
    protected function tearDown(): void
    {
        array_map('unlink', glob($this->workdir . '/*') ?: []);
        @rmdir($this->workdir);

        parent::tearDown();
    }

    /** @return void */
    public function test_builds_the_seed_from_a_local_archive(): void
    {
        $output = $this->workdir . '/species.ndjson.gz';

        $this->artisan('species:refresh-seed', [
            '--source' => $this->buildArchive(),
            '--output' => $output,
            '--fresh'  => true,
        ])->assertSuccessful();

        $this->assertFileExists($output);
        $records = $this->readOutput($output);
        $this->assertSame('Monstera deliciosa Liebm.', $records[2868241]['scientific_name']);
    }

    /** @return void */
    public function test_reuses_a_recent_seed_without_rebuilding(): void
    {
        $output = $this->workdir . '/species.ndjson.gz';
        file_put_contents($output, gzencode('untouched'));

        $this->artisan('species:refresh-seed', [
            '--source' => $this->buildArchive(),
            '--output' => $output,
        ])->assertSuccessful();

        // No --fresh and the file is recent, so the existing file is left as-is.
        $this->assertSame('untouched', gzdecode((string) file_get_contents($output)));
    }

    /** @return void */
    public function test_fails_when_a_local_source_is_missing(): void
    {
        $this->artisan('species:refresh-seed', [
            '--source' => $this->workdir . '/nope.zip',
            '--output' => $this->workdir . '/out.ndjson.gz',
            '--fresh'  => true,
        ])->assertFailed();
    }

    /**
     * @return string
     */
    private function buildArchive(): string
    {
        $meta = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <archive xmlns="http://rs.tdwg.org/dwc/text/">
          <core fieldsTerminatedBy="\t" ignoreHeaderLines="0" rowType="http://rs.tdwg.org/dwc/terms/Taxon">
            <files><location>Taxon.tsv</location></files>
            <id index="0"/>
            <field index="0" term="http://rs.tdwg.org/dwc/terms/taxonID"/>
            <field index="1" term="http://rs.tdwg.org/dwc/terms/taxonomicStatus"/>
            <field index="2" term="http://rs.tdwg.org/dwc/terms/kingdom"/>
            <field index="3" term="http://rs.tdwg.org/dwc/terms/scientificName"/>
            <field index="4" term="http://rs.tdwg.org/dwc/terms/taxonRank"/>
          </core>
        </archive>
        XML;

        $archive = $this->workdir . '/backbone.zip';
        $zip     = new ZipArchive;
        $zip->open($archive, ZipArchive::CREATE);
        $zip->addFromString('meta.xml', $meta);
        $zip->addFromString('Taxon.tsv', "2868241\taccepted\tPlantae\tMonstera deliciosa Liebm.\tspecies\n");
        $zip->close();

        return $archive;
    }

    /**
     * @param string $path
     *
     * @return array<int, array<string, mixed>>
     */
    private function readOutput(string $path): array
    {
        $handle = gzopen($path, 'rb');
        $byKey  = [];

        while (! gzeof($handle)) {
            $line = trim((string) gzgets($handle));

            if ($line !== '') {
                $record                     = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $byKey[$record['gbif_key']] = $record;
            }
        }
        gzclose($handle);

        return $byKey;
    }
}
