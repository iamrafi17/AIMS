<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('phone', 'contact_number');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('contact_number', 11)->nullable()->change();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->renameColumn('phone', 'contact_number');
            $table->renameColumn('parent_phone', 'parent_contact_number');
        });
        Schema::table('students', function (Blueprint $table) {
            $table->string('contact_number', 11)->nullable()->change();
            $table->string('parent_contact_number', 11)->nullable()->change();
        });

        Schema::table('htes', function (Blueprint $table) {
            $table->renameColumn('contact_phone', 'contact_number');
        });
        Schema::table('htes', function (Blueprint $table) {
            $table->string('contact_number', 11)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('contact_number', 20)->nullable()->change();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('contact_number', 'phone');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('contact_number', 20)->nullable()->change();
            $table->string('parent_contact_number', 20)->nullable()->change();
        });
        Schema::table('students', function (Blueprint $table) {
            $table->renameColumn('contact_number', 'phone');
            $table->renameColumn('parent_contact_number', 'parent_phone');
        });

        Schema::table('htes', function (Blueprint $table) {
            $table->string('contact_number', 20)->change();
        });
        Schema::table('htes', function (Blueprint $table) {
            $table->renameColumn('contact_number', 'contact_phone');
        });
    }
};
