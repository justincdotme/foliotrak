<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                             $id
 * @property int                             $plant_id
 * @property string                          $reminder_type
 * @property \Illuminate\Support\Carbon      $due_on
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property string                          $status
 * @property \Illuminate\Support\Carbon      $created_at
 * @property \Illuminate\Support\Carbon      $updated_at
 */
#[Fillable(['plant_id', 'reminder_type', 'due_on', 'sent_at', 'status'])]
class SentReminder extends Model
{
    /**
     * @return BelongsTo<Plant, $this>
     */
    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }
}
