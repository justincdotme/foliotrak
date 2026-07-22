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
        Schema::create('sensor_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_id')->constrained()->cascadeOnDelete();
            $table->decimal('temperature', 4, 1);
            $table->decimal('humidity', 4, 1);
            $table->timestamp('recorded_at');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['sensor_id', 'recorded_at']);
        });

        // Blueprint cannot express a descending index; newest-first watermark
        // lookups stay index-ordered with recorded_at DESC.
        DB::statement('create index sensor_readings_lookup on sensor_readings (sensor_id, recorded_at desc)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_readings');
    }
};
