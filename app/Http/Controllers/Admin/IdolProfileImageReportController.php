<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class IdolProfileImageReportController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $reports = DB::table('idol_profile_image_reports as report')
            ->join('idols as idol', 'idol.idx', '=', 'report.idol_idx')
            ->join('members as reporter', 'reporter.idx', '=', 'report.member_idx')
            ->when(in_array($status, ['pending', 'reviewing', 'reviewed', 'rejected'], true), fn ($query) => $query->where('report.status', $status))
            ->orderByRaw("CASE report.status WHEN 'pending' THEN 0 WHEN 'reviewing' THEN 1 WHEN 'reviewed' THEN 2 ELSE 3 END")
            ->orderByDesc('report.created_at')->orderByDesc('report.idx')
            ->select('report.*', 'idol.name as idol_name', 'idol.english_name', 'idol.type as idol_type', 'reporter.nickname as reporter_nickname')
            ->paginate(20)->withQueryString();

        return view('admin.idol-profile-image-reports.index', compact('reports', 'status'));
    }

    public function show(int $idx)
    {
        $report = $this->reportQuery($idx)->first();
        abort_if($report === null, 404);

        return view('admin.idol-profile-image-reports.show', compact('report'));
    }

    public function evidence(int $idx)
    {
        $report = DB::table('idol_profile_image_reports')->where('idx', $idx)
            ->first(['reported_image_path', 'evidence_path']);
        abort_if($report === null, 404);

        if ($report->evidence_path !== null) {
            abort_unless(str_starts_with($report->evidence_path, 'idol-rights-evidence/') && !str_contains($report->evidence_path, '..'), 404);
        }
        $disk = $report->evidence_path ? 'local' : 'public';
        $path = $report->evidence_path ?: $this->publicStorageRelativePath($report->reported_image_path);
        abort_if($path === null || !Storage::disk($disk)->exists($path), 404);

        $mime = Storage::disk($disk)->mimeType($path);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true), 404);

        return Storage::disk($disk)->response($path, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ], 'inline');
    }

    public function updateStatus(Request $request, int $idx)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'reviewing', 'reviewed', 'rejected'])],
            'moderation_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $admin = $request->attributes->get('currentMember');

        DB::transaction(function () use ($idx, $validated, $admin): void {
            $report = DB::table('idol_profile_image_reports')->where('idx', $idx)->lockForUpdate()->first();
            abort_if($report === null, 404);
            DB::table('idol_profile_image_reports')->where('idx', $idx)->update([
                'status' => $validated['status'],
                'moderated_by_member_idx' => $admin->idx,
                'moderated_at' => now(),
                'updated_at' => now(),
            ]);
            AdminActivityLogger::record((int) $admin->idx, 'idol_image_report_status_changed', 'idol_profile_image_report', $idx,
                $validated['moderation_note'] ?? null, $report->status.' -> '.$validated['status']);
        });

        return back()->with('admin_success', '신고 상태를 변경했습니다.');
    }

    public function hideImage(Request $request, int $idx)
    {
        $validated = $request->validate(['moderation_note' => ['required', 'string', 'max:1000']]);
        $admin = $request->attributes->get('currentMember');
        $privateEvidencePath = null;
        $publicPath = null;
        $publicFileRemoved = false;

        try {
            $result = DB::transaction(function () use ($idx, $validated, $admin, &$privateEvidencePath, &$publicPath, &$publicFileRemoved): string {
                $report = DB::table('idol_profile_image_reports')->where('idx', $idx)->lockForUpdate()->first();
                abort_if($report === null, 404);
                $idol = DB::table('idols')->where('idx', $report->idol_idx)->lockForUpdate()->first(['idx', 'type', 'name', 'image_path']);
                abort_if($idol === null, 404);

                if ($idol->image_path !== $report->reported_image_path) {
                    return 'changed';
                }

                $publicPath = $this->publicStorageRelativePath($report->reported_image_path);
                if ($publicPath === null) {
                    return 'unsupported';
                }

                if ($this->imagePathIsShared($idol->idx, $report->reported_image_path)) {
                    return 'shared';
                }

                if (Storage::disk('public')->exists($publicPath)) {
                    $privateEvidencePath = 'idol-rights-evidence/'.$idx.'-'.Str::random(40).'.'.(pathinfo($publicPath, PATHINFO_EXTENSION) ?: 'image');
                    if (!Storage::disk('local')->put($privateEvidencePath, Storage::disk('public')->get($publicPath))) {
                        throw new RuntimeException('Could not retain private profile-image evidence.');
                    }
                    if (!Storage::disk('public')->delete($publicPath)) {
                        throw new RuntimeException('Could not remove the reported public profile image.');
                    }
                    $publicFileRemoved = true;
                } else {
                    $privateEvidencePath = null;
                }

                DB::table('idols')->where('idx', $idol->idx)->where('image_path', $report->reported_image_path)
                    ->update(['image_path' => null, 'updated_at' => now()]);
                DB::table('idol_profile_image_reports')->where('idol_idx', $report->idol_idx)
                    ->where('image_path_hash', $report->image_path_hash)->update([
                    'evidence_path' => $privateEvidencePath,
                    'updated_at' => now(),
                ]);
                DB::table('idol_profile_image_reports')->where('idx', $idx)->update([
                    'status' => 'reviewed',
                    'moderated_by_member_idx' => $admin->idx,
                    'moderated_at' => now(),
                ]);

                AdminActivityLogger::record((int) $admin->idx, 'idol_profile_image_removed_for_rights',
                    $idol->type === 'group' ? 'group' : 'idol', (int) $idol->idx,
                    $validated['moderation_note'], '신고 이미지 제거 (신고 #'.$idx.')');

                return 'removed';
            });
        } catch (Throwable $exception) {
            if ($publicFileRemoved && $privateEvidencePath && Storage::disk('local')->exists($privateEvidencePath)) {
                Storage::disk('public')->put($publicPath, Storage::disk('local')->get($privateEvidencePath));
            }
            if ($privateEvidencePath) {
                Storage::disk('local')->delete($privateEvidencePath);
            }
            throw $exception;
        }

        if ($result !== 'removed' && $privateEvidencePath) {
            Storage::disk('local')->delete($privateEvidencePath);
        }

        return match ($result) {
            'removed' => back()->with('admin_success', '프로필 이미지를 비공개 처리하고 증거 파일을 보관했습니다.'),
            'changed' => back()->withErrors(['image' => '신고 접수 후 프로필 이미지가 변경되어 현재 이미지는 제거하지 않았습니다.']),
            'shared' => back()->withErrors(['image' => '이 이미지 파일을 다른 자료에서도 사용 중이어서 안전하게 제거할 수 없습니다.']),
            default => back()->withErrors(['image' => '관리 대상 저장소의 이미지가 아니어서 자동으로 제거할 수 없습니다.']),
        };
    }

    private function reportQuery(int $idx)
    {
        return DB::table('idol_profile_image_reports as report')
            ->join('idols as idol', 'idol.idx', '=', 'report.idol_idx')
            ->join('members as reporter', 'reporter.idx', '=', 'report.member_idx')
            ->leftJoin('members as moderator', 'moderator.idx', '=', 'report.moderated_by_member_idx')
            ->where('report.idx', $idx)
            ->select('report.*', 'idol.name as idol_name', 'idol.english_name', 'idol.type as idol_type',
                'idol.image_path as current_image_path', 'idol.status as idol_status',
                'reporter.nickname as reporter_nickname', 'moderator.nickname as moderator_nickname');
    }

    private function publicStorageRelativePath(string $path): ?string
    {
        if (!str_starts_with($path, 'storage/') || str_contains($path, '..')) {
            return null;
        }

        return substr($path, strlen('storage/'));
    }

    private function imagePathIsShared(int $idolIdx, string $path): bool
    {
        return DB::table('idols')->where('image_path', $path)->where('idx', '<>', $idolIdx)->exists()
            || DB::table('idol_registration_requests')->where('image_path', $path)
                ->where(fn ($query) => $query->whereNull('approved_idol_idx')->orWhere('approved_idol_idx', '<>', $idolIdx))->exists()
            || DB::table('members')->where('profile_image_path', $path)->exists();
    }
}
