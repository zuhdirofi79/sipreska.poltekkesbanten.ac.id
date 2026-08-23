<?php

namespace App\Http\Requests\ExternalActivity;

use App\Models\ExternalActivity;
use Illuminate\Foundation\Http\FormRequest;

class StorePublicExternalAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'institution' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'attendance_dates' => ['required', 'array', 'min:1'],
            'attendance_dates.*' => ['required', 'date'],
            'signature_data' => ['required', 'string'],
            'website' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Full Name wajib diisi.',
            'institution.required' => 'Institution wajib diisi.',
            'email.required' => 'Email Address wajib diisi.',
            'email.email' => 'Format Email Address tidak valid.',
            'attendance_dates.required' => 'Minimal satu tanggal kehadiran wajib dipilih.',
            'attendance_dates.min' => 'Minimal satu tanggal kehadiran wajib dipilih.',
            'signature_data.required' => 'Tanda tangan wajib diisi.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->filled('website')) {
                $validator->errors()->add('full_name', 'Permintaan tidak valid.');

                return;
            }

            $activity = $this->resolveExternalActivity();

            if ($activity === null) {
                $validator->errors()->add('full_name', 'Kegiatan tidak ditemukan.');

                return;
            }

            $allowed = $activity->allowedDateStrings();
            $selected = collect($this->input('attendance_dates', []))
                ->map(fn (mixed $date) => trim((string) $date))
                ->filter()
                ->unique()
                ->values();

            foreach ($selected as $date) {
                if (! in_array($date, $allowed, true)) {
                    $validator->errors()->add(
                        'attendance_dates',
                        'Tanggal kehadiran tidak termasuk dalam kegiatan ini.',
                    );

                    break;
                }
            }
        });
    }

    public function resolveExternalActivity(): ?ExternalActivity
    {
        $shortCode = $this->route('short_code');

        if (is_string($shortCode) && $shortCode !== '') {
            return ExternalActivity::query()
                ->with('dates')
                ->where('short_code', strtoupper($shortCode))
                ->first();
        }

        $publicToken = $this->route('public_token');

        if (is_string($publicToken) && $publicToken !== '') {
            return ExternalActivity::query()
                ->with('dates')
                ->where('public_token', $publicToken)
                ->first();
        }

        return null;
    }
}
