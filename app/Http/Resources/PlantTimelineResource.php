<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Plant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plant
 */
class PlantTimelineResource extends JsonResource
{
    /**
     * @param Plant                                                                        $plant
     * @param array<string, array<int, array{date: string, value: int|float|string|null}>> $trends
     * @param array<string, mixed>                                                         $condition
     * @param array<int, CareDue>                                                          $dueForCare
     */
    public function __construct(Plant $plant, private readonly array $trends, private readonly array $condition, private readonly array $dueForCare)
    {
        parent::__construct($plant);
    }

    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'plant'           => new PlantResource($this->resource, $this->condition, $this->dueForCare),
            'events'          => CareEventResource::collection($this->careEvents),
            'health_trend'    => $this->trends['health'],
            'weight_trend'    => $this->trends['weight'],
            'growth_trend'    => $this->trends['growth'],
            'light_trend'     => $this->trends['light'],
            'leaf_size_trend' => $this->trends['leaf_size'],
            'due_for_care'    => CareDueResource::collection($this->dueForCare),
            // The contract keeps the key present while recommendations are not yet computed.
            'recommendations' => [],
            'photos'          => PhotoResource::collection($this->photos),
        ];
    }
}
