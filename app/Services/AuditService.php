<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;

class AuditService
{
    /**
     * Whitelist of allowed field attributes per business module.
     * Any attribute not listed here is discarded automatically.
     */
    public const ALLOWED_FIELDS = [
        'rooms' => [
            'number',
            'type',
            'monthly_rate',
            'notes',
            'archived_at',
        ],
        'residents' => [
            'name',
            'phone',
            'origin_address',
            'archived_at',
        ],
        'users' => [
            'role_id',
            'name',
            'email',
            'is_active',
            'must_change_password',
        ],
        'facilities' => [
            'code',
            'name',
            'location_type',
            'room_id',
            'area_name',
            'condition',
            'notes',
            'archived_at',
        ],
        'placements' => [
            'resident_id',
            'room_id',
            'started_on',
            'ended_on',
            'agreed_monthly_rate',
            'end_reason',
        ],
        'invoices' => [
            'placement_id',
            'period_month',
            'due_on',
            'amount',
            'resident_name_snapshot',
            'room_number_snapshot',
        ],
        'payments' => [
            'invoice_id',
            'receipt_number',
            'amount',
            'paid_on',
            'method',
            'reference',
            'status',
            'voided_at',
            'void_reason',
        ],
        'complaints' => [
            'placement_id',
            'facility_id',
            'subject',
            'description',
            'status',
            'closed_at',
        ],
        'complaint_updates' => [
            'complaint_id',
            'from_status',
            'to_status',
            'note',
            'occurred_at',
        ],
        'auth' => [
            'email',
            'ip_address',
            'user_agent',
        ],
    ];

    /**
     * Forbidden keywords for defense-in-depth sanitization.
     * Any key containing these substrings is unconditionally rejected.
     */
    public const FORBIDDEN_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'token',
        'secret',
        'cookie',
        'credential',
        'auth',
        'authorization',
    ];

    /**
     * Log an activity performed by an authenticated User.
     * The actor identity is strictly accepted from server context (User model),
     * never trusted from client/browser input.
     *
     * IMPORTANT: This method executes synchronously within the caller's DB transaction.
     * Any database error will be thrown to ensure the caller transaction rolls back.
     */
    public function log(
        string $action,
        string $module,
        string $summary,
        User $actor,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $entityLabel = null,
        ?array $changes = null
    ): ActivityLog {
        return $this->record(
            actorId: $actor->id,
            actorName: $actor->name,
            action: $action,
            module: $module,
            summary: $summary,
            entityType: $entityType,
            entityId: $entityId,
            entityLabel: $entityLabel,
            changes: $changes
        );
    }

    /**
     * Log an activity performed by the System (automated jobs, seeder, scheduler).
     * System actor is ONLY supported through this explicit method.
     */
    public function logSystem(
        string $action,
        string $module,
        string $summary,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $entityLabel = null,
        ?array $changes = null,
        string $systemName = 'Sistem'
    ): ActivityLog {
        return $this->record(
            actorId: null,
            actorName: $systemName,
            action: $action,
            module: $module,
            summary: $summary,
            entityType: $entityType,
            entityId: $entityId,
            entityLabel: $entityLabel,
            changes: $changes
        );
    }

    /**
     * Internal recording method that sanitizes data, applies server-side UTC timestamp,
     * and performs a synchronous INSERT into activity_logs.
     */
    protected function record(
        ?int $actorId,
        string $actorName,
        string $action,
        string $module,
        string $summary,
        ?string $entityType,
        ?int $entityId,
        ?string $entityLabel,
        ?array $changes
    ): ActivityLog {
        $sanitizedChanges = $this->sanitizeChanges($changes, $module);

        // Server-determined timestamp stored strictly in UTC
        $occurredAt = now('UTC');

        return ActivityLog::create([
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'action' => $action,
            'module' => $module,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_label' => $entityLabel,
            'summary' => $summary,
            'changes' => $sanitizedChanges,
            'occurred_at' => $occurredAt,
        ]);
    }

    /**
     * Sanitize changes adhering to the contract {"before": {...}, "after": {...}}.
     * Unknown fields are discarded. Nested arrays/objects on scalar fields are rejected.
     */
    public function sanitizeChanges(?array $changes, string $module): ?array
    {
        if ($changes === null || empty($changes)) {
            return null;
        }

        $allowedKeys = self::ALLOWED_FIELDS[$module] ?? [];

        // Structured changes contract: {"before": {...}, "after": {...}}
        if (array_key_exists('before', $changes) || array_key_exists('after', $changes)) {
            $sanitizedBefore = isset($changes['before']) && is_array($changes['before'])
                ? $this->filterFieldArray($changes['before'], $allowedKeys)
                : null;

            $sanitizedAfter = isset($changes['after']) && is_array($changes['after'])
                ? $this->filterFieldArray($changes['after'], $allowedKeys)
                : null;

            if (empty($sanitizedBefore) && empty($sanitizedAfter)) {
                return null;
            }

            return array_filter([
                'before' => !empty($sanitizedBefore) ? $sanitizedBefore : null,
                'after' => !empty($sanitizedAfter) ? $sanitizedAfter : null,
            ], fn ($val) => $val !== null);
        }

        // If a flat array was supplied (e.g. initial create attributes), treat as 'after'
        $sanitized = $this->filterFieldArray($changes, $allowedKeys);

        return !empty($sanitized) ? ['after' => $sanitized] : null;
    }

    /**
     * Filter an array of fields against the module allowlist and security denylist.
     * Scalar fields do not accept objects or arrays to prevent concealing credentials.
     */
    protected function filterFieldArray(array $data, array $allowedKeys): array
    {
        $filtered = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);

            // 1. Unconditional check against forbidden terms
            if ($this->isForbiddenKey($lowerKey)) {
                continue;
            }

            // 2. Allowlist verification per module
            if (!in_array($key, $allowedKeys, true)) {
                continue;
            }

            // 3. Reject non-scalar values (objects/arrays) to prevent nested payload leaks
            if (is_array($value) || is_object($value)) {
                continue;
            }

            // Accept valid scalar value or null
            $filtered[$key] = $value;
        }

        return $filtered;
    }

    /**
     * Determine whether a key matches any forbidden sensitive keywords.
     */
    protected function isForbiddenKey(string $key): bool
    {
        // must_change_password is a valid boolean status flag, not a credential
        if ($key === 'must_change_password') {
            return false;
        }

        foreach (self::FORBIDDEN_KEYS as $forbidden) {
            if (str_contains($key, $forbidden)) {
                return true;
            }
        }

        return false;
    }
}
