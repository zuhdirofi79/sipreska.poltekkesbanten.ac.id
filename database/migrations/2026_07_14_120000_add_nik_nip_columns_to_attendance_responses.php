<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive migration: split legacy nip_nik into optional nik + nip columns.
 *
 * Legacy columns (nip_nik, nip_nik_normalized) and their unique index are retained.
 * Do NOT drop or rename legacy columns until data audit and code migration are complete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_responses', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_responses', 'nik')) {
                $table->string('nik', 255)->nullable()->after('name');
                $table->string('nik_normalized', 255)->nullable()->after('nik');
                $table->string('nip', 255)->nullable()->after('nik_normalized');
                $table->string('nip_normalized', 255)->nullable()->after('nip');
            }

            $table->index(['attendance_form_id', 'nik_normalized'], 'attendance_responses_form_nik_norm_idx');
            $table->index(['attendance_form_id', 'nip_normalized'], 'attendance_responses_form_nip_norm_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE attendance_responses MODIFY nip_nik VARCHAR(255) NULL');
            DB::statement('ALTER TABLE attendance_responses MODIFY nip_nik_normalized VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        Schema::table('attendance_responses', function (Blueprint $table) {
            $table->dropIndex('attendance_responses_form_nik_norm_idx');
            $table->dropIndex('attendance_responses_form_nip_norm_idx');

            if (Schema::hasColumn('attendance_responses', 'nik')) {
                $table->dropColumn(['nik', 'nik_normalized', 'nip', 'nip_normalized']);
            }
        });
    }
};
