<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('students')
            ->whereNotNull('user_id')
            ->select(['id', 'user_id', 'first_name', 'middle_name', 'last_name', 'contact_number', 'address'])
            ->orderBy('id')
            ->chunkById(100, function ($students): void {
                foreach ($students as $student) {
                    $name = collect([
                        $student->first_name,
                        $student->middle_name,
                        $student->last_name,
                    ])->filter(fn ($part) => filled($part))
                        ->map(fn ($part) => trim($part))
                        ->implode(' ');

                    DB::table('users')->where('id', $student->user_id)->update([
                        'name' => $name,
                        'contact_number' => $student->contact_number,
                        'address' => $student->address,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Profile repairs cannot be safely reversed to previously inconsistent values.
    }
};
