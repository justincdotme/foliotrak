<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Plant;
use App\Support\Care\CareDue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A dashboard due entry: the cross-plant list is the one surface that needs
 * plant identity next to the due state (FOL-72).
 *
 * @mixin CareDue
 */
class DueForCareResource extends JsonResource
{
    /**
     * @param Plant   $plant
     * @param CareDue $due
     */
    public function __construct(private readonly Plant $plant, CareDue $due)
    {
        parent::__construct($due);
    }

    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'plant_id'        => $this->plant->id,
            'common_name'     => $this->plant->common_name,
            'scientific_name' => $this->plant->scientific_name,
            'status'          => $this->status->value,
            'due_date'        => $this->dueDate->format('Y-m-d'),
            'type'            => $this->type->value,
            'daysLeft'        => $this->daysLeft,
            'interval'        => $this->intervalDays,
        ];
    }
}
