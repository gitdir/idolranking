<?php

namespace App\Http\Controllers;

use App\Support\HeartRewardManager;
use App\Support\IdolDebutDates;
use App\Support\IdolDuplicateMatcher;
use App\Support\SocialLinks;
use App\Support\RandomDummyActivity;
use App\Mail\IdolRegistrationRequestNotification;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Throwable;

class IdolRegistrationRequestController extends Controller
{
    private const REQUEST_CATEGORIES = [
        'boy' => ['individual', 'male'],
        'girl' => ['individual', 'female'],
        'boy_group' => ['group', 'male'],
        'girl_group' => ['group', 'female'],
    ];

    private const SOURCE_LANGUAGES = ['ko', 'en', 'ja', 'zh-CN', 'zh-TW'];

    public function create(Request $request, HeartRewardManager $heartRewards)
    {
        $requestType = (string) old('request_type', 'boy');
        $selectedGroup = null;
        if (in_array($requestType, ['boy', 'girl'], true) && ctype_digit((string) old('group_idx'))) {
            $gender = $requestType === 'boy' ? 'male' : 'female';
            $selectedGroup = DB::table('idols')->where('idx', old('group_idx'))
                ->where('type', 'group')->where('gender', $gender)->where('status', 'active')
                ->first(['idx', 'name', 'english_name', 'gender']);
        }
        $requestKey = bin2hex(random_bytes(32));
        $request->session()->put('idol_registration_request_key', $requestKey);
        $approvalRewardAmount = $heartRewards->enabledAmount('idol_registration_approved');

        return view('idol-registration-requests.create', compact('selectedGroup', 'requestKey', 'approvalRewardAmount'));
    }

