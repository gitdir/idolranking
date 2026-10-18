<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FanPostCommentReportController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $search = mb_substr(trim($request->string('q')->toString()), 0, 100);
        $latestCommentModeration = DB::table('admin_activity_logs')
            ->selectRaw('target_idx, MAX(idx) as latest_idx')
            ->where('target_type', 'fan_post_comment')
            ->whereIn('action', ['fan_post_comment_hidden', 'fan_post_comment_restored'])
            ->groupBy('target_idx');

        $reports = DB::table('fan_post_comment_reports as report')
            ->join('fan_post_comments as comment', 'comment.idx', '=', 'report.comment_idx')
            ->join('fan_posts as post', 'post.idx', '=', 'comment.fan_post_idx')
            ->join('members as reporter', 'reporter.idx', '=', 'report.member_idx')
            ->join('members as author', 'author.idx', '=', 'comment.member_idx')
            ->leftJoinSub($latestCommentModeration, 'latest_comment_moderation', function ($join) {
                $join->on('latest_comment_moderation.target_idx', '=', 'comment.idx');
            })
            ->leftJoin('admin_activity_logs as comment_moderation', 'comment_moderation.idx', '=', 'latest_comment_moderation.latest_idx')
            ->when(in_array($status, ['pending', 'reviewing', 'reviewed', 'rejected'], true),
                fn ($query) => $query->where('report.status', $status))
            ->when($search !== '', fn ($query) => $query->where(function ($where) use ($search) {
                $like = '%'.$search.'%';
                $where->where('comment.content', 'like', $like)
                    ->orWhere('post.title', 'like', $like)
                    ->orWhere('reporter.nickname', 'like', $like)
                    ->orWhere('author.nickname', 'like', $like);
            }))
            ->select([
                'report.idx', 'report.comment_idx', 'report.member_idx', 'report.actor_member_idx',
                'report.reason', 'report.details', 'report.status', 'report.created_at',
                'comment.content', 'comment.deleted_at as comment_deleted_at', 'comment.status as comment_status',
                'comment_moderation.action as last_comment_moderation_action',
                'post.idx as fan_post_idx', 'post.title as post_title',
                'reporter.nickname as reporter_nickname', 'author.nickname as author_nickname',
            ])
            ->orderByDesc('report.created_at')->orderByDesc('report.idx')
            ->paginate(20)->withQueryString();

        return view('admin.fan-post-comment-reports.index', compact('reports', 'status', 'search'));
    }

    public function updateStatus(Request $request, int $idx)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'reviewing', 'reviewed', 'rejected'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $admin = $request->attributes->get('currentMember');

        DB::transaction(function () use ($idx, $validated, $admin): void {
            $report = DB::table('fan_post_comment_reports')->where('idx', $idx)->lockForUpdate()->first();
            abort_if($report === null, 404);

            DB::table('fan_post_comment_reports')->where('idx', $idx)->update([
                'status' => $validated['status'],
                'updated_at' => now(),
            ]);
            AdminActivityLogger::record(
                (int) $admin->idx,
                'fan_post_comment_report_status_changed',
                'fan_post_comment_report',
                $idx,
                $validated['reason'] ?? null,
                $report->status.' -> '.$validated['status']
            );
        });

        return back()->with('admin_success', '댓글 신고 상태를 변경했습니다.');
    }

    public function hideComment(Request $request, int $idx)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $admin = $request->attributes->get('currentMember');

        DB::transaction(function () use ($idx, $validated, $admin): void {
            $comment = DB::table('fan_post_comments')->where('idx', $idx)->lockForUpdate()
                ->first(['idx', 'content', 'status', 'deleted_at']);
            abort_if($comment === null || $comment->status !== 'published' || $comment->deleted_at !== null, 404);

            DB::table('fan_post_comments')->where('idx', $idx)->update([
                'status' => 'hidden',
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
            AdminActivityLogger::record(
                (int) $admin->idx,
                'fan_post_comment_hidden',
                'fan_post_comment',
                $idx,
                $validated['reason'],
                mb_substr($comment->content, 0, 500)
            );
        });

        return back()->with('admin_success', '댓글을 비공개 처리했습니다. Heart 이력은 변경되지 않았습니다.');
    }

    public function restoreComment(Request $request, int $idx)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $admin = $request->attributes->get('currentMember');

        DB::transaction(function () use ($idx, $validated, $admin): void {
            $comment = DB::table('fan_post_comments')->where('idx', $idx)->lockForUpdate()
                ->first(['idx', 'status', 'deleted_at']);
            abort_if($comment === null, 404);
            abort_unless($comment->status === 'hidden' && $comment->deleted_at !== null, 409);

            // Author deletions share the hidden/deleted state but have no moderation audit event.
            $lastModerationAction = DB::table('admin_activity_logs')
                ->where('target_type', 'fan_post_comment')->where('target_idx', $idx)
                ->whereIn('action', ['fan_post_comment_hidden', 'fan_post_comment_restored'])
                ->orderByDesc('idx')->value('action');
            abort_unless($lastModerationAction === 'fan_post_comment_hidden', 409);

            DB::table('fan_post_comments')->where('idx', $idx)->update([
                'status' => 'published',
                'deleted_at' => null,
                'updated_at' => now(),
            ]);
            AdminActivityLogger::record(
                (int) $admin->idx,
                'fan_post_comment_restored',
                'fan_post_comment',
                $idx,
                $validated['reason'],
                '댓글 공개 복구'
            );
        });

        return back()->with('admin_success', '댓글을 공개 복구했습니다.');
    }
}
