<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const LENGTH = 6;

    public function up(): void
    {
        Schema::table('attendance_forms', function (Blueprint $table): void {
            if (! Schema::hasColumn('attendance_forms', 'short_code')) {
                $table->string('short_code', 12)->nullable()->unique()->after('public_token');
            }
        });

        $this->backfillShortCodes();
    }

    public function down(): void
    {
        Schema::table('attendance_forms', function (Blueprint $table): void {
            if (Schema::hasColumn('attendance_forms', 'short_code')) {
                $table->dropUnique(['short_code']);
                $table->dropColumn('short_code');
            }
        });
    }

    private function backfillShortCodes(): void
    {
        $rows = DB::table('attendance_forms')
            ->whereNull('short_code')
            ->orderBy('id')
            ->get(['id']);

        foreach ($rows as $row) {
            DB::table('attendance_forms')
                ->where('id', $row->id)
                ->update(['short_code' => $this->uniqueShortCode()]);
        }
    }

    private function uniqueShortCode(int $length = self::LENGTH, int $maxAttempts = 20): string
    {
        $alphabetLength = strlen(self::ALPHABET);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= self::ALPHABET[random_int(0, $alphabetLength - 1)];
            }

            $exists = DB::table('attendance_forms')->where('short_code', $code)->exists();

            if (! $exists) {
                return $code;
            }
        }

        if ($length < 12) {
            return $this->uniqueShortCode($length + 1, $maxAttempts);
        }

        throw new RuntimeException('Unable to generate unique attendance short_code during migration.');
    }
};
