<?php

declare(strict_types=1);

namespace App\Support\Gbif;

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
    private string $baseUrl;

    private string $userAgent;

    private int $timeout;

    private int $matchMinConfidence;

    private int $throttleMaxAttempts;

    private int $throttleDecaySeconds;

    private int $breakerBaseCooldown;

    private int $breakerMaxCooldown;

    private string $host;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.gbif.base_url'), '/');
        $this->userAgent = (string) config('services.gbif.user_agent');
        $this->timeout = (int) config('services.gbif.timeout');
        $this->matchMinConfidence = (int) config('services.gbif.match_min_confidence');
        $this->throttleMaxAttempts = (int) config('services.gbif.throttle.max_attempts');
        $this->throttleDecaySeconds = (int) config('services.gbif.throttle.decay_seconds');
        $this->breakerBaseCooldown = (int) config('services.gbif.breaker.base_cooldown_seconds');
        $this->breakerMaxCooldown = (int) config('services.gbif.breaker.max_cooldown_seconds');
        $this->host = (string) (parse_url($this->baseUrl, PHP_URL_HOST) ?: 'gbif');
    }

    /**
     * Fuzzy-match a query to GBIF taxa: the best match plus any alternatives,
     * normalized to cache rows. Empty means GBIF answered but nothing matched
     * well enough; null means the call was refused or did not return a success
     * (breaker open, throttle saturated, any non-2xx, or a timeout), so the
     * caller serves cache and leaves the query uncached for a later retry.
     *
     * @return list<array<string, mixed>>|null
     */
    public function lookup(string $query): ?array
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
                ->get($this->baseUrl.'/species/match', [
                    'name' => $query,
                    'strict' => 'false',
                    'verbose' => 'true',
                ])
                ->throw();
        } catch (Throwable $e) {
            // Record the root cause so a degraded (503) search is diagnosable.
            Log::warning('GBIF lookup failed; serving cache.', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            $this->recordFailure();

            return null;
        }

        $this->recordSuccess();

        $body = $response->json();

        return is_array($body) ? $this->extractMatches($body) : [];
    }

    /**
     * Search GBIF by vernacular (common) name. Called as a fallback when
     * `/species/match` returns nothing, since match is a scientific name endpoint
     * that cannot resolve common names.
     *
     * @return list<array<string, mixed>>|null
     */
    public function searchCommonName(string $query): ?array
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
                ->get($this->baseUrl.'/species/search', [
                    'q' => $query,
                    'rank' => 'SPECIES',
                    'limit' => 5,
                ])
                ->throw();
        } catch (Throwable $e) {
            // Record the root cause so a degraded search is diagnosable.
            Log::warning('GBIF search failed; serving cache.', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            $this->recordFailure();

            return null;
        }

        $this->recordSuccess();

        $body = $response->json();
        $results = is_array($body) ? ($body['results'] ?? []) : [];

        if (! is_array($results)) {
            return [];
        }

        $seen = [];
        $records = [];
        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }
            $record = $this->normalizeSearchResult($result);
            if ($record !== null && ! isset($seen[$record['gbif_key']])) {
                $seen[$record['gbif_key']] = true;
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<array<string, mixed>>
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
     * Keep only confident species-level name matches, resolving a synonym to its
     * accepted name so the cache holds the current taxonomy.
     *
     * @param  array<string, mixed>  $match
     * @return array<string, mixed>|null
     */
    private function normalizeMatch(array $match): ?array
    {
        $matchType = $match['matchType'] ?? 'NONE';
        if ($matchType !== 'EXACT' && $matchType !== 'FUZZY') {
            return null;
        }

        if ((int) ($match['confidence'] ?? 0) < $this->matchMinConfidence) {
            return null;
        }

        $isSynonym = ($match['status'] ?? null) === 'SYNONYM';

        $key = $isSynonym && isset($match['acceptedUsageKey'])
            ? $match['acceptedUsageKey']
            : ($match['usageKey'] ?? null);

        if ($key === null) {
            return null;
        }

        $scientificName = $isSynonym && isset($match['accepted'])
            ? $match['accepted']
            : ($match['scientificName'] ?? '');

        return [
            'gbif_key' => (string) $key,
            'scientific_name' => (string) $scientificName,
            'canonical_name' => $match['canonicalName'] ?? null,
            'common_name' => null,
            'rank' => $match['rank'] ?? null,
            'family' => $match['family'] ?? null,
            'payload' => $match,
        ];
    }

    /**
     * The `/species/search` response uses `key` not `usageKey`, `taxonomicStatus`
     * not `status`, and includes vernacular names inline.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>|null
     */
    private function normalizeSearchResult(array $result): ?array
    {
        if (($result['rank'] ?? null) !== 'SPECIES') {
            return null;
        }

        $isSynonym = ($result['taxonomicStatus'] ?? null) === 'SYNONYM';

        $key = $isSynonym && isset($result['acceptedKey'])
            ? $result['acceptedKey']
            : ($result['key'] ?? null);

        if ($key === null) {
            return null;
        }

        $scientificName = $isSynonym && isset($result['accepted'])
            ? $result['accepted']
            : ($result['scientificName'] ?? '');

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

        return [
            'gbif_key' => (string) $key,
            'scientific_name' => (string) $scientificName,
            'canonical_name' => $result['canonicalName'] ?? null,
            'common_name' => $englishNames[0] ?? null,
            'common_names' => $englishNames !== [] ? $englishNames : null,
            'rank' => $result['rank'],
            'family' => $result['family'] ?? null,
            'payload' => $result,
        ];
    }

    private function throttleKey(): string
    {
        return 'gbif-match:'.$this->host;
    }

    private function breakerKey(): string
    {
        return 'gbif-breaker:'.$this->host;
    }

    private function breakerFailuresKey(): string
    {
        return 'gbif-breaker-failures:'.$this->host;
    }

    private function breakerIsOpen(): bool
    {
        return Cache::has($this->breakerKey());
    }

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

    private function recordSuccess(): void
    {
        Cache::forget($this->breakerKey());
        Cache::forget($this->breakerFailuresKey());
    }
}
