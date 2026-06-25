<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlantStatus;
use App\Support\PlantConditionResolver;
use Database\Factories\PlantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property PlantStatus $status
 * @property Carbon|null $acquired_on
 */
#[Fillable([
    'common_name',
    'scientific_name',
    'gbif_key',
    'location',
    'acquired_on',
    'status',
    'notes',
    'watering_interval_days_override',
    'fertilizing_interval_days_override',
    'cover_photo_id',
])]
class Plant extends Model
{
    /** @use HasFactory<PlantFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => PlantStatus::Active->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => PlantStatus::class,
            'acquired_on' => 'date',
            'watering_interval_days_override' => 'integer',
            'fertilizing_interval_days_override' => 'integer',
            'cover_photo_id' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'plant_tag');
    }

    /**
     * @return HasMany<Photo, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    /**
     * @return BelongsTo<Photo, $this>
     */
    public function coverPhoto(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'cover_photo_id');
    }

    /**
     * At-a-glance condition (D24). The observation and watering-due inputs join in
     * Phase 2a once the care spine exists; until then status is the only signal.
     *
     * @return array{key: string, label: string}
     */
    public function condition(): array
    {
        return PlantConditionResolver::resolve($this->status);
    }
}
