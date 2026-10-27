<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_period_id')->constrained()->restrictOnDelete();
            $table->string('name', 160);
            $table->unsignedInteger('version');
            $table->unsignedInteger('row_version')->default(0);
            $table->string('status', 20)->default('draft');
            $table->string('rating_direction', 10)->nullable();
            $table->boolean('allow_no_rating')->default(false);
            $table->string('computation_method', 30)->nullable();
            $table->json('computation_settings')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->unsignedTinyInteger('published_slot')->nullable()->storedAs("CASE WHEN status = 'published' THEN 1 ELSE NULL END");
            $table->timestamps();
            $table->unique(['program_id', 'academic_period_id', 'version'], 'ef_scope_version_uq');
            $table->unique(['program_id', 'academic_period_id', 'published_slot'], 'ef_published_uq');
        });
        Schema::create('evaluation_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_form_id')->constrained()->restrictOnDelete();
            $table->string('title', 500);
            $table->unsignedInteger('sort_order');
            $table->timestamps();
            $table->unique(['id', 'evaluation_form_id'], 'ec_id_form_uq');
        });
        Schema::create('evaluation_subgroups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evaluation_form_id');
            $table->unsignedBigInteger('evaluation_category_id');
            $table->string('title', 500);
            $table->unsignedInteger('sort_order');
            $table->timestamps();
            $table->foreign(['evaluation_category_id', 'evaluation_form_id'], 'es_category_fk')->references(['id', 'evaluation_form_id'])->on('evaluation_categories')->restrictOnDelete();
            $table->unique(['id', 'evaluation_category_id', 'evaluation_form_id'], 'es_id_category_form_uq');
        });
        Schema::create('evaluation_criteria', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evaluation_form_id');
            $table->unsignedBigInteger('evaluation_category_id');
            $table->unsignedBigInteger('evaluation_subgroup_id')->nullable();
            $table->text('label');
            $table->string('extra_input_label', 160)->nullable();
            $table->unsignedInteger('sort_order');
            $table->timestamps();
            $table->foreign(['evaluation_category_id', 'evaluation_form_id'], 'eq_category_fk')->references(['id', 'evaluation_form_id'])->on('evaluation_categories')->restrictOnDelete();
            $table->foreign(['evaluation_subgroup_id', 'evaluation_category_id', 'evaluation_form_id'], 'eq_subgroup_fk')->references(['id', 'evaluation_category_id', 'evaluation_form_id'])->on('evaluation_subgroups')->restrictOnDelete();
            $table->unique(['id', 'evaluation_form_id'], 'eq_id_form_uq');
        });
        Schema::create('evaluation_rating_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_form_id')->constrained()->restrictOnDelete();
            $table->decimal('numeric_value', 10, 3);
            $table->string('label', 500);
            $table->unsignedInteger('sort_order');
            $table->timestamps();
            $table->unique(['evaluation_form_id', 'numeric_value'], 'er_value_uq');
            $table->unique(['id', 'evaluation_form_id'], 'er_id_form_uq');
        });
        Schema::table('evaluations', function (Blueprint $table) {
            // Retain a supporting index for the existing internship/student composite FK.
            $table->index(['student_id'], 'evaluations_student_history_idx');
            $table->dropUnique('evaluations_student_type_unique');
            $table->dropForeign(['student_id']);
            $table->foreign('student_id')->references('id')->on('students')->restrictOnDelete();
            $table->enum('evaluation_type', ['midterm', 'final', 'whole_semester'])->change();
            foreach (['work_quality', 'communication', 'professionalism', 'attendance', 'technical_skills', 'teamwork'] as $column) {
                $table->integer($column)->nullable()->change();
            }
            $table->foreignId('evaluation_form_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('row_version')->default(0);
            $table->unique(['id', 'evaluation_form_id'], 'ev_id_form_uq');
        });
        Schema::create('evaluation_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evaluation_id');
            $table->unsignedBigInteger('evaluation_form_id');
            $table->unsignedBigInteger('evaluation_criterion_id');
            $table->unsignedBigInteger('evaluation_rating_option_id')->nullable();
            $table->boolean('is_no_rating')->default(false);
            $table->text('extra_text')->nullable();
            $table->timestamps();
            $table->unique(['evaluation_id', 'evaluation_criterion_id'], 'ers_evaluation_criterion_uq');
            $table->foreign(['evaluation_id', 'evaluation_form_id'], 'ers_evaluation_fk')->references(['id', 'evaluation_form_id'])->on('evaluations')->restrictOnDelete();
            $table->foreign(['evaluation_criterion_id', 'evaluation_form_id'], 'ers_criterion_fk')->references(['id', 'evaluation_form_id'])->on('evaluation_criteria')->restrictOnDelete();
            $table->foreign(['evaluation_rating_option_id', 'evaluation_form_id'], 'ers_option_fk')->references(['id', 'evaluation_form_id'])->on('evaluation_rating_options')->restrictOnDelete();
        });
        $conditions = [
            'evaluations' => "evaluation_type <> 'whole_semester' OR (student_internship_id IS NOT NULL AND evaluation_form_id IS NOT NULL)",
            'evaluation_responses' => '(evaluation_rating_option_id IS NOT NULL AND is_no_rating = 0) OR (evaluation_rating_option_id IS NULL AND is_no_rating = 1)',
        ];
        foreach ($conditions as $table => $condition) {
            if (DB::getDriverName() === 'sqlite') {
                foreach (['INSERT', 'UPDATE'] as $event) {
                    $newCondition = preg_replace('/\b(evaluation_type|student_internship_id|evaluation_form_id|evaluation_rating_option_id|is_no_rating)\b/', 'NEW.$1', $condition);
                    DB::unprepared("CREATE TRIGGER {$table}_valid_{$event} BEFORE {$event} ON {$table} WHEN NOT ({$newCondition}) BEGIN SELECT RAISE(ABORT, 'Invalid evaluation answer or binding'); END");
                }
            } else {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_valid_ck CHECK ({$condition})");
            }
        }
    }

    public function down(): void
    {
        // A destructive rollback after use would destroy rubric history.
        if (DB::table('evaluation_forms')->exists() || DB::table('evaluations')->where('evaluation_type', 'whole_semester')->exists()) {
            throw new RuntimeException('Preserve evaluation data before rolling back this module. Use a forward migration after use.');
        }
        Schema::dropIfExists('evaluation_responses');
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS evaluations_valid_INSERT');
            DB::unprepared('DROP TRIGGER IF EXISTS evaluations_valid_UPDATE');
        } else {
            DB::statement('ALTER TABLE evaluations DROP CONSTRAINT evaluations_valid_ck');
        }
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropForeign(['evaluation_form_id']);
            $table->dropUnique('ev_id_form_uq');
            $table->dropColumn(['evaluation_form_id', 'row_version']);
            // Keep safer history indexes, nullable legacy scores, and RESTRICT deletion.
        });
        foreach (['evaluation_rating_options', 'evaluation_criteria', 'evaluation_subgroups', 'evaluation_categories', 'evaluation_forms'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
