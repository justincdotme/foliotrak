<?php

declare(strict_types=1);

namespace App\Support\Gbif;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Keeps the household's shared IP off GBIF's throttle list by capping and backing
 * off outbound calls locally rather than reacting after a block (ADR-0011). Uses
 * `/species/match` for scientific name correction (ADR-0013) and `/species/search`
 * for common name resolution when match returns nothing.
 */
class GbifClient
{
    /** @var string */
    private string $baseUrl;

    /** @var string */
    private string $userAgent;

    /** @var integer */
    private int $timeout;

    /** @var integer */
    private int $matchMinConfidence;

    /** @var integer */
    private int $throttleMaxAttempts;

    /** @var integer */
    private int $throttleDecaySeconds;

    /** @var integer */
    private int $breakerBaseCooldown;

    /** @var integer */
    private int $breakerMaxCooldown;

    /** @var string */
    private string $host;

    /** @return void */
    public function __construct()
    {
        $this->baseUrl              = rtrim((string) config('services.gbif.base_url'), '/');
        $this->userAgent            = (string) config('services.gbif.user_agent');
        $this->timeout              = (int) config('services.gbif.timeout');
        $this->matchMinConfidence   = (int) config('services.gbif.match_min_confidence');
        $this->throttleMaxAttempts  = (int) config('services.gbif.throttle.max_attempts');
        $this->throttleDecaySeconds = (int) config('services.gbif.throttle.decay_seconds');
        $this->breakerBaseCooldown  = (int) config('services.gbif.breaker.base_cooldown_seconds');
        $this->breakerMaxCooldown   = (int) config('services.gbif.breaker.max_cooldown_seconds');
        $this->host                 = (string) (parse_url($this->baseUrl, PHP_URL_HOST) ?: 'gbif');
    }

    /**
     * Fuzzy-match a query to GBIF taxa: the best match plus any alternatives,
     * normalized to cache rows. Empty means GBIF answered but nothing matched
     * well enough; null means the call was refused or did not return a success
     * (breaker open, throttle saturated, any non-2xx, or a timeout), so the
     * caller serves cache and leaves the query uncached for a later retry.
     *
     * @param string $query
     *
     * @return list<SpeciesRow>|null
     */
    public function lookup(string $query): ?array
    {
        $response = $this->guardedGet('/species/match', [
            'name'    => $query,
            'strict'  => 'false',
            'verbose' => 'true',
        ]);

        if ($response === null) {
            return null;
        }

        $body = $response->json();

        return is_array($body) ? $this->extractMatches($body) : [];
    }

    /**
     * Search GBIF by vernacular (common) name. Called as a fallback when
     * `/species/match` returns nothing, since match is a scientific name endpoint
     * that cannot resolve common names.
     *
     * @param string $query
     *
     * @return list<SpeciesRow>|null
     */
    public function searchCommonName(string $query): ?array
    {
        $response = $this->guardedGet('/species/search', [
            'q'     => $query,
            'rank'  => 'SPECIES',
            'limit' => 5,
        ]);

        if ($response === null) {
            return null;
        }

        $body    = $response->json();
        $results = is_array($body) ? ($body['results'] ?? []) : [];

        if (! is_array($results)) {
            return [];
        }

        $seen    = [];
        $records = [];

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }
            $record = $this->normalizeSearchResult($result);

