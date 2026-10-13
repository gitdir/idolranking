<?php

namespace App\Http\Controllers;

use App\Support\ManagedImageStorage;
use App\Support\PublicFanPostList;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function __invoke(Request $request, PublicFanPostList $fanPosts)
    {
        $query = mb_substr(trim($request->string('q')->toString()), 0, 100);
        $type = $request->string('type')->toString();
        $sort = $request->string('sort')->toString();

        if (!in_array($type, ['idol', 'group', 'fan-posts'], true)) $type = 'all';
        if (!in_array($sort, ['latest', 'liked', 'commented'], true)) $sort = 'latest';

        $counts = ['idol' => 0, 'group' => 0, 'fan-posts' => 0];
        $idols = $groups = $postResults = [];
        $paginator = null;

        if ($query !== '') {
            $counts['idol'] = $this->entityQuery('individual', $query)->count('i.idx');
            $counts['group'] = $this->entityQuery('group', $query)->count('i.idx');
            $counts['fan-posts'] = $fanPosts->searchCount($query);

            if ($type === 'all') {
                $idols = $this->entities('individual', $query, 5);
                $groups = $this->entities('group', $query, 5);
                $postResults = $fanPosts->search($query, $sort, 10);
            } elseif ($type === 'fan-posts') {
                $paginator = $fanPosts->searchPaginate($query, $sort);
                $postResults = $paginator->items();
            } else {
                $paginator = $this->entityQuery($type === 'idol' ? 'individual' : 'group', $query)
                    ->paginate(20)->withQueryString();
                $paginator->through(fn (object $idol): array => $this->entityCard($idol));
                if ($type === 'idol') $idols = $paginator->items();
                else $groups = $paginator->items();
            }
        }

        return view('search.index', [
            'query' => $query, 'type' => $type, 'sort' => $sort, 'counts' => $counts,
            'total' => array_sum($counts),
            'resultTotal' => $type === 'all' ? array_sum($counts) : $counts[$type],
            'idols' => $idols, 'groups' => $groups,
            'fanPosts' => $postResults, 'paginator' => $paginator,
            'seo' => ['title' => 'Search Results | IdolRanking', 'robots' => 'noindex, follow'],
        ]);
    }

    private function entityQuery(string $type, string $keyword): Builder
    {
        $monthStart = now()->startOfMonth();
        $votes = DB::table('idol_votes as iv')->join('members as vm', 'vm.idx', '=', 'iv.member_idx')
            ->selectRaw('iv.idol_idx, SUM(iv.heart_amount) hearts')
            ->where('iv.created_at', '>=', $monthStart)->where('iv.created_at', '<', $monthStart->copy()->addMonth())
            ->where('vm.member_level', '>', 0)->where('vm.status', 'active')->whereNull('vm.deleted_at')
            ->groupBy('iv.idol_idx');
        $posts = DB::table('fan_posts as fp')->join('members as pm', 'pm.idx', '=', 'fp.member_idx')
            ->selectRaw('fp.idol_idx, COUNT(*) posts')->where('fp.status', 'published')->whereNull('fp.deleted_at')
            ->where('pm.member_level', '>', 0)->where('pm.status', 'active')->whereNull('pm.deleted_at')
            ->groupBy('fp.idol_idx');
        $like = '%'.$keyword.'%';

        return DB::table('idols as i')
            ->leftJoinSub($votes, 'v', 'v.idol_idx', '=', 'i.idx')
            ->leftJoinSub($posts, 'p', 'p.idol_idx', '=', 'i.idx')
            ->where('i.status', 'active')->where('i.type', $type)
            ->where(fn (Builder $search) => $search->where('i.name', 'like', $like)
                ->orWhere('i.english_name', 'like', $like)->orWhere('i.content', 'like', $like))
            ->orderBy('i.name')->orderBy('i.idx')
            ->select(['i.idx', 'i.type', 'i.gender', 'i.name', 'i.english_name', 'i.image_path',
                DB::raw('COALESCE(v.hearts, 0) hearts'), DB::raw('COALESCE(p.posts, 0) posts')])
            ->selectSub(
                DB::table('idol_group_members as gm')->join('idols as g', 'g.idx', '=', 'gm.group_idx')
                    ->whereColumn('gm.individual_idx', 'i.idx')->where('g.status', 'active')
                    ->orderBy('g.idx')->limit(1)->select('g.name'),
                'group_name'
            )
            ->selectSub(
                DB::table('idol_group_members as gm')->join('idols as g', 'g.idx', '=', 'gm.group_idx')
                    ->whereColumn('gm.individual_idx', 'i.idx')->where('g.status', 'active')
                    ->orderBy('g.idx')->limit(1)->select('g.idx'),
                'group_idx'
            );
    }

    private function entities(string $type, string $keyword, int $limit): array
    {
        return $this->entityQuery($type, $keyword)->limit($limit)->get()
            ->map(fn (object $idol): array => $this->entityCard($idol))->all();
    }

    private function entityCard(object $idol): array
    {
        $group = $idol->type === 'group'
            ? ($idol->gender === 'male' ? 'Boy Group' : 'Girl Group')
            : ($idol->group_name ?? 'Solo');

        return [
            'idx' => (int) $idol->idx, 'name' => $idol->name, 'english_name' => $idol->english_name,
            'group' => $group, 'group_idx' => isset($idol->group_idx) ? (int) $idol->group_idx : null,
            'hearts' => (int) $idol->hearts, 'posts' => (int) $idol->posts,
            'image_url' => ManagedImageStorage::publicUrl($idol->image_path),
        ];
    }
}
