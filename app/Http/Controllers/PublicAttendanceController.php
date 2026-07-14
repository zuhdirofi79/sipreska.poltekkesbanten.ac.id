<?php

namespace App\Http\Controllers;

use App\Http\Requests\Activity\StorePublicAttendanceRequest;
use App\Models\AttendanceForm;
use App\Services\AttendanceService;
use App\Support\AttendanceConfirmation;
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
        $attendanceForm = AttendanceForm::query()
            ->with('activity.unit')
            ->where('public_token', $public_token)
            ->firstOrFail();

        return view('attendance.public', [
            'attendanceForm' => $attendanceForm,
            'activity' => $attendanceForm->activity,
            'duplicateSubmission' => session('attendance_duplicate', false),
        ]);
    }

    public function store(StorePublicAttendanceRequest $request, string $public_token): RedirectResponse
    {
        $attendanceForm = AttendanceForm::query()
            ->with('activity')
            ->where('public_token', $public_token)
            ->firstOrFail();

        try {
            $response = $this->attendanceService->submitResponse($attendanceForm, $request->validated(), $request);
        } catch (RuntimeException $exception) {
            if ($this->isDuplicateSubmissionError($exception)) {
                return redirect()
                    ->route('attendance.public.show', ['public_token' => $public_token])
                    ->with('attendance_duplicate', true);
            }

            return back()->withErrors(['form' => $exception->getMessage()])->withInput();
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                return redirect()
                    ->route('attendance.public.show', ['public_token' => $public_token])
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

        if ($response->masked_nik !== null) {
            $successData['nik_masked'] = $response->masked_nik;
        }

        if ($response->masked_nip !== null) {
            $successData['nip_masked'] = $response->masked_nip;
        }

        return redirect()
            ->route('attendance.public.success', ['public_token' => $attendanceForm->public_token])
            ->with('attendance_success', $successData);
    }

    public function success(string $public_token): View
    {
        $attendanceForm = AttendanceForm::query()
            ->with('activity')
            ->where('public_token', $public_token)
            ->firstOrFail();

        return view('attendance.public-success', [
            'attendanceForm' => $attendanceForm,
            'activity' => $attendanceForm->activity,
            'successData' => session('attendance_success'),
        ]);
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
