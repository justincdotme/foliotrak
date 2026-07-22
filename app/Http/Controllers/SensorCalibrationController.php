<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SensorType;
use App\Http\Requests\UpdateSensorCalibrationRequest;
use App\Models\Sensor;
use App\Models\SensorCalibrationPoint;
use App\Services\Sensors\MoistureCalibration;
use App\Services\Sensors\Transformers\MoistureTransformer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SensorCalibrationController extends Controller
{
    use AuthorizesRequests;

    /**
     * @param Sensor $sensor
     *
     * @return JsonResponse
     */
    public function show(Sensor $sensor): JsonResponse
    {
        $this->authorize('view', $sensor);
        $this->ensureMoisture($sensor);

        return $this->payload($sensor);
    }

    /**
     * @param UpdateSensorCalibrationRequest $request
     * @param Sensor                         $sensor
     *
     * @return JsonResponse
     */
    public function update(UpdateSensorCalibrationRequest $request, Sensor $sensor): JsonResponse
    {
        $this->authorize('update', $sensor);
        $this->ensureMoisture($sensor);

        /** @var list<array{position: int, value: int}> $points */
        $points = $request->validated('points');

        DB::transaction(function () use ($points, $sensor): void {
            $sensor->calibrationPoints()->delete();

            foreach ($points as $point) {
                $sensor->calibrationPoints()->create([
                    'position'  => $point['position'],
                    'raw_value' => $point['value'],
                ]);
            }
        });

        return $this->payload($sensor);
    }

    /**
     * @param Sensor $sensor
     *
     * @return void
     */
    private function ensureMoisture(Sensor $sensor): void
    {
        abort_unless($sensor->type === SensorType::Moisture, 422, 'Calibration applies only to moisture sensors.');
    }

    /**
     * @param Sensor $sensor
     *
     * @return JsonResponse
     */
    private function payload(Sensor $sensor): JsonResponse
    {
        $points = $sensor->calibrationPoints()
            ->orderBy('position')
            ->get()
            ->map(fn (SensorCalibrationPoint $point): array => [
                'position' => $point->position,
                'value'    => $point->raw_value,
            ])
            ->values()
            ->all();

        $latestReading = $sensor->readings()->orderByDesc('recorded_at')->first();
        $latest        = null;

        if ($latestReading !== null) {
            /** @var \Illuminate\Support\Carbon $recordedAt */
            $recordedAt = $latestReading->recorded_at;

            $latest = [
                'value'       => (new MoistureTransformer)->hydrate($latestReading->data)->moisture,
                'recorded_at' => $recordedAt->format('Y-m-d\TH:i:s\Z'),
            ];
        }

        return response()->json([
            'data' => [
                'points'    => $points,
                'suggested' => MoistureCalibration::suggestedPoints(),
                'latest'    => $latest,
            ],
        ]);
    }
}
