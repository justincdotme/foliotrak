<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observations', function (Blueprint $table) {
            $table->foreignId('care_event_id')->constrained()->cascadeOnDelete();
            $table->primary('care_event_id');
            $table->unsignedTinyInteger('overall_health')->nullable();
            $table->text('health_note')->nullable();
            $table->unsignedTinyInteger('light_level')->nullable();
            $table->string('growth_rate', 16)->nullable();
            $table->text('growth_note')->nullable();
            $table->decimal('leaf_size_mm', 6, 1)->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observations');
    }
};
