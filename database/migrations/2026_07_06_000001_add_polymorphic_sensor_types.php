<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sensors', function (Blueprint $table) {
            $table->string('type')->default('hygrometer');
        });

        Schema::table('sensor_readings', function (Blueprint $table) {
            $table->json('data')->nullable();
        });

        $this->backfill();

        Schema::table('sensor_readings', function (Blueprint $table) {
            $table->json('data')->nullable(false)->change();
            $table->dropColumn(['temperature', 'humidity', 'meta']);
        });
    }

    public function down(): void
    {
        Schema::table('sensor_readings', function (Blueprint $table) {
            $table->decimal('temperature', 4, 1)->nullable();
            $table->decimal('humidity', 4, 1)->nullable();
            $table->json('meta')->nullable();
        });

        $this->reverseBackfill();

        Schema::table('sensor_readings', function (Blueprint $table) {
            $table->decimal('temperature', 4, 1)->nullable(false)->change();
            $table->decimal('humidity', 4, 1)->nullable(false)->change();
            $table->dropColumn('data');
        });

        Schema::table('sensors', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }

    /**
     * Runs in PHP rather than as a single JSON_MERGE_PATCH statement so the
     * backfill also works against the SQLite connection the test suite uses.
     */
    private function backfill(): void
    {
        foreach (DB::table('sensor_readings')->cursor() as $reading) {
            $data = array_merge(
                [
                    'temperature' => (float) $reading->temperature,
                    'humidity'    => (float) $reading->humidity,
                ],
                $reading->meta !== null ? json_decode((string) $reading->meta, true) : [],
            );

            DB::table('sensor_readings')->where('id', $reading->id)->update([
                'data' => json_encode($data),
            ]);
        }
    }

    private function reverseBackfill(): void
    {
        foreach (DB::table('sensor_readings')->cursor() as $reading) {
            $data = json_decode((string) $reading->data, true);

            $temperature = $data['temperature'];
            $humidity    = $data['humidity'];
            unset($data['temperature'], $data['humidity']);

            DB::table('sensor_readings')->where('id', $reading->id)->update([
                'temperature' => $temperature,
                'humidity'    => $humidity,
                'meta'        => $data === [] ? null : json_encode($data),
            ]);
        }
    }
};
