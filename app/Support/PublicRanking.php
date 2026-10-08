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
        $fixtureCategories = $type === 'individual' ? ['boy', 'girl'] : ['boy-group', 'girl-group'];
        if ($gender === 'male') $fixtureCategories = [$fixtureCategories[0]];
        elseif ($gender === 'female') $fixtureCategories = [$fixtureCategories[1]];

        $fixtures = require resource_path('data/rankings.php');
        $fixtureRows = collect($fixtureCategories)->flatMap(fn (string $category) => $fixtures[$category]['idols'])
            ->sortBy([['hearts', 'desc'], ['idx', 'asc']])->values()->all();

        if (!DatabaseAvailability::isAvailable()) return $fixtureRows;

        $votes = DB::table('idol_votes')->selectRaw('idol_idx, SUM(heart_amount) hearts')
            ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->groupBy('idol_idx');
        $posts = DB::table('fan_posts')->selectRaw('idol_idx, COUNT(*) posts')
            ->where('status', 'published')->whereNull('deleted_at')->groupBy('idol_idx');
        $rows = DB::table('idols as i')->leftJoinSub($votes, 'v', 'v.idol_idx', '=', 'i.idx')
            ->leftJoinSub($posts, 'p', 'p.idol_idx', '=', 'i.idx')
            ->where('i.status', 'active')->where('i.type', $type)
            ->when($gender, fn ($query) => $query->where('i.gender', $gender))
            ->orderByDesc(DB::raw('COALESCE(v.hearts, 0)'))->orderBy('i.idx')
            ->get(['i.idx','i.name','i.gender','i.image_path', DB::raw('COALESCE(v.hearts,0) hearts'), DB::raw('COALESCE(p.posts,0) posts')]);

        if ($rows->isEmpty()) {
            $knownEntities = DB::table('idols')->whereIn('idx', array_column($fixtureRows, 'idx'))
                ->get(['idx', 'type', 'gender', 'status'])->keyBy('idx');
            return array_values(array_filter($fixtureRows, function (array $idol) use ($knownEntities, $type, $gender) {
                $known = $knownEntities->get($idol['idx']);
                return $known === null || ($known->status === 'active' && $known->type === $type
                    && ($gender === null || $known->gender === $gender));
            }));
        }

        $groupNames = $type === 'individual'
            ? DB::table('idol_group_members as gm')->join('idols as g', 'g.idx', '=', 'gm.group_idx')
                ->where('g.status', 'active')->whereIn('gm.individual_idx', $rows->pluck('idx'))->pluck('g.name', 'gm.individual_idx')
            : collect();

        return $rows->map(fn (object $idol) => [
            'idx'=>(int)$idol->idx, 'name'=>$idol->name,
            'group'=>$type === 'group' ? ($idol->gender === 'male' ? 'Boy Group' : 'Girl Group') : ($groupNames[$idol->idx] ?? 'Solo'),
            'hearts'=>(int)$idol->hearts, 'posts'=>(int)$idol->posts,
            'image'=>$type === 'group' ? 'group-blue' : 'portrait-blue',
            'image_url'=>ManagedImageStorage::publicUrl($idol->image_path),
        ])->all();
    }

    public function topVoters(int $idolIdx, CarbonInterface $month): array
    {
        if (!DatabaseAvailability::isAvailable()) return [];

        return DB::table('idol_votes as v')->join('members as m', 'm.idx', '=', 'v.member_idx')
            ->where('v.idol_idx', $idolIdx)->where('m.status', 'active')->whereNull('m.deleted_at')
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
