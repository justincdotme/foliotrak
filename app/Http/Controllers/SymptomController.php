<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\SymptomResource;
use App\Models\Symptom;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SymptomController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SymptomResource::collection(
            Symptom::query()->orderBy('sort_order')->orderBy('id')->get()
        );
    }
}
