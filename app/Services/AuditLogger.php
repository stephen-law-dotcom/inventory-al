<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public static function log(string $event, string $description, ?Model $subject = null, array $properties = []): void
    {
        $request = request();

        $log = ActivityLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'properties' => $properties ?: null,
        ]);

        NotificationService::fromActivity($log);
    }
}
