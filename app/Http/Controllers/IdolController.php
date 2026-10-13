<?php

namespace App\Http\Controllers;

use App\Support\ManagedImageStorage;
use App\Support\PublicFanPostList;
use App\Support\PublicRanking;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IdolController extends Controller
{
    public function show(Request $request, string $idx, PublicRanking $publicRanking, PublicFanPostList $fanPostList): View
    {
        $idol = DB::table('idols')->where('idx', $idx)->where('status', 'active')->first();
        abort_if($idol === null, 404);

        $sort = $request->string('sort')->toString();
        if (! in_array($sort, ['latest', 'liked', 'commented'], true)) {
            $sort = 'latest';
        }

        $type = $idol->type === 'group' ? 'group' : 'individual';
        $category = ($idol->gender === 'male' ? 'boy' : 'girl').($type === 'group' ? '-group' : '');
        $group = $type === 'individual'
            ? DB::table('idol_group_members as gm')->join('idols as g', 'g.idx', '=', 'gm.group_idx')
                ->where('gm.individual_idx', $idol->idx)->where('g.status', 'active')->orderBy('g.idx')
                ->first(['g.idx', 'g.name'])
            : null;

        $rankings = collect($publicRanking->category($category))->values();
        $rankIndex = $rankings->search(fn (array $row): bool => (int) $row['idx'] === (int) $idol->idx);
        $rankRow = $rankIndex === false ? null : $rankings[$rankIndex];

        $profile = [
            'Name' => $idol->name,
            'English Name' => $idol->english_name ?: '—',
            'Type' => $type === 'group' ? 'Group' : 'Individual',
            'Group' => $group?->name ?? ($type === 'group' ? '—' : 'Solo'),
            'Date of Birth' => $idol->birth_date ?: '—',
            'Nationality' => $idol->nationality ?: '—',
            'Height / Weight' => trim(($idol->height_cm ?: '—').' cm / '.($idol->weight_kg ?: '—').' kg'),
            'Blood Type' => $idol->blood_type ?: '—',
            'Agency' => $idol->agency ?: '—',
            'Debut Date' => $idol->debut_date ?: '—',
            'Position' => $idol->position ?: '—',
            'SNS' => $idol->sns ?: '—',
        ];

        $voters = collect($publicRanking->topVoters((int) $idol->idx, now()->startOfMonth()))
            ->map(fn (array $voter): array => $voter + ['votes' => $voter['hearts']])
            ->all();
        $posts = $fanPostList->forIdol((int) $idol->idx, $sort);
        $popularImages = $publicRanking->popularImages((int) $idol->idx);

        $trendStart = now()->startOfDay()->subDays(29);
        $trendRows = DB::table('idol_votes as v')->join('members as m', 'm.idx', '=', 'v.member_idx')
            ->where('v.idol_idx', $idol->idx)->where('v.created_at', '>=', $trendStart)
            ->where('v.created_at', '<', now()->startOfDay()->addDay())
            ->where('m.member_level', '>', 0)->where('m.status', 'active')->whereNull('m.deleted_at')
            ->selectRaw('DATE(v.created_at) as vote_date, SUM(v.heart_amount) as votes')
            ->groupBy('vote_date')->pluck('votes', 'vote_date');
        $trend = [];
        for ($day = $trendStart->copy(); $day->lte(now()->startOfDay()); $day->addDay()) {
            $date = $day->toDateString();
            $trend[] = ['date' => $date, 'votes' => (int) ($trendRows[$date] ?? 0)];
        }
        if (array_sum(array_column($trend, 'votes')) === 0) {
            $trend = [];
        }

        $name = $idol->name;
        $imageUrl = ManagedImageStorage::publicUrl($idol->image_path);

        return view('idols.show', [
            'activeMenu' => $category,
            'idol' => [
                'idx' => (int) $idol->idx,
                'name' => $name,
                'group' => $group?->name ?? ($type === 'group' ? ($idol->gender === 'male' ? 'Boy Group' : 'Girl Group') : 'Solo'),
                'group_idx' => $group?->idx,
                'monthly_rank' => $rankIndex === false ? '—' : $rankIndex + 1,
                'monthly_votes' => (int) ($rankRow['hearts'] ?? 0),
                'fan_posts' => (int) ($rankRow['posts'] ?? 0),
                'image_url' => $imageUrl,
                'popular_images' => $popularImages,
            ],
            'profile' => $profile,
            'voters' => $voters,
            'trend' => $trend,
            'posts' => $posts,
            'fanPostSort' => $sort,
            'seo' => [
                'title' => $name.' | IDOL RANKING',
                'description' => implode(' · ', array_filter([$name, $group?->name, $idol->agency])),
                'image' => $imageUrl,
                'url' => route('idol.show', ['idx' => $idx]),
                'type' => 'profile',
            ],
        ]);
    }
}
