<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SpeciesSuggestRequest;
use App\Http\Resources\SpeciesSuggestionResource;
use App\Services\PlantSearchService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SpeciesController extends Controller
{
    /**
     * @param SpeciesSuggestRequest $request
     * @param PlantSearchService    $search
     *
     * @return AnonymousResourceCollection
     */
    public function suggest(SpeciesSuggestRequest $request, PlantSearchService $search): AnonymousResourceCollection
    {
        $species = $search->search(
            (string) $request->string('q'),
            $request->integer('limit') ?: 8,
        );

        return SpeciesSuggestionResource::collection($species);
    }
}
