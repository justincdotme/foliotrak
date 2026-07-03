<?php

declare(strict_types=1);

namespace App\Support\Gbif;

use DOMDocument;
use DOMElement;
use Generator;
use Normalizer;
use RuntimeException;
use ZipArchive;

/** Fields matched by Darwin Core term, not position, because column order drifts between releases. */
class BackboneExtractor
{
    private const ACCEPTED_RANKS = ['SPECIES', 'SUBSPECIES', 'VARIETY', 'FORM', 'GENUS'];

    private const VERNACULAR_ROW_TYPE = 'vernacularname';

    private const ENGLISH_LANGUAGE_CODES = ['eng', 'en'];

    /**
     * @param  (callable(string): void)|null  $progress
     * @return int number of species written
     */
    public function extract(string $archivePath, string $outputPath, ?callable $progress = null): int
    {
        [$core, $vernacular] = $this->readDescriptor($archivePath);
        $plan = $this->planCore($core);

        $qualifying = $this->collectQualifyingKeys($archivePath, $core, $plan, $progress);

        $commonNames = $vernacular === null
            ? []
            : $this->collectCommonNames($archivePath, $vernacular, $qualifying);

        return $this->writeSeed($archivePath, $core, $plan, $commonNames, $outputPath, $progress);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>|null}
     */
    private function readDescriptor(string $archivePath): array
    {
        $xml = $this->readEntry($archivePath, 'meta.xml');

        // XXE and entity-expansion attacks both need a DTD, and a real DwC-A
        // meta.xml has none. Refusing a DOCTYPE plus LIBXML_NONET closes those
        // vectors without defusedxml, which has no stable stdlib-only analogue.
        if (stripos($xml, '<!doctype') !== false) {
            throw new RuntimeException('meta.xml carries a DOCTYPE declaration; refusing to parse it');
        }

        $dom = new DOMDocument;
        if (! @$dom->loadXML($xml, LIBXML_NONET) || $dom->documentElement === null) {
            throw new RuntimeException('could not parse meta.xml');
        }

        $core = null;
        $vernacular = null;
        foreach ($dom->documentElement->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $local = $this->localName($node->nodeName);
            if ($local === 'core') {
                $core = $node;
            } elseif ($local === 'extension'
                && $this->termLocalName($node->getAttribute('rowType')) === self::VERNACULAR_ROW_TYPE) {
                $vernacular = $node;
            }
        }

        if ($core === null) {
            throw new RuntimeException('meta.xml has no <core> element');
        }

        return [
            $this->parseSource($core, 'id'),
            $vernacular === null ? null : $this->parseSource($vernacular, 'coreid'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseSource(DOMElement $element, string $keyTag): array
    {
        $files = $this->firstChild($element, 'files');
        $location = $files === null ? null : $this->firstChild($files, 'location');
        if ($location === null || trim($location->textContent) === '') {
            throw new RuntimeException('meta.xml source is missing a <location>');
        }

        $key = $this->firstChild($element, $keyTag);
        if ($key === null || $key->getAttribute('index') === '') {
            throw new RuntimeException("meta.xml source is missing its <{$keyTag}> index");
        }

        $fields = [];
        foreach ($element->childNodes as $node) {
            if ($node instanceof DOMElement && $this->localName($node->nodeName) === 'field') {
                $term = $node->getAttribute('term');
                $index = $node->getAttribute('index');
                if ($term !== '' && $index !== '') {
                    $fields[$this->termLocalName($term)] = (int) $index;
                }
            }
        }

        return [
            'location' => trim($location->textContent),
            'separator' => $this->decodeSeparator($element->getAttribute('fieldsTerminatedBy')),
            'ignoreHeader' => (int) ($element->getAttribute('ignoreHeaderLines') ?: 0),
            'keyIndex' => (int) $key->getAttribute('index'),
            'fields' => $fields,
        ];
    }

    /**
     * @param  array<string, mixed>  $core
     * @return array<string, int|null>
     */
    private function planCore(array $core): array
    {
        $fields = $core['fields'];
        $kingdom = $fields['kingdom'] ?? null;
        $status = $fields['taxonomicstatus'] ?? null;
        $rank = $fields['taxonrank'] ?? $fields['rank'] ?? null;
        $scientific = $fields['scientificname'] ?? null;

        if ($kingdom === null || $status === null || $rank === null || $scientific === null) {
            throw new RuntimeException('meta.xml core is missing kingdom, taxonomicStatus, taxonRank, or scientificName');
        }

        return [
            'key' => $core['keyIndex'],
            'kingdom' => $kingdom,
            'status' => $status,
            'rank' => $rank,
            'scientific' => $scientific,
            'canonical' => $fields['canonicalname'] ?? null,
            'family' => $fields['family'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $core
     * @param  array<string, int|null>  $plan
     * @param  (callable(string): void)|null  $progress
     * @return array<int, true>
     */
    private function collectQualifyingKeys(string $archivePath, array $core, array $plan, ?callable $progress): array
    {
        $qualifying = [];
        $processed = 0;
        foreach ($this->iterateRows($archivePath, $core) as $row) {
            if ($progress !== null && ++$processed % 1_000_000 === 0) {
                $progress("scanned {$processed} taxa");
            }
            $match = $this->qualify($row, $plan);
            if ($match !== null) {
                $qualifying[$match[0]] = true;
            }
        }

        return $qualifying;
    }

    /**
     * @param  array<string, mixed>  $vernacular
     * @param  array<int, true>  $qualifying
     * @return array<int, list<string>>
     */
    private function collectCommonNames(string $archivePath, array $vernacular, array $qualifying): array
    {
        $nameIndex = $vernacular['fields']['vernacularname'] ?? null;
        $languageIndex = $vernacular['fields']['language'] ?? null;
        if ($nameIndex === null || $languageIndex === null) {
            return [];
        }

        $commonNames = [];
        $seen = [];
        foreach ($this->iterateRows($archivePath, $vernacular) as $row) {
            if (! in_array(strtolower(trim($this->cell($row, $languageIndex))), self::ENGLISH_LANGUAGE_CODES, true)) {
                continue;
            }
            $key = $this->toKey($this->cell($row, $vernacular['keyIndex']));
            if ($key === null || ! isset($qualifying[$key])) {
                continue;
            }
            $raw = $this->normalize($this->cell($row, $nameIndex));
            if ($raw === '') {
                continue;
            }

            // Some TAXREF entries pack multiple names comma-separated into one field.
            $parts = array_map('trim', explode(',', $raw));
            foreach ($parts as $name) {
                $name = $this->normalize($name);
                if ($name === '') {
                    continue;
                }
                $dedup = strtolower($name);
                if (isset($seen[$key][$dedup])) {
                    continue;
                }
                $seen[$key][$dedup] = true;
                $commonNames[$key][] = $name;
            }
        }

        return $commonNames;
    }

    /**
     * @param  array<string, mixed>  $core
     * @param  array<string, int|null>  $plan
     * @param  array<int, list<string>>  $commonNames
     * @param  (callable(string): void)|null  $progress
     */
    private function writeSeed(string $archivePath, array $core, array $plan, array $commonNames, string $outputPath, ?callable $progress): int
    {
        $directory = dirname($outputPath);
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("could not create output directory: {$directory}");
        }

        $out = gzopen($outputPath, 'wb9');
        if ($out === false) {
            throw new RuntimeException("could not open output for writing: {$outputPath}");
        }

        $written = 0;
        try {
            foreach ($this->iterateRows($archivePath, $core) as $row) {
                $match = $this->qualify($row, $plan);
                if ($match === null) {
                    continue;
                }
                [$key, $rank] = $match;

                $scientificName = $this->normalize($this->cell($row, $plan['scientific']));
                if ($scientificName === '') {
                    continue;
                }

                $canonical = $plan['canonical'] !== null
                    ? ($this->normalize($this->cell($row, $plan['canonical'])) ?: null)
                    : null;

                $names = $commonNames[$key] ?? null;

                $family = $plan['family'] !== null
                    ? ($this->normalize($this->cell($row, $plan['family'])) ?: null)
                    : null;

                $speciesRow = new SpeciesRow(
                    gbifKey: (string) $key,
                    scientificName: $scientificName,
                    canonicalName: $canonical,
                    commonName: $names[0] ?? null,
                    commonNames: $names,
                    rank: $rank,
                    family: $family,
                );

                gzwrite($out, json_encode($speciesRow->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
                if ($progress !== null && ++$written % 100_000 === 0) {
                    $progress("wrote {$written} species");
                }
            }
        } finally {
            gzclose($out);
        }

        return $written;
    }

    /**
     * @param  list<string>  $row
     * @param  array<string, int|null>  $plan
     * @return array{0: int, 1: string}|null
     */
    private function qualify(array $row, array $plan): ?array
    {
        if (strtoupper(trim($this->cell($row, $plan['kingdom']))) !== 'PLANTAE') {
            return null;
        }
        if (strtoupper(trim($this->cell($row, $plan['status']))) !== 'ACCEPTED') {
            return null;
        }
        $rank = strtoupper(trim($this->cell($row, $plan['rank'])));
        if (! in_array($rank, self::ACCEPTED_RANKS, true)) {
            return null;
        }
        $key = $this->toKey($this->cell($row, $plan['key']));
        if ($key === null) {
            return null;
        }

        return [$key, $rank];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return Generator<int, list<string>>
     */
    private function iterateRows(string $archivePath, array $source): Generator
    {
        $stream = @fopen('zip://'.$archivePath.'#'.$source['location'], 'rb');
        if ($stream === false) {
            throw new RuntimeException("could not stream archive entry: {$source['location']}");
        }

        try {
            $lineNumber = 0;
            while (($line = fgets($stream)) !== false) {
                if ($lineNumber++ < $source['ignoreHeader']) {
                    continue;
                }
                $line = rtrim($line, "\r\n");
                if ($line !== '') {
                    yield explode($source['separator'], $line);
                }
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  list<string>  $row
     */
    private function cell(array $row, ?int $index): string
    {
        if ($index === null || $index >= count($row)) {
            return '';
        }

        return $row[$index];
    }

    private function toKey(string $raw): ?int
    {
        $raw = trim($raw);

        return ctype_digit($raw) ? (int) $raw : null;
    }

    private function normalize(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $normalized = Normalizer::normalize($value, Normalizer::FORM_C);

        return trim($normalized === false ? $value : $normalized);
    }

    private function decodeSeparator(string $raw): string
    {
        if ($raw === '') {
            return "\t";
        }

        return str_replace(['\\t', '\\n', '\\r'], ["\t", "\n", "\r"], $raw);
    }

    private function readEntry(string $archivePath, string $name): string
    {
        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException("could not open archive: {$archivePath}");
        }
        $contents = $zip->getFromName($name);
        $zip->close();
        if ($contents === false) {
            throw new RuntimeException("archive is missing {$name}");
        }

        return $contents;
    }

    private function localName(string $nodeName): string
    {
        $colon = strpos($nodeName, ':');

        return strtolower($colon === false ? $nodeName : substr($nodeName, $colon + 1));
    }

    private function termLocalName(string $term): string
    {
        $term = trim($term);
        $slash = strrpos($term, '/');
        if ($slash !== false) {
            $term = substr($term, $slash + 1);
        }

        return strtolower($term);
    }

    private function firstChild(DOMElement $element, string $local): ?DOMElement
    {
        foreach ($element->childNodes as $node) {
            if ($node instanceof DOMElement && $this->localName($node->nodeName) === $local) {
                return $node;
            }
        }

        return null;
    }
}
