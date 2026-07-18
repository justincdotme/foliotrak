<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // For each 15-minute boundary, keep the reading whose timestamp
        // rounds to that boundary and delete the rest. The ingestion
        // command now snaps to quarter-hour marks on write, so future
        // rows will not re-introduce sub-interval duplicates.
        $kept = DB::table('sensor_readings')
            ->selectRaw('MIN(id) as keep_id')
            ->groupByRaw('sensor_id, ROUND(UNIX_TIMESTAMP(recorded_at) / 900)')
            ->pluck('keep_id');

        if ($kept->isEmpty()) {
            return;
        }

        DB::table('sensor_readings')
            ->whereNotIn('id', $kept)
            ->delete();

        DB::statement(
            'UPDATE sensor_readings SET recorded_at = FROM_UNIXTIME(ROUND(UNIX_TIMESTAMP(recorded_at) / 900) * 900)',
        );
    }

    public function down(): void
    {
        // Destructive data consolidation; original per-minute rows cannot be restored.
    }
};
