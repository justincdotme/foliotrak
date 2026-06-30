<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fertilizing_nutrients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nutrient_id')->constrained();
            $table->string('note', 128)->nullable();
            $table->unique(['care_event_id', 'nutrient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fertilizing_nutrients');
    }
};
