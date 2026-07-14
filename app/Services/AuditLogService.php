<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /** @var list<string> */
    private const SENSITIVE_EXACT_KEYS = [
        'nik',
        'nip',
        'nik_normalized',
        'nip_normalized',
        'nip_nik',
        'nip_nik_normalized',
    ];

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'token',
        'api_key',
        'secret',
    ];

    public function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null,
    ): ActivityLog {
        return ActivityLog::query()->create([
            'actor_user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }

    public function logModelChange(string $action, Model $model, ?array $oldValues, ?array $newValues, ?Request $request = null): ActivityLog
    {
        return $this->log(
            $action,
            $model->getMorphClass(),
            $model->getKey(),
            $oldValues,
            $newValues,
            $request,
        );
    }

    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $sanitized = [];

        foreach ($values as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        if (in_array($normalized, self::SENSITIVE_EXACT_KEYS, true)) {
            return true;
        }

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($normalized, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
