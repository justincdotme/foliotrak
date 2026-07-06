<?php

declare(strict_types=1);

use App\Models\CareEventType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_change_details', function (Blueprint $table) {
            $table->foreignId('care_event_id')->constrained()->cascadeOnDelete();
            $table->primary('care_event_id');
            // Nullable + nullOnDelete so deleting an equipment type preserves history; the label
            // snapshot below is what renders, so the entry survives the type going away.
            $table->foreignId('equipment_id')->nullable()->constrained('equipment')->nullOnDelete();
            $table->string('equipment_label', 96);
            $table->string('action', 16);
        });

        // A fixed sort_order (equipment sorts after the five core types) rather than max()+1:
        // on a fresh database this migration runs before the seeder, so the core types do not yet
        // exist and max() would place equipment first. The seeder declares the same value.
        CareEventType::firstOrCreate(
            ['key' => 'equipment'],
            ['label' => 'Equipment', 'sort_order' => 6],
        );
    }

    public function down(): void
    {
        CareEventType::query()->where('key', 'equipment')->delete();
        Schema::dropIfExists('equipment_change_details');
    }
};
