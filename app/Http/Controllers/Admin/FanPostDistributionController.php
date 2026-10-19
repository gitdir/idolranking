<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminActivityLogger;
use App\Support\FanPostDistributionSettings;
use App\Support\ManagedImageStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FanPostDistributionController extends Controller
{
    public function index(Request $request)
    {
        $this->administrator($request);
        $settings = FanPostDistributionSettings::current();
        $posts = DB::table('fan_posts as p')->join('members as m', 'm.idx', '=', 'p.member_idx')
            ->where('p.status', 'queued')->whereNull('p.deleted_at')
            ->orderBy('p.queued_at')->orderBy('p.idx')
            ->select('p.idx', 'p.title', 'p.status', 'p.queued_at', 'm.nickname')
            ->paginate(30)->withQueryString();

        return view('admin.fan-post-distribution.index', compact('settings', 'posts'));
    }

    public function updateSettings(Request $request)
    {
        $settings = $request->validate([
            'interval_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'max_posts' => ['required', 'integer', 'min:1', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
        ], [
            'interval_minutes.required' => '발행 간격을 입력해 주세요.',
            'interval_minutes.integer' => '발행 간격은 분 단위 정수로 입력해 주세요.',
            'interval_minutes.min' => '발행 간격은 1분 이상이어야 합니다.',
            'interval_minutes.max' => '발행 간격은 1,440분 이하여야 합니다.',
            'max_posts.required' => '최대 발행 수를 입력해 주세요.',
            'max_posts.integer' => '최대 발행 수는 정수로 입력해 주세요.',
            'max_posts.min' => '최대 발행 수는 1개 이상이어야 합니다.',
            'max_posts.max' => '최대 발행 수는 100개 이하여야 합니다.',
            'start_time.required' => '발행 시작 시각을 입력해 주세요.',
            'start_time.date_format' => '시각은 시:분 형식으로 입력해 주세요.',
            'end_time.required' => '발행 종료 시각을 입력해 주세요.',
            'end_time.date_format' => '시각은 시:분 형식으로 입력해 주세요.',
        ]);
        if ($settings['start_time'] >= $settings['end_time']) {
            throw ValidationException::withMessages(['end_time' => '종료 시각은 시작 시각보다 늦어야 합니다.']);
        }

        $admin = $this->administrator($request);
        $values = [
            'fan_post_distribution_interval_minutes' => (string) $settings['interval_minutes'],
            'fan_post_distribution_max_posts' => (string) $settings['max_posts'],
            'fan_post_distribution_start_time' => $settings['start_time'],
            'fan_post_distribution_end_time' => $settings['end_time'],
        ];
        DB::transaction(function () use ($values, $admin): void {
            foreach ($values as $key => $value) {
                DB::table('site_settings')->updateOrInsert(['setting_key' => $key], [
                    'setting_value' => $value,
                    'updated_by_member_idx' => $admin->idx,
                    'updated_at' => now(),
                ]);
            }
            AdminActivityLogger::record((int) $admin->idx, 'fan_post_distribution_settings_changed', 'site_settings', 0,
                null, '팬 게시물 예약 발행 설정 변경');
        });

        return back()->with('admin_success', '예약 발행 설정을 저장했습니다. 이미 대기 중인 게시물 순서는 유지됩니다.');
    }

    public function preview(Request $request, int $idx)
    {
        $this->administrator($request);
        $post = DB::table('fan_posts as p')->join('members as m', 'm.idx', '=', 'p.member_idx')
            ->join('idols as i', 'i.idx', '=', 'p.idol_idx')
            ->where('p.idx', $idx)->where('p.status', 'queued')->whereNull('p.deleted_at')
            ->first(['p.idx', 'p.title', 'p.content', 'p.status', 'p.queued_at', 'p.media_type', 'p.image_path',
                'p.youtube_video_id', 'm.nickname', 'i.name as idol_name']);
        abort_if($post === null, 404);
        $post->image_url = $post->media_type === 'image' ? ManagedImageStorage::publicUrl($post->image_path) : null;

        return view('admin.fan-post-distribution.preview', compact('post'));
    }

    public function publishNow(Request $request, int $idx)
    {
        $admin = $this->administrator($request);
        DB::transaction(function () use ($idx, $admin): void {
            $post = DB::table('fan_posts')->where('idx', $idx)->where('status', 'queued')
                ->whereNull('deleted_at')->lockForUpdate()->first(['idx', 'title']);
            abort_if($post === null, 404);
            $now = now('UTC');
            DB::table('fan_posts')->where('idx', $idx)->update([
                'status' => 'published', 'published_at' => $now, 'updated_at' => $now,
            ]);
            AdminActivityLogger::record((int) $admin->idx, 'fan_post_published_now', 'fan_post', $idx, null, $post->title);
        });

        return redirect()->route('admin.fan-post-distribution.index')->with('admin_success', '게시물을 즉시 공개했습니다.');
    }

    public function cancel(Request $request, int $idx)
    {
        $admin = $this->administrator($request);
        DB::transaction(function () use ($idx, $admin): void {
            $post = DB::table('fan_posts')->where('idx', $idx)->where('status', 'queued')
                ->whereNull('deleted_at')->lockForUpdate()->first(['idx', 'title']);
            abort_if($post === null, 404);
            DB::table('fan_posts')->where('idx', $idx)->update(['status' => 'cancelled', 'updated_at' => now()]);
            AdminActivityLogger::record((int) $admin->idx, 'fan_post_distribution_cancelled', 'fan_post', $idx, null, $post->title);
        });

        return redirect()->route('admin.fan-post-distribution.index')->with('admin_success', '예약을 취소했습니다. 게시물은 비공개 상태로 보존됩니다.');
    }

    private function administrator(Request $request): object
    {
        $member = $request->attributes->get('currentMember');
        abort_unless($member !== null && (int) $member->member_level === 10 && (int) $member->is_dummy === 0, 403);

        return $member;
    }
}
