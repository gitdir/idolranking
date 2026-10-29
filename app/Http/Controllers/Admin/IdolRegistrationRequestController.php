<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminActivityLogger;
use App\Support\IdolDebutDates;
use App\Support\HeartRewardManager;
use App\Support\IdolDuplicateMatcher;
use App\Support\ManagedImageStorage;
use App\Support\RandomDummyActivity;
use App\Support\SocialLinks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IdolRegistrationRequestController extends Controller
{
    public function __invoke(Request $request): View
    {
        $tab = in_array($request->query('tab'), ['pending', 'approved', 'rejected'], true)
            ? $request->query('tab')
            : 'pending';
        $filterStatus = in_array($request->query('status'), ['pending', 'approved', 'rejected'], true)
            ? $request->query('status')
            : '';
        $type = in_array($request->query('type'), ['boy', 'boy_group', 'girl', 'girl_group'], true)
            ? $request->query('type')
            : '';

        $query = DB::table('idol_registration_requests as request')
            ->join('members as member', 'member.idx', '=', 'request.member_idx')
            ->where('request.status', $filterStatus ?: $tab);

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($nested) use ($search): void {
                $nested->where('request.name', 'like', '%'.$search.'%')
                    ->orWhere('request.english_name', 'like', '%'.$search.'%')
                    ->orWhere('member.nickname', 'like', '%'.$search.'%');
            });
        }
        if ($type !== '') {
            $query->where('request.type', str_contains($type, '_group') ? 'group' : 'individual')
                ->where('request.gender', str_starts_with($type, 'boy') ? 'male' : 'female');
        }
        if ($this->validDate($request->query('start_date'))) {
            $query->whereDate('request.created_at', '>=', $request->query('start_date'));
        }
        if ($this->validDate($request->query('end_date'))) {
            $query->whereDate('request.created_at', '<=', $request->query('end_date'));
        }

        $sortColumns=['name'=>'request.name','english_name'=>'request.english_name','created_at'=>'request.created_at','status'=>'request.status'];
        $sort=array_key_exists($request->query('sort'),$sortColumns)?$request->query('sort'):'created_at';
        $direction=$request->query('direction')==='asc'?'asc':'desc';
        $requests = $query->orderBy($sortColumns[$sort],$direction)->orderByDesc('request.idx')
            ->paginate(20, [
                'request.idx', 'request.type', 'request.gender', 'request.name', 'request.english_name',
                'request.image_path', 'request.status', 'request.created_at',
                'member.nickname as applicant', 'member.email as applicant_email',
            ])->withQueryString();

        $statusCounts = DB::table('idol_registration_requests')
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->whereIn('status', ['pending', 'approved', 'rejected'])
            ->groupBy('status')->pluck('aggregate', 'status');

        return view('admin.idol-requests.index', compact('requests', 'tab', 'filterStatus', 'type', 'statusCounts','sort','direction'));
    }

    public function show(int $idx): View
    {
        $registrationRequest = $this->findRequest($idx);
        $requestLocalizations = DB::table('idol_registration_request_localizations')
            ->where('request_idx', $idx)
            ->orderBy('language_code')->orderBy('text_role')->get()
            ->groupBy('language_code');
        $requestDebuts = DB::table('idol_registration_request_debuts')->where('request_idx', $idx)
            ->orderBy('debut_date')->orderBy('country_code')->get();
        $reviewLanguageCodes = $requestLocalizations->keys()->merge(['en', 'ko'])->unique()->sort()->values();
        $duplicateSearchNames = collect([$registrationRequest->english_name, $registrationRequest->name])
            ->merge($requestLocalizations->flatten(1)->pluck('localized_name'))
            ->filter(fn ($name): bool => is_string($name) && trim($name) !== '')
            ->unique()->values()->all();
        $duplicateMatches = app(IdolDuplicateMatcher::class)->matchingActiveCategories([
            ['individual', 'male'], ['individual', 'female'], ['group', 'male'], ['group', 'female'],
        ], $duplicateSearchNames);
        if ($registrationRequest->approved_idol_idx) {
            $duplicateMatches = $duplicateMatches->reject(fn (object $idol): bool =>
                (int) $idol->idx === (int) $registrationRequest->approved_idol_idx
            )->values();
        }
        $sameCategory = [$registrationRequest->type, $registrationRequest->gender];
        $sameCategoryMatches = $duplicateMatches
            ->filter(fn (object $idol): bool => [$idol->type, $idol->gender] === $sameCategory)
            ->take(8)->values();
        $otherCategoryMatches = $duplicateMatches
            ->reject(fn (object $idol): bool => [$idol->type, $idol->gender] === $sameCategory)
            ->take(8)->values();
        $displayMatches = $sameCategoryMatches->concat($otherCategoryMatches);
        if ($displayMatches->contains(fn (object $idol): bool => $idol->type === 'individual')) {
            $groupNames = DB::table('idol_group_members as gm')
                ->join('idols as group_idol', 'group_idol.idx', '=', 'gm.group_idx')
                ->whereIn('gm.individual_idx', $displayMatches->where('type', 'individual')->pluck('idx')->all())
                ->where('group_idol.status', 'active')->orderBy('group_idol.idx')
                ->get(['gm.individual_idx', 'group_idol.name'])
                ->unique('individual_idx')->keyBy('individual_idx');
            $displayMatches = $displayMatches->map(function (object $idol) use ($groupNames): object {
                $idol->group_name = $groupNames->get($idol->idx)?->name;

                return $idol;
            });
            $sameCategoryMatches = $displayMatches->take($sameCategoryMatches->count())->values();
            $otherCategoryMatches = $displayMatches->skip($sameCategoryMatches->count())->values();
        }
        $sameCategoryCandidates = $sameCategoryMatches->map(fn (object $idol): array => $this->duplicateCandidateData($idol))->all();
        $otherCategoryCandidates = $otherCategoryMatches->map(fn (object $idol): array => $this->duplicateCandidateData($idol))->all();
        $registrationRequest->snsLinkValues = SocialLinks::inputUrls(
            $registrationRequest->sns_links ?? null,
            $registrationRequest->sns ?? null
        );
        $approvalRewardAmount = app(HeartRewardManager::class)->enabledAmount('idol_registration_approved');
        $groups = DB::table('idols')
            ->where('type', 'group')
            ->where('gender', $registrationRequest->gender)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['idx', 'name']);

        return view('admin.idol-requests.show', compact(
            'registrationRequest', 'groups', 'approvalRewardAmount', 'requestLocalizations', 'reviewLanguageCodes',
            'sameCategoryCandidates', 'otherCategoryCandidates', 'requestDebuts'
        ));
    }

    public function approve(Request $request, int $idx)
    {
        $admin = $request->attributes->get('currentMember');
        $request->validate(['profile_image' => ['nullable','file','image','mimes:jpg,jpeg,png','max:5120']], [
            'profile_image.image' => '프로필 이미지를 확인해 주세요.',
            'profile_image.mimes' => '프로필 이미지는 JPG 또는 PNG 형식이어야 합니다.',
            'profile_image.max' => '프로필 이미지는 5MB 이하여야 합니다.',
        ]);
        $replacementPath = $request->hasFile('profile_image')
            ? ManagedImageStorage::store($request->file('profile_image'), 'idol-registration-requests')
            : null;
        $originalImagePath = null;

        try {
            DB::transaction(function () use ($request, $idx, $admin, $replacementPath, &$originalImagePath): void {
            $registrationRequest = DB::table('idol_registration_requests')
                ->where('idx', $idx)
                ->lockForUpdate()
                ->first();
            abort_if($registrationRequest === null, 404);
            $this->ensurePending($registrationRequest);
            $originalImagePath = $registrationRequest->image_path;
            $finalImagePath = $replacementPath ?? $registrationRequest->image_path;

            $values = $this->validatedReviewValues($request, $registrationRequest);
            $reviewedLocalizations = $this->validatedReviewedLocalizations($request, $idx, $registrationRequest->type === 'individual');
            $reviewedLocalizations = $this->includeExplicitBasicNameEdits(
                $idx,
                $registrationRequest,
                $values,
                $reviewedLocalizations
            );
            $reviewedLocalizations = $this->includeExplicitBasicProfileEdits(
                $idx,
                $registrationRequest,
                $values,
                $reviewedLocalizations
            );
            $this->saveReviewedLocalizations($idx, $reviewedLocalizations);
            $approvedLocalizations = $this->approvedLocalizations($idx, $reviewedLocalizations, $values);
            $values['english_name'] = IdolDuplicateMatcher::trimName((string) $approvedLocalizations['en']['localized_name']);
            $approvedLocalizations['en']['localized_name'] = $values['english_name'];
            Validator::make(['english_name' => $values['english_name']], [
                'english_name' => ['required', 'string', 'min:2', 'max:200', IdolDuplicateMatcher::englishNameRule()],
            ])->validate();
            $koreanName = $approvedLocalizations['ko']['localized_name'] ?? null;
            if ($koreanName !== null) {
                $values['name'] = $koreanName;
            } elseif ($values['name'] === '') {
                $values['name'] = $values['english_name'];
            }
            $this->ensureNoDuplicate($registrationRequest, $values, $approvedLocalizations);
            $this->validateGroup($registrationRequest, $values['group_idx']);

            // The request row keeps the final reviewed profile, while the approved entity
            // receives the same public fields and remains linked by approved_idol_idx.
            $idolIdx = DB::table('idols')->insertGetId([
                'type' => $registrationRequest->type,
                'gender' => $registrationRequest->gender,
                'name' => $values['name'],
                'english_name' => $values['english_name'],
                'agency' => $values['agency'],
                'birth_date' => $values['birth_date'],
                'height_cm' => $values['height_cm'],
                'weight_kg' => $values['weight_kg'],
                'blood_type' => $values['blood_type'],
                'position' => $values['position'],
                'genre' => $values['genre'],
                'leader' => $values['leader'],
                'sns' => $values['sns'],
                'sns_links' => $values['sns_links'],
                'image_path' => $finalImagePath,
                'content' => $values['content'],
                'status' => 'active',
                'view_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            IdolDebutDates::copyRequestToIdol($idx, $idolIdx);

            if ($registrationRequest->type === 'individual' && $values['group_idx'] !== null) {
                DB::table('idol_group_members')->insert([
                    'individual_idx' => $idolIdx,
                    'group_idx' => $values['group_idx'],
                    'created_at' => now(),
                ]);
            }

            $now = now();
            foreach ($approvedLocalizations as $languageCode => $localized) {
                DB::table('idol_localizations')->insert([
                    'idol_idx' => $idolIdx,
                    'language_code' => $languageCode,
                    'localized_name' => $localized['localized_name'],
                    'description' => $localized['description'],
                    'agency' => $localized['agency'],
                    'position' => $localized['position'],
                    'genre' => $localized['genre'],
                    'leader' => $localized['leader'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('idol_registration_requests')->where('idx', $idx)->update($values + [
                'image_path' => $finalImagePath,
                'status' => 'approved',
                'approved_idol_idx' => $idolIdx,
                'reviewed_by_member_idx' => $admin->idx,
                'reviewed_at' => now(),
                'rejection_reason' => null,
                'updated_at' => now(),
            ]);

            AdminActivityLogger::record(
                (int) $admin->idx,
                'idol_request_approved',
                'idol_registration_request',
                $idx,
                null,
                sprintf('%s #%d 생성: %s', $registrationRequest->type === 'group' ? '그룹' : '아이돌', $idolIdx, $values['name'])
            );
            // Keep the reward in the approval transaction so failures leave the request pending for retry.
            // A disabled or ineligible reward returns zero and does not block approval.
            $rewardReferences = ['actor_member_idx' => (int) $admin->idx, 'memo' => 'Registration approval reward'];
            if ((int) ($admin?->member_level ?? 0) === 10) {
                $randomRequestIdx = DB::table('admin_dummy_activity_requests')
                    ->where('actor_member_idx', $admin->idx)
                    ->where('member_idx', $registrationRequest->member_idx)
                    ->where('action_code', 'idol_registration')
                    ->where('result_type', 'registration_request')
                    ->where('result_idx', $idx)
                    ->whereNotNull('completed_at')
                    ->value('idx');
                if ($randomRequestIdx !== null) {
                    $rewardReferences['random_dummy_request_idx'] = (int) $randomRequestIdx;
                }
            }
            app(HeartRewardManager::class)->award(
                (int) $registrationRequest->member_idx,
                'idol_registration_approved',
                'idol_registration_approved:'.$idx,
                $rewardReferences
            );
        });
        } catch (\Throwable $exception) {
            ManagedImageStorage::deleteIfUnreferenced($replacementPath);
            throw $exception;
        }
        if ($replacementPath !== null && $originalImagePath && $originalImagePath !== $replacementPath) {
            ManagedImageStorage::deleteIfUnreferenced($originalImagePath);
        }

        return redirect()->route('admin.idol-requests.show', $idx)
            ->with('admin_success', '아이돌 등록 신청을 승인했습니다.');
    }

    public function save(Request $request, int $idx)
    {
        $registrationRequest = DB::table('idol_registration_requests')->where('idx',$idx)->first();
        abort_if($registrationRequest === null, 404);
        $this->ensurePending($registrationRequest);
        $request->validate(['profile_image'=>['nullable','file','image','mimes:jpg,jpeg,png','max:5120']], [
            'profile_image.image'=>'프로필 이미지를 확인해 주세요.','profile_image.mimes'=>'프로필 이미지는 JPG 또는 PNG 형식이어야 합니다.','profile_image.max'=>'프로필 이미지는 5MB 이하여야 합니다.',
        ]);
        $values = $this->validatedReviewValues($request, $registrationRequest);
        $reviewedLocalizations = $this->validatedReviewedLocalizations($request, $idx, $registrationRequest->type === 'individual');
        $reviewedLocalizations = $this->includeExplicitBasicNameEdits(
            $idx,
            $registrationRequest,
            $values,
            $reviewedLocalizations
        );
        $reviewedLocalizations = $this->includeExplicitBasicProfileEdits(
            $idx,
            $registrationRequest,
            $values,
            $reviewedLocalizations
        );
        if ($values['name'] === '') {
            $values['name'] = $values['english_name'];
        }
        $this->validateGroup($registrationRequest, $values['group_idx']);
        $replacementPath = $request->hasFile('profile_image')
            ? ManagedImageStorage::store($request->file('profile_image'), 'idol-registration-requests') : null;
        $admin = $request->attributes->get('currentMember');
        try {
            DB::transaction(function () use ($idx,$registrationRequest,$values,$replacementPath,$admin,$reviewedLocalizations): void {
                $locked = DB::table('idol_registration_requests')->where('idx',$idx)->lockForUpdate()->first();
                abort_if($locked === null,404); $this->ensurePending($locked);
                DB::table('idol_registration_requests')->where('idx',$idx)->update($values + [
                    'image_path'=>$replacementPath ?? $locked->image_path, 'updated_at'=>now(),
                ]);
                $this->saveReviewedLocalizations($idx, $reviewedLocalizations);
                AdminActivityLogger::record((int)$admin->idx,'idol_request_updated','idol_registration_request',$idx,null,$values['name']);
            });
        } catch (\Throwable $exception) {
            ManagedImageStorage::deleteIfUnreferenced($replacementPath);
            throw $exception;
        }
        if ($replacementPath !== null && $registrationRequest->image_path) {
            ManagedImageStorage::deleteIfUnreferenced($registrationRequest->image_path);
        }
        return redirect()->route('admin.idol-requests.show',$idx)->with('admin_success','신청 정보를 저장했습니다.');
    }

    public function reject(Request $request, int $idx)
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ], [
            'rejection_reason.required' => '반려 사유를 입력해 주세요.',
            'rejection_reason.max' => '반려 사유는 1,000자 이하여야 합니다.',
        ]);
        $reason = trim($validated['rejection_reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['rejection_reason' => '반려 사유를 입력해 주세요.']);
        }
        $admin = $request->attributes->get('currentMember');

        DB::transaction(function () use ($idx, $admin, $reason): void {
            $registrationRequest = DB::table('idol_registration_requests')
                ->where('idx', $idx)
                ->lockForUpdate()
                ->first();
            abort_if($registrationRequest === null, 404);
            $this->ensurePending($registrationRequest);

            DB::table('idol_registration_requests')->where('idx', $idx)->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'reviewed_by_member_idx' => $admin->idx,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

            AdminActivityLogger::record(
                (int) $admin->idx,
                'idol_request_rejected',
                'idol_registration_request',
                $idx,
                $reason,
                $registrationRequest->name
            );
        });

        return redirect()->route('admin.idol-requests.show', $idx)
            ->with('admin_success', '아이돌 등록 신청을 반려했습니다.');
    }

    private function findRequest(int $idx): object
    {
        $registrationRequest = DB::table('idol_registration_requests as request')
            ->join('members as applicant', 'applicant.idx', '=', 'request.member_idx')
            ->leftJoin('idols as submitted_group', 'submitted_group.idx', '=', 'request.group_idx')
            ->leftJoin('idols as approved_idol', 'approved_idol.idx', '=', 'request.approved_idol_idx')
            ->leftJoin('members as reviewer', 'reviewer.idx', '=', 'request.reviewed_by_member_idx')
            ->where('request.idx', $idx)
            ->select([
                'request.*', 'applicant.nickname as applicant_name', 'applicant.email as applicant_email',
                'submitted_group.name as group_name', 'approved_idol.name as approved_idol_name',
                'reviewer.nickname as reviewer_name',
            ])->first();

        abort_if($registrationRequest === null, 404);

        return $registrationRequest;
    }

    /** @return array<string, array<string, ?string>> */
    private function validatedReviewedLocalizations(Request $request, int $requestIdx, bool $isIndividual): array
    {
        $input = $request->input('reviewed_localizations', []);
        if ($input === null) {
            $input = [];
        }
        if (is_array($input) && is_string($input['en']['localized_name'] ?? null)) {
            $input['en']['localized_name'] = IdolDuplicateMatcher::trimName($input['en']['localized_name']);
        }

        $profileFields = $isIndividual ? ['agency', 'position'] : ['agency', 'genre', 'leader'];
        $allowedFields = array_merge(['localized_name', 'description'], $profileFields);
        $allowedLanguages = DB::table('idol_registration_request_localizations')->where('request_idx', $requestIdx)
            ->pluck('language_code')->merge(['en', 'ko'])->unique()->all();
        $validated = Validator::make(['reviewed_localizations' => $input], [
            'reviewed_localizations' => ['array'],
            'reviewed_localizations.*' => ['array:'.implode(',', $allowedFields)],
            'reviewed_localizations.*.localized_name' => ['nullable', 'string', 'max:200'],
            'reviewed_localizations.*.description' => ['nullable', 'string', 'max:1000'],
            'reviewed_localizations.*.agency' => ['nullable', 'string', 'max:200'],
            'reviewed_localizations.*.position' => $isIndividual ? ['nullable', 'string', 'max:200'] : ['prohibited'],
            'reviewed_localizations.*.genre' => $isIndividual ? ['prohibited'] : ['nullable', 'string', 'max:200'],
            'reviewed_localizations.*.leader' => $isIndividual ? ['prohibited'] : ['nullable', 'string', 'max:200'],
        ], [
            'reviewed_localizations.*.localized_name.max' => '다국어 이름은 200자 이하여야 합니다.',
            'reviewed_localizations.*.description.max' => '다국어 소개는 1,000자 이하여야 합니다.',
        ])->validate()['reviewed_localizations'] ?? [];

        foreach (array_keys($validated) as $languageCode) {
            if (!in_array($languageCode, $allowedLanguages, true)) {
                throw ValidationException::withMessages([
                    'reviewed_localizations' => '제출되었거나 기본으로 지원하는 언어만 검토할 수 있습니다.',
                ]);
            }
        }

        foreach ($validated as $languageCode => $fields) {
            foreach ($fields as $field => $value) {
                $validated[$languageCode][$field] = is_string($value) && trim($value) !== '' ? $value : null;
            }
        }

        $englishLocalizedName = $validated['en']['localized_name'] ?? null;
        if ($englishLocalizedName !== null) {
            Validator::make(['english_name' => $englishLocalizedName], [
                'english_name' => ['required', 'string', 'min:2', 'max:200', IdolDuplicateMatcher::englishNameRule()],
            ])->validate();
        }

        return $validated;
    }

    private function saveReviewedLocalizations(int $requestIdx, array $localizations): void
    {
        $now = now();
        foreach ($localizations as $languageCode => $fields) {
            $key = ['request_idx' => $requestIdx, 'language_code' => $languageCode, 'text_role' => 'reviewed'];
            $existing = DB::table('idol_registration_request_localizations')->where($key)->first();
            if ($existing !== null) {
                DB::table('idol_registration_request_localizations')->where($key)->update($fields + ['updated_at' => $now]);
            } else {
                DB::table('idol_registration_request_localizations')->insert($key + array_merge(array_fill_keys([
                    'localized_name', 'description', 'agency', 'position', 'genre', 'leader',
                ], null), $fields) + [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Basic English and submitted-language names remain editable for compatibility.
     * Keep edits in the language originally supplied with the request.
     */
    private function includeExplicitBasicNameEdits(
        int $requestIdx,
        object $registrationRequest,
        array $values,
        array $reviewed
    ): array {
        if (trim($values['english_name']) !== trim((string) $registrationRequest->english_name)
            && trim((string) ($reviewed['en']['localized_name'] ?? '')) === '') {
            $reviewed['en'] ??= ['localized_name' => null, 'description' => null];
            $reviewed['en']['localized_name'] = $values['english_name'];
        }

        $submittedNameLanguage = DB::table('idol_registration_request_localizations')
            ->where('request_idx', $requestIdx)->where('language_code', '<>', 'en')->where('text_role', 'submitted')
            ->whereNotNull('localized_name')->value('language_code');
        if ($submittedNameLanguage !== null
            && trim($values['name']) !== trim((string) $registrationRequest->name)
            && trim((string) ($reviewed[$submittedNameLanguage]['localized_name'] ?? '')) === '') {
            $reviewed[$submittedNameLanguage] ??= ['localized_name' => null, 'description' => null];
            $reviewed[$submittedNameLanguage]['localized_name'] = $values['name'];
        }

        return $reviewed;
    }

    /**
     * Basic profile fields mirror the request's submitted language in legacy columns.
     * When those values are explicitly changed, retain the edit in that same language so
     * approved localizations do not hide it. Explicit per-language review input wins.
     */
    private function includeExplicitBasicProfileEdits(
        int $requestIdx,
        object $registrationRequest,
        array $values,
        array $reviewed
    ): array {
        $fieldMap = ['content' => 'description', 'agency' => 'agency'];
        if ($registrationRequest->type === 'individual') {
            $fieldMap['position'] = 'position';
        } else {
            $fieldMap['genre'] = 'genre';
            $fieldMap['leader'] = 'leader';
        }

        $submittedRows = DB::table('idol_registration_request_localizations')
            ->where('request_idx', $requestIdx)->where('text_role', 'submitted')->get();
        $sourceLanguages = $submittedRows->filter(function (object $row) use ($registrationRequest): bool {
            $submittedDescription = trim((string) ($row->description ?? ''));
            $legacyDescription = trim((string) ($registrationRequest->content ?? ''));

            return $submittedDescription !== '' && $submittedDescription === $legacyDescription;
        })->pluck('language_code')->unique()->values();

        if ($sourceLanguages->count() !== 1) {
            return $reviewed;
        }
        $sourceLanguage = $sourceLanguages->first();

        foreach ($fieldMap as $legacyField => $localizedField) {
            $oldValue = trim((string) ($registrationRequest->{$legacyField} ?? ''));
            $newValue = trim((string) ($values[$legacyField] ?? ''));
            if ($newValue === '' || $newValue === $oldValue) {
                continue;
            }

            if (trim((string) ($reviewed[$sourceLanguage][$localizedField] ?? '')) !== '') {
                continue;
            }

            $reviewed[$sourceLanguage] ??= array_fill_keys([
                'localized_name', 'description', 'agency', 'position', 'genre', 'leader',
            ], null);
            $reviewed[$sourceLanguage][$localizedField] = $newValue;
        }

        return $reviewed;
    }

    /**
     * Merge reviewed values field-by-field over original submissions. Legacy columns remain
     * populated for current pages, while every known source language is copied to the new idol.
     */
    private function approvedLocalizations(int $requestIdx, array $newReviewed, array $legacyValues): array
    {
        $rows = DB::table('idol_registration_request_localizations')
            ->where('request_idx', $requestIdx)->get()->keyBy(fn ($row) => $row->text_role.':'.$row->language_code);
        $languages = [];
        foreach ($rows as $row) {
            $languages[$row->language_code] = true;
        }
        foreach ($newReviewed as $languageCode => $_fields) {
            $languages[$languageCode] = true;
        }
        $languages['en'] = true;

        $result = [];
        foreach (array_keys($languages) as $languageCode) {
            $submitted = $rows->get('submitted:'.$languageCode);
            $reviewed = $rows->get('reviewed:'.$languageCode);
            $result[$languageCode] = [];
            foreach (['localized_name', 'description', 'agency', 'position', 'genre', 'leader'] as $field) {
                $result[$languageCode][$field] = $this->preferredLocalizedValue(
                    $reviewed->{$field} ?? null,
                    $submitted->{$field} ?? null
                );
            }
        }

        $result['en']['localized_name'] = $result['en']['localized_name']
            ?? $legacyValues['english_name'];

        return $result;
    }

    private function preferredLocalizedValue(?string $reviewed, ?string $submitted): ?string
    {
        if ($reviewed !== null && trim($reviewed) !== '') {
            return $reviewed;
        }

        return $submitted !== null && trim($submitted) !== '' ? $submitted : null;
    }

    private function validatedReviewValues(Request $request, object $registrationRequest): array
    {
        $isIndividual = $registrationRequest->type === 'individual';
        if (is_string($request->input('english_name'))) {
            $request->merge(['english_name' => IdolDuplicateMatcher::trimName($request->input('english_name'))]);
        }
        $rules = [
            'name' => ['nullable', 'string', 'max:200'],
            'english_name' => ['required', 'string', 'min:2', 'max:200', IdolDuplicateMatcher::englishNameRule()],
            'agency' => ['nullable', 'string', 'max:200'],
            'sns_links' => ['nullable', 'array', 'max:10'],
            'sns_links.*' => ['nullable', 'string', 'max:2048', 'url:http,https', static function ($attribute, $value, $fail): void {
                $parts = parse_url((string) $value);
                if (is_array($parts) && (isset($parts['user']) || isset($parts['pass']))) {
                    $fail(__('ui.social_link_invalid'));
                }
            }],
            'content' => ['nullable', 'string', 'max:1000'],
            'group_idx' => $isIndividual ? ['nullable', 'integer'] : ['nullable'],
            'birth_date' => $isIndividual ? ['nullable', 'date', 'before_or_equal:today'] : ['nullable'],
            'height_cm' => $isIndividual ? ['nullable', 'numeric', 'between:50,300'] : ['nullable'],
            'weight_kg' => $isIndividual ? ['nullable', 'numeric', 'between:10,500'] : ['nullable'],
            'blood_type' => $isIndividual ? ['nullable', 'in:A,B,AB,O'] : ['nullable'],
            'position' => $isIndividual ? ['nullable', 'string', 'max:200'] : ['nullable'],
            'genre' => ['nullable', 'string', 'max:200'],
            'leader' => ['nullable', 'string', 'max:200'],
        ];
        $messages = [
            'required' => ':attribute 항목을 입력해 주세요.',
            'string' => ':attribute 항목을 올바르게 입력해 주세요.',
            'max' => ':attribute 항목이 허용 길이를 초과했습니다.',
            'date' => ':attribute 항목에 올바른 날짜를 입력해 주세요.',
            'before_or_equal' => ':attribute 항목에는 오늘 이전 날짜를 입력해 주세요.',
            'integer' => ':attribute 항목을 올바르게 선택해 주세요.',
            'numeric' => ':attribute 항목에는 숫자를 입력해 주세요.',
            'between' => ':attribute 항목의 값이 허용 범위를 벗어났습니다.',
            'in' => ':attribute 항목을 올바르게 선택해 주세요.',
            'sns_links.*.url' => __('ui.social_link_invalid'),
            'sns_links.*.max' => __('ui.social_link_invalid'),
        ];
        $attributes = [
            'name' => '이름', 'english_name' => '영문 이름', 'agency' => '소속사',
            'sns_links' => 'SNS 링크', 'sns_links.*' => 'SNS URL', 'content' => '짧은 소개', 'group_idx' => '그룹',
            'birth_date' => '생년월일', 'height_cm' => '키', 'weight_kg' => '몸무게',
            'blood_type' => '혈액형', 'position' => '포지션', 'genre' => '장르', 'leader' => '리더',
        ];
        $values = Validator::make($request->all(), $rules, $messages, $attributes)->validate();

        foreach (['agency', 'birth_date', 'height_cm', 'weight_kg', 'blood_type', 'position', 'group_idx', 'genre', 'leader'] as $field) {
            $values[$field] = $values[$field] ?? null;
        }

        foreach (['agency', 'position', 'genre', 'leader'] as $field) {
            $values[$field] = isset($values[$field]) && trim((string) $values[$field]) !== ''
                ? trim((string) $values[$field])
                : null;
        }
        $values['sns'] = $registrationRequest->sns;
        $values['sns_links'] = SocialLinks::encodeSubmitted($values['sns_links'] ?? []);
        $values['english_name'] = IdolDuplicateMatcher::trimName($values['english_name']);
        $values['name'] = trim((string) ($values['name'] ?? ''));
        $values['content'] = trim((string) ($values['content'] ?? ''));
        $values['group_idx'] = $isIndividual && !empty($values['group_idx']) ? (int) $values['group_idx'] : null;

        if (!$isIndividual) {
            foreach (['birth_date', 'height_cm', 'weight_kg', 'blood_type', 'position'] as $field) {
                $values[$field] = null;
            }
        } else {
            $values['genre'] = null;
            $values['leader'] = null;
        }

        return $values;
    }

    private function ensurePending(object $registrationRequest): void
    {
        if ($registrationRequest->status !== 'pending') {
            throw ValidationException::withMessages(['status' => '이미 처리된 등록 신청입니다.']);
        }
    }

    private function ensureNoDuplicate(object $registrationRequest, array $values, array $localizations): void
    {
        $names = [$values['name'], $values['english_name']];
        foreach ($localizations as $localized) {
            $names[] = $localized['localized_name'] ?? null;
        }
        $duplicate = app(IdolDuplicateMatcher::class)
            ->matchingActive($registrationRequest->type, $registrationRequest->gender, $names, true)
            ->contains(fn (object $idol): bool => (bool) $idol->exact_match);

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => '같은 유형의 동일한 이름과 영문 이름을 가진 아이돌 또는 그룹이 이미 등록되어 있습니다.',
            ]);
        }
    }

    private function duplicateCandidateData(object $idol): array
    {
        $category = match ([$idol->type, $idol->gender]) {
            ['individual', 'male'] => '남자 아이돌',
            ['individual', 'female'] => '여자 아이돌',
            ['group', 'male'] => '남자 그룹',
            ['group', 'female'] => '여자 그룹',
        };

        return [
            'name' => $idol->name,
            'english_name' => $idol->english_name,
            'category' => $category,
            'group_name' => $idol->group_name ?? null,
            'image_url' => ManagedImageStorage::publicUrl($idol->image_path),
            'url' => route('idol.show', ['idx' => $idol->idx]),
            'exact' => (bool) $idol->exact_match,
        ];
    }

    private function validateGroup(object $registrationRequest, ?int $groupIdx): void
    {
        if ($registrationRequest->type !== 'individual' || $groupIdx === null) {
            return;
        }

        $valid = DB::table('idols')->where('idx', $groupIdx)
            ->where('type', 'group')
            ->where('gender', $registrationRequest->gender)
            ->where('status', 'active')
            ->exists();

        if (!$valid) {
            throw ValidationException::withMessages(['group_idx' => '선택한 그룹을 확인할 수 없거나 현재 사용할 수 없습니다.']);
        }
    }

    private function validDate(mixed $value): bool
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }
}
