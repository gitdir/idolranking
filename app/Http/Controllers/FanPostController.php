<?php

namespace App\Http\Controllers;

use App\Support\HeartRewardManager;
use App\Support\FanPostLanguages;
use App\Support\FanPostSourceHash;
use App\Support\PublicFanPostList;
use App\Support\RandomDummyActivity;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FanPostController extends Controller
{
    private const VIEW_COOKIE_NAME = 'fan_post_view_times';
    private const VIEW_WINDOW_SECONDS = 3600;
    private const VIEW_COOKIE_MAX_POSTS = 40;

    public function create(Request $request, HeartRewardManager $heartRewards)
    {
        $idolCategories = ['boy', 'boy_group', 'girl', 'girl_group'];
        $category = old('idol_category');
        $preselectedIdolIdx = null;

        if ($request->query->has('idol_idx')) {
            $queryIdolIdx = $request->query('idol_idx');
            abort_unless(is_string($queryIdolIdx) && ctype_digit($queryIdolIdx), 404);
            $preselectedIdol = DB::table('idols')->where('idx', $queryIdolIdx)->where('status', 'active')
                ->first(['idx', 'type', 'gender']);
            abort_if($preselectedIdol === null, 404);
            $preselectedCategory = $this->idolCategory($preselectedIdol);
            abort_if($preselectedCategory === null, 404);
            abort_if($request->query->has('idol_category') && $request->query('idol_category') !== $preselectedCategory, 404);

            $category ??= $preselectedCategory;
            $preselectedIdolIdx = (int) $preselectedIdol->idx;
        }

        if (!in_array($category, $idolCategories, true)) {
            $category = null;
        }

        $selectedIdolIdx = old('idol_idx', $preselectedIdolIdx);
        $idols = $category === null ? collect() : $this->activeIdolsForCategory($category);
        if ($selectedIdolIdx !== null && !$idols->contains(fn (object $idol): bool => (int) $idol->idx === (int) $selectedIdolIdx)) {
            $selectedIdolIdx = null;
        }
        $currentMember = $request->attributes->get('currentMember');
        $fanPostRewardSetting = DB::table('heart_reward_settings')->where('action_code', 'fan_post_create')
            ->first(['heart_amount', 'daily_limit', 'is_enabled']);
        $fanPostRewardEnabled = $fanPostRewardSetting !== null
            && (bool) $fanPostRewardSetting->is_enabled
            && (int) $fanPostRewardSetting->heart_amount > 0;

        return view('fan-posts.create', ['idols' => $idols, 'idolCategory' => $category, 'selectedIdolIdx' => $selectedIdolIdx,
            'idolCategories' => $idolCategories, 'editingPost' => null, 'postLanguages' => FanPostLanguages::all(),
            'approvalRewardAmount' => $heartRewards->enabledAmount('idol_registration_approved'),
            'fanPostRewardAmount' => $fanPostRewardEnabled ? (int) $fanPostRewardSetting->heart_amount : 0,
            'fanPostRewardDailyLimit' => $fanPostRewardEnabled ? max(0, (int) $fanPostRewardSetting->daily_limit) : 0,
            'requestKey' => bin2hex(random_bytes(32)), 'randomDummyMode' => app(RandomDummyActivity::class)->enabled($request),
            'isAdmin' => $currentMember !== null && (int) $currentMember->member_level === 10 && (int) $currentMember->is_dummy === 0]);
    }

    public function idolsForCategory(Request $request)
    {
        $validated = $request->validate(['category' => ['required', Rule::in(['boy', 'boy_group', 'girl', 'girl_group'])]]);

        return response()->json(['idols' => $this->activeIdolsForCategory($validated['category'])]);
    }

    public function store(Request $request)
    {
        $member = $request->attributes->get('activityMember');
        $actor = $request->attributes->get('activityActor');
        $currentMember = $request->attributes->get('currentMember');
        $autoDistribute = $request->boolean('auto_distribute');
        $isAdmin = $currentMember !== null && (int) $currentMember->member_level === 10 && (int) $currentMember->is_dummy === 0;
        abort_if($autoDistribute && ! $isAdmin, 403);
        $randomActivities = app(RandomDummyActivity::class);
        $randomMode = $randomActivities->enabled($request);
        $mediaType = $request->string('media_type')->toString();
        if ($mediaType === 'youtube' && blank($request->input('youtube_url')) && filled($request->input('content'))) {
            $mediaType = 'text';
        }
        if ($randomMode) {
            $request->merge(['language_code' => 'ko']);
        }
        $request->merge([
            'media_type' => $mediaType,
            'language_code' => $request->input('language_code') ?? app()->getLocale(),
        ]);
        $fieldNames = [
            'idol_category' => __('ui.request_type'), 'idol_idx' => __('ui.idol'), 'title' => __('ui.title'),
            'content' => __('ui.content'), 'language_code' => __('ui.post_language'), 'media_type' => __('ui.media'),
            'image' => __('ui.image'), 'youtube_url' => __('ui.youtube_url'), 'tags' => __('ui.tags'),
        ];
        $category = $request->validate(
            ['idol_category' => ['required', Rule::in(['boy', 'boy_group', 'girl', 'girl_group'])]],
            [],
            $fieldNames
        )['idol_category'];
        [$idolType, $idolGender] = $this->categoryParts($category);
        $validated = $request->validate([
            'idol_idx' => ['required', 'integer', Rule::exists('idols', 'idx')->where('status', 'active')->where('type', $idolType)->where('gender', $idolGender)],
            'title' => ['required', 'string', 'max:100'],
            'content' => [Rule::requiredIf($mediaType === 'text'), 'nullable', 'string', 'max:2000'],
            'language_code' => ['required', 'string', Rule::in(array_keys(FanPostLanguages::all()))],
            'media_type' => ['required', Rule::in(['text', 'image', 'youtube'])],
            'auto_distribute' => ['sometimes', 'boolean'],
            'image' => [Rule::requiredIf($mediaType === 'image'), Rule::prohibitedIf($mediaType !== 'image'), 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,gif', 'max:5120'],
            'youtube_url' => [Rule::requiredIf($mediaType === 'youtube'), Rule::prohibitedIf($mediaType !== 'youtube'), 'nullable', 'string', 'max:2048'],
            'tags' => ['nullable', 'string', 'max:619'],
            'request_key' => [$randomMode ? 'required' : 'nullable', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
        ], [
            'language_code.required' => __('ui.post_language_required'),
            'language_code.in' => __('ui.post_language_invalid'),
        ], $fieldNames);

        $youtubeId = null;
        if ($mediaType === 'youtube') {
            $youtubeId = $this->youtubeId($validated['youtube_url'] ?? '');
            if ($youtubeId === null) {
                return back()->withInput()->withErrors(['youtube_url' => __('messages.youtube_url_unsupported')]);
            }
        }
        $tags = collect(explode(',', $validated['tags'] ?? ''))
            ->map(fn ($tag) => trim($tag))->filter()->unique(fn ($tag) => mb_strtolower($tag))->values();
        if ($tags->count() > 20 || $tags->contains(fn ($tag) => mb_strlen($tag) > 30 || !preg_match('/^[\pL\pN_-]+$/u', $tag))) {
            return back()->withInput()->withErrors(['tags' => __('messages.fan_post_tags_invalid')]);
        }

        $imagePath = null;
        if ($mediaType === 'image') {
            $stored = $request->file('image')->store('fan-posts', 'public');
            $imagePath = 'storage/'.$stored;
        }
        $imageHash = $request->hasFile('image') ? hash_file('sha256', $request->file('image')->getRealPath()) : null;

        try {
            $reservation = $randomMode ? $randomActivities->reserve($request, 'fan_post_create', $validated['request_key'], [
                    'idol_idx' => (int) $validated['idol_idx'], 'title' => $validated['title'], 'content' => $validated['content'] ?? null,
                    'language_code' => $validated['language_code'], 'media_type' => $mediaType, 'youtube_id' => $youtubeId,
                    'image_hash' => $imageHash, 'tags' => $tags->all(), 'auto_distribute' => $autoDistribute,
                ]) : null;
            $created = DB::transaction(function () use ($request, $validated, $member, $actor, $mediaType, $imagePath, $youtubeId, $tags, $randomActivities, $reservation, $autoDistribute) {
                $reservation = $reservation ? $randomActivities->lockReservation($reservation) : null;
                if ($reservation?->replay) {
                    return ['idx' => (int) $reservation->result_idx, 'reservation' => $reservation, 'member_idx' => (int) $reservation->member_idx, 'replay' => true];
                }
                $activityMember = $reservation ? $randomActivities->member($reservation) : $member;
                abort_if($activityMember === null, 422, '사용할 수 있는 활성 더미 회원이 없습니다.');
                $createdAt = now('UTC');
                $postIdx = DB::table('fan_posts')->insertGetId([
                    'idol_idx' => $validated['idol_idx'], 'member_idx' => $activityMember->idx,
                    'actor_member_idx' => $actor->idx, 'title' => $validated['title'],
                    'language_code' => $validated['language_code'],
                    'media_type' => $mediaType,
                    'image_path' => $imagePath, 'youtube_video_id' => $mediaType === 'youtube' ? $youtubeId : null,
                    'content' => $validated['content'] ?? null, 'status' => $autoDistribute ? 'queued' : 'published',
                    'queued_at' => $autoDistribute ? $createdAt : null,
                    'published_at' => $autoDistribute ? null : $createdAt,
                    'view_count' => 0, 'created_at' => $createdAt, 'updated_at' => $createdAt,
                ]);
                if ($tags->isNotEmpty()) {
                    DB::table('fan_post_tags')->insert($tags->map(fn ($tag) => [
                        'fan_post_idx' => $postIdx, 'tag' => $tag, 'created_at' => now(),
                    ])->all());
                }
                if ($reservation) {
                    $randomActivities->complete($reservation, 'fan_post', (int) $postIdx);
                    $randomActivities->log($request, $reservation, 'fan_post_created', '랜덤 더미 회원이 팬포스트를 작성했습니다.');
                }
                $rewardedHearts = 0;
                $hasEligibleMedia = ($mediaType === 'image' && filled($imagePath))
                    || ($mediaType === 'youtube' && filled($youtubeId));
                if ($reservation && $hasEligibleMedia) {
                    $rewardedHearts = app(HeartRewardManager::class)->award(
                        (int) $activityMember->idx,
                        'fan_post_create',
                        'fan_post_reward:'.$postIdx,
                        ['actor_member_idx' => (int) $actor->idx, 'memo' => 'Eligible Fan Post creation reward',
                            'random_dummy_request_idx' => $reservation->idx]
                    );
                }
                return ['idx' => (int) $postIdx, 'reservation' => $reservation, 'member_idx' => (int) $activityMember->idx,
                    'replay' => false, 'rewarded_hearts' => $rewardedHearts];
            });
        } catch (\Throwable $exception) {
            if ($imagePath !== null) {
                Storage::disk('public')->delete(substr($imagePath, strlen('storage/')));
            }
            throw $exception;
        }

        $postIdx = $created['idx'];
        $reservation = $created['reservation'];
        if ($created['replay'] && $imagePath !== null) {
            Storage::disk('public')->delete(substr($imagePath, strlen('storage/')));
        }
        if ($created['replay']) {
            if ($autoDistribute) {
                return redirect()->route('admin.fan-post-distribution.index');
            }
            return redirect()->route('fan-post.show', ['idx' => $postIdx]);
        }

        $rewardedHearts = (int) ($created['rewarded_hearts'] ?? 0);
        $savedPost = DB::table('fan_posts')->where('idx', $postIdx)
            ->first(['idx', 'media_type', 'image_path', 'youtube_video_id']);
        $hasEligibleMedia = $savedPost !== null && (
            ($savedPost->media_type === 'image' && filled($savedPost->image_path))
            || ($savedPost->media_type === 'youtube' && filled($savedPost->youtube_video_id))
        );

        if ($hasEligibleMedia && ! $reservation) {
            try {
                // The post ID makes this reward idempotent; the helper enforces settings and the daily limit.
                $rewardedHearts = DB::transaction(fn () => app(HeartRewardManager::class)->award(
                    (int) $created['member_idx'],
                    'fan_post_create',
                    'fan_post_reward:'.$savedPost->idx,
                    ['actor_member_idx' => (int) $actor->idx, 'memo' => 'Eligible Fan Post creation reward'] + ($reservation ? ['random_dummy_request_idx' => $reservation->idx] : [])
                ));
            } catch (\Throwable $exception) {
                Log::error('Automatic Heart reward processing failed.', [
                    'reward_action' => 'fan_post_create',
                    'recipient_member_id' => (int) $created['member_idx'],
                    'actor_member_id' => (int) $actor->idx,
                    'fan_post_id' => (int) $savedPost->idx,
                    'comment_id' => null,
                    'operation_key' => 'fan_post_reward:'.$savedPost->idx,
                    'exception_class' => $exception::class,
                    'exception_message' => $exception->getMessage(),
                ]);
            }
        }

        if ($autoDistribute) {
            return redirect()->route('admin.fan-post-distribution.index')->with('global_notification', [
                'title' => '예약 발행 대기 등록', 'message' => '게시물이 비공개 대기열에 등록되었습니다.', 'type' => 'success',
            ]);
        }

        $redirect = redirect()->route('fan-post.show', ['idx' => $postIdx]);
        if ($rewardedHearts > 0) {
            $redirect->with('global_notification', [
                'title' => __('ui.fan_post_created'),
                'message' => __('ui.fan_post_created_reward', ['amount' => number_format($rewardedHearts)]),
                'type' => 'success',
            ]);
        }

        return $redirect;
    }

    private function activeIdolsForCategory(string $category)
    {
        [$type, $gender] = $this->categoryParts($category);

        return DB::table('idols')->where('status', 'active')->where('type', $type)->where('gender', $gender)
            ->orderBy('name')->orderBy('idx')->get(['idx', 'name', 'type']);
    }

    private function categoryParts(string $category): array
    {
        return match ($category) {
            'boy' => ['individual', 'male'],
            'boy_group' => ['group', 'male'],
            'girl' => ['individual', 'female'],
            'girl_group' => ['group', 'female'],
        };
    }

    private function idolCategory(object $idol): ?string
    {
        if (!in_array($idol->type, ['individual', 'group'], true) || !in_array($idol->gender, ['male', 'female'], true)) {
            return null;
        }

        return ($idol->gender === 'male' ? 'boy' : 'girl').($idol->type === 'group' ? '_group' : '');
    }

    public function edit(Request $request, string $idx)
    {
        $member = $request->attributes->get('activityMember');
        $post = DB::table('fan_posts as post')->join('idols as idol', 'idol.idx', '=', 'post.idol_idx')
            ->where('post.idx', $idx)->where('post.member_idx', $member->idx)->whereNull('post.deleted_at')
            ->first([
                'post.idx', 'post.idol_idx', 'post.title', 'post.content', 'post.language_code',
                'post.media_type', 'post.image_path', 'post.youtube_video_id', 'idol.name as idol_name',
                'idol.type as idol_type',
            ]);
        abort_if($post === null, 404);
        $postLanguages = FanPostLanguages::all();
        $languageCode = (string) ($post->language_code ?? '');
        $editingPost = [
            'idx' => $post->idx,
            'title' => $post->title,
            'content' => $post->content,
            'idol_idx' => $post->idol_idx,
            'idol_name' => $post->idol_name,
            'idol_type' => $post->idol_type,
            'language_code' => $languageCode,
            'language_name' => $postLanguages[$languageCode] ?? strtoupper($languageCode),
            'media_type' => $post->media_type,
            'image_url' => $post->media_type === 'image'
                ? \App\Support\ManagedImageStorage::publicUrl($post->image_path)
                : null,
            'youtube_url' => $post->media_type === 'youtube' && $post->youtube_video_id
                ? 'https://www.youtube.com/watch?v='.$post->youtube_video_id
                : null,
            'tags' => DB::table('fan_post_tags')->where('fan_post_idx', $post->idx)
                ->orderBy('idx')->pluck('tag')->implode(','),
        ];

        return view('fan-posts.create', [
            'editingPost' => $editingPost,
            'postLanguages' => $postLanguages,
        ]);
    }

    public function update(Request $request, string $idx)
    {
        $member = $request->attributes->get('activityMember');
        $post = DB::table('fan_posts')->where('idx', $idx)->where('member_idx', $member->idx)
            ->whereNull('deleted_at')->first(['idx', 'media_type']);
        abort_if($post === null, 404);

        $allowedFields = ['_token', '_method', 'title', 'content', 'tags'];
        $unexpectedFields = array_diff(array_keys($request->all()), $allowedFields);
        if ($unexpectedFields !== []) {
            $errors = [];
            foreach ($unexpectedFields as $field) {
                $message = match ($field) {
                    'image', 'image_path', 'image_order' => __('ui.fan_post_image_immutable'),
                    'media_type', 'youtube_url' => __('ui.fan_post_media_immutable'),
                    default => __('ui.fan_post_edit_fields_immutable'),
                };
                $errors[$field] = $message;
            }

            return back()->withInput()->withErrors($errors);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'content' => [Rule::requiredIf($post->media_type === 'text'), 'nullable', 'string', 'max:2000'],
            'tags' => ['sometimes', 'nullable', 'string', 'max:619'],
        ]);

        $tags = collect(explode(',', $validated['tags'] ?? ''))
            ->map(fn ($tag) => trim($tag))->filter()->unique(fn ($tag) => mb_strtolower($tag))->values();
        if ($tags->count() > 20 || $tags->contains(fn ($tag) => mb_strlen($tag) > 30 || !preg_match('/^[\pL\pN_-]+$/u', $tag))) {
            return back()->withInput()->withErrors(['tags' => __('messages.fan_post_tags_invalid')]);
        }

        DB::transaction(function () use ($idx, $validated, $tags, $request): void {
            DB::table('fan_posts')->where('idx', $idx)->update([
                'title' => $validated['title'], 'content' => $validated['content'] ?? null, 'updated_at' => now(),
            ]);

            if ($request->exists('tags')) {
                DB::table('fan_post_tags')->where('fan_post_idx', $idx)->delete();
                if ($tags->isNotEmpty()) {
                    DB::table('fan_post_tags')->insert($tags->map(fn ($tag) => [
                        'fan_post_idx' => $idx, 'tag' => $tag, 'created_at' => now(),
                    ])->all());
                }
            }
        });

        return redirect()->route('fan-post.show', ['idx' => $idx]);
    }

    public function destroy(Request $request, string $idx)
    {
        $member = $request->attributes->get('activityMember');

        DB::transaction(function () use ($idx, $member): void {
            $currentMember = DB::table('members')->where('idx', $member->idx)
                ->where('member_level', '>', 0)->where('status', 'active')->whereNull('deleted_at')
                ->lockForUpdate()->first(['idx']);
            abort_if($currentMember === null, 403);

            $post = DB::table('fan_posts')->where('idx', $idx)->where('member_idx', $currentMember->idx)
                ->whereNull('deleted_at')->lockForUpdate()->first(['idx']);
            abort_if($post === null, 404);

            // Soft deletion preserves image files and related history while public queries exclude the post.
            DB::table('fan_posts')->where('idx', $post->idx)->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('fan-posts.index')->with('global_notification', [
            'title' => __('ui.delete_fan_post'),
            'message' => __('ui.fan_post_deleted'),
            'type' => 'success',
        ]);
    }

    public function show(Request $request, string $idx, PublicFanPostList $fanPostList)
    {
        $record = DB::table('fan_posts as post')
            ->join('members as member', 'member.idx', '=', 'post.member_idx')
            ->join('idols as idol', 'idol.idx', '=', 'post.idol_idx')
            ->where('post.idx', $idx)->whereNull('post.deleted_at')
            ->where('idol.status', 'active')->where('member.member_level', '>', 0)
            ->where('member.status', 'active')->whereNull('member.deleted_at')
            ->first(['post.idx', 'post.idol_idx', 'post.status', 'post.title', 'post.language_code', 'post.media_type', 'post.image_path', 'post.youtube_video_id',
                'post.content', 'post.view_count', DB::raw('COALESCE(post.published_at, post.created_at) as published_at'), 'member.idx as author_idx',
                'member.member_code', 'member.nickname', 'member.country_code', 'member.profile_image_path', 'idol.name as idol_name']);
        abort_if($record === null, 404);
        if ($record->status === 'hidden') {
            return $this->redirectFromHiddenPost($request);
        }
        abort_unless($record->status === 'published', 404);

        $recommendationCounts = $this->recommendationCounts($idx);
        $post = [
            'idx' => (int) $record->idx, 'idol_idx' => (int) $record->idol_idx,
            'title' => $record->title, 'body' => $record->content, 'language_code' => $record->language_code,
            'country_name' => \App\Support\Countries::localizedName($record->country_code, app()->getLocale()), 'media_type' => $record->media_type,
            'image_url' => \App\Support\ManagedImageStorage::publicUrl($record->image_path),
            'original_image_url' => $record->media_type === 'image' && \App\Support\ManagedImageStorage::isAvailable($record->image_path)
                ? \App\Support\ManagedImageStorage::publicUrl($record->image_path)
                : null,
            'youtube_video_id' => $record->youtube_video_id, 'views' => (int) $record->view_count,
            'time' => \Illuminate\Support\Carbon::parse($record->published_at)->diffForHumans(),
            'recommendations' => $recommendationCounts['lifetime'],
            'monthly_recommendations' => $recommendationCounts['monthly'],
            'tags' => DB::table('fan_post_tags')->where('fan_post_idx', $idx)->orderBy('idx')->pluck('tag')->all(),
            'author' => ['member_code' => $record->member_code,
                'nickname' => $record->nickname, 'image_url' => $record->profile_image_path,
                'language_code' => $record->language_code,
                'country_code' => $record->country_code,
                'country_name' => \App\Support\Countries::localizedName($record->country_code, app()->getLocale())],
        ];
        $member = $request->attributes->get('activityMember');
        $isRecommended = $member !== null && DB::table('fan_post_likes')
            ->where('fan_post_idx', $idx)->where('member_idx', $member->idx)->exists();

        $morePosts = $fanPostList->byAuthor((int) $record->author_idx, (int) $idx);
        $trendingPosts = DB::table('fan_posts as trending')
                ->join('idols as trending_idol', 'trending_idol.idx', '=', 'trending.idol_idx')
                ->join('members as trending_member', 'trending_member.idx', '=', 'trending.member_idx')
                ->where('trending.idx', '<>', $idx)->where('trending.status', 'published')->whereNull('trending.deleted_at')
                ->where('trending_idol.status', 'active')->where('trending_member.member_level', '>', 0)
                ->where('trending_member.status', 'active')->whereNull('trending_member.deleted_at')
                ->where(function ($query): void {
                    $query->where(fn ($images) => $images->where('trending.media_type', 'image')->whereNotNull('trending.image_path')->where('trending.image_path', '<>', ''))
                        ->orWhere(fn ($videos) => $videos->where('trending.media_type', 'youtube')->whereNotNull('trending.youtube_video_id')->where('trending.youtube_video_id', '<>', ''));
                })
                ->orderByDesc('trending.view_count')
                ->orderByRaw('COALESCE(trending.published_at, trending.created_at) DESC')
                ->orderByDesc('trending.idx')
                ->limit(3)
                ->get(['trending.idx', 'trending.title', 'trending.media_type', 'trending.image_path',
                    'trending.youtube_video_id', DB::raw('COALESCE(trending.published_at, trending.created_at) as published_at')])
                ->map(fn ($item) => [
                    'idx' => (int) $item->idx,
                    'title' => $item->title,
                    'image_url' => $item->media_type === 'youtube' && $item->youtube_video_id
                        ? 'https://i.ytimg.com/vi/'.$item->youtube_video_id.'/hqdefault.jpg'
                        : \App\Support\ManagedImageStorage::publicUrl($item->image_path),
                    'time' => \Illuminate\Support\Carbon::parse($item->published_at)->diffForHumans(),
                ])->all();
        $fanPostSort = $request->string('sort')->toString();
        if (!in_array($fanPostSort, ['latest', 'monthly_liked', 'liked', 'commented'], true)) {
            $fanPostSort = 'latest';
        }
        $posts = $fanPostList->forIdol((int) $post['idol_idx'], $fanPostSort);

        $commentsPage = filter_var($request->query('comments_page', 1), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]) ?: 1;
        $commentsQuery = DB::table('fan_post_comments as comment')
            ->join('members as member', 'member.idx', '=', 'comment.member_idx')
            ->where('comment.fan_post_idx', $idx)->where('comment.status', 'published')->whereNull('comment.deleted_at')
            ->where('member.member_level', '>', 0)
            ->where('member.status', 'active')->whereNull('member.deleted_at')
            ->orderByDesc('comment.created_at')->orderByDesc('comment.idx');
        $commentCount = (clone $commentsQuery)->count();
        $commentsPage = min($commentsPage, max(1, (int) ceil($commentCount / 10)));
        $commentItems = $commentsQuery
            ->leftJoin('heart_histories as comment_reward', function ($join) {
                $join->on('comment_reward.comment_idx', '=', 'comment.idx')
                    ->on('comment_reward.member_idx', '=', 'comment.member_idx')
                    ->where('comment_reward.reason', '=', HeartRewardManager::ACTIONS['comment_create']['reason']);
            })
            ->forPage($commentsPage, 10)
            ->get(['comment.idx', 'comment.member_idx', 'comment.content', 'comment.language_code', 'comment.created_at',
                'member.member_code', 'member.nickname', 'member.country_code', 'member.profile_image_path',
                'comment_reward.idx as reward_history_idx', 'comment_reward.amount as reward_amount'])
            ->map(fn ($comment) => [
                'idx' => (int) $comment->idx,
                'member_code' => $comment->member_code,
                'nickname' => $comment->nickname,
                'country_code' => $comment->country_code,
                'image_url' => $comment->profile_image_path,
                'body' => $comment->content,
                'language_code' => $comment->language_code,
                'rewarded_hearts' => $comment->reward_history_idx === null ? null : (int) $comment->reward_amount,
                'time' => \Illuminate\Support\Carbon::parse($comment->created_at)->diffForHumans(),
                'can_delete' => $member !== null && (int) $comment->member_idx === (int) $member->idx,
                'can_report' => $member === null || (int) $comment->member_idx !== (int) $member->idx,
                'report_url' => route('fan-post.comments.report', ['idx' => $idx, 'commentIdx' => $comment->idx]),
            ]);
        $comments = new LengthAwarePaginator($commentItems, $commentCount, 10, $commentsPage, [
            'path' => $request->url(),
            'pageName' => 'comments_page',
            'query' => $request->query(),
            'fragment' => 'comments',
        ]);

        $nowTimestamp = now()->timestamp;
        $viewTimes = $this->recentPostViewTimes($request->cookie(self::VIEW_COOKIE_NAME), $nowTimestamp);
        $postCookieKey = ltrim($idx, '0');
        $viewCookieValue = null;

        if (!array_key_exists($postCookieKey, $viewTimes) && count($viewTimes) < self::VIEW_COOKIE_MAX_POSTS) {
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
                $viewTimes[$postCookieKey] = $nowTimestamp;
                $viewCookieValue = json_encode($viewTimes);
            }
        }

        $response = response()->view('fan-posts.show', [
            'post' => $post, 'posts' => $posts, 'morePosts' => $morePosts,
            'trendingPosts' => $trendingPosts,
            'isRecommended' => $isRecommended, 'comments' => $comments, 'fanPostSort' => $fanPostSort,
            'recommendationRequestKey' => bin2hex(random_bytes(32)),
            'randomDummyMode' => app(RandomDummyActivity::class)->enabled($request),
            'isOwnPost' => $member !== null && (int) $record->author_idx === (int) $member->idx,
            'databasePost' => true,
            'seo' => [
                'title' => $post['title'].' | IDOL RANKING',
                'description' => mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($post['body'] ?? ''))), 0, 180),
                'image' => ($post['media_type'] ?? 'image') === 'image' ? \App\Support\ManagedImageStorage::publicUrl($post['image_url'] ?? null)
                    : (($post['media_type'] ?? null) === 'youtube' && ($post['youtube_video_id'] ?? null) ? 'https://i.ytimg.com/vi/'.$post['youtube_video_id'].'/hqdefault.jpg' : null),
                'url' => route('fan-post.show', ['idx' => $idx]),
                'type' => 'article',
            ],
        ]);

        if ($viewCookieValue !== null) {
            $response->headers->setCookie(Cookie::make(
                self::VIEW_COOKIE_NAME,
                $viewCookieValue,
                60,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax'
            ));
        }

        return $response;
    }

    private function recentPostViewTimes(mixed $cookieValue, int $nowTimestamp): array
    {
        if (!is_string($cookieValue) || strlen($cookieValue) > 4096) {
            return [];
        }

        $decoded = json_decode($cookieValue, true);
        if (!is_array($decoded)) {
            return [];
        }

        $viewTimes = [];
        foreach ($decoded as $postIdx => $timestamp) {
            $postIdx = (string) $postIdx;
            $postKey = ltrim($postIdx, '0');
            if ($postKey === '' || !ctype_digit($postIdx) || !is_int($timestamp)
                || $timestamp < 0 || $timestamp > $nowTimestamp
                || ($nowTimestamp - $timestamp) >= self::VIEW_WINDOW_SECONDS) {
                continue;
            }

            $viewTimes[$postKey] = $timestamp;
        }

        arsort($viewTimes, SORT_NUMERIC);

        return array_slice($viewTimes, 0, self::VIEW_COOKIE_MAX_POSTS, true);
    }

    public function translation(Request $request, string $idx)
    {
        $targetLanguage = $request->query('language', app()->getLocale());
        if (!is_string($targetLanguage) || !array_key_exists($targetLanguage, config('locales.public', []))
            || $targetLanguage !== app()->getLocale()) {
            return response()->json([
                'success' => false,
                'code' => 'translation_target_invalid',
                'message' => __('ui.translation_target_invalid'),
            ], 422);
        }

        $post = DB::table('fan_posts as post')
            ->join('members as member', 'member.idx', '=', 'post.member_idx')
            ->join('idols as idol', 'idol.idx', '=', 'post.idol_idx')
            ->where('post.idx', $idx)->where('post.status', 'published')->whereNull('post.deleted_at')
            ->where('idol.status', 'active')->where('member.member_level', '>', 0)
            ->where('member.status', 'active')->whereNull('member.deleted_at')
            ->first(['post.title', 'post.content', 'post.language_code']);

        if ($post === null) {
            return response()->json([
                'success' => false,
                'code' => 'fan_post_not_found',
                'message' => __('ui.fan_post_not_found'),
            ], 404);
        }

        $sourceLanguage = FanPostLanguages::canonicalCode($post->language_code);
        if ($sourceLanguage === null) {
            return $this->translationUnavailable();
        }

        $currentSourceHash = FanPostSourceHash::make($post->title, $post->content);
        if ($sourceLanguage === $targetLanguage) {
            return $this->translationResponse($post->title, $post->content, $targetLanguage);
        }

        $translation = $this->validFanPostTranslation($idx, $targetLanguage, $currentSourceHash);
        if ($translation !== null) {
            return $this->translationResponse($translation->translated_title, $translation->translated_content, $targetLanguage);
        }

        $lockKey = 'fan-post-translation:'.hash('sha256', $idx.'|'.$targetLanguage.'|'.$currentSourceHash);
        $lock = Cache::lock($lockKey, 60);
        if (!$lock->get()) {
            return response()->json([
                'success' => false,
                'code' => 'translation_retry',
                'message' => __('ui.translation_error'),
            ], 503)->header('Cache-Control', 'no-store');
        }

        try {
            // Another request may have generated the translation before this request got the lock.
            $translation = $this->validFanPostTranslation($idx, $targetLanguage, $currentSourceHash);
            if ($translation !== null) {
                return $this->translationResponse($translation->translated_title, $translation->translated_content, $targetLanguage);
            }

            $apiKey = config('services.google_translate.api_key');
            if (!is_string($apiKey) || trim($apiKey) === '') {
                Log::warning('Fan Post translation could not start.', [
                    'failure_category' => 'missing_api_key',
                    'api_key_configured' => false,
                ]);
                return $this->translationGenerationFailed();
            }

            $translated = $this->requestGoogleTranslation(
                $apiKey,
                $post->title,
                $post->content,
                $sourceLanguage,
                $targetLanguage
            );
            if ($translated === null) {
                return $this->translationGenerationFailed();
            }

            $saved = DB::transaction(function () use ($idx, $targetLanguage, $currentSourceHash, $translated): bool {
                $currentPost = DB::table('fan_posts as post')
                    ->join('members as member', 'member.idx', '=', 'post.member_idx')
                    ->join('idols as idol', 'idol.idx', '=', 'post.idol_idx')
                    ->where('post.idx', $idx)->where('post.status', 'published')->whereNull('post.deleted_at')
                    ->where('idol.status', 'active')->where('member.member_level', '>', 0)
                    ->where('member.status', 'active')->whereNull('member.deleted_at')
                    ->lockForUpdate()->first(['post.title', 'post.content']);

                if ($currentPost === null || !hash_equals(
                    $currentSourceHash,
                    FanPostSourceHash::make($currentPost->title, $currentPost->content)
                )) {
                    return false;
                }

                $now = now();
                DB::table('fan_post_translations')->updateOrInsert(
                    ['fan_post_idx' => $idx, 'language_code' => $targetLanguage],
                    [
                        'translated_title' => $translated['title'],
                        'translated_content' => $translated['content'],
                        'source_hash' => $currentSourceHash,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                return true;
            });

            if (!$saved) {
                return $this->translationGenerationFailed();
            }

            return $this->translationResponse($translated['title'], $translated['content'], $targetLanguage);
        } finally {
            $lock->release();
        }
    }

    public function commentTranslation(Request $request, string $idx, string $commentIdx)
    {
        $targetLanguage = $request->query('language', app()->getLocale());
        if (!is_string($targetLanguage) || !array_key_exists($targetLanguage, config('locales.public', []))
            || $targetLanguage !== app()->getLocale()) {
            return response()->json([
                'success' => false,
                'code' => 'translation_target_invalid',
                'message' => __('ui.translation_target_invalid'),
            ], 422)->header('Cache-Control', 'no-store');
        }

        $comment = $this->publicCommentQuery($idx, $commentIdx)->first(['comment.content', 'comment.language_code']);
        if ($comment === null) {
            return response()->json([
                'success' => false,
                'code' => 'comment_not_found',
                'message' => __('ui.fan_post_not_found'),
            ], 404)->header('Cache-Control', 'no-store');
        }

        $sourceLanguage = FanPostLanguages::canonicalCode($comment->language_code);
        if ($sourceLanguage === null) {
            return $this->commentTranslationUnavailable();
        }
        if ($sourceLanguage === $targetLanguage) {
            return $this->commentTranslationResponse($comment->content, $targetLanguage);
        }

        // Hash the exact stored text: even whitespace changes invalidate this cache entry.
        $sourceHash = hash('sha256', $comment->content);
        $cached = $this->validCommentTranslation($commentIdx, $targetLanguage, $sourceHash);
        if ($cached !== null) {
            return $this->commentTranslationResponse($cached->translated_content, $targetLanguage);
        }

        $lockKey = 'fan-post-comment-translation:'.hash('sha256', $commentIdx.'|'.$targetLanguage.'|'.$sourceHash);
        $lock = Cache::lock($lockKey, 60);
        if (!$lock->get()) {
            return $this->commentTranslationFailed('translation_retry', 503);
        }

        try {
            $cached = $this->validCommentTranslation($commentIdx, $targetLanguage, $sourceHash);
            if ($cached !== null) {
                return $this->commentTranslationResponse($cached->translated_content, $targetLanguage);
            }

            $apiKey = config('services.google_translate.api_key');
            if (!is_string($apiKey) || trim($apiKey) === '') {
                Log::warning('Fan Post comment translation could not start.', [
                    'failure_category' => 'missing_api_key',
                    'api_key_configured' => false,
                ]);
                return $this->commentTranslationFailed();
            }

            // Reuse the existing plain-text Google v2 request and its sanitized failure logging.
            $translated = $this->requestGoogleTranslation(
                $apiKey,
                $comment->content,
                null,
                $sourceLanguage,
                $targetLanguage
            );
            if ($translated === null) {
                return $this->commentTranslationFailed();
            }

            $saved = DB::transaction(function () use ($idx, $commentIdx, $targetLanguage, $sourceHash, $translated): bool {
                $current = $this->publicCommentQuery($idx, $commentIdx)->lockForUpdate()
                    ->first(['comment.content']);
                if ($current === null || !hash_equals($sourceHash, hash('sha256', $current->content))) {
                    return false;
                }

                $now = now();
                DB::table('fan_post_comment_translations')->updateOrInsert(
                    ['comment_idx' => $commentIdx, 'language_code' => $targetLanguage],
                    [
                        'translated_content' => $translated['title'],
                        'source_hash' => $sourceHash,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                return true;
            });

            if (!$saved) {
                return $this->commentTranslationFailed();
            }

            return $this->commentTranslationResponse($translated['title'], $targetLanguage);
        } finally {
            $lock->release();
        }
    }

    private function publicCommentQuery(string $idx, string $commentIdx)
    {
        return DB::table('fan_post_comments as comment')
            ->join('fan_posts as post', 'post.idx', '=', 'comment.fan_post_idx')
            ->join('members as comment_author', 'comment_author.idx', '=', 'comment.member_idx')
            ->join('members as author', 'author.idx', '=', 'post.member_idx')
            ->join('idols as idol', 'idol.idx', '=', 'post.idol_idx')
            ->where('comment.idx', $commentIdx)->where('comment.fan_post_idx', $idx)
            ->where('comment.status', 'published')->whereNull('comment.deleted_at')
            ->where('comment_author.member_level', '>', 0)->where('comment_author.status', 'active')->whereNull('comment_author.deleted_at')
            ->where('post.status', 'published')->whereNull('post.deleted_at')
            ->where('author.member_level', '>', 0)->where('author.status', 'active')->whereNull('author.deleted_at')
            ->where('idol.status', 'active');
    }

    private function validCommentTranslation(string $commentIdx, string $targetLanguage, string $sourceHash): ?object
    {
        $translation = DB::table('fan_post_comment_translations')
            ->where('comment_idx', $commentIdx)->where('language_code', $targetLanguage)
            ->first(['translated_content', 'source_hash']);

        return $translation !== null && hash_equals($sourceHash, $translation->source_hash) ? $translation : null;
    }

    private function commentTranslationResponse(string $content, string $targetLanguage)
    {
        return response()->json([
            'success' => true,
            'data' => ['content' => $content, 'language_code' => $targetLanguage],
        ])->header('Cache-Control', 'no-store');
    }

    private function commentTranslationUnavailable()
    {
        return $this->commentTranslationFailed('translation_unavailable', 404);
    }

    private function commentTranslationFailed(string $code = 'translation_error', int $status = 503)
    {
        $messageKey = $code === 'translation_unavailable' ? 'ui.translation_unavailable' : 'js.translation_error';

        return response()->json([
            'success' => false,
            'code' => $code,
            'message' => __($messageKey),
        ], $status)->header('Cache-Control', 'no-store');
    }

    private function validFanPostTranslation(string $postIdx, string $targetLanguage, string $sourceHash): ?object
    {
        $translation = DB::table('fan_post_translations')
            ->where('fan_post_idx', $postIdx)->where('language_code', $targetLanguage)
            ->first(['translated_title', 'translated_content', 'source_hash']);

        return $translation !== null && hash_equals($sourceHash, $translation->source_hash)
            ? $translation
            : null;
    }

    private function requestGoogleTranslation(
        string $apiKey,
        string $title,
        ?string $content,
        string $sourceLanguage,
        string $targetLanguage
    ): ?array {
        $sourceTexts = [$title];
        $contentIsPresent = $content !== null && $content !== '';
        if ($contentIsPresent) {
            $sourceTexts[] = $content;
        }

        try {
            $response = Http::asJson()->withQueryParameters(['key' => $apiKey])
                ->connectTimeout(3)->timeout(10)
                ->post('https://translation.googleapis.com/language/translate/v2', [
                    'q' => $sourceTexts,
                    'source' => $sourceLanguage,
                    'target' => $targetLanguage,
                    'format' => 'text',
                ]);
        } catch (\Throwable $exception) {
            Log::warning('Google Translate request failed before receiving a response.', [
                'failure_category' => 'transport_error',
                'exception_type' => class_basename($exception),
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
            ]);
            return null;
        }

        if (!$response->successful()) {
            $this->logGoogleTranslationFailure(
                $response->status(),
                $response->json('error.code'),
                $response->json('error.status'),
                $response->json('error.message'),
                $sourceLanguage,
                $targetLanguage,
                $apiKey,
                'http_error'
            );
            return null;
        }

        $translations = $response->json('data.translations');
        if (!is_array($translations) || count($translations) !== count($sourceTexts)
            || !isset($translations[0]) || !is_array($translations[0])
            || !isset($translations[0]['translatedText']) || !is_string($translations[0]['translatedText'])
            || $translations[0]['translatedText'] === '') {
            $this->logGoogleTranslationFailure(
                $response->status(), null, null, null, $sourceLanguage, $targetLanguage, $apiKey, 'invalid_response'
            );
            return null;
        }

        $translatedContent = $content;
        if ($contentIsPresent) {
            $contentTranslation = $translations[1] ?? null;
            if (!is_array($contentTranslation) || !isset($contentTranslation['translatedText'])
                || !is_string($contentTranslation['translatedText']) || $contentTranslation['translatedText'] === '') {
                $this->logGoogleTranslationFailure(
                    $response->status(), null, null, null, $sourceLanguage, $targetLanguage, $apiKey, 'invalid_response'
                );
                return null;
            }
            $translatedContent = $contentTranslation['translatedText'];
        }

        return ['title' => $translations[0]['translatedText'], 'content' => $translatedContent];
    }

    private function logGoogleTranslationFailure(
        ?int $httpStatus,
        mixed $googleErrorCode,
        mixed $googleErrorStatus,
        mixed $googleErrorMessage,
        string $sourceLanguage,
        string $targetLanguage,
        string $apiKey,
        string $failureCategory
    ): void {
        Log::warning('Google Cloud Translation returned an unusable response.', [
            'failure_category' => $failureCategory,
            'http_status' => $httpStatus,
            'google_error_code' => $this->safeTranslationLogValue($googleErrorCode, $apiKey),
            'google_error_status' => $this->safeTranslationLogValue($googleErrorStatus, $apiKey),
            'google_error_message' => $this->safeTranslationLogValue($googleErrorMessage, $apiKey),
            'source_language' => $sourceLanguage,
            'target_language' => $targetLanguage,
        ]);
    }

    private function safeTranslationLogValue(mixed $value, string $apiKey): ?string
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $value = (string) $value;
        if ($apiKey !== '') {
            $value = str_replace($apiKey, '[REDACTED]', $value);
        }
        $value = preg_replace('~https?://\S+~i', '[URL]', $value) ?? '';
        $value = preg_replace('/(?i)(api[_-]?key|authorization|bearer|key)(\s*[:=]\s*|\s+)[^\s,;]+/', '$1=[REDACTED]', $value) ?? '';
        $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? '';

        return mb_substr(trim($value), 0, 300);
    }

    private function translationResponse(string $title, ?string $content, string $targetLanguage)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'title' => $title,
                'content' => $content,
                'language_code' => $targetLanguage,
            ],
        ])->header('Cache-Control', 'no-store');
    }

    private function translationGenerationFailed()
    {
        return response()->json([
            'success' => false,
            'code' => 'translation_error',
            'message' => __('ui.translation_error'),
        ], 503)->header('Cache-Control', 'no-store');
    }

    private function translationUnavailable()
    {
        return response()->json([
            'success' => false,
            'code' => 'translation_unavailable',
            'message' => __('ui.translation_unavailable'),
        ], 404)->header('Cache-Control', 'no-store');
    }

    public function storeComment(Request $request, string $idx)
    {
        $member = $request->attributes->get('activityMember');
        $actor = $request->attributes->get('activityActor');
        $randomActivities = app(RandomDummyActivity::class);
        $randomMode = $randomActivities->enabled($request);
        if ($randomMode) {
            $request->merge(['language_code' => 'ko']);
        }
        $request->merge([
            'language_code' => $request->input('language_code') ?? app()->getLocale(),
        ]);
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
            'language_code' => ['required', 'string', Rule::in(array_keys(FanPostLanguages::all()))],
            'request_key' => ['required', 'string', 'min:16', 'max:128', 'regex:/^[A-Za-z0-9-]+$/'],
        ], [
            'content.required' => __('ui.comment_content_required'),
            'content.max' => __('ui.comment_content_too_long'),
            'language_code.required' => __('ui.comment_language_required'),
            'language_code.in' => __('ui.comment_language_invalid'),
            'request_key.required' => __('ui.comment_request_invalid'),
            'request_key.min' => __('ui.comment_request_invalid'),
            'request_key.max' => __('ui.comment_request_invalid'),
            'request_key.regex' => __('ui.comment_request_invalid'),
        ]);

        $reservation = $randomMode ? $randomActivities->reserve($request, 'comment_create', $validated['request_key'], [
                'fan_post_idx' => (int) $idx, 'content' => $validated['content'], 'language_code' => $validated['language_code'],
            ]) : null;
        $result = DB::transaction(function () use ($request, $idx, $member, $actor, $validated, $randomActivities, $reservation): array {
            $reservation = $reservation ? $randomActivities->lockReservation($reservation) : null;
            if ($reservation?->replay) {
                $existingComment = DB::table('fan_post_comments')->where('idx', $reservation->result_idx)->first();
                abort_if($existingComment === null, 409, '댓글 요청 결과를 확인할 수 없습니다.');
                $commentMember = DB::table('members')->where('idx', $existingComment->member_idx)
                    ->first(['idx', 'member_code', 'nickname', 'profile_image_path', 'member_level', 'is_dummy', 'heart_balance']);
                return ['comment' => $this->commentResponse($existingComment, $commentMember),
                    'comment_count' => $this->publishedCommentCount((int) $idx), 'created' => false,
                    'member_idx' => (int) $existingComment->member_idx, 'reservation' => $reservation];
            }
            $activityMember = $reservation ? $randomActivities->member($reservation) : $member;
            abort_if($activityMember === null, 422, '사용할 수 있는 활성 더미 회원이 없습니다.');
            $currentMember = DB::table('members')->where('idx', $activityMember->idx)
                ->where('member_level', '>', 0)->where('status', 'active')->whereNull('deleted_at')
                ->lockForUpdate()->first(['idx', 'member_code', 'nickname', 'profile_image_path', 'member_level', 'is_dummy', 'heart_balance']);
            abort_if($currentMember === null, 403);

            $post = DB::table('fan_posts as p')->join('idols as i', 'i.idx', '=', 'p.idol_idx')->where('p.idx', $idx)
                ->where('p.status', 'published')->whereNull('p.deleted_at')->where('i.status', 'active')
                ->lockForUpdate()->first(['p.idx']);
            if ($post === null) {
                return ['error' => __('ui.fan_post_not_found'), 'status' => 404];
            }

            $existing = DB::table('fan_post_comments')
                ->where('actor_member_idx', $actor->idx)
                ->where('request_key', $validated['request_key'])
                ->first();
            if ($existing !== null) {
                if ((int) $existing->fan_post_idx !== (int) $post->idx
                    || (int) $existing->member_idx !== (int) $currentMember->idx
                    || $existing->content !== $validated['content']) {
                    return ['error' => __('ui.comment_request_reused'), 'status' => 409];
                }
                if ($existing->status !== 'published' || $existing->deleted_at !== null) {
                    return ['error' => __('ui.comment_request_completed'), 'status' => 409];
                }

                return [
                    'comment' => $this->commentResponse($existing, $currentMember),
                    'comment_count' => $this->publishedCommentCount((int) $post->idx),
                    'created' => false,
                    'member_idx' => (int) $currentMember->idx,
                    'reservation' => null,
                ];
            }

            $commentIdx = DB::table('fan_post_comments')->insertGetId([
                'fan_post_idx' => $post->idx,
                'member_idx' => $currentMember->idx,
                'actor_member_idx' => $actor->idx,
                'request_key' => $validated['request_key'],
                'content' => $validated['content'],
                'language_code' => $validated['language_code'],
                'status' => 'published',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($reservation) {
                $randomActivities->complete($reservation, 'comment', (int) $commentIdx);
                $randomActivities->log($request, $reservation, 'comment_created', '랜덤 더미 회원이 댓글을 작성했습니다.');
            }

            $rewardedHearts = 0;
            if ($reservation) {
                $rewardedHearts = app(HeartRewardManager::class)->award(
                    (int) $currentMember->idx,
                    'comment_create',
                    'comment_reward:'.$commentIdx,
                    ['comment_idx' => $commentIdx, 'actor_member_idx' => (int) $actor->idx,
                        'memo' => 'Fan Post comment creation reward', 'random_dummy_request_idx' => $reservation->idx]
                );
            }

            $comment = DB::table('fan_post_comments')->where('idx', $commentIdx)->first();

            return [
                'comment' => $this->commentResponse($comment, $currentMember),
                'comment_count' => $this->publishedCommentCount((int) $post->idx),
                'created' => true,
                'member_idx' => (int) $currentMember->idx,
                'reservation' => $reservation,
                'rewarded_hearts' => $rewardedHearts,
            ];
        });

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'message' => $result['error']], $result['status']);
        }

        $result['rewarded_hearts'] = 0;
        $result['reward_recorded'] = false;
        if ($result['created'] && ! $result['reservation']) {
            try {
                $commentIdx = (int) $result['comment']['idx'];
                // The persisted comment ID makes retries idempotent; settings and limits stay centralized.
                DB::transaction(fn () => app(HeartRewardManager::class)->award(
                    (int) $result['member_idx'],
                    'comment_create',
                    'comment_reward:'.$commentIdx,
                    ['comment_idx' => $commentIdx, 'actor_member_idx' => (int) $actor->idx,
                        'memo' => 'Fan Post comment creation reward'] + ($result['reservation'] ? ['random_dummy_request_idx' => $result['reservation']->idx] : [])
                ));
            } catch (\Throwable $exception) {
                Log::error('Automatic Heart reward processing failed.', [
                    'reward_action' => 'comment_create',
                    'recipient_member_id' => (int) $result['member_idx'],
                    'actor_member_id' => (int) $actor->idx,
                    'fan_post_id' => (int) $idx,
                    'comment_id' => $commentIdx,
                    'operation_key' => 'comment_reward:'.$commentIdx,
                    'exception_class' => $exception::class,
                    'exception_message' => $exception->getMessage(),
                ]);
            }
        }

        // A zero return can mean either ineligible or an actual zero-Heart award. The history row distinguishes them.
        try {
            $rewardHistory = DB::table('heart_histories')
                ->where('member_idx', $result['member_idx'])
                ->where('operation_key', 'comment_reward:'.$result['comment']['idx'])
                ->where('reason', HeartRewardManager::ACTIONS['comment_create']['reason'])
                ->first(['amount']);
            if ($rewardHistory !== null) {
                $result['rewarded_hearts'] = (int) $rewardHistory->amount;
                $result['reward_recorded'] = true;
            }
        } catch (\Throwable $exception) {
            Log::error('Automatic comment Heart reward lookup failed.', [
                'reward_action' => 'comment_create',
                'recipient_member_id' => (int) $member->idx,
                'actor_member_id' => (int) $actor->idx,
                'fan_post_id' => (int) $idx,
                'comment_id' => (int) $result['comment']['idx'],
                'operation_key' => 'comment_reward:'.$result['comment']['idx'],
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => $result['reward_recorded']
                ? __('ui.comment_posted_reward', ['amount' => number_format($result['rewarded_hearts'])])
                : __('ui.comment_posted_message'),
            'data' => $result,
        ], $result['created'] ? 201 : 200);
    }

    public function destroyComment(Request $request, string $idx, string $commentIdx)
    {
        $member = $request->attributes->get('activityMember');

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
            'message' => __('ui.comment_deleted'),
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
            'highlight_dummy_nickname' => \App\Support\AdminDummyNickname::matches(memberCode: $member->member_code),
            'member_label' => __('ui.member_view_posts_label', ['name' => $member->nickname]),
            'image_url' => \App\Support\ManagedImageStorage::publicUrl($member->profile_image_path),
            'content' => $comment->content,
            'language_code' => $comment->language_code ?? null,
            'created_label' => __('ui.just_now'),
            'delete_label' => __('ui.delete_comment'),
            'translation_url' => route('fan-post.comments.translation', [
                'idx' => $comment->fan_post_idx,
                'commentIdx' => $comment->idx,
            ]),
            'translation_target_language' => app()->getLocale(),
            'delete_url' => route('fan-post.comments.destroy', ['idx' => $comment->fan_post_idx, 'commentIdx' => $comment->idx]),
            'can_delete' => true,
        ];
    }

    public function report(Request $request, string $idx)
    {
        $member = $request->attributes->get('activityMember');
        $actor = $request->attributes->get('activityActor');
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
            return response()->json(['success' => false, 'message' => __('ui.fan_post_not_found')], 404);
        }
        if ((int) $post->member_idx === (int) $member->idx) {
            return response()->json(['success' => false, 'message' => __('ui.cannot_report_own_post')], 422);
        }

        try {
            DB::table('fan_post_reports')->insert([
                'fan_post_idx' => $post->idx,
                'member_idx' => $member->idx,
                'actor_member_idx' => $actor->idx,
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
                    'message' => __('ui.already_reported_post'),
                ], 409);
            }
            throw $exception;
        }

        return response()->json([
            'success' => true,
            'message' => __('ui.report_submitted_thanks'),
        ], 201);
    }

    public function reportComment(Request $request, string $idx, string $commentIdx)
    {
        $member = $request->attributes->get('activityMember');
        $actor = $request->attributes->get('activityActor');
        $validated = $request->validate([
            'reason' => ['required', Rule::in(['spam', 'harassment_hate', 'inappropriate_content', 'other'])],
            'details' => ['nullable', 'string', 'max:2000', 'required_if:reason,other'],
        ]);

        $comment = DB::table('fan_post_comments as comment')
            ->join('fan_posts as post', 'post.idx', '=', 'comment.fan_post_idx')
            ->join('idols as idol', 'idol.idx', '=', 'post.idol_idx')
            ->join('members as post_author', 'post_author.idx', '=', 'post.member_idx')
            ->join('members as comment_author', 'comment_author.idx', '=', 'comment.member_idx')
            ->where('comment.idx', $commentIdx)->where('comment.fan_post_idx', $idx)
            ->where('comment.status', 'published')->whereNull('comment.deleted_at')
            ->where('post.status', 'published')->whereNull('post.deleted_at')
            ->where('idol.status', 'active')
            ->where('post_author.member_level', '>', 0)->where('post_author.status', 'active')->whereNull('post_author.deleted_at')
            ->where('comment_author.member_level', '>', 0)->where('comment_author.status', 'active')->whereNull('comment_author.deleted_at')
            ->first(['comment.idx', 'comment.member_idx']);

        if ($comment === null) {
            return response()->json(['success' => false, 'message' => __('ui.comment_not_found')], 404);
        }
        if ((int) $comment->member_idx === (int) $member->idx) {
            return response()->json(['success' => false, 'message' => __('ui.cannot_report_own_comment')], 422);
        }

        try {
            DB::table('fan_post_comment_reports')->insert([
                'comment_idx' => $comment->idx,
                'member_idx' => $member->idx,
                'actor_member_idx' => $actor->idx,
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
                    'message' => __('ui.already_reported_comment'),
                ], 409);
            }
            throw $exception;
        }

        return response()->json([
            'success' => true,
            'message' => __('ui.report_submitted_thanks'),
        ], 201);
    }

    public function toggleRecommendation(Request $request, string $idx)
    {
        $member = $request->attributes->get('activityMember');
        $actor = $request->attributes->get('activityActor');
        $randomActivities = app(RandomDummyActivity::class);
        $randomMode = $randomActivities->enabled($request);
        $validated = $request->validate([
            'request_key' => [$randomMode ? 'required' : 'nullable', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
        ]);

        $reservation = $randomMode ? $randomActivities->reserve($request, 'fan_post_recommend', $validated['request_key'], [
                'fan_post_idx' => (int) $idx,
            ]) : null;
        $result = DB::transaction(function () use ($request, $idx, $member, $actor, $randomActivities, $reservation) {
            $reservation = $reservation ? $randomActivities->lockReservation($reservation) : null;
            if ($reservation?->replay) {
                $post = DB::table('fan_posts')->where('idx', $reservation->result_idx)->first(['idx', 'member_idx']);
                abort_if($post === null, 409, '추천 요청 결과를 확인할 수 없습니다.');
                $counts = $this->recommendationCounts($post->idx);
                return ['recommended' => true, 'recommendations' => $counts['lifetime'],
                    'monthly_recommendations' => $counts['monthly'], 'created' => false,
                    'post_idx' => (int) $post->idx, 'author_member_idx' => (int) $post->member_idx,
                    'reservation' => $reservation];
            }
            $activityMember = $reservation ? $randomActivities->member($reservation) : $member;
            abort_if($activityMember === null, 422, '사용할 수 있는 활성 더미 회원이 없습니다.');
            $post = DB::table('fan_posts as p')->join('idols as i', 'i.idx', '=', 'p.idol_idx')->where('p.idx', $idx)
                ->where('p.status', 'published')->whereNull('p.deleted_at')->where('i.status', 'active')
                ->lockForUpdate()->first(['p.idx', 'p.member_idx']);
            if ($post === null) {
                return null;
            }

            $recommendationExists = DB::table('fan_post_likes')
                ->where('fan_post_idx', $post->idx)->where('member_idx', $activityMember->idx)->exists();
            if (! $recommendationExists) {
                DB::table('fan_post_likes')->insert([
                    'fan_post_idx' => $post->idx,
                    'member_idx' => $activityMember->idx,
                    'actor_member_idx' => $actor->idx,
                    'created_at' => now(),
                ]);
            }
            if ($reservation) {
                $randomActivities->complete($reservation, 'fan_post_recommendation', (int) $post->idx);
                $randomActivities->log($request, $reservation, 'fan_post_recommended', '랜덤 더미 회원이 팬포스트를 추천했습니다.');
            }

            if ($reservation && ! $recommendationExists && (int) $post->member_idx !== (int) $activityMember->idx) {
                app(HeartRewardManager::class)->award(
                    (int) $post->member_idx,
                    'fan_post_recommend_received',
                    'fan_post_recommend_received:'.$post->idx.':'.$activityMember->idx,
                    ['actor_member_idx' => (int) $actor->idx, 'memo' => 'Fan Post recommendation received reward',
                        'random_dummy_request_idx' => $reservation->idx]
                );
            }

            $recommendationCounts = $this->recommendationCounts($post->idx);

            return [
                'recommended' => true,
                'recommendations' => $recommendationCounts['lifetime'],
                'monthly_recommendations' => $recommendationCounts['monthly'],
                'created' => ! $recommendationExists,
                'post_idx' => (int) $post->idx,
                'author_member_idx' => (int) $post->member_idx,
                'reservation' => $reservation,
            ];
        });

        if ($result === null) {
            abort(404);
        }

        if ($result['created'] && ! $result['reservation']
            && $result['author_member_idx'] !== (int) $member->idx) {
            try {
                // The author receives the reward; the post/member pair is the stable recommendation identity.
                DB::transaction(fn () => app(HeartRewardManager::class)->award(
                    $result['author_member_idx'],
                    'fan_post_recommend_received',
                    'fan_post_recommend_received:'.$result['post_idx'].':'.($result['reservation']?->member_idx ?? $member->idx),
                    [
                        'actor_member_idx' => (int) $actor->idx,
                        'memo' => 'Fan Post recommendation received reward',
                    ] + ($result['reservation'] ? ['random_dummy_request_idx' => $result['reservation']->idx] : [])
                ));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        unset($result['created'], $result['post_idx'], $result['author_member_idx']);

        return response()->json(['success' => true, 'data' => $result]);
    }

    private function recommendationCounts(int|string $postIdx): array
    {
        $monthStart = now('UTC')->startOfMonth();

        return [
            'lifetime' => DB::table('fan_post_likes')->where('fan_post_idx', $postIdx)->count(),
            'monthly' => DB::table('fan_post_likes')->where('fan_post_idx', $postIdx)
                ->where('created_at', '>=', $monthStart)
                ->where('created_at', '<', $monthStart->copy()->addMonth())
                ->count(),
        ];
    }

    private function redirectFromHiddenPost(Request $request)
    {
        $listPath = parse_url(route('fan-posts.index'), PHP_URL_PATH);
        $parameters = $request->query();
        $referer = $request->headers->get('referer');
        $refererParts = is_string($referer) ? parse_url($referer) : false;

        if (is_array($refererParts)
            && isset($refererParts['host'])
            && strcasecmp($refererParts['host'], $request->getHost()) === 0
            && ($refererParts['path'] ?? null) === $listPath) {
            parse_str($refererParts['query'] ?? '', $parameters);
        }

        $safeParameters = [];
        $sort = $parameters['sort'] ?? null;
        if (is_string($sort) && in_array($sort, ['latest', 'monthly_liked', 'liked', 'commented'], true)) {
            $safeParameters['sort'] = $sort;
        }
        $page = filter_var($parameters['page'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1000000],
        ]);
        if ($page !== false) {
            $safeParameters['page'] = $page;
        }

        return redirect()->route('fan-posts.index', $safeParameters)->with('global_notification', [
            'title' => __('ui.fan_post_unavailable_title'),
            'message' => __('ui.fan_post_no_longer_public'),
            'type' => 'info',
        ]);
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
