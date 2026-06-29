<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('care_event_type_id')->constrained();
            $table->dateTime('occurred_at');
            $table->foreignId('logged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['plant_id', 'occurred_at']);
            $table->index(['care_event_type_id', 'occurred_at']);
            $table->index(['plant_id', 'care_event_type_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_events');
    }
};
