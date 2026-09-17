<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExternalActivity\StorePublicExternalAttendanceRequest;
use App\Models\ExternalActivity;
use App\Services\ExternalAttendanceService;
use App\Support\ExternalAttendancePublicRoutes;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

class PublicExternalAttendanceController extends Controller
{
    public function __construct(
        private readonly ExternalAttendanceService $attendanceService,
    ) {}

    public function show(string $public_token): View
    {
        return $this->renderForm($this->findByPublicToken($public_token));
    }

    public function showByShortCode(string $short_code): View
    {
        return $this->renderForm($this->findByShortCode($short_code));
    }

    public function store(StorePublicExternalAttendanceRequest $request, string $public_token): RedirectResponse
    {
        return $this->storeResponse(
            $request,
            $this->findByPublicToken($public_token),
            ExternalAttendancePublicRoutes::SHOW,
            ['public_token' => $public_token],
        );
    }

    public function storeByShortCode(StorePublicExternalAttendanceRequest $request, string $short_code): RedirectResponse
    {
        $activity = $this->findByShortCode($short_code);

        return $this->storeResponse(
            $request,
            $activity,
            ExternalAttendancePublicRoutes::SHORT_SHOW,
            ['short_code' => $activity->short_code],
        );
    }

    public function success(string $public_token): View
    {
        $activity = $this->findByPublicToken($public_token);

        return view('external-attendance.public-success', [
            'activity' => $activity,
            'successData' => session('external_attendance_success'),
        ]);
    }

    private function renderForm(ExternalActivity $activity): View
    {
        $activity->load(['dates', 'questions']);

        return view('external-attendance.public', [
            'activity' => $activity,
            'questions' => $activity->use_custom_questions
                ? $activity->questions->where('is_active', true)->values()
                : collect(),
            'duplicateSubmission' => session('external_attendance_duplicate', false),
        ]);
    }

    private function storeResponse(
        StorePublicExternalAttendanceRequest $request,
        ExternalActivity $activity,
        string $duplicateShowRoute,
        array $duplicateShowParams,
    ): RedirectResponse {
        try {
            $participant = $this->attendanceService->submitResponse($activity, $request->validated(), $request);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === ExternalAttendanceService::DUPLICATE_MESSAGE) {
                return redirect()
                    ->route($duplicateShowRoute, $duplicateShowParams)
                    ->with('external_attendance_duplicate', true);
            }

            return back()->withErrors(['form' => $exception->getMessage()])->withInput();
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                return redirect()
                    ->route($duplicateShowRoute, $duplicateShowParams)
                    ->with('external_attendance_duplicate', true);
            }

            throw $exception;
        }

        $participant->load('attendanceDates');

        return redirect()
            ->route(ExternalAttendancePublicRoutes::SUCCESS, ['public_token' => $activity->public_token])
            ->with('external_attendance_success', [
                'name' => $participant->full_name,
                'institution' => $participant->institution,
                'email' => $participant->email,
                'activity_title' => $activity->title,
                'attendance_dates' => $participant->formattedAttendanceDates('d F Y'),
                'submitted_at' => $participant->submitted_at?->toIso8601String(),
                'reference_code' => $participant->referenceCode(),
            ]);
    }

    private function findByPublicToken(string $publicToken): ExternalActivity
    {
        return ExternalActivity::query()
            ->with(['dates', 'questions'])
            ->where('public_token', $publicToken)
            ->firstOrFail();
    }

    private function findByShortCode(string $shortCode): ExternalActivity
    {
        return ExternalActivity::query()
            ->with(['dates', 'questions'])
            ->where('short_code', strtoupper($shortCode))
            ->firstOrFail();
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate')
            || $exception->getCode() === '23000';
    }
}
