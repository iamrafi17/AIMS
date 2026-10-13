<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $invalid = DB::table('moas as moas')
            ->leftJoin('programs as programs', 'programs.id', '=', 'moas.program_id')
            ->where(function ($query) {
                $query->whereNull('programs.id')
                    ->orWhereColumn('moas.college_id', '!=', 'programs.college_id');
            })->exists();

        if ($invalid) {
            throw new RuntimeException('MOA migration stopped: assign a valid program and resolve college mismatches before removing moas.college_id.');
        }

        Schema::table('moas', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired', 'returned'])->default('pending')->change();
            $table->enum('program_status', ['pending', 'endorsed', 'rejected', 'returned'])->default('pending')->change();
        });
        Schema::table('approval_records', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'approved', 'rejected', 'returned'])->default('pending')->change();
        });

        DB::table('moas')->where('program_status', 'rejected')->update(['program_status' => 'returned']);
        DB::table('moas')->where('status', 'rejected')->update(['status' => 'returned']);
        DB::table('moas')->where('status', 'expired')->whereNotNull('approved_at')->update(['status' => 'approved']);
        DB::table('moas')->where('status', 'expired')->update(['status' => 'pending']);

        Schema::table('moas', function (Blueprint $table): void {
            $table->dropForeign(['college_id']);
            $table->dropForeign(['program_id']);
            $table->dropColumn('college_id');
        });

        Schema::table('moas', function (Blueprint $table): void {
            $table->foreignId('program_id')->nullable(false)->change();
            $table->foreign('program_id')->references('id')->on('programs')->restrictOnDelete();
            $table->enum('status', ['pending', 'approved', 'returned'])->default('pending')->change();
            $table->enum('program_status', ['pending', 'endorsed', 'returned'])->default('pending')->change();
        });
        DB::table('approval_records')->where('subject_type', 'moa')->where('status', 'rejected')
            ->update(['status' => 'returned']);
    }

    public function down(): void
    {
        DB::table('approval_records')->where('subject_type', 'moa')->where('status', 'returned')
            ->update(['status' => 'rejected']);
        Schema::table('approval_records', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->change();
        });
        Schema::table('moas', function (Blueprint $table): void {
            $table->foreignId('college_id')->nullable()->constrained();
        });
        DB::table('moas')->join('programs', 'programs.id', '=', 'moas.program_id')
            ->update(['moas.college_id' => DB::raw('programs.college_id')]);
        Schema::table('moas', function (Blueprint $table): void {
            $table->foreignId('college_id')->nullable(false)->change();
            $table->dropForeign(['program_id']);
            $table->foreignId('program_id')->nullable()->change();
            $table->foreign('program_id')->references('id')->on('programs')->nullOnDelete();
        });
        DB::table('moas')->where('status', 'returned')->update(['status' => 'rejected']);
        DB::table('moas')->where('program_status', 'returned')->update(['program_status' => 'rejected']);
        Schema::table('moas', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])->default('pending')->change();
            $table->enum('program_status', ['pending', 'endorsed', 'rejected'])->default('pending')->change();
        });
    }
};
