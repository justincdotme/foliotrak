<?php

declare(strict_types=1);

namespace App\Support\Gbif;

use DateTimeInterface;
use Illuminate\Support\Carbon;

final readonly class SpeciesRow
{
    /**
     * @param string                    $gbifKey
     * @param string                    $scientificName
     * @param string|null               $canonicalName
     * @param string|null               $commonName
     * @param list<string>|null         $commonNames
     * @param string|null               $rank
     * @param string|null               $family
     * @param array<string, mixed>|null $payload
     * @param Carbon|null               $cachedAt
     */
    public function __construct(
        public string $gbifKey,
        public string $scientificName,
        public ?string $canonicalName = null,
        public ?string $commonName = null,
        public ?array $commonNames = null,
        public ?string $rank = null,
        public ?string $family = null,
        public ?array $payload = null,
        public ?Carbon $cachedAt = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     *
     * @return self|null
     */
    public static function fromArray(array $data): ?self
    {
        $key  = $data['gbif_key'] ?? null;
        $name = $data['scientific_name'] ?? null;

        if ($key === null || ! is_string($name) || $name === '') {
            return null;
        }

        $commonNames = $data['common_names'] ?? null;
        $cachedAt    = $data['cached_at'] ?? null;

        if (is_string($cachedAt) && $cachedAt !== '') {
            $cachedAt = Carbon::parse($cachedAt);
        } elseif ($cachedAt instanceof DateTimeInterface) {
            $cachedAt = Carbon::instance($cachedAt);
        } else {
            $cachedAt = null;
        }

        return new self(
            gbifKey: (string) $key,
            scientificName: $name,
            canonicalName: $data['canonical_name'] ?? null,
            commonName: $data['common_name'] ?? null,
            commonNames: is_array($commonNames) ? array_values($commonNames) : null,
            rank: $data['rank'] ?? null,
            family: $data['family'] ?? null,
            payload: isset($data['payload']) && is_array($data['payload']) ? $data['payload'] : null,
            cachedAt: $cachedAt,
        );
    }

    /**
     * The seven API-facing fields. Excludes payload (persistence concern) and
     * cachedAt (staleness concern).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'gbif_key'        => $this->gbifKey,
            'scientific_name' => $this->scientificName,
            'canonical_name'  => $this->canonicalName,
            'common_name'     => $this->commonName,
            'common_names'    => $this->commonNames,
            'rank'            => $this->rank,
            'family'          => $this->family,
        ];
    }
}
