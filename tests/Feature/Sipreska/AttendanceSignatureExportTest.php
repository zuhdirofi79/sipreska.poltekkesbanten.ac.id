<?php

namespace Tests\Feature\Sipreska;

use App\Enums\AttendanceWorkUnit;
use App\Models\Activity;
use App\Models\AttendanceForm;
use App\Models\AttendanceResponse;
use App\Support\AttendanceSignatureExport;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class AttendanceSignatureExportTest extends TestCase
{
    use SipreskaTestHelpers;

    private const SIGNATURE_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSipreskaBasics();
        Storage::fake('sipreska');
    }

    public function test_helper_builds_pdf_data_uri_from_stored_signature(): void
    {
        $path = 'docs/tanda_tangan/1/sig.png';
        Storage::disk('sipreska')->put($path, base64_decode(self::SIGNATURE_PNG_BASE64, true));

        $response = new AttendanceResponse([
            'signature_disk' => 'sipreska',
            'signature_path' => $path,
        ]);

        $src = AttendanceSignatureExport::pdfSrc($response);

        $this->assertNotNull($src);
        $this->assertStringStartsWith('data:image/png;base64,', $src);
        $this->assertSame('Ada', AttendanceSignatureExport::excelLabel($response));
    }

    public function test_helper_returns_dash_when_signature_missing(): void
    {
        $response = new AttendanceResponse([
            'signature_disk' => 'sipreska',
            'signature_path' => null,
        ]);

        $this->assertNull(AttendanceSignatureExport::pdfSrc($response));
        $this->assertSame('-', AttendanceSignatureExport::excelLabel($response));
    }

    public function test_excel_export_includes_signature_column_with_ada_or_dash(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = $this->createActivity();
        $form = $this->openForm($activity);

        $withPath = 'docs/tanda_tangan/'.$activity->id.'/with-sig.png';
        Storage::disk('sipreska')->put($withPath, base64_decode(self::SIGNATURE_PNG_BASE64, true));

        $withSignature = AttendanceResponse::query()->create([
            'attendance_form_id' => $form->id,
            'name' => 'Ada Signature',
            'nik' => '3201010101010001',
            'nik_normalized' => '3201010101010001',
            'work_unit' => AttendanceWorkUnit::D3Kebidanan->value,
            'signature_disk' => 'sipreska',
            'signature_path' => $withPath,
            'submitted_at' => now()->subMinute(),
        ]);

        $withoutSignature = AttendanceResponse::query()->create([
            'attendance_form_id' => $form->id,
            'name' => 'Tanpa Signature File',
            'nik' => '3201010101010002',
            'nik_normalized' => '3201010101010002',
            'work_unit' => AttendanceWorkUnit::D3Kebidanan->value,
            'signature_disk' => 'sipreska',
            'signature_path' => 'docs/tanda_tangan/'.$activity->id.'/missing.png',
            'submitted_at' => now(),
        ]);

        $this->assertSame('Ada', AttendanceSignatureExport::excelLabel($withSignature));
        $this->assertSame('-', AttendanceSignatureExport::excelLabel($withoutSignature));

        $response = $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.export-excel', $activity));

        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'attendance-sig-export-').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $reader = new Reader;
        $reader->open($path);

        $headers = [];
        $labelsByName = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                $values = array_map(fn ($cell) => $cell->getValue(), $row->getCells());

                if ($rowIndex === 1) {
                    $headers = $values;

                    continue;
                }

                $nameIndex = array_search('Nama', $headers, true);
                $signatureIndex = array_search('Tanda Tangan', $headers, true);
                $this->assertNotFalse($nameIndex);
                $this->assertNotFalse($signatureIndex);
                $labelsByName[$values[$nameIndex]] = $values[$signatureIndex];
            }
        }

        $reader->close();
        @unlink($path);

        $this->assertContains('Tanda Tangan', $headers);
        $this->assertSame('Ada', $labelsByName['Ada Signature']);
        $this->assertSame('-', $labelsByName['Tanpa Signature File']);
    }

    public function test_pdf_export_and_print_include_signature_column(): void
    {
        $admin = $this->adminUnit('d3-kebidanan');
        $activity = $this->createActivity();
        $form = $this->openForm($activity);

        $signaturePath = 'docs/tanda_tangan/'.$activity->id.'/export-sig.png';
        Storage::disk('sipreska')->put($signaturePath, base64_decode(self::SIGNATURE_PNG_BASE64, true));

        AttendanceResponse::query()->create([
            'attendance_form_id' => $form->id,
            'name' => 'Peserta Signature',
            'nik' => '3201010101010003',
            'nik_normalized' => '3201010101010003',
            'work_unit' => AttendanceWorkUnit::D3Kebidanan->value,
            'signature_disk' => 'sipreska',
            'signature_path' => $signaturePath,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.export-pdf', $activity))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin)
            ->get(route('activities.daftar-hadir.print', $activity))
            ->assertOk()
            ->assertSee('Tanda Tangan', false)
            ->assertSee('data:image/png;base64,', false)
            ->assertSee('Peserta Signature', false);
    }

    private function createActivity(): Activity
    {
        $admin = $this->adminUnit('d3-kebidanan');

        return Activity::factory()->create([
            'unit_id' => $admin->unit_id,
            'category_id' => $this->defaultCategory()->id,
            'title' => 'Signature Export Test',
        ]);
    }

    private function openForm(Activity $activity): AttendanceForm
    {
        return AttendanceForm::query()->create([
            'activity_id' => $activity->id,
            'public_token' => bin2hex(random_bytes(32)),
            'is_open' => true,
            'signature_required' => true,
        ]);
    }
}
