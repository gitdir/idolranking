<?php

namespace App\Http\Controllers;

use App\Support\ManagedImageStorage;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class IdolProfileImageReportController extends Controller
{
    public function store(Request $request, string $idx)
    {
        $validated = $request->validate([
            'reason' => ['required', Rule::in(['copyright', 'portrait_rights', 'other'])],
            'details' => ['nullable', 'string', 'max:2000', 'required_if:reason,other'],
        ]);
        $member = $request->attributes->get('activityMember');
        $actor = $request->attributes->get('activityActor');

        try {
            $saved = DB::transaction(function () use ($idx, $validated, $member, $actor): bool {
                $idol = DB::table('idols')->where('idx', $idx)->where('status', 'active')
                    ->lockForUpdate()->first(['idx', 'image_path']);
                if ($idol === null || !$this->reportablePath($idol->image_path)) {
                    return false;
                }

                DB::table('idol_profile_image_reports')->insert([
                    'idol_idx' => $idol->idx,
                    'member_idx' => $member->idx,
                    'actor_member_idx' => $actor->idx,
                    'reported_image_path' => $idol->image_path,
                    'image_path_hash' => hash('sha256', $idol->image_path),
                    'reason' => $validated['reason'],
                    'details' => filled($validated['details'] ?? null) ? trim($validated['details']) : null,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return true;
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                return response()->json(['success' => false, 'message' => __('ui.profile_image_already_reported')], 409);
            }

            throw $exception;
        }

        if (!$saved) {
            return response()->json(['success' => false, 'message' => __('ui.profile_image_not_reportable')], 404);
        }

        return response()->json(['success' => true, 'message' => __('ui.profile_image_report_submitted')], 201);
    }

    private function reportablePath(?string $path): bool
    {
        return is_string($path)
            && str_starts_with($path, 'storage/')
            && !str_contains($path, '..')
            && $path !== 'storage/'.ltrim(ManagedImageStorage::DEFAULT_IMAGE_PATH, '/')
            && ManagedImageStorage::isAvailable($path);
    }
}
