<?php

namespace App\Console\Commands;

use App\Models\AttendanceForm;
use Illuminate\Console\Command;

class BackfillAttendanceShortCodesCommand extends Command
{
    protected $signature = 'sipreska:backfill-attendance-short-codes';

    protected $description = 'Fill missing short_code values on attendance_forms for short public links.';

    public function handle(): int
    {
        $query = AttendanceForm::query()->whereNull('short_code')->orderBy('id');
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Semua attendance_forms sudah memiliki short_code.');

            return self::SUCCESS;
        }

        $this->info("Mengisi short_code untuk {$total} form...");

        $filled = 0;

        $query->chunkById(100, function ($forms) use (&$filled): void {
            foreach ($forms as $form) {
                /** @var AttendanceForm $form */
                $form->short_code = AttendanceForm::generateUniqueShortCode();
                $form->save();
                $filled++;
            }
        });

        $this->info("Selesai. {$filled} short_code ditambahkan.");

        return self::SUCCESS;
    }
}
