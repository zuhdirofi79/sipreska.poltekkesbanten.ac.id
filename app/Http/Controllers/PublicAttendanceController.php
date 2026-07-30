<?php

namespace App\Http\Controllers;

use App\Http\Requests\Activity\StorePublicAttendanceRequest;
use App\Models\AttendanceForm;
use App\Services\AttendanceService;
use App\Support\AttendanceConfirmation;
use App\Support\AttendancePublicRoutes;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

class PublicAttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    public function show(string $public_token): View
    {
        return $this->renderForm($this->findByPublicToken($public_token));
    }

    public function showByShortCode(string $short_code): View
    {
        return $this->renderForm($this->findByShortCode($short_code));
    }

    public function store(StorePublicAttendanceRequest $request, string $public_token): RedirectResponse
    {
        return $this->storeResponse(
            $request,
            $this->findByPublicToken($public_token),
            AttendancePublicRoutes::SHOW,
            ['public_token' => $public_token],
        );
    }

    public function storeByShortCode(StorePublicAttendanceRequest $request, string $short_code): RedirectResponse
    {
        $form = $this->findByShortCode($short_code);

        return $this->storeResponse(
            $request,
            $form,
            AttendancePublicRoutes::SHORT_SHOW,
            ['short_code' => $form->short_code],
        );
    }

    public function success(string $public_token): View
    {
        $attendanceForm = $this->findByPublicToken($public_token);

        return view('attendance.public-success', [
            'attendanceForm' => $attendanceForm,
            'activity' => $attendanceForm->activity,
            'successData' => session('attendance_success'),
        ]);
    }

    private function renderForm(AttendanceForm $attendanceForm): View
    {
        return view('attendance.public', [
            'attendanceForm' => $attendanceForm,
            'activity' => $attendanceForm->activity,
            'duplicateSubmission' => session('attendance_duplicate', false),
        ]);
    }

    private function storeResponse(
        StorePublicAttendanceRequest $request,
        AttendanceForm $attendanceForm,
        string $duplicateShowRoute,
        array $duplicateShowParams,
    ): RedirectResponse {
        try {
            $response = $this->attendanceService->submitResponse($attendanceForm, $request->validated(), $request);
        } catch (RuntimeException $exception) {
            if ($this->isDuplicateSubmissionError($exception)) {
                return redirect()
                    ->route($duplicateShowRoute, $duplicateShowParams)
                    ->with('attendance_duplicate', true);
            }

            return back()->withErrors(['form' => $exception->getMessage()])->withInput();
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                return redirect()
                    ->route($duplicateShowRoute, $duplicateShowParams)
                    ->with('attendance_duplicate', true);
            }

            throw $exception;
        }

        $attendanceForm->loadMissing('activity');

        $successData = [
            'name' => $response->name,
            'activity_title' => $attendanceForm->activity->title,
            'activity_date' => $attendanceForm->activity->activity_date?->toDateString(),
            'location' => $attendanceForm->activity->location,
            'submitted_at' => $response->submitted_at?->toIso8601String(),
            'reference_code' => AttendanceConfirmation::referenceCode($response),
        ];

        if ($response->masked_nip !== null) {
            $successData['nip_masked'] = $response->masked_nip;
        }

        return redirect()
            ->route(AttendancePublicRoutes::SUCCESS, ['public_token' => $attendanceForm->public_token])
            ->with('attendance_success', $successData);
    }

    private function findByPublicToken(string $publicToken): AttendanceForm
    {
        return AttendanceForm::query()
            ->with('activity.unit')
            ->where('public_token', $publicToken)
            ->firstOrFail();
    }

    private function findByShortCode(string $shortCode): AttendanceForm
    {
        return AttendanceForm::query()
            ->with('activity.unit')
            ->where('short_code', strtoupper($shortCode))
            ->firstOrFail();
    }

    private function isDuplicateSubmissionError(RuntimeException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'sudah tercatat untuk kegiatan ini');
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate')
            || $exception->getCode() === '23000';
    }
}
