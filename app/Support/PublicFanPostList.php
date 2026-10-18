<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class PublicFanPostList
{
    public function forIdol(int $idolIdx, string $sort): array
    {
        return $this->query($sort)
            ->where('p.idol_idx', $idolIdx)
            ->get()
            ->map(fn (object $post): array => $this->card($post))
            ->all();
    }

    public function byAuthor(int $memberIdx, int $excludeIdx, int $limit = 3): array
    {
        return $this->baseQuery()
            ->where('p.member_idx', $memberIdx)->where('p.idx', '<>', $excludeIdx)
            ->orderByDesc('p.created_at')->orderByDesc('p.idx')->limit($limit)
            ->get(['p.idx', 'p.title', 'p.media_type', 'p.image_path', 'p.youtube_video_id', 'p.created_at'])
            ->map(fn (object $post): array => [
                'idx' => (int) $post->idx,
                'title' => $post->title,
                'image_url' => $post->media_type === 'youtube' && $post->youtube_video_id
                    ? 'https://i.ytimg.com/vi/'.$post->youtube_video_id.'/hqdefault.jpg'
                    : ManagedImageStorage::publicUrl($post->image_path),
                'time' => \Illuminate\Support\Carbon::parse($post->created_at)->diffForHumans(),
            ])
            ->all();
    }

    public function paginate(string $sort, int $perPage = 20): LengthAwarePaginator
    {
        $posts = $this->query($sort)->paginate($perPage)->withQueryString();
        $posts->through(fn (object $post): array => $this->card($post));

        return $posts;
    }

    public function take(string $sort, int $limit): array
    {
        return $this->query($sort)
            ->limit($limit)
            ->get()
            ->map(fn (object $post): array => $this->card($post))
            ->all();
    }

    public function search(string $keyword, string $sort, int $limit): array
    {
        return $this->searchQuery($keyword, $sort)
            ->limit($limit)
            ->get()
            ->map(fn (object $post): array => $this->card($post))
            ->all();
    }

    public function searchPaginate(string $keyword, string $sort, int $perPage = 20): LengthAwarePaginator
    {
        $posts = $this->searchQuery($keyword, $sort)->paginate($perPage)->withQueryString();
        $posts->through(fn (object $post): array => $this->card($post));

        return $posts;
    }

    public function searchCount(string $keyword): int
    {
        return $this->applySearch($this->baseQuery(), $keyword)->count('p.idx');
    }

    private function query(string $sort): Builder
    {
        $monthStart = now('UTC')->startOfMonth();
        $query = $this->baseQuery()
            ->select([
                'p.idx', 'p.media_type', 'p.image_path', 'p.youtube_video_id',
                'p.content', 'p.view_count', 'p.created_at',
                'p.language_code', 'm.idx as member_idx', 'm.member_code', 'm.nickname', 'm.country_code', 'm.profile_image_path',
            ])
            ->selectSub(
                DB::table('fan_post_likes as l')->selectRaw('COUNT(*)')
                    ->whereColumn('l.fan_post_idx', 'p.idx'),
                'like_count'
            )
            ->selectSub(
                DB::table('fan_post_likes as l')->selectRaw('COUNT(*)')
                    ->whereColumn('l.fan_post_idx', 'p.idx')
                    ->where('l.created_at', '>=', $monthStart)
                    ->where('l.created_at', '<', $monthStart->copy()->addMonth()),
                'monthly_like_count'
            )
            ->selectSub(
                DB::table('fan_post_comments as c')->selectRaw('COUNT(*)')
                    ->whereColumn('c.fan_post_idx', 'p.idx')
                    ->where('c.status', 'published')->whereNull('c.deleted_at'),
                'comment_count'
            );

        match ($sort) {
            'monthly_liked' => $query->orderByDesc('monthly_like_count')->orderByDesc('p.created_at')->orderByDesc('p.idx'),
            'liked' => $query->orderByDesc('like_count')->orderByDesc('p.created_at')->orderByDesc('p.idx'),
            'commented' => $query->orderByDesc('comment_count')->orderByDesc('p.created_at')->orderByDesc('p.idx'),
            default => $query->orderByDesc('p.created_at')->orderByDesc('p.idx'),
        };

        return $query;
    }

    private function baseQuery(): Builder
    {
        return DB::table('fan_posts as p')
            ->join('members as m', 'm.idx', '=', 'p.member_idx')
            ->join('idols as i', 'i.idx', '=', 'p.idol_idx')
            ->where('p.status', 'published')
            ->whereNull('p.deleted_at')
            ->where('m.member_level', '>', 0)
            ->where('m.status', 'active')
            ->whereNull('m.deleted_at')
            ->where('i.status', 'active');
    }

    private function searchQuery(string $keyword, string $sort): Builder
    {
        return $this->applySearch($this->query($sort), $keyword);
    }

    private function applySearch(Builder $query, string $keyword): Builder
    {
        $like = '%'.$keyword.'%';

        return $query->where(function (Builder $search) use ($like): void {
            $search->where('p.title', 'like', $like)
                ->orWhere('p.content', 'like', $like)
                ->orWhereExists(function (Builder $tags) use ($like): void {
                    $tags->selectRaw('1')->from('fan_post_tags as t')
                        ->whereColumn('t.fan_post_idx', 'p.idx')
                        ->where('t.tag', 'like', $like);
                });
        });
    }

    private function card(object $post): array
    {
        return [
            $this->profilePlaceholder($post->member_idx),
            $post->nickname,
            $post->content ?? '',
            (int) $post->view_count,
            (int) $post->like_count,
            (int) $post->comment_count,
            'square',
            'member_code' => $post->member_code,
            'language_code' => $post->language_code,
            'country_code' => $post->country_code,
            'country_name' => Countries::localizedName($post->country_code, app()->getLocale()),
            'author_image_url' => $post->profile_image_path,
            'fan_post_idx' => (int) $post->idx,
            'monthly_like_count' => (int) $post->monthly_like_count,
            'media_type' => $post->media_type,
            'youtube_video_id' => $post->youtube_video_id,
            'image_url' => ManagedImageStorage::publicUrl($post->image_path),
        ];
    }

    private function profilePlaceholder(int|string $memberIdx): string
    {
        $placeholders = ['portrait-rose', 'portrait-blue', 'portrait-sage'];

        return $placeholders[((int) $memberIdx - 1) % count($placeholders)];
    }
}
