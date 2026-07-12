<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_calibration_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->unsignedSmallInteger('raw_value');
            $table->timestamps();
            $table->unique(['sensor_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_calibration_points');
    }
};
