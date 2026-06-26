<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\FertilizerFormResource;
use App\Models\FertilizerForm;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FertilizerFormController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return FertilizerFormResource::collection(
            FertilizerForm::query()->orderBy('sort_order')->get()
        );
    }
}
