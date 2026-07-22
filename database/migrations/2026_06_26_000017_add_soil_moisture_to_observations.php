<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('observations', function (Blueprint $table) {
            $table->string('soil_moisture_relative', 8)->nullable();
            $table->unsignedTinyInteger('soil_moisture_precise')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('observations', function (Blueprint $table) {
            $table->dropColumn(['soil_moisture_relative', 'soil_moisture_precise']);
        });
    }
};
