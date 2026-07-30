<?php

namespace App\Http\Requests\Activity;

use App\Enums\AttendanceWorkUnit;
use App\Models\Activity;
use App\Models\OfflineAttendanceImport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOfflineAttendanceImportReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Activity $activity */
        $activity = $this->route('activity');
        /** @var OfflineAttendanceImport $import */
        $import = $this->route('import');

        return ($this->user()?->can('manageAttendance', $activity) ?? false)
            && (int) $import->activity_id === (int) $activity->id;
    }

    public function rules(): array
    {
        return [
            'default_work_unit' => ['nullable', 'string', Rule::enum(AttendanceWorkUnit::class)],
            'add_row' => ['nullable', 'boolean'],
            'rows' => ['nullable', 'array'],
            'rows.*.is_selected' => ['nullable', 'boolean'],
            'rows.*.review_name' => ['nullable', 'string', 'max:255'],
            'rows.*.review_nip' => ['nullable', 'string', 'max:50'],
            'rows.*.review_position' => ['nullable', 'string', 'max:255'],
            'rows.*.review_notes' => ['nullable', 'string', 'max:255'],
            'rows.*.signature' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }

    /**
     * @return array{default_work_unit?: ?string, rows: array<int, array<string, mixed>>}
     */
    public function reviewPayload(): array
    {
        $rows = [];

        foreach ((array) $this->input('rows', []) as $rowId => $row) {
            $rows[(int) $rowId] = [
                'is_selected' => (bool) ($row['is_selected'] ?? false),
                'review_name' => $row['review_name'] ?? null,
                'review_nip' => $row['review_nip'] ?? null,
                'review_position' => $row['review_position'] ?? null,
                'review_notes' => $row['review_notes'] ?? null,
                'signature' => $this->file("rows.{$rowId}.signature"),
            ];
        }

        return [
            'default_work_unit' => $this->filled('default_work_unit')
                ? (string) $this->input('default_work_unit')
                : null,
            'rows' => $rows,
        ];
    }
}
