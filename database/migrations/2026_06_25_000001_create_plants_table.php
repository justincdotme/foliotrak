<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('plants', function (Blueprint $table) {
            $table->id();
            $table->string('common_name')->nullable();
            $table->string('scientific_name')->nullable();
            $table->string('gbif_key', 64)->nullable()->index();
            $table->string('location')->nullable();
            $table->date('acquired_on')->nullable();
            $table->string('status', 16)->default('active');
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('watering_interval_days_override')->nullable();
            $table->unsignedSmallInteger('fertilizing_interval_days_override')->nullable();
            // Constrained to photos once that table exists (see the cover-photo FK migration).
            $table->unsignedBigInteger('cover_photo_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plants');
    }
};