    public function groupSearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'request_type' => ['required', Rule::in(['boy', 'girl'])],
            'q' => ['required', 'string', 'min:1', 'max:200'],
        ]);

        $gender = $validated['request_type'] === 'boy' ? 'male' : 'female';
        $term = trim($validated['q']);
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($term)).'%';
        $nameLike = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
        $groups = DB::table('idols')
            ->leftJoin('idol_localizations as group_en', function ($join): void {
                $join->on('group_en.idol_idx', '=', 'idols.idx')->where('group_en.language_code', '=', 'en');
            })
            ->leftJoin('idol_localizations as group_ko', function ($join): void {
                $join->on('group_ko.idol_idx', '=', 'idols.idx')->where('group_ko.language_code', '=', 'ko');
            })
            ->where('type', 'group')->where('gender', $gender)->where('status', 'active')
            ->where(function ($query) use ($like, $nameLike): void {
                $query->whereRaw('LOWER(idols.english_name) LIKE ?', [$like])
                    ->orWhere('idols.name', 'like', $nameLike)
                    ->orWhereRaw('LOWER(group_en.localized_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(group_ko.localized_name) LIKE ?', [$like])
                    ->orWhereExists(fn ($aliases) => $aliases->selectRaw('1')->from('idol_name_aliases as group_alias')
                        ->whereColumn('group_alias.idol_idx', 'idols.idx')
                        ->whereRaw('LOWER(group_alias.alias_name) LIKE ?', [$like]));
            })
            ->orderByRaw('CASE WHEN LOWER(idols.english_name) = ? OR LOWER(idols.name) = ? OR LOWER(group_en.localized_name) = ? OR LOWER(group_ko.localized_name) = ? THEN 0 ELSE 1 END', [mb_strtolower($term), mb_strtolower($term), mb_strtolower($term), mb_strtolower($term)])
            ->orderBy('idols.english_name')->orderBy('idols.name')->orderBy('idols.idx')
            ->limit(10)->get([
                'idols.idx', 'idols.name', 'idols.english_name', 'idols.gender',
                'group_en.localized_name as localized_english_name', 'group_ko.localized_name as localized_korean_name',
            ]);

        return response()->json(['success' => true, 'groups' => $groups]);
    }

    public function duplicateSearch(Request $request, IdolDuplicateMatcher $duplicateMatcher): JsonResponse
    {
        if (is_string($request->input('english_name'))) {
            $request->merge(['english_name' => IdolDuplicateMatcher::trimName($request->input('english_name'))]);
        }
        $validated = $request->validate([
            'request_type' => ['required', Rule::in(array_keys(self::REQUEST_CATEGORIES))],
            'english_name' => ['required', 'string', 'min:2', 'max:200', IdolDuplicateMatcher::englishNameRule()],
        ]);
        $validated['english_name'] = IdolDuplicateMatcher::trimName($validated['english_name']);
        $englishName = $validated['english_name'];
        $selectedCategory = self::REQUEST_CATEGORIES[$validated['request_type']];
        $matches = $duplicateMatcher->matchingActiveCategories(array_values(self::REQUEST_CATEGORIES), [$englishName]);
        $candidates = $matches->filter(fn (object $idol): bool => [$idol->type, $idol->gender] === $selectedCategory)->take(8)->values();
        $otherCandidates = $matches->reject(fn (object $idol): bool => [$idol->type, $idol->gender] === $selectedCategory)->take(8)->values();
        $exactDuplicate = $matches->contains(fn (object $idol): bool =>
            [$idol->type, $idol->gender] === $selectedCategory && (bool) $idol->exact_match
        );
        $displayCandidates = $candidates->concat($otherCandidates);
        if ($displayCandidates->contains(fn (object $idol): bool => $idol->type === 'individual')) {
            $groupNames = DB::table('idol_group_members as gm')
                ->join('idols as group_idol', 'group_idol.idx', '=', 'gm.group_idx')
                ->whereIn('gm.individual_idx', $displayCandidates->where('type', 'individual')->pluck('idx')->all())
                ->where('group_idol.status', 'active')
                ->orderBy('group_idol.idx')
                ->get(['gm.individual_idx', 'group_idol.name'])
                ->unique('individual_idx')->keyBy('individual_idx');
            $displayCandidates = $displayCandidates->map(function (object $idol) use ($groupNames): object {
                $idol->group_name = $groupNames->get($idol->idx)?->name;

                return $idol;
            });
            $candidates = $displayCandidates->take($candidates->count())->values();
            $otherCandidates = $displayCandidates->skip($candidates->count())->values();
        }
        $token = bin2hex(random_bytes(32));

        $request->session()->put('idol_registration_duplicate_search', [
            'fingerprint' => $this->duplicateSearchFingerprint($validated['request_type'], $validated['english_name']),
            'token' => $token,
        ]);

        return response()->json([
            'success' => true,
            'exact_duplicate' => $exactDuplicate,
            'candidates' => $candidates->map(fn (object $idol): array => $this->candidateData($idol))->all(),
            'other_candidates' => $otherCandidates->map(fn (object $idol): array => $this->candidateData($idol))->all(),
            'token' => $token,
        ]);
    }

    public function store(Request $request, IdolDuplicateMatcher $duplicateMatcher)
    {
        $member = $request->attributes->get('activityMember');
        $actor = $request->attributes->get('activityActor');
        $randomActivities = app(RandomDummyActivity::class);
        $randomMode = $randomActivities->enabled($request);
        if ($randomMode) {
            $request->merge(['source_language_code' => 'ko']);
        }
        $requestType = $request->string('request_type')->toString();
        $isGroup = in_array($requestType, ['boy_group', 'girl_group'], true);
        $request->merge(['request_type' => $requestType]);
        if ($isGroup) {
            $request->merge([
                'group_idx' => null,
                'birth_date' => null,
                'height_cm' => null,
                'weight_kg' => null,
                'blood_type' => null,
                'position' => null,
            ]);
        } else {
            $request->merge(['genre' => null, 'leader' => null]);
        }
        if (is_string($request->input('english_name'))) {
            $request->merge(['english_name' => IdolDuplicateMatcher::trimName($request->input('english_name'))]);
        }
        $validated = $request->validate([
            'request_type' => ['required', Rule::in(['boy', 'boy_group', 'girl', 'girl_group'])],
            'name' => ['nullable', 'string', 'max:200'],
            'english_name' => ['required', 'string', 'min:2', 'max:200', IdolDuplicateMatcher::englishNameRule()],
            'group_idx' => ['nullable', 'integer'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'height_cm' => ['nullable', 'numeric', 'between:50,300'],
            'weight_kg' => ['nullable', 'numeric', 'between:10,500'],
            'blood_type' => ['nullable', Rule::in(['A', 'B', 'AB', 'O'])],
            'agency' => ['nullable', 'string', 'max:200'],
            'position' => ['nullable', 'string', 'max:200'],
            'genre' => ['nullable', 'string', 'max:200'],
            'leader' => ['nullable', 'string', 'max:200'],
            'sns_links' => ['nullable', 'array', 'max:3'],
            'sns_links.*' => ['nullable', 'string', 'max:2048', 'url:http,https', static function ($attribute, $value, $fail): void {
                $parts = parse_url((string) $value);
                if (is_array($parts) && (isset($parts['user']) || isset($parts['pass']))) {
                    $fail(__('ui.social_link_invalid'));
                }
            }],
            'profile_image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'content' => ['required', 'string', 'max:1000'],
            'source_language_code' => ['required', 'string', Rule::in(self::SOURCE_LANGUAGES)],
            'request_key' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
        ], [
            'sns_links.*.url' => __('ui.social_link_invalid'),
            'sns_links.*.max' => __('ui.social_link_invalid'),
            'genre.string' => __('ui.group_field_invalid'),
            'genre.max' => __('ui.group_field_max'),
            'leader.string' => __('ui.group_field_invalid'),
            'leader.max' => __('ui.group_field_max'),
            'source_language_code.required' => __('ui.source_language_invalid'),
            'source_language_code.in' => __('ui.source_language_invalid'),
        ]);
        // Keep the user's internal spacing and punctuation; only trim the outer whitespace.
        $validated['english_name'] = IdolDuplicateMatcher::trimName($validated['english_name']);
        $debutDates = IdolDebutDates::validate($request->input('debut_dates', []));
        $validated['sns_links'] = SocialLinks::encodeSubmitted($validated['sns_links'] ?? []);
        $submittedKoreanName = $validated['name'] ?? null;
        // The SQL name columns are NOT NULL; keep an explicitly entered Korean name, otherwise use English.
        $validated['name'] = is_string($validated['name'] ?? null) && trim($validated['name']) !== ''
            ? $validated['name']
            : $validated['english_name'];
        $legacyName = trim($validated['name']);
        $legacyEnglishName = trim($validated['english_name']);
        $legacyContent = trim($validated['content']);

        $gender = str_starts_with($requestType, 'boy') ? 'male' : 'female';
        if (!$isGroup && !empty($validated['group_idx'])) {
            $validGroup = DB::table('idols')->where('idx', $validated['group_idx'])
                ->where('type', 'group')->where('gender', $gender)->where('status', 'active')->exists();
            if (!$validGroup) {
                return back()->withInput()->withErrors(['group_idx' => __('messages.idol_group_type_mismatch')]);
            }
        }

        $randomPayload = [
            'request_type' => $requestType, 'name' => $validated['name'], 'english_name' => $validated['english_name'],
            'group_idx' => $validated['group_idx'] ?? null, 'birth_date' => $validated['birth_date'] ?? null,
            'height_cm' => $validated['height_cm'] ?? null, 'weight_kg' => $validated['weight_kg'] ?? null,
            'blood_type' => $validated['blood_type'] ?? null, 'agency' => $validated['agency'] ?? null,
            'position' => $validated['position'] ?? null, 'genre' => $validated['genre'] ?? null,
            'leader' => $validated['leader'] ?? null, 'sns_links' => $validated['sns_links'], 'content' => $validated['content'],
            'language_code' => 'ko', 'debut_dates' => $debutDates,
            'profile_image_hash' => hash_file('sha256', $request->file('profile_image')->getRealPath()),
        ];
        if ($randomMode && ($existingRandomRequest = $randomActivities->completed(
            $request,
            'idol_registration',
            $validated['request_key'],
            $randomPayload,
        )) !== null) {
            abort_if($existingRandomRequest->result_type !== 'registration_request' || $existingRandomRequest->result_idx === null, 409);
            $approvedIdolIdx = DB::table('idol_registration_requests')->where('idx', $existingRandomRequest->result_idx)->value('approved_idol_idx');
            $request->session()->forget(['idol_registration_request_key', 'idol_registration_duplicate_search']);

            return $approvedIdolIdx
                ? redirect()->route('idol.show', ['idx' => $approvedIdolIdx])->with('success', __('messages.idol_request_submitted'))
                : redirect()->route('idol-registration.create')->with('success', __('messages.idol_request_submitted'));
        }

        $existingRequest = DB::table('idol_registration_requests')
            ->where('actor_member_idx', $actor->idx)
            ->where('request_key', $validated['request_key'])
            ->first(['idx', 'member_idx']);
        if ($existingRequest && ! $randomMode) {
            if ((int) $existingRequest->member_idx !== (int) $member->idx) {
                return back()->withInput()->withErrors([
                    'request' => __('messages.idol_request_key_reused'),
                ]);
            }

            return redirect()->route('idol-registration.create')
                ->with('success', __('messages.idol_request_submitted'));
        }
        $sessionRequestKey = (string) $request->session()->get('idol_registration_request_key', '');
        if ($sessionRequestKey === '' || !hash_equals($sessionRequestKey, $validated['request_key'])) {
            return back()->withInput()->withErrors([
                'request' => __('messages.idol_request_expired'),
            ]);
        }

        // Recheck against current active records at submission; browser confirmation is only an acknowledgement.
        $exactAndSimilarMatches = $duplicateMatcher->matchingActive(
            $isGroup ? 'group' : 'individual',
            $gender,
            [$validated['english_name'], $validated['name']]
        );
        if ($exactAndSimilarMatches->contains(fn (object $idol): bool => (bool) $idol->exact_match)) {
            return back()->withInput()->withErrors(['english_name' => __('ui.idol_duplicate_exact')]);
        }

        // Similar matches are based on the exact field the member verified in the UI.
        $candidates = $duplicateMatcher->matchingActive(
            $isGroup ? 'group' : 'individual',
            $gender,
            [trim($validated['english_name'])]
        );
        $search = $request->session()->get('idol_registration_duplicate_search');
        $expectedFingerprint = $this->duplicateSearchFingerprint($requestType, $validated['english_name']);
        $submittedToken = (string) $request->input('duplicate_search_token', '');
        $searchWasCurrent = is_array($search)
            && hash_equals($expectedFingerprint, (string) ($search['fingerprint'] ?? ''))
            && $submittedToken !== ''
            && hash_equals((string) ($search['token'] ?? ''), $submittedToken);

        if (!$searchWasCurrent) {
            return back()->withInput()->withErrors(['english_name' => __('ui.idol_duplicate_search_required')]);
        }
        if ($candidates->isNotEmpty()) {
            if (!$request->boolean('not_duplicate_confirmed')) {
                return back()->withInput()->withErrors(['not_duplicate_confirmed' => __('ui.idol_duplicate_confirmation_required')]);
            }
        }

        $submittedLocalizations = [
            'en' => ['localized_name' => $validated['english_name'], 'description' => null, 'agency' => null, 'position' => null, 'genre' => null, 'leader' => null],
        ];
        if (is_string($submittedKoreanName) && $submittedKoreanName !== '') {
            // Only store a Korean row when the member actually supplied a Korean-name field.
            $submittedLocalizations['ko'] = ['localized_name' => $submittedKoreanName, 'description' => null, 'agency' => null, 'position' => null, 'genre' => null, 'leader' => null];
        }
        $sourceLanguage = $validated['source_language_code'];
        $submittedLocalizations[$sourceLanguage] ??= ['localized_name' => null, 'description' => null, 'agency' => null, 'position' => null, 'genre' => null, 'leader' => null];
        $submittedLocalizations[$sourceLanguage]['description'] = $validated['content'];
        foreach (['agency', 'position', 'genre', 'leader'] as $field) {
            if (isset($validated[$field]) && $validated[$field] !== '') {
                $submittedLocalizations[$sourceLanguage][$field] = $validated[$field];
            }
        }

        $stored = $request->file('profile_image')->store('idol-registration-requests', 'public');
        $imagePath = 'storage/'.$stored;
        $createdRequestIdx = null;
        try {
            $reservation = $randomMode ? $randomActivities->reserve($request, 'idol_registration', $validated['request_key'], $randomPayload) : null;
            $approvedIdolIdx = DB::transaction(function () use ($request, $member, $actor, $isGroup, $gender, $validated, $imagePath, $legacyName, $legacyEnglishName, $legacyContent, $submittedLocalizations, $debutDates, $randomActivities, $reservation, &$createdRequestIdx): ?int {
                $reservation = $reservation ? $randomActivities->lockReservation($reservation) : null;
                if ($reservation?->replay) {
                    return DB::table('idol_registration_requests')->where('idx', $reservation->result_idx)->value('approved_idol_idx');
                }
                $activityMember = $reservation ? $randomActivities->member($reservation) : $member;
                abort_if($activityMember === null, 422, '사용할 수 있는 활성 더미 회원이 없습니다.');
                $requestIdx = DB::table('idol_registration_requests')->insertGetId([
                    'member_idx' => $activityMember->idx, 'actor_member_idx' => $actor->idx,
                    'type' => $isGroup ? 'group' : 'individual', 'gender' => $gender,
                    'name' => $legacyName, 'english_name' => $legacyEnglishName,
                    'group_idx' => $isGroup ? null : ($validated['group_idx'] ?? null),
                    'birth_date' => $isGroup ? null : ($validated['birth_date'] ?? null),
                    'height_cm' => $isGroup ? null : ($validated['height_cm'] ?? null), 'weight_kg' => $isGroup ? null : ($validated['weight_kg'] ?? null),
                    'blood_type' => $isGroup ? null : ($validated['blood_type'] ?? null), 'agency' => $validated['agency'] ?? null,
                    'position' => $isGroup ? null : ($validated['position'] ?? null),
                    'genre' => $isGroup ? ($validated['genre'] ?? null) : null,
                    'leader' => $isGroup ? ($validated['leader'] ?? null) : null,
                    'sns' => null, 'sns_links' => $validated['sns_links'], 'image_path' => $imagePath,
                    'content' => $legacyContent, 'reference_url' => null, 'note' => null,
                    'request_key' => $validated['request_key'], 'status' => 'pending',
                    'created_at' => now(), 'updated_at' => now(),
                ], 'idx');
                $createdRequestIdx = (int) $requestIdx;

                IdolDebutDates::insertForRequest($requestIdx, $debutDates);

                $now = now();
                $rows = [];
                foreach ($submittedLocalizations as $languageCode => $localizedText) {
                    $rows[] = [
                        'request_idx' => $requestIdx,
                        'language_code' => $languageCode,
                        'text_role' => 'submitted',
                        'localized_name' => $localizedText['localized_name'],
                        'description' => $localizedText['description'],
                        'agency' => $localizedText['agency'],
                        'position' => $localizedText['position'],
                        'genre' => $localizedText['genre'],
                        'leader' => $localizedText['leader'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                DB::table('idol_registration_request_localizations')->insert($rows);

                if ($reservation) {
                    $randomActivities->complete($reservation, 'registration_request', (int) $requestIdx);
                    $randomActivities->log($request, $reservation, 'idol_registration_submitted', '랜덤 더미 회원 명의로 아이돌/그룹 등록을 신청하고 즉시 승인했습니다.');
                    app(\App\Http\Controllers\Admin\IdolRegistrationRequestController::class)->approve($request, (int) $requestIdx);
                    return (int) DB::table('idol_registration_requests')->where('idx', $requestIdx)->value('approved_idol_idx');
                }

                return null;
            });
        } catch (QueryException $exception) {
            Storage::disk('public')->delete($stored);
            if ((string) $exception->getCode() === '23000' && DB::table('idol_registration_requests')
                ->where('actor_member_idx', $actor->idx)->where('request_key', $validated['request_key'])
                ->where('member_idx', $member->idx)->exists()) {
                return redirect()->route('idol-registration.create')
                    ->with('success', __('messages.idol_request_submitted'));
            }
            throw $exception;
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($stored);
            throw $exception;
        }

        $request->session()->forget('idol_registration_request_key');
        $request->session()->forget('idol_registration_duplicate_search');

        if ($createdRequestIdx !== null) {
            $this->notifyAdministrators($createdRequestIdx);
        }

        if ($approvedIdolIdx !== null) {
            return redirect()->route('idol.show', ['idx' => $approvedIdolIdx])
                ->with('success', '등록 신청이 승인되어 공개되었습니다.');
        }

        return redirect()->route('idol-registration.create')->with('success', __('messages.idol_request_submitted'));
    }

    private function notifyAdministrators(int $requestIdx): void
    {
        try {
            $registrationRequest = DB::table('idol_registration_requests as request')
                ->join('members as applicant', 'applicant.idx', '=', 'request.member_idx')
                ->where('request.idx', $requestIdx)
                ->first([
                    'request.type', 'request.english_name', 'request.created_at',
                    'applicant.nickname as applicant_nickname',
                ]);
            if ($registrationRequest === null) {
                return;
            }

            $recipientEmails = DB::table('members')
                ->where('member_level', 10)
                ->where('is_dummy', 0)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->whereNotNull('email')
                ->where('email', '<>', '')
                ->pluck('email')
                ->unique()
                ->values();

            foreach ($recipientEmails as $email) {
                try {
                    Mail::mailer('smtp')->to((string) $email)->send(new IdolRegistrationRequestNotification(
                        requestType: (string) $registrationRequest->type,
                        englishName: (string) $registrationRequest->english_name,
                        applicantNickname: (string) $registrationRequest->applicant_nickname,
                        submittedAt: (string) $registrationRequest->created_at,
                        reviewUrl: rtrim((string) config('app.url'), '/')
                            .route('admin.idol-requests.show', ['idx' => $requestIdx], false),
                    ));
                } catch (Throwable $exception) {
                    Log::warning('Idol registration admin email could not be sent.', [
                        'request_idx' => $requestIdx,
                        'exception' => $exception::class,
                    ]);
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Idol registration admin email notification failed.', [
                'request_idx' => $requestIdx,
                'exception' => $exception::class,
            ]);
        }
    }

    private function candidateData(object $idol): array
    {
        $englishName = (string) ($idol->english_name ?? '');
        $name = (string) $idol->name;
        $categoryKey = match ([$idol->type, $idol->gender]) {
            ['individual', 'male'] => 'boy',
            ['individual', 'female'] => 'girl',
            ['group', 'male'] => 'boy_group',
            ['group', 'female'] => 'girl_group',
        };

        return [
            'name' => $name,
            'english_name' => $englishName,
            'category' => __('ui.idol_duplicate_category_'.$categoryKey),
            'group_name' => $idol->type === 'individual' ? ($idol->group_name ?: null) : null,
            'image_url' => \App\Support\ManagedImageStorage::publicUrl($idol->image_path),
            'url' => route('idol.show', ['idx' => $idol->idx]),
            'exact' => (bool) ($idol->exact_match ?? false),
        ];
    }

    private function duplicateSearchFingerprint(string $requestType, string $englishName): string
    {
        return hash('sha256', $requestType.'|'.IdolDuplicateMatcher::trimName($englishName));
    }
}
