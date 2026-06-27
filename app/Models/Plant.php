<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlantStatus;
use App\Support\CareScheduleResolver;
use App\Support\PlantConditionResolver;
use Database\Factories\PlantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
    'location_id',
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
            'location_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'plant_tag');
    }

    /**
     * @return BelongsToMany<Equipment, $this>
     */
    public function equipment(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'equipment_plant');
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
     * @return HasMany<CareEvent, $this>
     */
    public function careEvents(): HasMany
    {
        return $this->hasMany(CareEvent::class);
    }

    /**
     * @return HasOne<CareEvent, $this>
     */
    public function latestObservationEvent(): HasOne
    {
        return $this->hasOne(CareEvent::class)->ofMany(
            ['occurred_at' => 'max', 'id' => 'max'],
            fn (Builder $query) => $query->whereHas('careEventType', fn (Builder $type) => $type->where('key', 'observation')),
        );
    }

    /**
     * @return HasMany<CareEvent, $this>
     */
    public function wateringEvents(): HasMany
    {
        return $this->careEventsOfType('watering');
    }

    /**
     * @return HasMany<CareEvent, $this>
     */
    public function fertilizingEvents(): HasMany
    {
        return $this->careEventsOfType('fertilizing');
    }

    /**
     * @return HasMany<CareEvent, $this>
     */
    public function observationEvents(): HasMany
    {
        return $this->careEventsOfType('observation');
    }

    /**
     * @return HasMany<CareEvent, $this>
     */
    public function relocationEvents(): HasMany
    {
        return $this->careEventsOfType('relocation');
    }

    /**
     * Every logged event of one type, oldest first, so the median interval and the
     * latest event both read from one eager-loaded collection.
     *
     * @return HasMany<CareEvent, $this>
     */
    private function careEventsOfType(string $key): HasMany
    {
        return $this->hasMany(CareEvent::class)
            ->whereHas('careEventType', fn (Builder $query) => $query->where('key', $key))
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    /**
     * At-a-glance condition, resolved from the latest observation and the
     * watering-due signal.
     *
     * @return array{key: string, label: string}
     */
    public function condition(): array
    {
        $observation = $this->latestObservationEvent?->observation;
        $symptoms = $observation === null ? collect() : $observation->symptoms;

        return PlantConditionResolver::resolve(
            $this->status,
            $observation?->overall_health,
            $symptoms->pluck('category')->unique()->values()->all(),
            $symptoms->pluck('key')->values()->all(),
            $this->isLikelyDry(),
        );
    }

    private function isLikelyDry(): bool
    {
        $waterings = $this->wateringEvents;

        $interval = CareScheduleResolver::intervalForType(
            $this->watering_interval_days_override,
            $waterings->map(fn (CareEvent $event): Carbon => $event->occurred_at)->all(),
        );
        if ($interval === null) {
            return false;
        }

        $lastWatered = $waterings->last()?->occurred_at;
        if ($lastWatered === null) {
            return false;
        }

        $overdueDays = $lastWatered->copy()->addDays($interval)->diffInDays(now(), false);

        return $overdueDays > max(2, $interval * 0.4);
    }
}
