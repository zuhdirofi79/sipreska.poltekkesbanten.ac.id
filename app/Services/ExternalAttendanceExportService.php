<?php

namespace App\Services;

use App\Models\ExternalActivity;
use App\Support\DateTimeFormatter;
use App\Support\ExternalAttendanceSignatureExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExternalAttendanceExportService
{
    public function __construct(
        private readonly ExternalAttendanceService $attendanceService,
    ) {}

    /** @param array{search?: ?string, institution?: ?string, email?: ?string, attendance_date?: ?string, sort?: string} $filters */
    public function exportExcel(ExternalActivity $activity, array $filters = []): StreamedResponse
    {
        $participants = $this->attendanceService
            ->participantsQuery($activity, $filters)
            ->get();
        $filename = $this->buildFilename($activity, 'xlsx');
        $activity->loadMissing('questions');
        $eventDates = $activity->dates
            ->map(fn ($date) => $date->event_date?->format('Y-m-d'))
            ->filter()
            ->implode(', ');

        return response()->streamDownload(function () use ($activity, $participants, $eventDates) {
            $writer = new Writer;
            $writer->getOptions()->setColumnWidth(14, 10);
            $writer->openToFile('php://output');

            $questionColumns = $activity->questions
                ->map(fn ($question) => $question->label)
                ->all();

            $headerStyle = (new Style)->setFontBold();
            $textStyle = (new Style)->setFormat('@');

            $writer->addRow(Row::fromValues(array_merge([
                'No',
                'Full Name',
                'Institution',
                'Email Address',
            ], $questionColumns, [
                'Attendance Date',
                'Waktu Submit',
                'Tanda Tangan',
                'Nama Kegiatan',
                'Tanggal Kegiatan',
                'Status Kegiatan',
                'Lokasi Offline',
                'Link Kegiatan Online',
            ]), $headerStyle));

            foreach ($participants as $index => $participant) {
                $customAnswers = $activity->questions
                    ->map(fn ($question) => $this->excelText($participant->displayedAnswerFor($question)))
                    ->all();

                $writer->addRow(new Row(array_merge([
                    Cell::fromValue($index + 1),
                    Cell::fromValue($participant->full_name),
                    Cell::fromValue($participant->institution),
                    Cell::fromValue($this->excelText($participant->email), $textStyle),
                ], array_map(fn ($value) => Cell::fromValue($value, $textStyle), $customAnswers), [
                    Cell::fromValue($participant->formattedAttendanceDates('d M Y')),
                    Cell::fromValue(DateTimeFormatter::display($participant->submitted_at)),
                    Cell::fromValue(ExternalAttendanceSignatureExport::excelLabel($participant)),
                    Cell::fromValue($activity->title),
                    Cell::fromValue($eventDates !== '' ? $eventDates : '-'),
                    Cell::fromValue($activity->status->label()),
                    Cell::fromValue($this->excelText($activity->location_address)),
                    Cell::fromValue($this->excelText($activity->online_url), $textStyle),
                ])));
            }

            $writer->close();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** @param array{search?: ?string, institution?: ?string, email?: ?string, attendance_date?: ?string, sort?: string} $filters */
    public function exportPdf(ExternalActivity $activity, array $filters = []): Response
    {
        $participants = $this->attendanceService
            ->participantsQuery($activity, $filters)
            ->get();
        $filename = $this->buildFilename($activity, 'pdf');
        $activity->loadMissing('questions');

        $pdf = Pdf::loadView('exports.external-attendance-pdf', [
            'activity' => $activity,
            'participants' => $participants,
            'generatedAt' => DateTimeFormatter::display(now()),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    private function excelText(?string $value): string
    {
        return $value ?? '-';
    }

    private function buildFilename(ExternalActivity $activity, string $extension): string
    {
        $slug = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($activity->title)) ?: 'kegiatan-eksternal';
        $date = now()->format('Y-m-d');

        return "daftar-hadir-eksternal-{$slug}-{$date}.{$extension}";
    }
}
