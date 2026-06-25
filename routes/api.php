<?php

use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PlantController;
use App\Http\Controllers\SpeciesController;
use App\Http\Controllers\TagController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('plants', PlantController::class);
    Route::apiResource('tags', TagController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('plants/{plant}/photos', [PhotoController::class, 'index']);
    Route::post('plants/{plant}/photos', [PhotoController::class, 'store']);
    Route::delete('photos/{photo}', [PhotoController::class, 'destroy']);

    Route::get('species/suggest', [SpeciesController::class, 'suggest']);
});
