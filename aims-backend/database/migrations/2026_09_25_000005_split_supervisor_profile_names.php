<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supervisor_profiles', function (Blueprint $table): void {
            $table->string('first_name', 100)->nullable()->after('user_id');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('last_name', 100)->nullable()->after('middle_name');
        });

        DB::table('supervisor_profiles')->orderBy('id')->get(['id', 'full_name'])->each(function (object $profile): void {
            $parts = preg_split('/\s+/u', trim((string) $profile->full_name), -1, PREG_SPLIT_NO_EMPTY);
            $firstName = array_shift($parts) ?: null;
            $lastName = $parts ? array_pop($parts) : null;

            DB::table('supervisor_profiles')->where('id', $profile->id)->update([
                'first_name' => $firstName,
                'middle_name' => $parts ? implode(' ', $parts) : null,
                'last_name' => $lastName,
            ]);
        });

        Schema::table('supervisor_profiles', function (Blueprint $table): void {
            $table->dropColumn('full_name');
        });
    }

    public function down(): void
    {
        Schema::table('supervisor_profiles', function (Blueprint $table): void {
            $table->string('full_name')->nullable();
        });

        DB::table('supervisor_profiles')->orderBy('id')->get()->each(function (object $profile): void {
            DB::table('supervisor_profiles')->where('id', $profile->id)->update([
                'full_name' => implode(' ', array_filter([
                    $profile->first_name, $profile->middle_name, $profile->last_name,
                ], fn ($part) => filled($part))),
            ]);
        });

        Schema::table('supervisor_profiles', function (Blueprint $table): void {
            $table->dropColumn(['first_name', 'middle_name', 'last_name']);
        });
    }
};
