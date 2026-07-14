<?php

namespace App\Rules;

use App\Services\AttendanceService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidIdentityNumber implements ValidationRule
{
    public function __construct(
        private readonly string $message = 'Format identitas tidak valid.',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail($this->message);

            return;
        }

        if (strlen($value) > 32) {
            $fail($this->message);

            return;
        }

        if (! preg_match('/^[\d\s.\-]+$/', $value)) {
            $fail($this->message);

            return;
        }

        $normalized = app(AttendanceService::class)->normalizeIdentity($value);

        if ($normalized === null || strlen($normalized) > 32) {
            $fail($this->message);
        }
    }
}
