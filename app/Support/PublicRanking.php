<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PublicRanking
{
    public function __construct(private readonly IdolLocalizationResolver $localizations)
    {
    }

    public function category(string $category): array
    {
        [$type, $gender] = match ($category) {
            'boy' => ['individual', 'male'], 'girl' => ['individual', 'female'],
            'boy-group' => ['group', 'male'], 'girl-group' => ['group', 'female'],
            default => abort(404),
        };

        return $this->ranking($type, $gender, now('UTC')->startOfMonth());
    }

    public function ranking(string $type, ?string $gender, CarbonInterface $month): array
    {
        $monthStart = $month->copy()->setTimezone('UTC')->startOfMonth();
        $nextMonthStart = $monthStart->copy()->addMonth();
        $votes = DB::table('idol_votes as iv')->join('members as vm', 'vm.idx', '=', 'iv.member_idx')
            ->selectRaw('iv.idol_idx, SUM(iv.heart_amount) hearts')
            ->where('iv.created_at', '>=', $monthStart)->where('iv.created_at', '<', $nextMonthStart)
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
            ->get(['i.idx','i.name','i.english_name','i.content','i.gender','i.image_path', DB::raw('COALESCE(v.hearts,0) hearts'), DB::raw('COALESCE(p.posts,0) posts')]);

        // Keep unranked zero-vote items stable during the UTC day while changing their order daily.
        $dailySeed = now('UTC')->toDateString();
        $rows = $rows->sort(function (object $left, object $right) use ($dailySeed): int {
            $leftHearts = (int) $left->hearts;
            $rightHearts = (int) $right->hearts;
            if ($leftHearts !== $rightHearts) return $rightHearts <=> $leftHearts;

            if ($leftHearts === 0) {
                $order = hash('sha256', $dailySeed.':'.$left->idx) <=> hash('sha256', $dailySeed.':'.$right->idx);
                if ($order !== 0) return $order;
            }

            return (int) $left->idx <=> (int) $right->idx;
        })->values();
        $representativeImages = $this->representativeImageMaps($rows->pluck('idx')->map(fn ($idx) => (int) $idx)->all(), $monthStart, $nextMonthStart);

        $groups = $type === 'individual' && $rows->isNotEmpty()
            ? DB::table('idol_group_members as gm')->join('idols as g', 'g.idx', '=', 'gm.group_idx')
                ->where('g.status', 'active')->whereIn('gm.individual_idx', $rows->pluck('idx'))
                ->orderBy('gm.individual_idx')->orderBy('gm.group_idx')
                ->get(['gm.individual_idx', 'g.idx', 'g.name', 'g.english_name', 'g.content'])
            : collect();
        $localized = $this->localizations->resolveMany($rows->concat($groups));
        $groupNames = [];
        foreach ($groups as $group) {
            $groupNames[$group->individual_idx] ??= $localized[(int) $group->idx]['name'] ?? $group->name;
        }

        return $rows->map(fn (object $idol) => [
            'idx'=>(int)$idol->idx, 'name'=>$localized[(int) $idol->idx]['name'] ?? ($idol->english_name ?: $idol->name),
            'group'=>$type === 'group' ? ($idol->gender === 'male' ? 'Boy Group' : 'Girl Group') : ($groupNames[$idol->idx] ?? 'Solo'),
            'hearts'=>(int)$idol->hearts, 'posts'=>(int)$idol->posts,
            'image_url'=>ManagedImageStorage::publicUrl($idol->image_path),
            'representative_image_url' => $representativeImages[(int) $idol->idx] ?? ManagedImageStorage::publicUrl($idol->image_path),
        ])->all();
    }

    public function popularImages(int $idolIdx, int $limit = 3): array
    {
        $monthStart = now('UTC')->startOfMonth();
        $nextMonthStart = $monthStart->copy()->addMonth();
        $monthly = $this->eligibleImagePosts($idolIdx)
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('fan_post_likes as l')
                ->whereColumn('l.fan_post_idx', 'p.idx')
                ->where('l.created_at', '>=', $monthStart)->where('l.created_at', '<', $nextMonthStart))
            ->selectSub(DB::table('fan_post_likes as l')->selectRaw('COUNT(*)')
                ->whereColumn('l.fan_post_idx', 'p.idx')
                ->where('l.created_at', '>=', $monthStart)->where('l.created_at', '<', $nextMonthStart), 'recommendations')
            ->orderByDesc('recommendations')->orderByDesc('p.created_at')->orderByDesc('p.idx')
            ->limit(max(0, $limit))->get();

        // Prefer this month's liked posts; only use lifetime popularity if the idol received no likes this month.
        $posts = $monthly->isNotEmpty() ? $monthly : $this->eligibleImagePosts($idolIdx)
            ->selectSub(DB::table('fan_post_likes as l')->selectRaw('COUNT(*)')
                ->whereColumn('l.fan_post_idx', 'p.idx'), 'recommendations')
            ->orderByDesc('recommendations')->orderByDesc('p.created_at')->orderByDesc('p.idx')
            ->limit(max(0, $limit))->get();

        return $posts->map(fn (object $post): string => $this->imageUrl($post))
            ->filter()->values()->all();
    }

    private function eligibleImagePosts(int $idolIdx): Builder
    {
        return $this->eligibleImagePostsForIdols([$idolIdx]);
    }

    private function eligibleImagePostsForIdols(array $idolIds): Builder
    {
        return DB::table('fan_posts as p')
            ->join('members as m', 'm.idx', '=', 'p.member_idx')
            ->join('idols as i', 'i.idx', '=', 'p.idol_idx')
            ->whereIn('p.idol_idx', $idolIds)->where('p.status', 'published')->whereNull('p.deleted_at')
            ->where('i.status', 'active')->where('m.member_level', '>', 0)
            ->where('m.status', 'active')->whereNull('m.deleted_at')
            ->whereIn('p.media_type', ['image', 'youtube'])
            ->where(function ($query): void {
                $query->where(fn ($images) => $images->where('p.media_type', 'image')->whereNotNull('p.image_path')->where('p.image_path', '<>', ''))
                    ->orWhere(fn ($videos) => $videos->where('p.media_type', 'youtube')->whereNotNull('p.youtube_video_id')->where('p.youtube_video_id', '<>', ''));
            })
            ->select(['p.media_type', 'p.image_path', 'p.youtube_video_id']);
    }

    /** Load one preferred image per ranked idol in two batch queries, avoiding per-card lookups. */
    private function representativeImageMaps(array $idolIds, CarbonInterface $monthStart, CarbonInterface $nextMonthStart): array
    {
        if ($idolIds === []) return [];

        $eligiblePostIds = $this->eligibleImagePostsForIdols($idolIds)->select('p.idx');
        $monthlyLikes = DB::table('fan_post_likes')
            ->where('created_at', '>=', $monthStart)->where('created_at', '<', $nextMonthStart)
            ->whereIn('fan_post_idx', clone $eligiblePostIds)
            ->select('fan_post_idx')->selectRaw('COUNT(*) as like_count')->groupBy('fan_post_idx');
        $monthlyCandidates = $this->eligibleImagePostsForIdols($idolIds)
            ->joinSub($monthlyLikes, 'likes', 'likes.fan_post_idx', '=', 'p.idx')
            ->select('p.idol_idx', 'p.media_type', 'p.image_path', 'p.youtube_video_id')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY p.idol_idx ORDER BY likes.like_count DESC, p.created_at DESC, p.idx DESC) as image_rank');
        $monthly = DB::query()->fromSub($monthlyCandidates, 'ranked')
            ->where('ranked.image_rank', 1)->get(['ranked.idol_idx', 'ranked.media_type', 'ranked.image_path', 'ranked.youtube_video_id']);

        $lifetimeLikes = DB::table('fan_post_likes')
            ->whereIn('fan_post_idx', clone $eligiblePostIds)
            ->select('fan_post_idx')->selectRaw('COUNT(*) as like_count')->groupBy('fan_post_idx');
        $lifetimeCandidates = $this->eligibleImagePostsForIdols($idolIds)
            ->leftJoinSub($lifetimeLikes, 'likes', 'likes.fan_post_idx', '=', 'p.idx')
            ->select('p.idol_idx', 'p.media_type', 'p.image_path', 'p.youtube_video_id')
            ->selectRaw('COALESCE(likes.like_count, 0) as like_count')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY p.idol_idx ORDER BY COALESCE(likes.like_count, 0) DESC, p.created_at DESC, p.idx DESC) as image_rank');
        $lifetime = DB::query()->fromSub($lifetimeCandidates, 'ranked')
            ->where('ranked.image_rank', 1)->get(['ranked.idol_idx', 'ranked.media_type', 'ranked.image_path', 'ranked.youtube_video_id']);

        $images = [];
        foreach ($lifetime as $post) {
            $images[(int) $post->idol_idx] = $this->imageUrl($post);
        }
        foreach ($monthly as $post) {
            $images[(int) $post->idol_idx] = $this->imageUrl($post);
        }

        return $images;
    }

    private function imageUrl(object $post): string
    {
        return $post->media_type === 'youtube'
            ? 'https://i.ytimg.com/vi/'.$post->youtube_video_id.'/hqdefault.jpg'
            : ManagedImageStorage::publicUrl($post->image_path);
    }

    /** Keep the existing eligible Fan Post ordering; fall back only when none qualify. */
    public function representativeImages(int $idolIdx, ?string $profileImageUrl, int $limit = 3): array
    {
        $images = $this->popularImages($idolIdx, $limit);

        return $images !== [] ? $images : [
            $profileImageUrl ?: ManagedImageStorage::defaultUrl(),
        ];
    }

    public function topVoters(int $idolIdx, CarbonInterface $month): array
    {
        $monthStart = $month->copy()->setTimezone('UTC')->startOfMonth();
        $nextMonthStart = $monthStart->copy()->addMonth();
        return DB::table('idol_votes as v')->join('members as m', 'm.idx', '=', 'v.member_idx')
            ->where('v.idol_idx', $idolIdx)->where('m.member_level', '>', 0)
            ->where('m.status', 'active')->whereNull('m.deleted_at')
            ->where('v.created_at', '>=', $monthStart)->where('v.created_at', '<', $nextMonthStart)
            ->groupBy('m.idx', 'm.member_code', 'm.nickname', 'm.country_code', 'm.profile_image_path')
            ->orderByDesc(DB::raw('SUM(v.heart_amount)'))->orderBy('m.idx')->limit(5)
            ->get(['m.member_code','m.nickname','m.country_code','m.profile_image_path', DB::raw('SUM(v.heart_amount) hearts')])
            ->map(fn (object $voter) => [
                'member_code'=>$voter->member_code, 'nickname'=>$voter->nickname,
                'country_code'=>$voter->country_code,
                'image_url'=>$voter->profile_image_path, 'bio'=>'', 'hearts'=>(int)$voter->hearts,
            ])->all();
    }
}
