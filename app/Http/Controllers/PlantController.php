<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RecordEquipmentChange;
use App\Actions\RecordRelocation;
use App\Http\Requests\StorePlantRequest;
use App\Http\Requests\UpdatePlantRequest;
use App\Http\Resources\PlantResource;
use App\Models\Plant;
use App\Support\Care\CareDue;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PlantController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display plus the inputs the derived condition reads, eager-loaded so the
     * list does not fan out into per-plant condition queries.
     *
     * @var list<string>
     */
    private const RELATIONS = [
        'tags',
        'equipment',
        'sensors',
        'coverPhoto',
        'location',
        'latestObservationEvent.observation.symptoms',
        'wateringEvents',
        'fertilizingEvents',
    ];

    /**
     * @param Request $request
     *
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Plant::class);

        $plants = Plant::query()
            ->with(self::RELATIONS)
            ->when($request->filled('tag'), fn ($query) => $query->whereHas(
                'tags',
                fn ($tags) => $tags->whereKey($request->integer('tag')),
            ))
            ->latest()
            ->get();

        return PlantResource::collection(
            $plants->map(fn (Plant $plant) => $this->present($plant)),
        );
    }

    /**
     * @param StorePlantRequest     $request
     * @param RecordEquipmentChange $recordEquipmentChange
     *
     * @return JsonResponse
     */
    public function store(StorePlantRequest $request, RecordEquipmentChange $recordEquipmentChange): JsonResponse
    {
        $this->authorize('create', Plant::class);

        $plant = DB::transaction(function () use ($request, $recordEquipmentChange): Plant {
            $plant = Plant::create($request->safe()->except(['tag_ids', 'equipment_ids', 'sensor_ids']));
            $this->syncTags($request, $plant);
            $this->syncEquipment($request, $plant, $recordEquipmentChange);
            $this->syncSensors($request, $plant);

            return $plant;
        });

        return $this->present($plant->load(self::RELATIONS))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * @param Plant $plant
     *
     * @return PlantResource
     */
    public function show(Plant $plant): PlantResource
    {
        $this->authorize('view', $plant);

        return $this->present($plant->load(self::RELATIONS));
    }

    /**
     * @param UpdatePlantRequest    $request
     * @param Plant                 $plant
     * @param RecordRelocation      $recordRelocation
     * @param RecordEquipmentChange $recordEquipmentChange
     *
     * @return PlantResource
     */
    public function update(UpdatePlantRequest $request, Plant $plant, RecordRelocation $recordRelocation, RecordEquipmentChange $recordEquipmentChange): PlantResource
    {
        $this->authorize('update', $plant);

        DB::transaction(function () use ($request, $plant, $recordRelocation, $recordEquipmentChange): void {
            $plant->update($request->safe()->except(['tag_ids', 'equipment_ids', 'sensor_ids', 'location_id']));

            if ($request->has('location_id')) {
                $recordRelocation->record($plant, $request->locationId(), userId: $request->user()?->id);
            }

            $this->syncTags($request, $plant);
            $this->syncEquipment($request, $plant, $recordEquipmentChange);
            $this->syncSensors($request, $plant);
        });

        return $this->present($plant->load(self::RELATIONS));
    }

    /**
     * @param Plant $plant
     *
     * @return Response
     */
    public function destroy(Plant $plant): Response
    {
        $this->authorize('delete', $plant);

        $plant->delete();

        return response()->noContent();
    }

    /**
     * Sync tags only when the caller sent the key, so an unrelated update never
     * silently detaches a plant's tags.
     *
     * @param Request $request
     * @param Plant   $plant
     *
     * @return void
     */
    private function syncTags(Request $request, Plant $plant): void
    {
        if ($request->has('tag_ids')) {
            $plant->tags()->sync($request->collect('tag_ids')->all());
        }
    }

    /**
     * @param Request               $request
     * @param Plant                 $plant
     * @param RecordEquipmentChange $recordEquipmentChange
     *
     * @return void
     */
    private function syncEquipment(Request $request, Plant $plant, RecordEquipmentChange $recordEquipmentChange): void
    {
        if ($request->has('equipment_ids')) {
            $recordEquipmentChange->record($plant, $request->collect('equipment_ids')->all(), $request->user()?->id);
        }
    }

    /**
     * @param Request $request
     * @param Plant   $plant
     *
     * @return void
     */
    private function syncSensors(Request $request, Plant $plant): void
    {
        if ($request->has('sensor_ids')) {
            $plant->sensors()->sync($request->collect('sensor_ids')->all());
        }
    }

    /**
     * @param Plant $plant
     *
     * @return PlantResource
     */
    private function present(Plant $plant): PlantResource
    {
        return new PlantResource($plant, $plant->condition(), CareDue::forPlant($plant));
    }
}
