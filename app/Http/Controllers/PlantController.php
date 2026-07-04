<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
        'coverPhoto',
        'location',
        'latestObservationEvent.observation.symptoms',
        'wateringEvents',
        'fertilizingEvents',
    ];

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
            $plants->map(fn (Plant $plant) => $this->present($plant))
        );
    }

    public function store(StorePlantRequest $request): JsonResponse
    {
        $this->authorize('create', Plant::class);

        $plant = DB::transaction(function () use ($request): Plant {
            $plant = Plant::create($request->safe()->except(['tag_ids', 'equipment_ids']));
            $this->syncTags($request, $plant);
            $this->syncEquipment($request, $plant);

            return $plant;
        });

        return $this->present($plant->load(self::RELATIONS))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Plant $plant): PlantResource
    {
        $this->authorize('view', $plant);

        return $this->present($plant->load(self::RELATIONS));
    }

    public function update(UpdatePlantRequest $request, Plant $plant, RecordRelocation $recordRelocation): PlantResource
    {
        $this->authorize('update', $plant);

        DB::transaction(function () use ($request, $plant, $recordRelocation): void {
            $plant->update($request->safe()->except(['tag_ids', 'equipment_ids', 'location_id']));

            if ($request->has('location_id')) {
                $recordRelocation->record($plant, $request->locationId(), userId: $request->user()?->id);
            }

            $this->syncTags($request, $plant);
            $this->syncEquipment($request, $plant);
        });

        return $this->present($plant->load(self::RELATIONS));
    }

    public function destroy(Plant $plant): Response
    {
        $this->authorize('delete', $plant);

        $plant->delete();

        return response()->noContent();
    }

    /**
     * Sync tags only when the caller sent the key, so an unrelated update never
     * silently detaches a plant's tags.
     */
    private function syncTags(Request $request, Plant $plant): void
    {
        if ($request->has('tag_ids')) {
            $plant->tags()->sync($request->collect('tag_ids')->all());
        }
    }

    private function syncEquipment(Request $request, Plant $plant): void
    {
        if ($request->has('equipment_ids')) {
            $plant->equipment()->sync($request->collect('equipment_ids')->all());
        }
    }

    private function present(Plant $plant): PlantResource
    {
        return new PlantResource($plant, $plant->condition(), CareDue::forPlant($plant));
    }
}
