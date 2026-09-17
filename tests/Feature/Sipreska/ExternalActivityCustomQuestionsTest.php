<?php

namespace Tests\Feature\Sipreska;

use App\Enums\ExternalActivityQuestionType;
use App\Enums\ExternalActivityStatus;
use App\Models\ExternalActivity;
use App\Models\ExternalActivityQuestion;
use App\Models\ExternalAttendanceAnswer;
use App\Models\ExternalAttendanceParticipant;
use App\Services\ExternalActivityQuestionService;
use App\Support\ExternalAttendancePublicRoutes;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SipreskaTestHelpers;
use Tests\TestCase;

class ExternalActivityCustomQuestionsTest extends TestCase
{
    use SipreskaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSipreskaBasics();
        Storage::fake('sipreska');
        app()->setLocale('id');
    }

    /** @param list<string> $dates */
    private function payload(array $dates = ['2026-08-25', '2026-08-26'], array $overrides = []): array
    {
        return array_merge([
            'title' => 'Seminar and Job Fair 2026 Health Career Gateway',
            'status' => ExternalActivityStatus::Terdaftar->value,
            'is_open' => '1',
            'dates' => $dates,
        ], $overrides);
    }

    /** @param list<string> $dates */
    private function createEvent(array $dates = ['2026-08-25', '2026-08-26'], array $overrides = []): ExternalActivity
    {
        $activity = ExternalActivity::factory()->create(array_merge([
            'title' => 'Seminar and Job Fair 2026 Health Career Gateway',
            'is_open' => true,
            'use_custom_questions' => false,
            'created_by' => $this->superadmin()->id,
        ], $overrides));

        foreach ($dates as $date) {
            $activity->dates()->create(['event_date' => $date]);
        }

        return $activity->fresh(['dates', 'questions']) ?? $activity;
    }

    /** @param array<string, mixed> $overrides */
    private function attendancePayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Budi',
            'institution' => 'Universitas X',
            'email' => 'budi@example.com',
            'attendance_dates' => ['2026-08-25'],
            'signature_data' => $this->minimalPngBase64(),
        ], $overrides);
    }

    private function enableCustomQuestions(ExternalActivity $activity, array $questionOverrides = []): ExternalActivity
    {
        $this->actingAs($this->superadmin())
            ->put(route('external-activities.update', $activity), $this->payload(
                $activity->allowedDateStrings(),
                array_merge(['use_custom_questions' => '1'], $questionOverrides),
            ))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return $activity->fresh(['dates', 'questions']) ?? $activity;
    }

    /** @return array<int, mixed> */
    private function answersFor(ExternalActivity $activity, array $overrides = []): array
    {
        $answers = [];

        foreach ($activity->questions()->active()->get() as $question) {
            $answers[$question->id] = $overrides[$question->default_key] ?? $overrides[$question->id] ?? match ($question->default_key) {
                'nama_lengkap' => 'Budi Santoso',
                'status_peserta' => 'Alumni',
                'nomor_hp' => '081234567890',
                'alamat_email' => 'budi.alumni@example.com',
                'wilayah_domisili' => 'Kota Tangerang',
                'asal_institusi_kelulusan' => 'Poltekkes Kemenkes Banten',
                'tahun_lulus' => '2020',
                'program_studi' => 'D3 Kebidanan',
                'perusahaan_diharapkan' => 'RSUD Provinsi Banten',
                default => $this->fallbackAnswer($question),
            };
        }

        return $answers;
    }

    private function fallbackAnswer(ExternalActivityQuestion $question): mixed
    {
        $options = $question->allowedOptions();

        return match ($question->type) {
            ExternalActivityQuestionType::Checkbox => $options === [] ? [] : [$options[0]],
            ExternalActivityQuestionType::Select, ExternalActivityQuestionType::Radio => $options[0] ?? 'Opsi',
            ExternalActivityQuestionType::Number => '1',
            ExternalActivityQuestionType::Email => 'peserta@example.com',
            ExternalActivityQuestionType::Date => '2026-08-25',
            default => 'Jawaban',
        };
    }

    public function test_custom_questions_off_keeps_existing_public_attendance_flow(): void
    {
        $activity = $this->createEvent();

        $this->get(ExternalAttendancePublicRoutes::showUrlForActivity($activity))
            ->assertOk()
            ->assertSee('Full Name', false)
            ->assertDontSee('Nama Lengkap', false)
            ->assertDontSee('Status Peserta', false);

        $this->post(ExternalAttendancePublicRoutes::storeUrlForActivity($activity), $this->attendancePayload())
            ->assertRedirect(route(ExternalAttendancePublicRoutes::SUCCESS, ['public_token' => $activity->public_token]));

        $this->assertSame(1, $activity->participants()->count());
        $this->assertSame(0, ExternalAttendanceAnswer::query()->count());
    }

    public function test_enabling_custom_questions_seeds_nine_default_questions(): void
    {
        $activity = $this->createEvent();
        $activity = $this->enableCustomQuestions($activity);

        $this->assertTrue($activity->use_custom_questions);
        $this->assertSame(9, $activity->questions()->count());
        $this->assertSame(
            collect(ExternalActivityQuestionService::defaultDefinitions())->pluck('label')->all(),
            $activity->questions()->orderBy('sort_order')->pluck('label')->all(),
        );

        $this->get(ExternalAttendancePublicRoutes::showUrlForActivity($activity))
            ->assertOk()
            ->assertSee('Nama Lengkap', false)
            ->assertSee('Status Peserta', false)
            ->assertSee('Nomor HP', false)
            ->assertSee('Alamat Email', false)
            ->assertSee('Wilayah Domisili', false)
            ->assertSee('Asal Institusi Kelulusan', false)
            ->assertSee('Tahun Lulus', false)
            ->assertSee('Program Studi', false)
            ->assertSee('Rumah Sakit/Klinik/Perusahaan yang paling Anda harapkan hadir dalam Job Fair?', false);
    }

    public function test_submit_valid_custom_answers_persists_relational_records(): void
    {
        $activity = $this->enableCustomQuestions($this->createEvent());

        $this->post(ExternalAttendancePublicRoutes::storeUrlForActivity($activity), $this->attendancePayload([
            'custom_answers' => $this->answersFor($activity),
        ]))->assertRedirect();

        $participant = ExternalAttendanceParticipant::query()->firstOrFail();
        $this->assertSame(9, $participant->answers()->count());
        $this->assertSame(
            'Budi Santoso',
            $participant->answers()->whereHas('question', fn ($query) => $query->where('default_key', 'nama_lengkap'))->value('value'),
        );
        $this->assertSame(
            'Alumni',
            $participant->answers()->whereHas('question', fn ($query) => $query->where('default_key', 'status_peserta'))->value('value'),
        );

        $this->actingAs($this->superadmin())
            ->get(route('external-activities.attendance.show', [$activity, $participant]))
            ->assertOk()
            ->assertSee('Budi Santoso', false)
            ->assertSee('Alumni', false)
            ->assertSee('081234567890', false);
    }

    public function test_required_custom_question_cannot_be_empty(): void
    {
        $activity = $this->enableCustomQuestions($this->createEvent());
        $required = $activity->questions()->active()->where('is_required', true)->firstOrFail();
        $answers = $this->answersFor($activity);
        $answers[$required->id] = '';

        $this->post(ExternalAttendancePublicRoutes::storeUrlForActivity($activity), $this->attendancePayload([
            'custom_answers' => $answers,
        ]))->assertSessionHasErrors('custom_answers.'.$required->id);

        $this->assertSame(0, ExternalAttendanceParticipant::query()->count());
    }

    public function test_optional_custom_question_can_be_empty(): void
    {
        $activity = $this->enableCustomQuestions($this->createEvent());
        $optional = $activity->questions()->where('default_key', 'perusahaan_diharapkan')->firstOrFail();
        $answers = $this->answersFor($activity);
        unset($answers[$optional->id]);

        $this->post(ExternalAttendancePublicRoutes::storeUrlForActivity($activity), $this->attendancePayload([
            'custom_answers' => $answers,
        ]))->assertRedirect();

        $participant = ExternalAttendanceParticipant::query()->firstOrFail();
        $this->assertNull($participant->answerFor($optional)?->value);
    }

    public function test_admin_can_add_custom_question_and_it_appears_on_public_form(): void
    {
        $activity = $this->enableCustomQuestions($this->createEvent());
        $existing = $activity->questions->map(fn ($question) => $question->toBuilderArray())->all();
        $existing[] = [
            'id' => null,
            'label' => 'Apakah Anda bersedia dihubungi oleh panitia?',
            'type' => ExternalActivityQuestionType::Radio->value,
            'is_required' => '1',
            'is_active' => '1',
            'options_text' => "Ya\nTidak",
            'default_key' => null,
        ];

        $this->actingAs($this->superadmin())
            ->put(route('external-activities.update', $activity), $this->payload(
                $activity->allowedDateStrings(),
                [
                    'use_custom_questions' => '1',
                    'questions' => $existing,
                ],
            ))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(10, $activity->questions()->count());

        $this->get(ExternalAttendancePublicRoutes::showUrlForActivity($activity))
            ->assertOk()
            ->assertSee('Apakah Anda bersedia dihubungi oleh panitia?', false)
            ->assertSee('type="radio"', false);
    }

    public function test_inactive_question_is_hidden_from_public_form_and_can_be_reenabled(): void
    {
        $activity = $this->enableCustomQuestions($this->createEvent());
        $phone = $activity->questions()->where('default_key', 'nomor_hp')->firstOrFail();

        $questions = $activity->questions->map(function ($question) use ($phone) {
            $row = $question->toBuilderArray();
            if ((int) $question->id === (int) $phone->id) {
                $row['is_active'] = '0';
            }

            return $row;
        })->all();

        $this->actingAs($this->superadmin())
            ->put(route('external-activities.update', $activity), $this->payload(
                $activity->allowedDateStrings(),
                ['use_custom_questions' => '1', 'questions' => $questions],
            ))
            ->assertRedirect();

        $this->get(ExternalAttendancePublicRoutes::showUrlForActivity($activity))
            ->assertOk()
            ->assertDontSee('Nomor HP', false);

        $questions = $activity->fresh('questions')->questions->map(function ($question) use ($phone) {
            $row = $question->toBuilderArray();
            if ((int) $question->id === (int) $phone->id) {
                $row['is_active'] = '1';
            }

            return $row;
        })->all();

        $this->actingAs($this->superadmin())
            ->put(route('external-activities.update', $activity), $this->payload(
                $activity->allowedDateStrings(),
                ['use_custom_questions' => '1', 'questions' => $questions],
            ))
            ->assertRedirect();

        $this->get(ExternalAttendancePublicRoutes::showUrlForActivity($activity))
            ->assertOk()
            ->assertSee('Nomor HP', false);
    }

    public function test_repeated_edit_does_not_duplicate_default_questions(): void
    {
        $activity = $this->enableCustomQuestions($this->createEvent());
        $this->assertSame(9, $activity->questions()->count());

        $activity = $this->enableCustomQuestions($activity);
        $activity = $this->enableCustomQuestions($activity);

        $this->assertSame(9, $activity->questions()->count());
        $this->assertSame(9, $activity->questions()->pluck('default_key')->filter()->unique()->count());
    }

    public function test_turning_feature_off_hides_questions_but_keeps_configuration(): void
    {
        $activity = $this->enableCustomQuestions($this->createEvent());

        $this->actingAs($this->superadmin())
            ->put(route('external-activities.update', $activity), $this->payload(
                $activity->allowedDateStrings(),
                ['use_custom_questions' => '0'],
            ))
            ->assertRedirect();

        $activity->refresh();
        $this->assertFalse($activity->use_custom_questions);
        $this->assertSame(9, $activity->questions()->count());

        $this->get(ExternalAttendancePublicRoutes::showUrlForActivity($activity))
            ->assertOk()
            ->assertDontSee('Status Peserta', false);

        $this->post(ExternalAttendancePublicRoutes::storeUrlForActivity($activity), $this->attendancePayload())
            ->assertRedirect();

        $this->assertSame(0, ExternalAttendanceAnswer::query()->count());
    }

    public function test_historical_answers_remain_after_question_is_deactivated(): void
    {
        $activity = $this->enableCustomQuestions($this->createEvent());

        $this->post(ExternalAttendancePublicRoutes::storeUrlForActivity($activity), $this->attendancePayload([
            'custom_answers' => $this->answersFor($activity),
        ]))->assertRedirect();

        $phone = $activity->questions()->where('default_key', 'nomor_hp')->firstOrFail();
        $questions = $activity->questions->map(function ($question) use ($phone) {
            $row = $question->toBuilderArray();
            if ((int) $question->id === (int) $phone->id) {
                $row['is_active'] = '0';
            }

            return $row;
        })->all();

        $this->actingAs($this->superadmin())
            ->put(route('external-activities.update', $activity), $this->payload(
                $activity->allowedDateStrings(),
                ['use_custom_questions' => '1', 'questions' => $questions],
            ))
            ->assertRedirect();

        $this->assertSame('081234567890', ExternalAttendanceAnswer::query()->where('question_id', $phone->id)->value('value'));
        $this->assertTrue($phone->fresh()->exists);
        $this->assertFalse($phone->fresh()->is_active);

        $participant = ExternalAttendanceParticipant::query()->firstOrFail();
        $this->actingAs($this->superadmin())
            ->get(route('external-activities.attendance.show', [$activity, $participant]))
            ->assertOk()
            ->assertSee('081234567890', false);
    }

    public function test_question_id_from_another_activity_is_rejected(): void
    {
        $activityA = $this->enableCustomQuestions($this->createEvent(['2026-08-25']));
        $activityB = $this->enableCustomQuestions($this->createEvent(['2026-09-01'], ['title' => 'Event B']));
        $foreignQuestion = $activityB->questions()->where('default_key', 'nama_lengkap')->firstOrFail();

        $answers = $this->answersFor($activityA);
        $answers[$foreignQuestion->id] = 'Hacked Name';

        $this->post(ExternalAttendancePublicRoutes::storeUrlForActivity($activityA), $this->attendancePayload([
            'attendance_dates' => ['2026-08-25'],
            'custom_answers' => $answers,
        ]))->assertRedirect();

        $this->assertFalse(
            ExternalAttendanceAnswer::query()->where('question_id', $foreignQuestion->id)->exists()
        );
        $this->assertSame(1, $activityA->participants()->count());
        $this->assertSame(0, $activityB->participants()->count());
    }

    public function test_multi_day_attendance_stores_one_participant_profile_and_answer_set(): void
    {
        $activity = $this->enableCustomQuestions($this->createEvent(['2026-08-25', '2026-08-26', '2026-08-27']));

        $this->post(ExternalAttendancePublicRoutes::storeUrlForActivity($activity), $this->attendancePayload([
            'attendance_dates' => ['2026-08-25', '2026-08-26', '2026-08-27'],
            'custom_answers' => $this->answersFor($activity),
        ]))->assertRedirect();

        $this->assertSame(1, $activity->participants()->count());
        $participant = $activity->participants()->firstOrFail();
        $this->assertCount(3, $participant->attendanceDates);
        $this->assertSame(9, $participant->answers()->count());

        $this->post(ExternalAttendancePublicRoutes::storeUrlForActivity($activity), $this->attendancePayload([
            'attendance_dates' => ['2026-08-26'],
            'custom_answers' => $this->answersFor($activity),
        ]))->assertRedirect()->assertSessionHas('external_attendance_duplicate', true);

        $this->assertSame(1, $activity->participants()->count());
        $this->assertSame(9, ExternalAttendanceAnswer::query()->count());
    }

    public function test_guest_cannot_manage_custom_questions(): void
    {
        $activity = $this->createEvent();

        $this->put(route('external-activities.update', $activity), $this->payload(
            $activity->allowedDateStrings(),
            ['use_custom_questions' => '1'],
        ))->assertRedirect(route('login'));

        $this->assertFalse($activity->fresh()->use_custom_questions);
        $this->assertSame(0, $activity->questions()->count());
    }

    public function test_select_answer_must_come_from_configured_options(): void
    {
        $activity = $this->enableCustomQuestions($this->createEvent());
        $status = $activity->questions()->where('default_key', 'status_peserta')->firstOrFail();
        $answers = $this->answersFor($activity);
        $answers[$status->id] = 'Bukan Opsi';

        $this->post(ExternalAttendancePublicRoutes::storeUrlForActivity($activity), $this->attendancePayload([
            'custom_answers' => $answers,
        ]))->assertSessionHasErrors('custom_answers.'.$status->id);

        $this->assertSame(0, ExternalAttendanceParticipant::query()->count());
    }

    public function test_export_still_works_when_custom_questions_are_enabled(): void
    {
        $admin = $this->superadmin();
        $activity = $this->enableCustomQuestions($this->createEvent());

        $this->post(ExternalAttendancePublicRoutes::storeUrlForActivity($activity), $this->attendancePayload([
            'custom_answers' => $this->answersFor($activity),
        ]))->assertRedirect();

        $this->actingAs($admin)
            ->get(route('external-activities.attendance.export-excel', $activity))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($admin)
            ->get(route('external-activities.attendance.export-pdf', $activity))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin)
            ->get(route('external-activities.attendance.index', $activity))
            ->assertOk()
            ->assertSee('Budi Santoso', false)
            ->assertSee('Status Peserta', false);
    }
}
