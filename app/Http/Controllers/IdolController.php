<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\PublicRanking;
use App\Support\PublicFanPostList;
use App\Support\DatabaseAvailability;

class IdolController extends Controller
{
    public function show(Request $request, string $idx, PublicRanking $publicRanking, PublicFanPostList $fanPostList): View
    {
        $sort = $request->string('sort')->toString();
        if (!in_array($sort, ['latest', 'liked', 'commented'], true)) {
            $sort = 'latest';
        }

        $databaseIdol = null;
        if (DatabaseAvailability::isAvailable()) {
            $databaseIdol = DB::table('idols')->where('idx', $idx)->first();
            if ($databaseIdol !== null && $databaseIdol->status !== 'active') {
                abort(404);
            }
        }
        $idols = require resource_path('data/idols.php');
        abort_unless(isset($idols[$idx]) || $databaseIdol !== null, 404);
        $selected = $databaseIdol === null ? $idols[$idx] : [
            'idx'=>(int)$databaseIdol->idx, 'name'=>$databaseIdol->name,
            'group'=>$databaseIdol->type === 'group' ? ($databaseIdol->gender === 'male' ? 'Boy Group' : 'Girl Group') : 'Solo',
            'group_idx'=>null, 'category'=>($databaseIdol->gender === 'male' ? 'boy' : 'girl').($databaseIdol->type === 'group' ? '-group' : ''),
            'type'=>$databaseIdol->type === 'group' ? 'Group' : 'Individual', 'monthly_rank'=>'—',
            'hearts'=>0, 'posts'=>0, 'image'=>$databaseIdol->type === 'group' ? 'group-blue' : 'portrait-blue',
        ];
        if ($databaseIdol !== null && $databaseIdol->type === 'individual') {
            $group = DB::table('idol_group_members as gm')->join('idols as g','g.idx','=','gm.group_idx')
                ->where('gm.individual_idx',$databaseIdol->idx)->where('g.status','active')->first(['g.idx','g.name']);
            $selected['group'] = $group?->name ?? 'Solo';
            $selected['group_idx'] = $group?->idx;
        }
        $data = require resource_path('data/idol-detail.php');
        $data['activeMenu'] = $selected['category'];
        $data['idol'] = array_replace($data['idol'], [
            'idx' => $selected['idx'], 'name' => $selected['name'],
            'group' => $selected['group'], 'group_idx' => $selected['group_idx'] ?? null,
        ]);
        if ($databaseIdol !== null) {
            $data['idol']['image_url'] = \App\Support\ManagedImageStorage::publicUrl($databaseIdol->image_path);
            $ranked = collect($publicRanking->category($selected['category']))->values();
            $rankIndex = $ranked->search(fn(array $item) => (int)$item['idx'] === (int)$databaseIdol->idx);
            $rankItem = $rankIndex === false ? null : $ranked[$rankIndex];
            $data['idol']['monthly_rank'] = $rankIndex === false ? '—' : $rankIndex + 1;
            $data['idol']['monthly_votes'] = $rankItem['hearts'] ?? 0;
            $data['idol']['fan_posts'] = $rankItem['posts'] ?? 0;
            $data['voters'] = collect($publicRanking->topVoters((int) $databaseIdol->idx, now()->startOfMonth()))
                ->map(fn (array $voter) => $voter + ['votes' => $voter['hearts']])
                ->all();
            $data['profile'] = [
                'Name'=>$databaseIdol->name, 'English Name'=>$databaseIdol->english_name ?: '—',
                'Type'=>$databaseIdol->type === 'group' ? 'Group' : 'Individual', 'Group'=>$selected['group'],
                'Date of Birth'=>$databaseIdol->birth_date ?: '—', 'Nationality'=>$databaseIdol->nationality ?: '—',
                'Height / Weight'=>trim(($databaseIdol->height_cm ?: '—').' cm / '.($databaseIdol->weight_kg ?: '—').' kg'),
                'Blood Type'=>$databaseIdol->blood_type ?: '—', 'Agency'=>$databaseIdol->agency ?: '—',
                'Debut Date'=>$databaseIdol->debut_date ?: '—', 'Position'=>$databaseIdol->position ?: '—', 'SNS'=>$databaseIdol->sns ?: '—',
            ];
            $data['posts'] = $fanPostList->forIdol((int) $databaseIdol->idx, $sort);
        }
        if ($databaseIdol === null && $selected['idx'] !== 1) {
            $data['idol'] = array_replace($data['idol'], [
                'monthly_rank' => $selected['monthly_rank'], 'monthly_votes' => $selected['hearts'],
                'fan_posts' => $selected['posts'], 'popular_images' => array_fill(0, 3, $selected['image']),
            ]);
            // Unknown profile details stay empty instead of inheriting Jungkook's biography.
            $data['profile'] = array_fill_keys(array_keys($data['profile']), '—');
            $data['profile']['Name'] = $selected['name'];
            $data['profile']['English Name'] = $selected['name'];
            $data['profile']['Type'] = $selected['type'];
            $data['profile']['Group'] = $selected['type'] === 'Individual' ? $selected['group'] : '—';
        }

        if ($databaseIdol === null) {
            $data['posts'] = match ($sort) {
                'liked' => collect($data['posts'])->sortByDesc(fn (array $post) => $post[4])->values()->all(),
                'commented' => collect($data['posts'])->sortByDesc(fn (array $post) => $post[5])->values()->all(),
                default => $data['posts'],
            };
        }
        $data['fanPostSort'] = $sort;
        $descriptionParts = array_filter([$data['idol']['name'] ?? null, $data['idol']['group'] ?? null, $data['profile']['Agency'] ?? null]);
        $data['seo'] = [
            'title' => ($data['idol']['name'] ?? 'Idol').' | IDOL RANKING',
            'description' => implode(' · ', $descriptionParts),
            'image' => \App\Support\ManagedImageStorage::publicUrl($databaseIdol?->image_path),
            'url' => route('idol.show', ['idx' => $idx]),
            'type' => 'profile',
        ];

        return view('idols.show', $data);
    }

}
