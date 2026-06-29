<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    public function log(User $user, string $action, ?Model $subject = null, array $metadata = []): ActivityLog
    {
        return ActivityLog::query()->create([
            'user_id' => $user->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
        ]);
    }
}
