<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('repotting_details', function (Blueprint $table) {
            $table->foreignId('care_event_id')->constrained()->cascadeOnDelete();
            $table->primary('care_event_id');
            $table->text('soil_recipe')->nullable();
            $table->decimal('pot_size_value', 5, 1)->nullable();
            $table->string('pot_size_unit', 8)->nullable();
            $table->boolean('fertilizer_added')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repotting_details');
    }
};
