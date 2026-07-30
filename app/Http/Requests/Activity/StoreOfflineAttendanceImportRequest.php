<?php

namespace App\Http\Requests\Activity;

use App\Models\Activity;
use Illuminate\Foundation\Http\FormRequest;

class StoreOfflineAttendanceImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Activity $activity */
        $activity = $this->route('activity');

        return $this->user()?->can('manageAttendance', $activity) ?? false;
    }

    public function rules(): array
    {
        return [
            'scan' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'scan.required' => 'File scan daftar hadir wajib diupload.',
            'scan.mimes' => 'File harus PDF, JPG, JPEG, atau PNG.',
            'scan.max' => 'Ukuran file maksimal 10 MB.',
        ];
    }
}
