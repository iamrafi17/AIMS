<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $names = [];
        $records = DB::table('htes')->get(['id', 'name']);
        foreach ($records as $record) {
            $name = preg_replace('/\s+/u', ' ', trim($record->name));
            $key = Str::lower($name);
            if (isset($names[$key])) {
                throw new RuntimeException('Duplicate HTE names must be resolved before removing normalized_name. No records were deleted.');
            }
            $names[$key] = true;
        }
        foreach ($records as $record) {
            DB::table('htes')->where('id', $record->id)->update(['name' => preg_replace('/\s+/u', ' ', trim($record->name))]);
        }
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            Schema::table('htes', fn (Blueprint $table) => $table->unique('name', 'htes_name_unique'));
        } else {
            DB::statement('CREATE UNIQUE INDEX htes_name_unique ON htes (LOWER(name))');
        }
        Schema::table('htes', function (Blueprint $table) {
            $table->dropUnique('htes_normalized_name_unique');
            $table->dropColumn('normalized_name');
        });
    }

    public function down(): void
    {
        Schema::table('htes', fn (Blueprint $table) => $table->string('normalized_name')->nullable());
        foreach (DB::table('htes')->get(['id', 'name']) as $record) {
            DB::table('htes')->where('id', $record->id)->update(['normalized_name' => Str::lower($record->name)]);
        }
        Schema::table('htes', fn (Blueprint $table) => $table->unique('normalized_name', 'htes_normalized_name_unique'));
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            Schema::table('htes', fn (Blueprint $table) => $table->dropUnique('htes_name_unique'));
        } else {
            DB::statement('DROP INDEX htes_name_unique');
        }
    }
};