            if ($record !== null && ! isset($seen[$record->gbifKey])) {
                $seen[$record->gbifKey] = true;
                $records[]              = $record;
            }
        }

        return $records;
    }

    /**
     * Execute a GET request with throttle and circuit breaker guards.
     *
     * @param string               $path
     * @param array<string, mixed> $params
     *
     * @return Response|null
     */
    private function guardedGet(string $path, array $params): ?Response
    {
        if ($this->breakerIsOpen()) {
            return null;
        }

        if (RateLimiter::tooManyAttempts($this->throttleKey(), $this->throttleMaxAttempts)) {
            return null;
        }

        RateLimiter::increment($this->throttleKey(), $this->throttleDecaySeconds);

        try {
            $response = Http::withHeaders(['User-Agent' => $this->userAgent])
                ->timeout($this->timeout)
                ->get($this->baseUrl . $path, $params)
                ->throw();
        } catch (Throwable $e) {
            Log::warning('GBIF request failed; serving cache.', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            $this->recordFailure();

            return null;
        }

        $this->recordSuccess();

        return $response;
    }

    /**
     * Extract the best match and alternatives from a match response.
     *
     * @param array<string, mixed> $body
     *
     * @return list<SpeciesRow>
     */
    private function extractMatches(array $body): array
    {
        $candidates = [$body];

        $alternatives = $body['alternatives'] ?? null;

        if (is_array($alternatives)) {
            foreach ($alternatives as $alternative) {
                if (is_array($alternative)) {
                    $candidates[] = $alternative;
                }
            }
        }

        $records = [];

        foreach ($candidates as $candidate) {
            $record = $this->normalizeMatch($candidate);

            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * GBIF's match and search endpoints use different field names for the same
     * synonym-to-accepted resolution.
     *
     * @param array<string, mixed> $record
     * @param string               $statusField
     * @param string               $keyField
     * @param string               $acceptedKeyField
     *
     * @return array{key: mixed, scientificName: string}|null
     */
    private function resolveSynonym(array $record, string $statusField, string $keyField, string $acceptedKeyField): ?array
    {
        $isSynonym = ($record[$statusField] ?? null) === 'SYNONYM';

        $key = $isSynonym && isset($record[$acceptedKeyField])
            ? $record[$acceptedKeyField]
            : ($record[$keyField] ?? null);

        if ($key === null) {
            return null;
        }

        $scientificName = $isSynonym && isset($record['accepted'])
            ? $record['accepted']
            : ($record['scientificName'] ?? '');

        return ['key' => $key, 'scientificName' => (string) $scientificName];
    }

    /**
     * Keep only confident species-level name matches, resolving a synonym to its
     * accepted name so the cache holds the current taxonomy.
     *
     * @param array<string, mixed> $match
     *
     * @return SpeciesRow|null
     */
    private function normalizeMatch(array $match): ?SpeciesRow
    {
        $matchType = $match['matchType'] ?? 'NONE';

        if ($matchType !== 'EXACT' && $matchType !== 'FUZZY') {
            return null;
        }

        if ((int) ($match['confidence'] ?? 0) < $this->matchMinConfidence) {
            return null;
        }

        $resolved = $this->resolveSynonym($match, 'status', 'usageKey', 'acceptedUsageKey');

        if ($resolved === null) {
            return null;
        }

        return new SpeciesRow(
            gbifKey: (string) $resolved['key'],
            scientificName: $resolved['scientificName'],
            canonicalName: $match['canonicalName'] ?? null,
            rank: $match['rank'] ?? null,
            family: $match['family'] ?? null,
            payload: $match,
        );
    }

    /**
     * The `/species/search` response uses `key` not `usageKey`, `taxonomicStatus`
     * not `status`, and includes vernacular names inline.
     *
     * @param array<string, mixed> $result
     *
     * @return SpeciesRow|null
     */
    private function normalizeSearchResult(array $result): ?SpeciesRow
    {
        if (($result['rank'] ?? null) !== 'SPECIES') {
            return null;
        }

        $resolved = $this->resolveSynonym($result, 'taxonomicStatus', 'key', 'acceptedKey');

        if ($resolved === null) {
            return null;
        }

        $englishNames = [];

        foreach ($result['vernacularNames'] ?? [] as $entry) {
            if (is_array($entry) && in_array($entry['language'] ?? '', ['eng', 'en'], true)) {
                $name = $entry['vernacularName'] ?? null;

                if ($name !== null && $name !== '') {
                    $englishNames[] = $name;
                }
            }
        }
        $englishNames = array_values(array_unique($englishNames));

        return new SpeciesRow(
            gbifKey: (string) $resolved['key'],
            scientificName: $resolved['scientificName'],
            canonicalName: $result['canonicalName'] ?? null,
            commonName: $englishNames[0] ?? null,
            commonNames: $englishNames !== [] ? $englishNames : null,
            rank: $result['rank'],
            family: $result['family'] ?? null,
            payload: $result,
        );
    }

    /**
     * @return string
     */
    private function throttleKey(): string
    {
        return 'gbif-match:' . $this->host;
    }

    /**
     * @return string
     */
    private function breakerKey(): string
    {
        return 'gbif-breaker:' . $this->host;
    }

    /**
     * @return string
     */
    private function breakerFailuresKey(): string
    {
        return 'gbif-breaker-failures:' . $this->host;
    }

    /**
     * @return boolean
     */
    private function breakerIsOpen(): bool
    {
        return Cache::has($this->breakerKey());
    }

    /** @return void */
    private function recordFailure(): void
    {
        $failures = (int) Cache::get($this->breakerFailuresKey(), 0) + 1;
        $cooldown = (int) min(
            $this->breakerBaseCooldown * (2 ** ($failures - 1)),
            $this->breakerMaxCooldown,
        );

        // Outlive the open window so the next failure escalates the cooldown
        // rather than restarting the backoff. A success clears it (recordSuccess).
        Cache::put($this->breakerFailuresKey(), $failures, $this->breakerMaxCooldown * 2);
        Cache::put($this->breakerKey(), true, $cooldown);
    }

    /** @return void */
    private function recordSuccess(): void
    {
        Cache::forget($this->breakerKey());
        Cache::forget($this->breakerFailuresKey());
    }
}
