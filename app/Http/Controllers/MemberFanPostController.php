<?php

namespace App\Http\Controllers;

use App\Support\Countries;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class MemberFanPostController extends Controller
{
    public function mine(Request $request): RedirectResponse
    {
        $member = $request->attributes->get('activityMember');

        return redirect()->route('member.fan-posts', [
            'member_code' => $member->member_code,
        ] + $request->query());
    }

    public function show(Request $request, string $member_code)
    {
        $member = DB::table('members')
            ->where('member_code', $member_code)
            ->where('member_level', '>', 0)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first(['idx', 'member_code', 'nickname', 'country_code', 'profile_image_path']);

        if ($member === null) {
            return response()->view('members.not-found', [], 404);
        }

        $sort = $request->string('sort')->toString();
        if (!in_array($sort, ['latest', 'liked', 'commented'], true)) {
            $sort = 'latest';
        }

        $query = DB::table('fan_posts as p')
            ->join('idols as i', 'i.idx', '=', 'p.idol_idx')
            ->where('p.member_idx', $member->idx)
            ->where('p.status', 'published')
            ->whereNull('p.deleted_at')
            ->where('i.status', 'active')
            ->select(['p.idx', 'p.media_type', 'p.image_path', 'p.youtube_video_id', 'p.content', 'p.view_count', 'p.created_at'])
            ->selectSub(
                DB::table('fan_post_likes as l')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('l.fan_post_idx', 'p.idx'),
                'like_count'
            )
            ->selectSub(
                DB::table('fan_post_comments as c')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('c.fan_post_idx', 'p.idx')
                    ->where('c.status', 'published')
                    ->whereNull('c.deleted_at'),
                'comment_count'
            );

        match ($sort) {
            'liked' => $query->orderByDesc('like_count')->orderByDesc('p.created_at')->orderByDesc('p.idx'),
            'commented' => $query->orderByDesc('comment_count')->orderByDesc('p.created_at')->orderByDesc('p.idx'),
            default => $query->orderByDesc('p.created_at')->orderByDesc('p.idx'),
        };

        $fanPosts = $query->paginate(20)->withQueryString();
        $fanPosts->through(fn ($post) => [
            'portrait-rose',
            $member->nickname,
            $post->content ?? '',
            $post->view_count,
            $post->like_count,
            $post->comment_count,
            'square',
            'member_code' => $member->member_code,
            'author_image_url' => $member->profile_image_path,
            'media_type' => $post->media_type,
            'youtube_video_id' => $post->youtube_video_id,
            'image_url' => \App\Support\ManagedImageStorage::publicUrl($post->image_path),
            'fan_post_idx' => (int) $post->idx,
        ]);

        $publishedPosts = DB::table('fan_posts as p')
            ->join('idols as i', 'i.idx', '=', 'p.idol_idx')
            ->where('p.member_idx', $member->idx)
            ->where('p.status', 'published')
            ->whereNull('p.deleted_at');
        $publishedPosts->where('i.status', 'active');
        $postCount = (clone $publishedPosts)->count();
        $commentCount = DB::table('fan_post_comments as c')
            ->join('fan_posts as p', 'p.idx', '=', 'c.fan_post_idx')
            ->join('idols as i', 'i.idx', '=', 'p.idol_idx')
            ->where('p.member_idx', $member->idx)
            ->where('p.status', 'published')
            ->whereNull('p.deleted_at')
            ->where('i.status', 'active')
            ->where('c.status', 'published')
            ->whereNull('c.deleted_at')
            ->count();

        $memberSummary = [
            'member_code' => $member->member_code,
            'profile_image_url' => \App\Support\ManagedImageStorage::publicUrl($member->profile_image_path),
            'nickname' => $member->nickname,
            'country' => $this->countryName($member->country_code),
            'country_code' => $member->country_code,
            'bio' => null,
            // Replace this presentation value when the percentile query is introduced.
            'like_ranking' => '—',
            'fan_post_count' => $postCount,
            'comment_count' => $commentCount,
        ];

        $showSamplePagination = false;
        $currentMember = $request->attributes->get('activityMember');
        $canCreateFanPost = $currentMember !== null && (int) $currentMember->idx === (int) $member->idx;

        return view('members.fan-posts', compact('memberSummary', 'fanPosts', 'sort', 'showSamplePagination', 'member_code', 'canCreateFanPost'));
    }

    private function countryName(?string $countryCode): string
    {
        return Countries::name($countryCode) ?? ($countryCode ?: 'Country not set');
    }
}
