<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relocation_details', function (Blueprint $table) {
            $table->foreignId('care_event_id')->constrained()->cascadeOnDelete();
            $table->primary('care_event_id');
            $table->string('from_location')->nullable();
            $table->string('to_location')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relocation_details');
    }
};
