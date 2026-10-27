<?php

use App\Support\DeploymentWorkflow;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_records', function (Blueprint $table) {
            $table->foreignId('deployment_internship_id')->nullable()->constrained('student_internships')->restrictOnDelete();
            $table->string('deployment_stage', 40)->nullable()->index();
            $table->json('deployment_snapshot')->nullable();
            $table->foreignId('dean_forwarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dean_forwarded_at')->nullable();
        });
        DeploymentWorkflow::adoptLegacyPending();
    }

    public function down(): void
    {
        Schema::table('approval_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deployment_internship_id');
            $table->dropConstrainedForeignId('dean_forwarded_by');
            $table->dropIndex(['deployment_stage']);
            $table->dropColumn(['deployment_stage', 'deployment_snapshot', 'dean_forwarded_at']);
        });
    }
};
