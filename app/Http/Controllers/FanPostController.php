<?php

namespace App\Http\Controllers;

use App\Support\HeartRewardManager;
use App\Support\DatabaseAvailability;
use App\Support\PublicFanPostList;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FanPostController extends Controller
{
    public function create()
    {
        $idols = DB::table('idols')->where('status', 'active')
            ->orderBy('name')->orderBy('idx')->get(['idx', 'name', 'type']);

        return view('fan-posts.create', ['idols' => $idols, 'editingPost' => null]);
    }

    public function store(Request $request)
    {
        $member = $request->attributes->get('currentMember');
        $mediaType = $request->string('media_type')->toString();
        $request->merge(['media_type' => $mediaType]);
        $validated = $request->validate([
            'idol_idx' => ['required', 'integer', Rule::exists('idols', 'idx')->where('status', 'active')],
            'title' => ['required', 'string', 'max:100'],
            'content' => ['required', 'string', 'max:2000'],
            'media_type' => ['required', Rule::in(['text', 'image', 'youtube'])],
            'image' => [Rule::requiredIf($mediaType === 'image'), Rule::prohibitedIf($mediaType !== 'image'), 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,gif', 'max:5120'],
            'youtube_url' => [Rule::requiredIf($mediaType === 'youtube'), Rule::prohibitedIf($mediaType !== 'youtube'), 'nullable', 'string', 'max:2048'],
            'tags' => ['nullable', 'string', 'max:619'],
        ]);

        $youtubeId = null;
        if ($mediaType === 'youtube') {
            $youtubeId = $this->youtubeId($validated['youtube_url'] ?? '');
            if ($youtubeId === null) {
                return back()->withInput()->withErrors(['youtube_url' => 'Enter a supported YouTube video URL.']);
            }
        }
        $tags = collect(explode(',', $validated['tags'] ?? ''))
            ->map(fn ($tag) => trim($tag))->filter()->unique(fn ($tag) => mb_strtolower($tag))->values();
        if ($tags->count() > 20 || $tags->contains(fn ($tag) => mb_strlen($tag) > 30 || !preg_match('/^[\pL\pN_-]+$/u', $tag))) {
            return back()->withInput()->withErrors(['tags' => 'Use up to 20 tags containing letters, numbers, underscores, or hyphens.']);
        }

        $imagePath = null;
        if ($mediaType === 'image') {
            $stored = $request->file('image')->store('fan-posts', 'public');
            $imagePath = 'storage/'.$stored;
        }

        try {
            $postIdx = DB::transaction(function () use ($validated, $member, $mediaType, $imagePath, $youtubeId, $tags) {
                $postIdx = DB::table('fan_posts')->insertGetId([
                    'idol_idx' => $validated['idol_idx'], 'member_idx' => $member->idx,
                    'actor_member_idx' => $member->idx, 'title' => $validated['title'],
                    'media_type' => $mediaType,
                    'image_path' => $imagePath, 'youtube_video_id' => $mediaType === 'youtube' ? $youtubeId : null,
                    'content' => $validated['content'], 'status' => 'published',
                    'view_count' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($tags->isNotEmpty()) {
                    DB::table('fan_post_tags')->insert($tags->map(fn ($tag) => [
                        'fan_post_idx' => $postIdx, 'tag' => $tag, 'created_at' => now(),
                    ])->all());
                }
                return $postIdx;
            });
        } catch (\Throwable $exception) {
            if ($imagePath !== null) {
                Storage::disk('public')->delete(substr($imagePath, strlen('storage/')));
            }
            throw $exception;
        }

        $rewardedHearts = 0;
        $savedPost = DB::table('fan_posts')->where('idx', $postIdx)
            ->first(['idx', 'media_type', 'image_path', 'youtube_video_id']);
        $hasEligibleMedia = $savedPost !== null && (
            ($savedPost->media_type === 'image' && filled($savedPost->image_path))
            || ($savedPost->media_type === 'youtube' && filled($savedPost->youtube_video_id))
        );

        if ($hasEligibleMedia) {
            try {
                // The post ID makes this reward idempotent; the helper enforces settings and the daily limit.
                $rewardedHearts = DB::transaction(fn () => app(HeartRewardManager::class)->award(
                    (int) $member->idx,
                    'fan_post_create',
                    'fan_post_reward:'.$savedPost->idx,
                    ['memo' => 'Eligible Fan Post creation reward']
                ));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $redirect = redirect()->route('fan-post.show', ['idx' => $postIdx]);
        if ($rewardedHearts > 0) {
            $redirect->with('global_notification', [
                'title' => 'Fan Post created',
                'message' => 'Fan Post created! +'.number_format($rewardedHearts).' Hearts',
                'type' => 'success',
            ]);
        }

        return $redirect;
    }

    public function edit(Request $request, string $idx)
    {
        $member = $request->attributes->get('currentMember');
        $post = DB::table('fan_posts')->where('idx', $idx)->where('member_idx', $member->idx)
            ->whereNull('deleted_at')->first(['idx', 'idol_idx', 'title', 'content', 'media_type', 'image_path', 'youtube_video_id']);
        abort_if($post === null, 404);
        $idols = DB::table('idols')->where('status', 'active')->orderBy('name')->orderBy('idx')->get(['idx', 'name', 'type']);
        $editingPost = [
            'idx' => $post->idx, 'idol_idx' => $post->idol_idx, 'title' => $post->title,
            'content' => $post->content, 'media_type' => $post->media_type,
            'image_url' => \App\Support\ManagedImageStorage::publicUrl($post->image_path),
            'youtube_url' => $post->youtube_video_id ? 'https://www.youtube.com/watch?v='.$post->youtube_video_id : null,
            'tags' => DB::table('fan_post_tags')->where('fan_post_idx', $post->idx)->orderBy('idx')->pluck('tag')->implode(','),
        ];

        return view('fan-posts.create', compact('idols', 'editingPost'));
    }

    public function update(Request $request, string $idx)
    {
        $member = $request->attributes->get('currentMember');
        $post = DB::table('fan_posts')->where('idx', $idx)->where('member_idx', $member->idx)
            ->whereNull('deleted_at')->first(['idx', 'image_path']);
        abort_if($post === null, 404);
        $mediaType = $request->string('media_type')->toString();
        $request->merge(['media_type' => $mediaType]);
        $validated = $request->validate([
            'idol_idx' => ['required', 'integer', Rule::exists('idols', 'idx')->where('status', 'active')],
            'title' => ['required', 'string', 'max:100'], 'content' => ['required', 'string', 'max:2000'],
            'media_type' => ['required', Rule::in(['text', 'image', 'youtube'])],
            'image' => [Rule::requiredIf($mediaType === 'image' && $post->image_path === null), Rule::prohibitedIf($mediaType !== 'image'), 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,gif', 'max:5120'],
            'youtube_url' => [Rule::requiredIf($mediaType === 'youtube'), Rule::prohibitedIf($mediaType !== 'youtube'), 'nullable', 'string', 'max:2048'],
            'tags' => ['nullable', 'string', 'max:619'],
        ]);
        $youtubeId = $mediaType === 'youtube' ? $this->youtubeId($validated['youtube_url'] ?? '') : null;
        if ($mediaType === 'youtube' && $youtubeId === null) return back()->withInput()->withErrors(['youtube_url' => 'Enter a supported YouTube video URL.']);
        $tags = $this->validatedTags($validated['tags'] ?? '');
        if ($tags === null) return back()->withInput()->withErrors(['tags' => 'Use up to 20 tags containing letters, numbers, underscores, or hyphens.']);
        $newImagePath = $post->image_path;
        if ($mediaType === 'image' && $request->hasFile('image')) {
            $newImagePath = 'storage/'.$request->file('image')->store('fan-posts', 'public');
        } elseif ($mediaType !== 'image') {
            $newImagePath = null;
        }
        try {
            DB::transaction(function () use ($idx, $validated, $mediaType, $newImagePath, $youtubeId, $tags) {
                DB::table('fan_posts')->where('idx', $idx)->update([
                    'idol_idx' => $validated['idol_idx'], 'title' => $validated['title'], 'content' => $validated['content'],
                    'media_type' => $mediaType, 'image_path' => $newImagePath,
                    'youtube_video_id' => $mediaType === 'youtube' ? $youtubeId : null, 'updated_at' => now(),
                ]);
                DB::table('fan_post_tags')->where('fan_post_idx', $idx)->delete();
                if ($tags->isNotEmpty()) DB::table('fan_post_tags')->insert($tags->map(fn ($tag) => ['fan_post_idx' => $idx, 'tag' => $tag, 'created_at' => now()])->all());
            });
        } catch (\Throwable $exception) {
            if ($newImagePath !== null && $newImagePath !== $post->image_path) Storage::disk('public')->delete(substr($newImagePath, 8));
            throw $exception;
        }
        if ($post->image_path && $post->image_path !== $newImagePath) Storage::disk('public')->delete(substr($post->image_path, 8));
        return redirect()->route('fan-post.show', ['idx' => $idx]);
    }

    public function show(Request $request, string $idx, PublicFanPostList $fanPostList)
    {
        $fixtures = require resource_path('data/fan-posts.php');
        $fixturePost = $fixtures[$idx] ?? null;
        $databaseDriverAvailable = DatabaseAvailability::isAvailable();
        $databasePostState = $databaseDriverAvailable
            ? DB::table('fan_posts as p')->join('idols as i', 'i.idx', '=', 'p.idol_idx')->where('p.idx', $idx)
                ->first(['p.status', 'p.deleted_at', 'i.status as idol_status'])
            : null;
        if ($databasePostState !== null && ($databasePostState->status !== 'published' || $databasePostState->deleted_at !== null || $databasePostState->idol_status !== 'active')) {
            abort(404);
        }
        $databaseRecord = $databaseDriverAvailable ? DB::table('fan_posts as post')
            ->join('members as member', 'member.idx', '=', 'post.member_idx')
            ->join('idols as idol', 'idol.idx', '=', 'post.idol_idx')
            ->where('post.idx', $idx)->where('post.status', 'published')->whereNull('post.deleted_at')->where('idol.status', 'active')
            ->first(['post.idx', 'post.idol_idx', 'post.title', 'post.media_type', 'post.image_path', 'post.youtube_video_id',
                'post.content', 'post.view_count', 'post.created_at', 'member.idx as author_idx',
                'member.member_code', 'member.nickname', 'member.profile_image_path', 'idol.name as idol_name']) : null;
        abort_unless($fixturePost !== null || $databaseRecord !== null, 404);
        $post = $databaseRecord === null ? $fixturePost : [
            'idx' => (int) $databaseRecord->idx, 'idol_idx' => (int) $databaseRecord->idol_idx,
            'title' => $databaseRecord->title, 'body' => $databaseRecord->content, 'media_type' => $databaseRecord->media_type,
            'image' => 'portrait-rose', 'image_url' => \App\Support\ManagedImageStorage::publicUrl($databaseRecord->image_path),
            'youtube_video_id' => $databaseRecord->youtube_video_id, 'views' => (int) $databaseRecord->view_count,
            'recommendations' => 0, 'tags' => DB::table('fan_post_tags')->where('fan_post_idx', $idx)->orderBy('idx')->pluck('tag')->all(),
            'comments' => [], 'shape' => 'square',
            'author' => ['idx' => (int) $databaseRecord->author_idx, 'member_code' => $databaseRecord->member_code,
                'nickname' => $databaseRecord->nickname, 'image' => 'portrait-rose',
                'image_url' => $databaseRecord->profile_image_path, 'bio' => ''],
        ];
        $member = $request->attributes->get('currentMember');
        $databasePost = $databaseDriverAvailable && $databaseRecord !== null;
        if ($databasePost) {
            $post['recommendations'] = DB::table('fan_post_likes')->where('fan_post_idx', $idx)->count();
            $isRecommended = $member !== null && DB::table('fan_post_likes')
                ->where('fan_post_idx', $idx)->where('member_idx', $member->idx)->exists();
        } else {
            $isRecommended = $member !== null
                && (bool) $request->session()->get('fixture_fan_post_likes.'.$idx, false);
            if ($isRecommended) {
                $post['recommendations']++;
            }
        }
        $morePosts = array_slice(array_filter($fixtures, fn ($item) => $item['author']['idx'] === $post['author']['idx'] && $item['idx'] !== $post['idx']), 0, 3);
        $trendingPosts = $databaseDriverAvailable
            ? DB::table('fan_posts as trending')
                ->join('idols as trending_idol', 'trending_idol.idx', '=', 'trending.idol_idx')
                ->where('trending.idx', '<>', $idx)
                ->where('trending.status', 'published')
                ->whereNull('trending.deleted_at')
                ->where('trending_idol.status', 'active')
                ->orderByDesc('trending.view_count')
                ->orderByDesc('trending.created_at')
                ->orderByDesc('trending.idx')
                ->limit(3)
                ->get(['trending.idx', 'trending.title', 'trending.media_type', 'trending.image_path',
                    'trending.youtube_video_id', 'trending.created_at'])
                ->map(fn ($item) => [
                    'idx' => (int) $item->idx,
                    'title' => $item->title,
                    'image_url' => $item->media_type === 'youtube' && $item->youtube_video_id
                        ? 'https://i.ytimg.com/vi/'.$item->youtube_video_id.'/hqdefault.jpg'
                        : $item->image_path,
                    'time' => \Illuminate\Support\Carbon::parse($item->created_at)->diffForHumans(),
                ])->all()
            : [];
        if (count($trendingPosts) < 3 && $databaseRecord !== null) {
            $trendingPosts[] = [
                'idx' => (int) $databaseRecord->idx,
                'title' => $databaseRecord->title,
                'image_url' => $databaseRecord->media_type === 'youtube' && $databaseRecord->youtube_video_id
                    ? 'https://i.ytimg.com/vi/'.$databaseRecord->youtube_video_id.'/hqdefault.jpg'
                    : $databaseRecord->image_path,
                'time' => \Illuminate\Support\Carbon::parse($databaseRecord->created_at)->diffForHumans(),
            ];
        }
        $fanPostSort = $request->string('sort')->toString();
        if (!in_array($fanPostSort, ['latest', 'liked', 'commented'], true)) {
            $fanPostSort = 'latest';
        }
        $posts = $databaseDriverAvailable
            ? $fanPostList->forIdol((int) $post['idol_idx'], $fanPostSort)
            : [];

        $commentsPage = filter_var($request->query('comments_page', 1), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]) ?: 1;
        if ($databasePost) {
            $commentsQuery = DB::table('fan_post_comments as comment')
                ->join('members as member', 'member.idx', '=', 'comment.member_idx')
                ->where('comment.fan_post_idx', $idx)
                ->where('comment.status', 'published')
                ->whereNull('comment.deleted_at')
                ->orderByDesc('comment.created_at')
                ->orderByDesc('comment.idx');
            $commentCount = (clone $commentsQuery)->count();
            $commentsPage = min($commentsPage, max(1, (int) ceil($commentCount / 10)));
            $commentItems = $commentsQuery->forPage($commentsPage, 10)
                ->get(['comment.idx', 'comment.member_idx', 'comment.content', 'comment.created_at',
                    'member.member_code', 'member.nickname', 'member.profile_image_path'])
                ->map(fn ($comment) => [
                    'idx' => (int) $comment->idx,
                    'member_idx' => (int) $comment->member_idx,
                    'member_code' => $comment->member_code,
                    'nickname' => $comment->nickname,
                    'image_url' => $comment->profile_image_path,
                    'image' => 'portrait-rose',
                    'body' => $comment->content,
                    'time' => \Illuminate\Support\Carbon::parse($comment->created_at)->diffForHumans(),
                    'can_delete' => $member !== null && (int) $comment->member_idx === (int) $member->idx,
                ]);
        } else {
            $commentCount = count($post['comments']);
            $commentsPage = min($commentsPage, max(1, (int) ceil($commentCount / 10)));
            $commentItems = collect(array_slice($post['comments'], ($commentsPage - 1) * 10, 10))
                ->map(fn ($comment, $offset) => $comment + ['time' => ($offset + 1).' hours ago']);
        }
        $comments = new LengthAwarePaginator($commentItems, $commentCount, 10, $commentsPage, [
            'path' => $request->url(),
            'pageName' => 'comments_page',
            'query' => $request->query(),
            'fragment' => 'comments',
        ]);

        if ($databasePost) {
            $viewCountIncremented = DB::table('fan_posts')
                ->where('idx', $idx)
                ->where('status', 'published')
                ->whereNull('deleted_at')
                ->whereExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('idols')
                        ->whereColumn('idols.idx', 'fan_posts.idol_idx')
                        ->where('idols.status', 'active');
                })
                ->increment('view_count');

            if ($viewCountIncremented === 1) {
                $post['views']++;
            }
        }

        return view('fan-posts.show', [
            'post' => $post, 'posts' => $posts, 'morePosts' => $morePosts,
            'trendingPosts' => $trendingPosts,
            'isRecommended' => $isRecommended, 'comments' => $comments, 'fanPostSort' => $fanPostSort,
            'isOwnPost' => $databasePost && $member !== null && (int) $post['author']['idx'] === (int) $member->idx,
            'databasePost' => $databasePost,
            'seo' => [
                'title' => $post['title'].' | IDOL RANKING',
                'description' => mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($post['body'] ?? ''))), 0, 180),
                'image' => ($post['media_type'] ?? 'image') === 'image' ? \App\Support\ManagedImageStorage::publicUrl($post['image_url'] ?? null)
                    : (($post['media_type'] ?? null) === 'youtube' && ($post['youtube_video_id'] ?? null) ? 'https://i.ytimg.com/vi/'.$post['youtube_video_id'].'/hqdefault.jpg' : null),
                'url' => route('fan-post.show', ['idx' => $idx]),
                'type' => 'article',
            ],
        ]);
    }

    public function storeComment(Request $request, string $idx)
    {
        $member = $request->attributes->get('currentMember');
        $request->merge(['content' => trim((string) $request->input('content'))]);
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
            'request_key' => ['required', 'string', 'min:16', 'max:128', 'regex:/^[A-Za-z0-9-]+$/'],
        ], [
            'content.required' => 'Please write a comment.',
            'content.max' => 'Comments may not exceed 1,000 characters.',
            'request_key.required' => 'Unable to submit the comment. Please try again.',
            'request_key.regex' => 'Unable to submit the comment. Please try again.',
        ]);

        $result = DB::transaction(function () use ($idx, $member, $validated): array {
            $currentMember = DB::table('members')->where('idx', $member->idx)
                ->where('member_level', '>', 0)->where('status', 'active')->whereNull('deleted_at')
                ->lockForUpdate()->first(['idx', 'member_code', 'nickname', 'profile_image_path', 'member_level', 'is_dummy', 'heart_balance']);
            abort_if($currentMember === null, 403);

            $post = DB::table('fan_posts as p')->join('idols as i', 'i.idx', '=', 'p.idol_idx')->where('p.idx', $idx)
                ->where('p.status', 'published')->whereNull('p.deleted_at')->where('i.status', 'active')
                ->lockForUpdate()->first(['p.idx']);
            if ($post === null) {
                return ['error' => 'Fan Post not found.', 'status' => 404];
            }

            $existing = DB::table('fan_post_comments')
                ->where('actor_member_idx', $currentMember->idx)
                ->where('request_key', $validated['request_key'])
                ->first();
            if ($existing !== null) {
                if ((int) $existing->fan_post_idx !== (int) $post->idx || $existing->content !== $validated['content']) {
                    return ['error' => 'This comment request has already been used.', 'status' => 409];
                }
                if ($existing->status !== 'published' || $existing->deleted_at !== null) {
                    return ['error' => 'This comment request has already been completed.', 'status' => 409];
                }

                return [
                    'comment' => $this->commentResponse($existing, $currentMember),
                    'comment_count' => $this->publishedCommentCount((int) $post->idx),
                    'created' => false,
                ];
            }

            $commentIdx = DB::table('fan_post_comments')->insertGetId([
                'fan_post_idx' => $post->idx,
                'member_idx' => $currentMember->idx,
                'actor_member_idx' => $currentMember->idx,
                'request_key' => $validated['request_key'],
                'content' => $validated['content'],
                'status' => 'published',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $comment = DB::table('fan_post_comments')->where('idx', $commentIdx)->first();

            return [
                'comment' => $this->commentResponse($comment, $currentMember),
                'comment_count' => $this->publishedCommentCount((int) $post->idx),
                'created' => true,
            ];
        });

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'message' => $result['error']], $result['status']);
        }

        $result['rewarded_hearts'] = 0;
        if ($result['created']) {
            try {
                $commentIdx = (int) $result['comment']['idx'];
                // The persisted comment ID makes retries idempotent; settings and limits stay centralized.
                $result['rewarded_hearts'] = DB::transaction(fn () => app(HeartRewardManager::class)->award(
                    (int) $member->idx,
                    'comment_create',
                    'comment_reward:'.$commentIdx,
                    ['comment_idx' => $commentIdx, 'memo' => 'Fan Post comment creation reward']
                ));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return response()->json([
            'success' => true,
            'message' => $result['rewarded_hearts'] > 0
                ? 'Comment posted! +'.number_format($result['rewarded_hearts']).' Hearts'
                : 'Comment posted.',
            'data' => $result,
        ], $result['created'] ? 201 : 200);
    }

    public function destroyComment(Request $request, string $idx, string $commentIdx)
    {
        $member = $request->attributes->get('currentMember');

        $commentCount = DB::transaction(function () use ($idx, $commentIdx, $member): int {
            $currentMember = DB::table('members')->where('idx', $member->idx)
                ->where('member_level', '>', 0)->where('status', 'active')->whereNull('deleted_at')
                ->lockForUpdate()->first(['idx']);
            abort_if($currentMember === null, 403);

            $postExists = DB::table('fan_posts')->where('idx', $idx)->exists();
            abort_unless($postExists, 404);
            $comment = DB::table('fan_post_comments')->where('idx', $commentIdx)
                ->where('fan_post_idx', $idx)->whereNull('deleted_at')->lockForUpdate()->first(['idx', 'member_idx']);
            abort_if($comment === null, 404);
            abort_unless((int) $comment->member_idx === (int) $currentMember->idx, 403);

            DB::table('fan_post_comments')->where('idx', $comment->idx)->update([
                'status' => 'hidden',
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->publishedCommentCount((int) $idx);
        });

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted.',
            'data' => ['comment_count' => $commentCount],
        ]);
    }

    private function publishedCommentCount(int $fanPostIdx): int
    {
        return DB::table('fan_post_comments')->where('fan_post_idx', $fanPostIdx)
            ->where('status', 'published')->whereNull('deleted_at')->count();
    }

    private function commentResponse(object $comment, object $member): array
    {
        return [
            'idx' => (int) $comment->idx,
            'member_code' => $member->member_code,
            'member_url' => route('member.fan-posts', ['member_code' => $member->member_code]),
            'nickname' => $member->nickname,
            'image_url' => \App\Support\ManagedImageStorage::publicUrl($member->profile_image_path),
            'content' => $comment->content,
            'created_label' => 'just now',
            'delete_url' => route('fan-post.comments.destroy', ['idx' => $comment->fan_post_idx, 'commentIdx' => $comment->idx]),
            'can_delete' => true,
        ];
    }

    public function report(Request $request, string $idx)
    {
        $member = $request->attributes->get('currentMember');
        $validated = $request->validate([
            'reason' => ['required', Rule::in([
                'spam', 'harassment_hate', 'inappropriate_content',
                'copyright', 'misinformation', 'other',
            ])],
            'details' => ['nullable', 'string', 'max:2000', 'required_if:reason,other'],
        ]);

        $post = DB::table('fan_posts as p')->join('idols as i', 'i.idx', '=', 'p.idol_idx')->where('p.idx', $idx)
            ->where('p.status', 'published')->whereNull('p.deleted_at')->where('i.status', 'active')
            ->first(['p.idx', 'p.member_idx']);
        if ($post === null) {
            return response()->json(['success' => false, 'message' => 'Fan Post not found.'], 404);
        }
        if ((int) $post->member_idx === (int) $member->idx) {
            return response()->json(['success' => false, 'message' => 'You cannot report your own Fan Post.'], 422);
        }

        try {
            DB::table('fan_post_reports')->insert([
                'fan_post_idx' => $post->idx,
                'member_idx' => $member->idx,
                'reason' => $validated['reason'],
                'details' => filled($validated['details'] ?? null) ? trim($validated['details']) : null,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reported this Fan Post.',
                ], 409);
            }
            throw $exception;
        }

        return response()->json([
            'success' => true,
            'message' => 'Report submitted. Thank you for letting us know.',
        ], 201);
    }

    public function toggleRecommendation(Request $request, string $idx)
    {
        $member = $request->attributes->get('currentMember');

        $result = DB::transaction(function () use ($idx, $member) {
            $post = DB::table('fan_posts as p')->join('idols as i', 'i.idx', '=', 'p.idol_idx')->where('p.idx', $idx)
                ->where('p.status', 'published')->whereNull('p.deleted_at')->where('i.status', 'active')
                ->lockForUpdate()->first(['p.idx', 'p.member_idx']);
            if ($post === null) {
                return null;
            }

            $recommendationExists = DB::table('fan_post_likes')
                ->where('fan_post_idx', $post->idx)->where('member_idx', $member->idx)->exists();
            if (! $recommendationExists) {
                DB::table('fan_post_likes')->insert([
                    'fan_post_idx' => $post->idx,
                    'member_idx' => $member->idx,
                    'created_at' => now(),
                ]);
            }

            return [
                'recommended' => true,
                'recommendations' => DB::table('fan_post_likes')->where('fan_post_idx', $post->idx)->count(),
                'created' => ! $recommendationExists,
                'post_idx' => (int) $post->idx,
                'author_member_idx' => (int) $post->member_idx,
            ];
        });

        if ($result === null) {
            $databasePostState = DB::table('fan_posts')->where('idx', $idx)->first(['status', 'deleted_at']);
            if ($databasePostState !== null) {
                abort(404);
            }
            $fixtures = require resource_path('data/fan-posts.php');
            abort_unless(isset($fixtures[$idx]), 404);
            $alreadyRecommended = (bool) $request->session()->get('fixture_fan_post_likes.'.$idx, false);
            $request->session()->put('fixture_fan_post_likes.'.$idx, true);
            $result = [
                'recommended' => true,
                'recommendations' => $fixtures[$idx]['recommendations'] + 1,
                'created' => ! $alreadyRecommended,
            ];
        } elseif ($result['created'] && $result['author_member_idx'] !== (int) $member->idx) {
            try {
                // The author receives the reward; the post/member pair is the stable recommendation identity.
                DB::transaction(fn () => app(HeartRewardManager::class)->award(
                    $result['author_member_idx'],
                    'fan_post_recommend_received',
                    'fan_post_recommend_received:'.$result['post_idx'].':'.$member->idx,
                    [
                        'actor_member_idx' => (int) $member->idx,
                        'memo' => 'Fan Post recommendation received reward',
                    ]
                ));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        unset($result['created'], $result['post_idx'], $result['author_member_idx']);

        return response()->json(['success' => true, 'data' => $result]);
    }

    private function youtubeId(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if ($parts === false || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) return null;
        $host = strtolower($parts['host'] ?? '');
        $id = null;
        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            $id = trim($parts['path'] ?? '', '/');
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            parse_str($parts['query'] ?? '', $query);
            $path = trim($parts['path'] ?? '', '/');
            $id = $path === 'watch' ? ($query['v'] ?? null) : (preg_match('~^(?:shorts|embed)/([^/]+)~', $path, $match) ? $match[1] : null);
        }
        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;
    }

    private function validatedTags(string $value): ?\Illuminate\Support\Collection
    {
        $tags = collect(explode(',', $value))->map(fn ($tag) => trim($tag))->filter()
            ->unique(fn ($tag) => mb_strtolower($tag))->values();
        return $tags->count() > 20 || $tags->contains(fn ($tag) => mb_strlen($tag) > 30 || !preg_match('/^[\pL\pN_-]+$/u', $tag)) ? null : $tags;
    }
}
