<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moas', function (Blueprint $table) {
            $table->foreignId('vpaa_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('vpaa_reviewed_at')->nullable();
        });

        // Preserve completed decisions from the previous VPAA-final workflow as reviewed history.
        DB::table('moas')->where('status', 'approved')->whereNotNull('approved_by')
            ->whereIn('approved_by', DB::table('users')->join('roles', 'roles.id', '=', 'users.role_id')
                ->where('roles.code', 'vpaa')->select('users.id'))
            ->update(['vpaa_reviewed_by' => DB::raw('approved_by'), 'vpaa_reviewed_at' => DB::raw('approved_at')]);
    }

    public function down(): void
    {
        Schema::table('moas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vpaa_reviewed_by');
            $table->dropColumn('vpaa_reviewed_at');
        });
    }
};
