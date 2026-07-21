<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->enum('consent_status', ['pending', 'rejected', 'done'])->default('pending')->after('registration_status');
            $table->enum('schedule_status', ['pending', 'rejected', 'approved'])->default('pending')->after('consent_status');
            $table->date('ojt_start_date')->nullable()->after('schedule_status');
            $table->date('ojt_end_date')->nullable()->after('ojt_start_date');
            $table->decimal('required_ojt_hours', 8, 2)->default(486)->after('ojt_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'consent_status',
                'schedule_status',
                'ojt_start_date',
                'ojt_end_date',
                'required_ojt_hours',
            ]);
        });
    }
};
