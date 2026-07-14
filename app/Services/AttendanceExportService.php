<?php

namespace App\Services;

use App\Enums\AttendanceWorkUnit;
use App\Models\Activity;
use App\Models\AttendanceForm;
use App\Support\DateTimeFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceExportService
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    /** @param array{search?: ?string, work_unit?: ?string, date_from?: ?string, date_to?: ?string, sort?: string} $filters */
    public function exportExcel(Activity $activity, AttendanceForm $form, array $filters = []): StreamedResponse
    {
        $responses = $this->attendanceService
            ->responsesQuery($form, $filters)
            ->orderBy('submitted_at')
            ->get();
        $filename = $this->buildFilename($activity, 'xlsx');

        return response()->streamDownload(function () use ($activity, $responses) {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $headerStyle = (new Style)->setFontBold();
            $writer->addRow(Row::fromValues([
                'No',
                'Nama',
                'NIP/NIK',
                'Jabatan',
                'Unit Kerja',
                'Instansi',
                'Waktu Submit',
            ], $headerStyle));

            foreach ($responses as $index => $response) {
                $workUnit = AttendanceWorkUnit::tryFrom($response->work_unit);

                $writer->addRow(Row::fromValues([
                    $index + 1,
                    $response->name,
                    $response->nip_nik,
                    $response->position ?? '-',
                    $workUnit?->label() ?? $response->work_unit,
                    $response->institution_name ?? '-',
                    DateTimeFormatter::display($response->submitted_at),
                ]));
            }

            $writer->close();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** @param array{search?: ?string, work_unit?: ?string, date_from?: ?string, date_to?: ?string, sort?: string} $filters */
    public function exportPdf(Activity $activity, AttendanceForm $form, array $filters = []): Response
    {
        $responses = $this->attendanceService
            ->responsesQuery($form, $filters)
            ->orderBy('submitted_at')
            ->get();
        $filename = $this->buildFilename($activity, 'pdf');

        $pdf = Pdf::loadView('exports.attendance-pdf', [
            'activity' => $activity,
            'responses' => $responses,
            'generatedAt' => DateTimeFormatter::display(now()),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    private function buildFilename(Activity $activity, string $extension): string
    {
        $slug = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($activity->title)) ?: 'kegiatan';
        $date = $activity->activity_date?->format('Y-m-d') ?? now()->format('Y-m-d');

        return "daftar-hadir-{$slug}-{$date}.{$extension}";
    }
}
