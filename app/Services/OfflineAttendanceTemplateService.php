<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\AttendanceForm;
use App\Support\AttendanceLetterhead;
use App\Support\AttendancePublicRoutes;
use App\Support\DateTimeFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class OfflineAttendanceTemplateService
{
    public function download(Activity $activity, AttendanceForm $form, int $rows = 30): Response
    {
        $rows = max(10, min(50, $rows));
        $slug = Str::slug($activity->title) ?: 'kegiatan';
        $filename = "template-daftar-hadir-{$slug}.pdf";

        $pdf = Pdf::loadView('exports.offline-attendance-template', [
            'activity' => $activity,
            'form' => $form,
            'rows' => $rows,
            'generatedAt' => DateTimeFormatter::display(now()),
            'letterheadSrc' => AttendanceLetterhead::pdfSrc(),
            'templateCode' => $form->short_code ?: $form->public_token,
            'qrSrc' => $this->qrDataUri($form),
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    private function qrDataUri(AttendanceForm $form): ?string
    {
        $url = AttendancePublicRoutes::showUrlForForm($form);

        if ($url === null || $url === '') {
            return null;
        }

        try {
            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'outputBase64' => false,
                'eccLevel' => EccLevel::M,
                'scale' => 4,
                'imageTransparent' => false,
                'addQuietzone' => true,
                'quietzoneSize' => 1,
            ]);

            $png = (new QRCode($options))->render($url);

            return 'data:image/png;base64,'.base64_encode($png);
        } catch (\Throwable) {
            return null;
        }
    }
}
