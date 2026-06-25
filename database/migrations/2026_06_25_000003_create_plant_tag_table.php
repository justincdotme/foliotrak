<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('plant_tag', function (Blueprint $table) {
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('plant_tags')->cascadeOnDelete();
            $table->primary(['plant_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_tag');
    }
};
