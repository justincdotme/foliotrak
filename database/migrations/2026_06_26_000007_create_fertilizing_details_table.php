<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fertilizing_details', function (Blueprint $table) {
            $table->foreignId('care_event_id')->constrained()->cascadeOnDelete();
            $table->primary('care_event_id');
            $table->foreignId('fertilizer_form_id')->constrained();
            $table->string('brand', 128)->nullable();
            $table->string('product', 191)->nullable();
            $table->decimal('npk_n', 5, 2)->nullable();
            $table->decimal('npk_p', 5, 2)->nullable();
            $table->decimal('npk_k', 5, 2)->nullable();
            $table->unsignedTinyInteger('dose_pct')->nullable();
            $table->unsignedInteger('amount_ml')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fertilizing_details');
    }
};
