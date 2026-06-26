<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->foreign('care_event_id')->references('id')->on('care_events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropForeign(['care_event_id']);
        });
    }
};
