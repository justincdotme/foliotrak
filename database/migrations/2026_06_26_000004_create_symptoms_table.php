<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('symptoms', function (Blueprint $table) {
            $table->id();
            $table->string('category', 16);
            $table->string('key', 48)->unique();
            $table->string('label', 96);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_custom')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('symptoms');
    }
};
