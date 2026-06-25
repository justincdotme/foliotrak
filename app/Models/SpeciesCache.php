<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Database\Factories\SpeciesCacheFactory;

#[Fillable([
    'gbif_key',
    'scientific_name',
    'canonical_name',
    'common_name',
    'rank',
    'family',
    'payload',
])]
class SpeciesCache extends Model
{
    /** @use HasFactory<SpeciesCacheFactory> */
    use HasFactory;

    protected $table = 'species_cache';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
