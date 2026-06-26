<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\NutrientResource;
use App\Models\Nutrient;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NutrientController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return NutrientResource::collection(
            Nutrient::query()->orderBy('sort_order')->get()
        );
    }
}
