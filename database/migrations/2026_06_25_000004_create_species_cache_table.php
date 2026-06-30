<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('species_cache', function (Blueprint $table) {
            $table->id();
            $table->string('gbif_key', 64)->unique();
            $table->string('scientific_name')->index();
            $table->string('canonical_name')->nullable();
            $table->string('common_name')->nullable();
            $table->string('rank', 32)->nullable();
            $table->string('family', 128)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('species_cache');
    }
};
