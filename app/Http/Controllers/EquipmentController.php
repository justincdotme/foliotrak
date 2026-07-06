<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreEquipmentRequest;
use App\Http\Requests\UpdateEquipmentRequest;
use App\Http\Resources\EquipmentResource;
use App\Models\Equipment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class EquipmentController extends Controller
{
    use AuthorizesRequests;

    /**
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Equipment::class);

        return EquipmentResource::collection(
            Equipment::query()->orderBy('sort_order')->get(),
        );
    }

    /**
     * @param StoreEquipmentRequest $request
     *
     * @return JsonResponse
     */
    public function store(StoreEquipmentRequest $request): JsonResponse
    {
        $this->authorize('create', Equipment::class);

        $label = $request->string('label')->value();

        $equipment = Equipment::create([
            'label'      => $label,
            'key'        => $this->uniqueKey($label),
            'sort_order' => (int) Equipment::query()->max('sort_order') + 1,
        ]);

        return EquipmentResource::make($equipment)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * @param UpdateEquipmentRequest $request
     * @param Equipment              $equipment
     *
     * @return EquipmentResource
     */
    public function update(UpdateEquipmentRequest $request, Equipment $equipment): EquipmentResource
    {
        $this->authorize('update', $equipment);

        $equipment->update($request->validated());

        return EquipmentResource::make($equipment);
    }

    /**
     * @param Equipment $equipment
     *
     * @return Response
     */
    public function destroy(Equipment $equipment): Response
    {
        $this->authorize('delete', $equipment);

        $equipment->delete();

        return response()->noContent();
    }

    /**
     * Slug the label and disambiguate against the unique key column so two distinct
     * labels that slugify the same still each get a stored row.
     *
     * @param string $label
     *
     * @return string
     */
    private function uniqueKey(string $label): string
    {
        $base = Str::slug($label, '_') ?: 'equipment';
        $key  = $base;
        $n    = 2;

        while (Equipment::query()->where('key', $key)->exists()) {
            $key = $base . '_' . $n++;
        }

        return $key;
    }
}
