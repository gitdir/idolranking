<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FanPostReportController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $reason = $request->string('reason')->toString();
        $search = trim($request->string('q')->toString());
        $allowed=['created_at'=>'r.created_at','status'=>'r.status'];
        $sort=array_key_exists($request->query('sort'),$allowed)?$request->query('sort'):'created_at'; $direction=$request->query('direction')==='asc'?'asc':'desc';
        $reports = DB::table('fan_post_reports as r')
            ->join('fan_posts as p', 'p.idx', '=', 'r.fan_post_idx')
            ->join('members as reporter', 'reporter.idx', '=', 'r.member_idx')
            ->join('members as author', 'author.idx', '=', 'p.member_idx')
            ->when(in_array($status, ['pending', 'reviewing', 'reviewed', 'rejected'], true), fn ($q) => $q->where('r.status', $status))
            ->when(in_array($reason, ['spam', 'harassment_hate', 'inappropriate_content', 'copyright', 'misinformation', 'other'], true), fn ($q) => $q->where('r.reason', $reason))
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('p.title', 'like', '%'.$search.'%')->orWhere('reporter.nickname', 'like', '%'.$search.'%');
            }))
            ->select('r.*', 'p.title', 'p.status as post_status', 'reporter.nickname as reporter_nickname', 'author.nickname as author_nickname')
            ->orderBy($allowed[$sort],$direction)->orderByDesc('r.idx')
            ->paginate(20)->withQueryString();

        return view('admin.fan-post-reports.index', compact('reports', 'status', 'reason', 'search','sort','direction'));
    }

    public function showPost(int $idx)
    {
        $post = DB::table('fan_posts as p')->join('members as m', 'm.idx', '=', 'p.member_idx')
            ->leftJoin('idols as i', 'i.idx', '=', 'p.idol_idx')->where('p.idx', $idx)
            ->first(['p.*', 'm.nickname', 'i.name as idol_name']);
        abort_if($post === null, 404);

        return view('admin.fan-posts.show', compact('post'));
    }

    public function updateStatus(Request $request, int $idx)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'reviewing', 'reviewed', 'rejected'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], ['status.required' => '처리 상태를 선택해 주세요.', 'status.in' => '올바른 처리 상태를 선택해 주세요.', 'reason.max' => '처리 사유는 1,000자 이하여야 합니다.']);
        $admin = $request->attributes->get('currentMember');

        DB::transaction(function () use ($idx, $validated, $admin) {
            $report = DB::table('fan_post_reports')->where('idx', $idx)->lockForUpdate()->first();
            abort_if($report === null, 404);
            DB::table('fan_post_reports')->where('idx', $idx)->update(['status' => $validated['status'], 'updated_at' => now()]);
            AdminActivityLogger::record((int) $admin->idx, 'report_status_changed', 'fan_post_report', $idx, $validated['reason'] ?? null, $report->status.' -> '.$validated['status']);
        });

        return back()->with('admin_success', '신고 처리 상태를 변경했습니다.');
    }

    public function updateVisibility(Request $request, int $idx)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['published', 'hidden'])],
            'reason' => ['required', 'string', 'max:1000'],
        ], ['status.required' => '공개 상태를 선택해 주세요.', 'status.in' => '올바른 공개 상태를 선택해 주세요.', 'reason.required' => '조치 사유를 입력해 주세요.', 'reason.max' => '조치 사유는 1,000자 이하여야 합니다.']);
        $admin = $request->attributes->get('currentMember');

        DB::transaction(function () use ($idx, $validated, $admin) {
            $post = DB::table('fan_posts')->where('idx', $idx)->whereNull('deleted_at')->lockForUpdate()->first(['idx', 'status', 'title']);
            abort_if($post === null, 404);
            abort_unless(in_array($post->status, ['published', 'hidden'], true), 404);
            $updates = ['status' => $validated['status'], 'updated_at' => now()];
            if ($validated['status'] === 'published') {
                $updates['published_at'] = now('UTC');
            }
            DB::table('fan_posts')->where('idx', $idx)->update($updates);
            $action = $validated['status'] === 'hidden' ? 'fan_post_hidden' : 'fan_post_restored';
            AdminActivityLogger::record((int) $admin->idx, $action, 'fan_post', $idx, $validated['reason'], $post->title);
        });

        return back()->with('admin_success', '팬포스트 공개 상태를 변경했습니다.');
    }
}
