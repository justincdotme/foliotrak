<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::table('plants', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('relocation_details', function (Blueprint $table) {
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->nullOnDelete();
        });

        $this->backfill();

        Schema::table('plants', function (Blueprint $table) {
            $table->dropColumn('location');
        });

        Schema::table('relocation_details', function (Blueprint $table) {
            $table->dropColumn(['from_location', 'to_location']);
        });
    }

    public function down(): void
    {
        Schema::table('plants', function (Blueprint $table) {
            $table->string('location')->nullable();
        });

        Schema::table('relocation_details', function (Blueprint $table) {
            $table->string('from_location')->nullable();
            $table->string('to_location')->nullable();
        });

        $this->reverseBackfill();

        Schema::table('relocation_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('from_location_id');
            $table->dropConstrainedForeignId('to_location_id');
        });

        Schema::table('plants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });

        Schema::dropIfExists('locations');
    }

    private function backfill(): void
    {
        $names = collect()
            ->merge(DB::table('plants')->whereNotNull('location')->distinct()->pluck('location'))
            ->merge(DB::table('relocation_details')->whereNotNull('from_location')->distinct()->pluck('from_location'))
            ->merge(DB::table('relocation_details')->whereNotNull('to_location')->distinct()->pluck('to_location'))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique(fn ($name) => mb_strtolower($name))
            ->values();

        $now = now();
        foreach ($names as $name) {
            DB::table('locations')->insert([
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $lookup = [];
        foreach (DB::table('locations')->get() as $row) {
            $lookup[mb_strtolower($row->name)] = $row->id;
        }

        foreach (DB::table('plants')->whereNotNull('location')->cursor() as $plant) {
            $id = $lookup[mb_strtolower(trim($plant->location))] ?? null;
            if ($id !== null) {
                DB::table('plants')->where('id', $plant->id)->update(['location_id' => $id]);
            }
        }

        foreach (DB::table('relocation_details')->cursor() as $detail) {
            $updates = [];
            if ($detail->from_location !== null) {
                $id = $lookup[mb_strtolower(trim($detail->from_location))] ?? null;
                if ($id !== null) {
                    $updates['from_location_id'] = $id;
                }
            }
            if ($detail->to_location !== null) {
                $id = $lookup[mb_strtolower(trim($detail->to_location))] ?? null;
                if ($id !== null) {
                    $updates['to_location_id'] = $id;
                }
            }
            if ($updates !== []) {
                DB::table('relocation_details')->where('care_event_id', $detail->care_event_id)->update($updates);
            }
        }
    }

    private function reverseBackfill(): void
    {
        foreach (DB::table('plants')->whereNotNull('location_id')->cursor() as $plant) {
            $name = DB::table('locations')->where('id', $plant->location_id)->value('name');
            if ($name !== null) {
                DB::table('plants')->where('id', $plant->id)->update(['location' => $name]);
            }
        }

        foreach (DB::table('relocation_details')->cursor() as $detail) {
            $updates = [];
            if ($detail->from_location_id !== null) {
                $name = DB::table('locations')->where('id', $detail->from_location_id)->value('name');
                if ($name !== null) {
                    $updates['from_location'] = $name;
                }
            }
            if ($detail->to_location_id !== null) {
                $name = DB::table('locations')->where('id', $detail->to_location_id)->value('name');
                if ($name !== null) {
                    $updates['to_location'] = $name;
                }
            }
            if ($updates !== []) {
                DB::table('relocation_details')->where('care_event_id', $detail->care_event_id)->update($updates);
            }
        }
    }
};
