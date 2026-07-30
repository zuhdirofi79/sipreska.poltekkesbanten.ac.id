<?php

namespace App\Services;

use App\Enums\AttendanceWorkUnit;
use App\Models\Activity;
use App\Models\AttendanceForm;
use App\Support\AttendanceSignatureExport;
use App\Support\DateTimeFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use OpenSpout\Common\Entity\Cell;
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
            ->get();
        $filename = $this->buildFilename($activity, 'xlsx');

        return response()->streamDownload(function () use ($activity, $responses) {
            $writer = new Writer;
            // OpenSpout has no image embed API; widen the text indicator column.
            $writer->getOptions()->setColumnWidth(14, 10);
            $writer->openToFile('php://output');

            $headerStyle = (new Style)->setFontBold();
            $textStyle = (new Style)->setFormat('@');

            $writer->addRow(Row::fromValues([
                'No',
                'Nama',
                'NIP',
                'Identitas Lama',
                'Jabatan',
                'Unit Kerja',
                'Instansi',
                'Waktu Submit',
                'Tanda Tangan',
                'Judul Kegiatan',
                'Tanggal Kegiatan',
                'Tempat',
                'Unit Penyelenggara',
            ], $headerStyle));

            foreach ($responses as $index => $response) {
                $workUnit = AttendanceWorkUnit::tryFrom($response->work_unit);

                $writer->addRow(new Row([
                    Cell::fromValue($index + 1),
                    Cell::fromValue($response->name),
                    Cell::fromValue($this->excelText($response->nip), $textStyle),
                    Cell::fromValue($this->excelText($response->legacyIdentity()), $textStyle),
                    Cell::fromValue($response->position ?? '-'),
                    Cell::fromValue($workUnit?->label() ?? $response->work_unit),
                    Cell::fromValue($response->institution_name ?? '-'),
                    Cell::fromValue(DateTimeFormatter::display($response->submitted_at)),
                    // Text indicator only — OpenSpout cannot embed signature PNGs.
                    Cell::fromValue(AttendanceSignatureExport::excelLabel($response)),
                    Cell::fromValue($activity->title),
                    Cell::fromValue($activity->activity_date?->format('Y-m-d') ?? '-'),
                    Cell::fromValue($activity->location ?? '-'),
                    Cell::fromValue($activity->unit?->short_name ?? '-'),
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
            ->get();
        $filename = $this->buildFilename($activity, 'pdf');

        $pdf = Pdf::loadView('exports.attendance-pdf', [
            'activity' => $activity,
            'responses' => $responses,
            'generatedAt' => DateTimeFormatter::display(now()),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    private function excelText(?string $value): string
    {
        return $value ?? '-';
    }

    private function buildFilename(Activity $activity, string $extension): string
    {
        $slug = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($activity->title)) ?: 'kegiatan';
        $date = $activity->activity_date?->format('Y-m-d') ?? now()->format('Y-m-d');

        return "daftar-hadir-{$slug}-{$date}.{$extension}";
    }
}
