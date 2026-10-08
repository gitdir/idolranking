<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class AdminActivityLogger
{
    public static function record(int $adminIdx, string $action, string $targetType, int $targetIdx, ?string $reason = null, ?string $summary = null): void
    {
        DB::table('admin_activity_logs')->insert([
            'actor_member_idx' => $adminIdx,
            'action' => $action,
            'target_type' => $targetType,
            'target_idx' => $targetIdx,
            'reason' => $reason,
            'summary' => $summary,
            'created_at' => now(),
        ]);
    }
}
