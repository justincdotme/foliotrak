<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SpeciesCacheFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

#[Fillable([
    'gbif_key',
    'scientific_name',
    'canonical_name',
    'common_name',
    'common_names',
    'rank',
    'family',
    'payload',
    'cached_at',
])]
class SpeciesCache extends Model
{
    /** @use HasFactory<SpeciesCacheFactory> */
    use HasFactory;

    use Searchable;

    protected $table = 'species_cache';

    protected function casts(): array
    {
        return [
            'common_names' => 'array',
            'payload' => 'array',
            'cached_at' => 'datetime',
        ];
    }

    /**
     * Index the full display record so search reads serve straight from
     * Meilisearch without a database round-trip per query. Only the name fields
     * are searchable (config/scout.php); the rest are stored for the response.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'gbif_key' => $this->gbif_key,
            'scientific_name' => $this->scientific_name,
            'canonical_name' => $this->canonical_name,
            'common_name' => $this->common_name,
            'common_names' => $this->common_names,
            'rank' => $this->rank,
            'family' => $this->family,
            'cached_at' => $this->cached_at,
        ];
    }
}
