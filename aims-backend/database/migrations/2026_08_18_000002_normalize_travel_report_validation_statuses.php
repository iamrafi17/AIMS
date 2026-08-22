<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('travel_reports')
            ->where('status', 'program_head_validated')
            ->update([
                'status' => 'pending_vpaa',
                'forwarded_at' => DB::raw('COALESCE(forwarded_at, program_head_validated_at, CURRENT_TIMESTAMP)'),
                'updated_at' => now(),
            ]);

        DB::table('travel_reports')
            ->where('status', 'for_revision')
            ->update([
                'status' => 'pending_program_head',
                'updated_at' => now(),
            ]);

        DB::table('travel_reports')
            ->where('status', 'returned_by_vpaa')
            ->update([
                'status' => 'pending_vpaa',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // This is an intentional one-way normalization; the previous return stage is ambiguous.
    }
};
