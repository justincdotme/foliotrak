<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('species_cache', function (Blueprint $table) {
            $table->json('common_names')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('species_cache', function (Blueprint $table) {
            $table->dropColumn('common_names');
        });
    }
};
