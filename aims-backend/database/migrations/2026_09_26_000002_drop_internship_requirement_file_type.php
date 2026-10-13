<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internship_requirements', function (Blueprint $table): void {
            $table->dropColumn('file_type');
        });
    }

    public function down(): void
    {
        Schema::table('internship_requirements', function (Blueprint $table): void {
            $table->enum('file_type', ['pdf', 'image', 'document'])->nullable()->after('file_path');
        });

        DB::table('internship_requirements')
            ->whereNotNull('file_path')
            ->orderBy('id')
            ->chunkById(100, function ($requirements): void {
                foreach ($requirements as $requirement) {
                    $extension = strtolower(pathinfo($requirement->file_path, PATHINFO_EXTENSION));
                    $fileType = match (true) {
                        $extension === 'pdf' => 'pdf',
                        in_array($extension, ['jpg', 'jpeg', 'png'], true) => 'image',
                        default => 'document',
                    };

                    DB::table('internship_requirements')->where('id', $requirement->id)
                        ->update(['file_type' => $fileType]);
                }
            });
    }
};
