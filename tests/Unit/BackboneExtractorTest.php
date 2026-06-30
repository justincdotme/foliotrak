<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Gbif\BackboneExtractor;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

class BackboneExtractorTest extends TestCase
{
    private string $workdir;

    private string $archive;

    private string $output;

    /** @var array<int, array<string, mixed>> */
    private array $byKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workdir = sys_get_temp_dir().'/backbone-test-'.bin2hex(random_bytes(6));
        mkdir($this->workdir, 0775, true);
        $this->archive = $this->workdir.'/backbone.zip';
        $this->output = $this->workdir.'/species.ndjson.gz';

        $this->buildArchive();
        (new BackboneExtractor)->extract($this->archive, $this->output);
        $this->byKey = $this->readOutput();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->workdir.'/*') ?: []);
        @rmdir($this->workdir);

        parent::tearDown();
    }

    public function test_keeps_only_accepted_plant_taxa_at_offered_ranks(): void
    {
        $this->assertSame(
            [2868241, 2769648, 7777777, 8888888, 9999999, 1111111, 1212121],
            array_keys($this->byKey),
        );
    }

    public function test_excludes_non_plant_kingdoms_even_with_an_english_name(): void
    {
        $this->assertArrayNotHasKey(2222222, $this->byKey); // Animalia
        $this->assertArrayNotHasKey(3333333, $this->byKey); // Fungi
    }

    public function test_excludes_synonyms_doubtful_and_coarse_ranks(): void
    {
        $this->assertArrayNotHasKey(4444444, $this->byKey); // synonym
        $this->assertArrayNotHasKey(5555555, $this->byKey); // doubtful
        $this->assertArrayNotHasKey(6666666, $this->byKey); // family rank
    }

    public function test_gbif_key_is_written_as_an_integer(): void
    {
        $this->assertIsInt($this->byKey[2868241]['gbif_key']);
    }

    public function test_joins_the_first_english_common_name(): void
    {
        $monstera = $this->byKey[2868241];
        $this->assertSame('Monstera deliciosa Liebm.', $monstera['scientific_name']);
        $this->assertSame('Monstera deliciosa', $monstera['canonical_name']);
        $this->assertSame('Swiss cheese plant', $monstera['common_name']);
        $this->assertSame('SPECIES', $monstera['rank']);
        $this->assertSame('Araceae', $monstera['family']);
        $this->assertSame(
            ['Swiss cheese plant', 'Split-leaf philodendron'],
            $monstera['common_names'],
        );
    }

    public function test_omits_common_name_when_no_english_name_exists(): void
    {
        $this->assertArrayNotHasKey('common_name', $this->byKey[2769648]);
        $this->assertArrayNotHasKey('common_name', $this->byKey[7777777]);
        $this->assertArrayNotHasKey('common_names', $this->byKey[2769648]);
        $this->assertArrayNotHasKey('common_names', $this->byKey[7777777]);
    }

    public function test_normalizes_rank_to_uppercase_across_offered_ranks(): void
    {
        $this->assertSame('GENUS', $this->byKey[7777777]['rank']);
        $this->assertSame('SUBSPECIES', $this->byKey[8888888]['rank']);
        $this->assertSame('VARIETY', $this->byKey[9999999]['rank']);
        $this->assertSame('FORM', $this->byKey[1111111]['rank']);
    }

    public function test_applies_nfc_normalization_to_string_values(): void
    {
        $record = $this->byKey[1212121];
        $this->assertSame('Mammillaria décipiens', $record['canonical_name']);
        $this->assertStringNotContainsString("\u{0301}", $record['scientific_name']);
    }

    public function test_refuses_a_meta_xml_carrying_a_doctype(): void
    {
        $hostile = $this->workdir.'/doctype.zip';
        $zip = new ZipArchive;
        $zip->open($hostile, ZipArchive::CREATE);
        $zip->addFromString('meta.xml', '<?xml version="1.0"?><!DOCTYPE a [<!ENTITY x "y">]><archive/>');
        $zip->addFromString('Taxon.tsv', "x\n");
        $zip->close();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DOCTYPE');
        (new BackboneExtractor)->extract($hostile, $this->workdir.'/out.ndjson.gz');
    }

    private function buildArchive(): void
    {
        // Columns are out of natural order with a header row to skip, so a
        // position-hardcoding regression fails this test.
        $meta = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <archive xmlns="http://rs.tdwg.org/dwc/text/">
          <core fieldsTerminatedBy="\t" ignoreHeaderLines="1" rowType="http://rs.tdwg.org/dwc/terms/Taxon">
            <files><location>Taxon.tsv</location></files>
            <id index="0"/>
            <field index="0" term="http://rs.tdwg.org/dwc/terms/taxonID"/>
            <field index="1" term="http://rs.tdwg.org/dwc/terms/family"/>
            <field index="2" term="http://rs.tdwg.org/dwc/terms/taxonomicStatus"/>
            <field index="3" term="http://rs.tdwg.org/dwc/terms/kingdom"/>
            <field index="4" term="http://rs.tdwg.org/dwc/terms/scientificName"/>
            <field index="5" term="http://rs.tdwg.org/dwc/terms/taxonRank"/>
            <field index="6" term="http://rs.gbif.org/terms/1.0/canonicalName"/>
          </core>
          <extension fieldsTerminatedBy="\t" ignoreHeaderLines="1" rowType="http://rs.gbif.org/terms/1.0/VernacularName">
            <files><location>VernacularName.tsv</location></files>
            <coreid index="0"/>
            <field index="1" term="http://purl.org/dc/terms/language"/>
            <field index="2" term="http://rs.tdwg.org/dwc/terms/vernacularName"/>
          </extension>
        </archive>
        XML;

        // Mixed-case enums mirror real backbone values; the decomposed accent
        // (e + U+0301) must fold to a single code point under NFC.
        $decomposed = "Mammillaria de\u{0301}cipiens";
        $taxa = [
            ['taxonID', 'family', 'taxonomicStatus', 'kingdom', 'scientificName', 'taxonRank', 'canonicalName'],
            ['2868241', 'Araceae', 'accepted', 'Plantae', 'Monstera deliciosa Liebm.', 'species', 'Monstera deliciosa'],
            ['2769648', 'Asparagaceae', 'accepted', 'Plantae', 'Dracaena trifasciata (Prain) Mabb.', 'species', 'Dracaena trifasciata'],
            ['7777777', 'Araceae', 'accepted', 'Plantae', 'Monstera Adans.', 'genus', 'Monstera'],
            ['8888888', 'Rosaceae', 'accepted', 'Plantae', 'Rosa canina subsp. dumalis', 'subspecies', 'Rosa canina dumalis'],
            ['9999999', 'Rosaceae', 'accepted', 'Plantae', 'Rosa gallica var. officinalis', 'variety', 'Rosa gallica officinalis'],
            ['1111111', 'Rosaceae', 'accepted', 'Plantae', 'Fragaria vesca f. alba', 'form', 'Fragaria vesca alba'],
            ['1212121', 'Cactaceae', 'accepted', 'Plantae', $decomposed.' K.Brandegee', 'species', $decomposed],
            ['2222222', 'Felidae', 'accepted', 'Animalia', 'Panthera leo (Linnaeus, 1758)', 'species', 'Panthera leo'],
            ['3333333', 'Agaricaceae', 'accepted', 'Fungi', 'Agaricus bisporus', 'species', 'Agaricus bisporus'],
            ['4444444', 'Asteraceae', 'synonym', 'Plantae', 'Aster synonymus', 'species', 'Aster synonymus'],
            ['5555555', 'Asteraceae', 'doubtful', 'Plantae', 'Aster dubius', 'species', 'Aster dubius'],
            ['6666666', 'Rosaceae', 'accepted', 'Plantae', 'Rosaceae', 'family', 'Rosaceae'],
        ];
        $vernacular = [
            ['coreid', 'language', 'vernacularName'],
            ['2868241', 'eng', 'Swiss cheese plant'],
            ['2868241', 'eng', 'Split-leaf philodendron'],
            ['2868241', 'spa', 'Costilla de Adan'],
            ['7777777', 'fra', 'Monstera'],
            ['2222222', 'eng', 'Lion'],
        ];

        $zip = new ZipArchive;
        $zip->open($this->archive, ZipArchive::CREATE);
        $zip->addFromString('meta.xml', $meta);
        $zip->addFromString('Taxon.tsv', $this->toTsv($taxa));
        $zip->addFromString('VernacularName.tsv', $this->toTsv($vernacular));
        $zip->close();
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function toTsv(array $rows): string
    {
        return implode("\n", array_map(static fn (array $row): string => implode("\t", $row), $rows))."\n";
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readOutput(): array
    {
        $handle = gzopen($this->output, 'rb');
        $byKey = [];
        while (! gzeof($handle)) {
            $line = trim((string) gzgets($handle));
            if ($line === '') {
                continue;
            }
            $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $byKey[$record['gbif_key']] = $record;
        }
        gzclose($handle);

        return $byKey;
    }
}
