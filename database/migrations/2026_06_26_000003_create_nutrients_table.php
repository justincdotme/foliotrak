<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrients', function (Blueprint $table) {
            $table->id();
            $table->string('key', 48)->unique();
            $table->string('label', 96);
            $table->string('symbol', 16)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrients');
    }
};
