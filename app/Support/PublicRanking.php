<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class PublicRanking
{
    public function category(string $category): array
    {
        [$type, $gender] = match ($category) {
            'boy' => ['individual', 'male'], 'girl' => ['individual', 'female'],
            'boy-group' => ['group', 'male'], 'girl-group' => ['group', 'female'],
            default => abort(404),
        };

        return $this->ranking($type, $gender, now()->startOfMonth());
    }

    public function ranking(string $type, ?string $gender, CarbonInterface $month): array
    {
        $votes = DB::table('idol_votes as iv')->join('members as vm', 'vm.idx', '=', 'iv.member_idx')
            ->selectRaw('iv.idol_idx, SUM(iv.heart_amount) hearts')
            ->whereBetween('iv.created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->where('vm.member_level', '>', 0)->where('vm.status', 'active')->whereNull('vm.deleted_at')
            ->groupBy('iv.idol_idx');
        $posts = DB::table('fan_posts as fp')->join('members as pm', 'pm.idx', '=', 'fp.member_idx')
            ->selectRaw('fp.idol_idx, COUNT(*) posts')->where('fp.status', 'published')->whereNull('fp.deleted_at')
            ->where('pm.member_level', '>', 0)->where('pm.status', 'active')->whereNull('pm.deleted_at')
            ->groupBy('fp.idol_idx');
        $rows = DB::table('idols as i')->leftJoinSub($votes, 'v', 'v.idol_idx', '=', 'i.idx')
            ->leftJoinSub($posts, 'p', 'p.idol_idx', '=', 'i.idx')
            ->where('i.status', 'active')->where('i.type', $type)
            ->when($gender, fn ($query) => $query->where('i.gender', $gender))
            ->orderByDesc(DB::raw('COALESCE(v.hearts, 0)'))->orderBy('i.idx')
            ->get(['i.idx','i.name','i.gender','i.image_path', DB::raw('COALESCE(v.hearts,0) hearts'), DB::raw('COALESCE(p.posts,0) posts')]);

        $groupNames = $type === 'individual'
            ? DB::table('idol_group_members as gm')->join('idols as g', 'g.idx', '=', 'gm.group_idx')
                ->where('g.status', 'active')->whereIn('gm.individual_idx', $rows->pluck('idx'))->pluck('g.name', 'gm.individual_idx')
            : collect();

        return $rows->map(fn (object $idol) => [
            'idx'=>(int)$idol->idx, 'name'=>$idol->name,
            'group'=>$type === 'group' ? ($idol->gender === 'male' ? 'Boy Group' : 'Girl Group') : ($groupNames[$idol->idx] ?? 'Solo'),
            'hearts'=>(int)$idol->hearts, 'posts'=>(int)$idol->posts,
            'image_url'=>ManagedImageStorage::publicUrl($idol->image_path),
        ])->all();
    }

    public function popularImages(int $idolIdx, int $limit = 3): array
    {
        return DB::table('fan_posts as p')
            ->join('members as m', 'm.idx', '=', 'p.member_idx')
            ->join('idols as i', 'i.idx', '=', 'p.idol_idx')
            ->where('p.idol_idx', $idolIdx)->where('p.status', 'published')->whereNull('p.deleted_at')
            ->where('i.status', 'active')->where('m.member_level', '>', 0)
            ->where('m.status', 'active')->whereNull('m.deleted_at')
            ->whereIn('p.media_type', ['image', 'youtube'])
            ->where(function ($query): void {
                $query->where(fn ($images) => $images->where('p.media_type', 'image')->whereNotNull('p.image_path')->where('p.image_path', '<>', ''))
                    ->orWhere(fn ($videos) => $videos->where('p.media_type', 'youtube')->whereNotNull('p.youtube_video_id')->where('p.youtube_video_id', '<>', ''));
            })
            ->select(['p.media_type', 'p.image_path', 'p.youtube_video_id'])
            ->selectSub(DB::table('fan_post_likes as l')->selectRaw('COUNT(*)')->whereColumn('l.fan_post_idx', 'p.idx'), 'recommendations')
            ->orderByDesc('recommendations')->orderByDesc('p.created_at')->orderByDesc('p.idx')
            ->limit(max(0, $limit))
            ->get()->map(fn (object $post): string => $post->media_type === 'youtube'
                ? 'https://i.ytimg.com/vi/'.$post->youtube_video_id.'/hqdefault.jpg'
                : ManagedImageStorage::publicUrl($post->image_path))
            ->filter()->values()->all();
    }

    public function topVoters(int $idolIdx, CarbonInterface $month): array
    {
        return DB::table('idol_votes as v')->join('members as m', 'm.idx', '=', 'v.member_idx')
            ->where('v.idol_idx', $idolIdx)->where('m.member_level', '>', 0)
            ->where('m.status', 'active')->whereNull('m.deleted_at')
            ->whereBetween('v.created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->groupBy('m.idx', 'm.member_code', 'm.nickname', 'm.profile_image_path')
            ->orderByDesc(DB::raw('SUM(v.heart_amount)'))->orderBy('m.idx')->limit(5)
            ->get(['m.member_code','m.nickname','m.profile_image_path', DB::raw('SUM(v.heart_amount) hearts')])
            ->map(fn (object $voter) => [
                'member_code'=>$voter->member_code, 'nickname'=>$voter->nickname,
                'image_url'=>$voter->profile_image_path, 'bio'=>'', 'hearts'=>(int)$voter->hearts,
            ])->all();
    }
}
