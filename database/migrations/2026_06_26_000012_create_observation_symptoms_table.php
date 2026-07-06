<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observation_symptoms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('observation_id')->constrained('observations', 'care_event_id')->cascadeOnDelete();
            $table->foreignId('symptom_id')->constrained();
            $table->unique(['observation_id', 'symptom_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observation_symptoms');
    }
};
