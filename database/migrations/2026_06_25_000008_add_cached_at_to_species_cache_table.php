<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('species_cache', function (Blueprint $table) {
            $table->timestamp('cached_at')->nullable()->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('species_cache', function (Blueprint $table) {
            $table->dropColumn('cached_at');
        });
    }
};
