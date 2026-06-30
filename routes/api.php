<?php

use App\Http\Controllers\CareEventController;
use App\Http\Controllers\CareEventTypeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\FertilizerFormController;
use App\Http\Controllers\FertilizingController;
use App\Http\Controllers\GroupInsightsController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\NutrientController;
use App\Http\Controllers\ObservationController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PlantController;
use App\Http\Controllers\PlantRecommendationController;
use App\Http\Controllers\PlantTimelineController;
use App\Http\Controllers\RelocationController;
use App\Http\Controllers\RepottingController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SpeciesController;
use App\Http\Controllers\SymptomController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\WateringController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('plants', PlantController::class);
    Route::apiResource('tags', TagController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('plants/{plant}/timeline', [PlantTimelineController::class, 'show']);
    Route::get('plants/{plant}/recommendations', [PlantRecommendationController::class, 'show']);

    Route::get('plants/{plant}/photos', [PhotoController::class, 'index']);
    Route::post('plants/{plant}/photos', [PhotoController::class, 'store']);
    Route::delete('photos/{photo}', [PhotoController::class, 'destroy']);

    Route::post('plants/{plant}/waterings', [WateringController::class, 'store']);
    Route::post('plants/{plant}/fertilizings', [FertilizingController::class, 'store']);
    Route::post('plants/{plant}/repottings', [RepottingController::class, 'store']);
    Route::post('plants/{plant}/observations', [ObservationController::class, 'store']);
    Route::post('plants/{plant}/relocations', [RelocationController::class, 'store']);
    Route::patch('care-events/{event}', [CareEventController::class, 'update']);
    Route::delete('care-events/{event}', [CareEventController::class, 'destroy']);

    Route::get('care-event-types', [CareEventTypeController::class, 'index']);
    Route::get('fertilizer-forms', [FertilizerFormController::class, 'index']);
    Route::get('nutrients', [NutrientController::class, 'index']);
    Route::get('symptoms', [SymptomController::class, 'index']);
    Route::get('equipment', [EquipmentController::class, 'index']);
    Route::get('locations', [LocationController::class, 'index']);
    Route::post('locations', [LocationController::class, 'store']);

    Route::get('species/suggest', [SpeciesController::class, 'suggest']);

    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('insights/group', [GroupInsightsController::class, 'index']);
    Route::get('insights/locations', [GroupInsightsController::class, 'locationSummary']);

    Route::get('settings', [SettingsController::class, 'show']);
    Route::patch('settings', [SettingsController::class, 'update']);
});
