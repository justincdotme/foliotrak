<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sent_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->string('reminder_type', 16);
            $table->date('due_on');
            $table->dateTime('sent_at')->nullable();
            $table->string('status', 16);
            $table->timestamps();

            // The idempotency claim: one reminder per plant, type, and due date.
            $table->unique(['plant_id', 'reminder_type', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_reminders');
    }
};
