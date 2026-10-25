<?php

namespace App\Http\Controllers;

use App\Rules\AllowedNickname;
use App\Support\HeartRewardManager;
use App\Support\ManagedImageStorage;
use App\Support\Countries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class MyPageController extends Controller
{
    public function checkNickname(Request $request)
    {
        $member = $request->attributes->get('currentMember');
        $request->merge(['nickname' => trim((string) $request->input('nickname'))]);
        $formatValidator = Validator::make($request->all(), [
            'nickname' => ['required', 'string', 'min:2', 'max:20'],
        ], [
            'nickname.required' => 'Please enter a nickname.',
            'nickname.min' => 'Nickname must be between 2 and 20 characters.',
            'nickname.max' => 'Nickname must be between 2 and 20 characters.',
        ]);

        if ($formatValidator->fails()) {
            return response()->json([
                'success' => false,
                'available' => false,
                'reason' => 'invalid',
                'message' => $formatValidator->errors()->first('nickname'),
            ], 422);
        }

        $prohibitedValidator = Validator::make($request->all(), [
            'nickname' => [new AllowedNickname],
        ]);
        if ($prohibitedValidator->fails()) {
            return response()->json([
                'success' => false,
                'available' => false,
                'reason' => 'prohibited',
                'message' => $prohibitedValidator->errors()->first('nickname'),
            ], 422);
        }

        $duplicateValidator = Validator::make($request->all(), [
            'nickname' => [\Illuminate\Validation\Rule::unique('members', 'nickname')->ignore($member->idx, 'idx')],
        ], [
            'nickname.unique' => 'This nickname is already in use.',
        ]);
        if ($duplicateValidator->fails()) {
            return response()->json([
                'success' => false,
                'available' => false,
                'reason' => 'duplicate',
                'message' => $duplicateValidator->errors()->first('nickname'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'available' => true,
            'reason' => 'available',
            'message' => __('messages.nickname_available'),
            'nickname' => $request->string('nickname')->toString(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $member = $request->attributes->get('currentMember');
        $actor = $request->attributes->get('activityActor');
        $request->merge([
            'nickname' => trim((string) $request->input('nickname')),
            'country_code' => strtoupper(trim((string) $request->input('country_code'))),
        ]);
        $validated = $request->validate([
            'nickname' => ['bail', 'required', 'string', 'min:2', 'max:20', new AllowedNickname,
                \Illuminate\Validation\Rule::unique('members', 'nickname')->ignore($member->idx, 'idx')],
            'country_code' => ['required', 'string', Rule::in(Countries::codes())],
            'profile_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,gif', 'max:5120'],
        ], [
            'nickname.required' => 'Please enter a nickname.',
            'nickname.min' => 'Nickname must be between 2 and 20 characters.',
            'nickname.max' => 'Nickname must be between 2 and 20 characters.',
            'nickname.unique' => 'This nickname is already in use.',
        ]);
        $newImagePath = $request->hasFile('profile_image')
            ? ManagedImageStorage::store($request->file('profile_image'), 'members')
            : $member->profile_image_path;

        try {
            $rewardedHearts = DB::transaction(function () use ($member, $actor, $validated, $newImagePath): int {
                DB::table('members')->where('idx', $member->idx)->update([
                    'nickname' => $validated['nickname'],
                    'country_code' => $validated['country_code'],
                    'profile_image_path' => $newImagePath,
                    'updated_at' => now(),
                ]);

                return app(HeartRewardManager::class)->award((int) $member->idx, 'profile_complete', 'profile_complete:'.$member->idx,
                    ['actor_member_idx' => (int) $actor->idx, 'memo' => 'Profile completion reward']);
            });
        } catch (\Throwable $exception) {
            if ($newImagePath !== $member->profile_image_path) {
                ManagedImageStorage::deleteIfUnreferenced($newImagePath);
            }
            throw $exception;
        }

        if ($newImagePath !== $member->profile_image_path) {
            ManagedImageStorage::deleteIfUnreferenced($member->profile_image_path);
        }

        return response()->json(['success'=>true,'message'=>__('ui.profile_updated'),'data'=>[
            'rewarded_hearts'=>$rewardedHearts,
            'profile_image_url'=>ManagedImageStorage::publicUrl($newImagePath),
        ]]);
    }

    public function show(Request $request)
    {
        $member = $request->attributes->get('currentMember');
        $activeTab = in_array($request->query('tab'), ['hearts', 'posts', 'invites'], true)
            ? $request->query('tab') : 'hearts';
        $publishedPosts = DB::table('fan_posts as p')
            ->where('p.member_idx', $member->idx)
            ->where('p.status', 'published')
            ->whereNull('p.deleted_at');
        $monthStart = now('UTC')->startOfMonth();
        $posts = $activeTab === 'posts' ? (clone $publishedPosts)
                ->select(['p.idx', 'p.media_type', 'p.image_path', 'p.youtube_video_id', 'p.content', 'p.view_count', 'p.language_code'])
                ->selectSub(DB::table('fan_post_likes as l')->selectRaw('COUNT(*)')->whereColumn('l.fan_post_idx', 'p.idx'), 'likes')
                ->selectSub(DB::table('fan_post_likes as l')->selectRaw('COUNT(*)')->whereColumn('l.fan_post_idx', 'p.idx')
                    ->where('l.created_at', '>=', $monthStart)->where('l.created_at', '<', $monthStart->copy()->addMonth()), 'monthly_likes')
                ->selectSub(DB::table('fan_post_comments as c')->selectRaw('COUNT(*)')->whereColumn('c.fan_post_idx', 'p.idx')
                    ->where('c.status', 'published')->whereNull('c.deleted_at'), 'comments')
                ->orderByRaw('COALESCE(p.published_at, p.created_at) DESC')->orderByDesc('p.idx')->limit(10)->get()
                ->map(fn ($post) => [
                    'portrait-rose', $member->nickname, $post->content ?? '', $post->view_count, $post->likes, $post->comments, 'square',
                    'member_code' => $member->member_code,
                    'country_code' => $member->country_code,
                    'author_image_url' => $member->profile_image_path,
                    'media_type' => $post->media_type,
                    'youtube_video_id' => $post->youtube_video_id,
                    'image_url' => \App\Support\ManagedImageStorage::publicUrl($post->image_path),
                    'fan_post_idx' => (int) $post->idx,
                    'monthly_like_count' => (int) $post->monthly_likes,
                    'language_code' => $post->language_code,
                ])->all() : [];

        $heartHistories = null;
        if ($activeTab === 'hearts') {
            $heartHistories = DB::table('heart_histories as h')
                ->leftJoin('idol_votes as v', 'v.idx', '=', 'h.vote_idx')
                ->leftJoin('idols as i', 'i.idx', '=', 'v.idol_idx')
                ->where('h.member_idx', $member->idx)
                ->where('h.created_at', '>=', now()->subMonthsNoOverflow(3))
                ->select('h.idx', 'h.created_at', 'h.reason', 'h.amount', 'i.type as voted_idol_type')
                ->orderByDesc('h.created_at')->orderByDesc('h.idx')
                ->paginate(20)->appends(['tab' => 'hearts'])->fragment('my-page-lower-tabs');

            $heartHistories->through(fn ($history) => $this->presentHeartHistory($history));
        }

        $invitedMembers = null;
        if ($activeTab === 'invites') {
            $invitedMembers = DB::table('members as invited')
                ->where('invited.referred_by_member_idx', $member->idx)
                ->select('invited.idx', 'invited.member_code', 'invited.nickname', 'invited.profile_image_path',
                    'invited.created_at')
                ->orderByDesc('invited.created_at')->orderByDesc('invited.idx')
                ->paginate(20)->appends(['tab' => 'invites'])->fragment('my-page-lower-tabs');

            $operationKeys = $invitedMembers->getCollection()->map(fn ($invited) => 'referral_reward:'.$invited->idx)->all();
            $rewards = $operationKeys === [] ? collect() : DB::table('heart_histories')
                ->where('member_idx', $member->idx)
                ->where('reason', 'invite_friend_reward')
                ->where('amount', '>', 0)
                ->whereIn('operation_key', $operationKeys)
                ->get(['actor_member_idx', 'operation_key', 'amount'])
                ->keyBy('operation_key');
            $invitedMembers->through(function ($invited) use ($rewards) {
                $reward = $rewards->get('referral_reward:'.$invited->idx);
                $invited->reward_amount = $reward !== null && (int) $reward->actor_member_idx === (int) $invited->idx
                    ? (int) $reward->amount : null;
                return $invited;
            });
        }

        $memberStats = [
            // Uses the same presentation value as the public member page until percentile data is available.
            'like_ranking' => 'Top 5%',
            'fan_post_count' => (clone $publishedPosts)->count(),
            'comment_count' => DB::table('fan_post_comments as c')
                ->join('fan_posts as p', 'p.idx', '=', 'c.fan_post_idx')
                ->where('p.member_idx', $member->idx)
                ->where('p.status', 'published')
                ->whereNull('p.deleted_at')
                ->where('c.status', 'published')
                ->whereNull('c.deleted_at')
                ->count(),
        ];

        return view('my-page', ['posts' => $posts, 'currentMember' => $member, 'memberStats' => $memberStats,
            'activeTab' => $activeTab, 'heartHistories' => $heartHistories, 'invitedMembers' => $invitedMembers]);
    }

    public function heartHistory(Request $request)
    {
        $member = $request->attributes->get('currentMember');
        $filter = in_array($request->query('filter'), ['all', 'earned', 'spent'], true)
            ? $request->query('filter') : 'all';

        $histories = DB::table('heart_histories as h')
            ->leftJoin('idol_votes as v', 'v.idx', '=', 'h.vote_idx')
            ->leftJoin('idols as i', 'i.idx', '=', 'v.idol_idx')
            ->where('h.member_idx', $member->idx)
            ->when($filter === 'earned', fn ($query) => $query->where('h.amount', '>=', 0))
            ->when($filter === 'spent', fn ($query) => $query->where('h.amount', '<', 0))
            ->select('h.idx', 'h.created_at', 'h.reason', 'h.amount', 'i.type as voted_idol_type')
            ->orderByDesc('h.created_at')->orderByDesc('h.idx')
            ->paginate(20)->appends(['filter' => $filter])
            ->through(fn ($history) => $this->presentHeartHistory($history));

        return view('my-page.heart-history', [
            'currentMember' => $member,
            'heartHistories' => $histories,
            'filter' => $filter,
            'seo' => [
                'title' => __('ui.heart_history').' | IdolRanking',
                'description' => __('ui.heart_history_description'),
                'robots' => 'noindex,nofollow',
            ],
        ]);
    }

    private function presentHeartHistory(object $history): object
    {
        [$history->activity, $history->icon] = match ($history->reason) {
            'signup_reward' => [__('ui.history_signup'), 'heart'],
            'profile_complete' => [__('ui.history_profile'), 'heart'],
            'daily_attendance' => [__('ui.activity_daily_attendance'), 'heart'],
            'fan_post_reward' => [__('ui.activity_fan_post'), 'post'],
            'comment_reward' => [__('ui.activity_comment'), 'comment'],
            'comment_reward_reversal' => [__('ui.history_comment_reversed'), 'comment'],
            'fan_post_recommend_received' => [__('ui.activity_recommend'), 'heart'],
            'invite_friend_reward' => [__('ui.activity_invite'), 'people'],
            HeartRewardManager::PARTNER_BANNER_REASON => [__('ui.partner_banners'), 'heart'],
            'idol_registration_approved' => [__('ui.activity_idol_registration'), 'heart'],
            'quiz_weekly_rank_1' => [__('quiz.weekly_rank_history', ['rank' => 1]), 'heart'],
            'quiz_weekly_rank_2' => [__('quiz.weekly_rank_history', ['rank' => 2]), 'heart'],
            'quiz_weekly_rank_3' => [__('quiz.weekly_rank_history', ['rank' => 3]), 'heart'],
            'vote' => [match ($history->voted_idol_type) {
                'group' => __('ui.vote_for_group'),
                'individual' => __('ui.vote_for_idol_generic'),
                default => __('ui.vote'),
            }, 'vote'],
            'admin_adjustment' => [__('ui.history_adjustment'), 'heart'],
            'ad_reward' => [__('ui.activity_watch_ads'), 'heart'],
            'event_reward' => [__('ui.history_event_reward'), 'heart'],
            default => [__('ui.history_other'), 'heart'],
        };

        return $history;
    }

    public function changePassword(Request $request)
    {
        $member = $request->attributes->get('currentMember');
        if ($member === null) {
            return response()->json(['success' => false, 'message' => __('messages.signin_to_change_password')], 401);
        }
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            // Matches the existing reset-password UI policy.
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()->symbols(),
                function ($attribute, $value, $fail) {
                    // Bcrypt's byte limit prevents silent truncation; this is not a complexity policy.
                    if (is_string($value) && config('hashing.driver', 'bcrypt') === 'bcrypt'
                        && (strlen($value) > 72 || str_contains($value, "\0"))) {
                        $fail('The password must fit within 72 bytes and contain no null characters.');
                    }
                },
            ],
            'password_confirmation' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => __('messages.password_entries_invalid'), 'errors' => $validator->errors()], 422);
        }

        return DB::transaction(function () use ($request, $member) {
            $record = DB::table('members')->where('idx', $member->idx)->lockForUpdate()->first();
            if (!$record || $record->member_level <= 0 || $record->status !== 'active' || $record->deleted_at !== null) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return response()->json(['success' => false, 'message' => __('messages.signin_again')], 401);
            }
            if ($record->password_hash === null) {
                return response()->json(['success' => false, 'message' => __('messages.google_only_password')], 409);
            }
            if (!Hash::check($request->string('current_password')->toString(), $record->password_hash)) {
                return response()->json(['success' => false, 'message' => __('messages.current_password_incorrect'), 'errors' => ['current_password' => [__('messages.current_password_incorrect')]]], 422);
            }
            DB::table('members')->where('idx', $record->idx)->update([
                'password_hash' => Hash::make($request->string('password')->toString()),
                'password_changed_at' => now(), 'updated_at' => now(),
            ]);

            return response()->json(['success' => true, 'message' => __('messages.password_change_success'), 'data' => (object) []]);
        });
    }
}
