<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\CareEventTypeResource;
use App\Models\CareEventType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CareEventTypeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CareEventTypeResource::collection(
            CareEventType::query()->orderBy('sort_order')->get()
        );
    }
}
