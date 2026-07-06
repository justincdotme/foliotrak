<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when a search misses the local index and GBIF is unavailable, so the SPA
 * can tell "search is down" apart from "no plant matched" (ADR-0016).
 */
class SearchDegradedException extends RuntimeException
{
    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Species search is temporarily unavailable. Please try again.',
            'code'    => 'search_degraded',
        ], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
    }
}
