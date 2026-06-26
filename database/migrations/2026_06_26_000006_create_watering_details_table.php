<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('watering_details', function (Blueprint $table) {
            $table->foreignId('care_event_id')->constrained()->cascadeOnDelete();
            $table->primary('care_event_id');
            $table->unsignedInteger('amount_ml')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watering_details');
    }
};
