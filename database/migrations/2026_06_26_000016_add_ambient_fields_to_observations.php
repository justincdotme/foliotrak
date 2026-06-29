<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('observations', function (Blueprint $table) {
            $table->unsignedTinyInteger('ambient_humidity_pct')->nullable();
            $table->decimal('ambient_temp_c', 5, 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('observations', function (Blueprint $table) {
            $table->dropColumn(['ambient_humidity_pct', 'ambient_temp_c']);
        });
    }
};
