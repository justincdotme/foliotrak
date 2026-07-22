<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

/**
 * @property int                             $id
 * @property string                          $gbif_key
 * @property string                          $scientific_name
 * @property string|null                     $canonical_name
 * @property string|null                     $common_name
 * @property array<int, string>|null         $common_names
 * @property string|null                     $rank
 * @property string|null                     $family
 * @property array<string, mixed>|null       $payload
 * @property \Illuminate\Support\Carbon|null $cached_at
 * @property \Illuminate\Support\Carbon      $created_at
 * @property \Illuminate\Support\Carbon      $updated_at
 */
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

    /** @var string Table name */
    protected $table = 'species_cache';

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
            'gbif_key'        => $this->gbif_key,
            'scientific_name' => $this->scientific_name,
            'canonical_name'  => $this->canonical_name,
            'common_name'     => $this->common_name,
            'common_names'    => $this->common_names,
            'rank'            => $this->rank,
            'family'          => $this->family,
            'cached_at'       => $this->cached_at,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'common_names' => 'array',
            'payload'      => 'array',
            'cached_at'    => 'datetime',
        ];
    }
}
