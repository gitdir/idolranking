<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminActivityLogger;
use App\Support\IdolDebutDates;
use App\Support\IdolDuplicateMatcher;
use App\Support\ManagedImageStorage;
use App\Support\SocialLinks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class IdolController extends Controller
{
    public function index(Request $request, string $kind)
    {
        abort_unless(in_array($kind, ['individual', 'group'], true), 404);
        $search = trim($request->string('q')->toString());
        $allowed=['name'=>'name','created_at'=>'created_at','status'=>'status'];
        $sort=array_key_exists($request->query('sort'),$allowed)?$request->query('sort'):'created_at'; $direction=$request->query('direction')==='asc'?'asc':'desc';
        $items = DB::table('idols')->where('type', $kind)
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderBy($allowed[$sort],$direction)->orderByDesc('idx')->paginate(20)->withQueryString();

        return view('admin.idols.index', compact('items', 'kind', 'search','sort','direction'));
    }

    public function updateVisibility(Request $request, int $idx)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'reason' => ['required', 'string', 'max:1000'],
        ], ['status.required' => '공개 상태를 선택해 주세요.', 'status.in' => '올바른 공개 상태를 선택해 주세요.', 'reason.required' => '조치 사유를 입력해 주세요.', 'reason.max' => '조치 사유는 1,000자 이하여야 합니다.']);
        $admin = $request->attributes->get('currentMember');

        DB::transaction(function () use ($idx, $validated, $admin) {
            $idol = DB::table('idols')->where('idx', $idx)->lockForUpdate()->first(['idx', 'type', 'status', 'name']);
            abort_if($idol === null, 404);
            DB::table('idols')->where('idx', $idx)->update(['status' => $validated['status'], 'updated_at' => now()]);
            $target = $idol->type === 'group' ? 'group' : 'idol';
            $action = $target.'_'.($validated['status'] === 'inactive' ? 'hidden' : 'restored');
            AdminActivityLogger::record((int) $admin->idx, $action, $target, $idx, $validated['reason'], $idol->name);
        });

        return back()->with('admin_success', '공개 상태를 변경했습니다.');
    }

    public function edit(int $idx)
    {
        $idol = DB::table('idols')->where('idx', $idx)->first();
        abort_if($idol === null, 404);
        $idol->snsLinkValues = SocialLinks::inputUrls($idol->sns_links ?? null, $idol->sns ?? null);
        $debutDateRows = old('debut_dates');
        if (!is_array($debutDateRows)) {
            $debutDateRows = IdolDebutDates::forIdol($idx);
        }
        $storedLocalizations = DB::table('idol_localizations')->where('idol_idx', $idx)
            ->orderBy('language_code')->get()->keyBy('language_code');
        $storedAliases = DB::table('idol_name_aliases')->where('idol_idx', $idx)
            ->orderBy('idx')->get()->groupBy('language_code');
        $multilingualLocalizations = old('localizations');
        if (!is_array($multilingualLocalizations)) {
            $languages = collect($storedLocalizations->keys())->merge($storedAliases->keys())
                ->unique()->sort()->values();
            $multilingualLocalizations = $languages->map(function (string $languageCode) use ($storedLocalizations, $storedAliases): array {
                $localization = $storedLocalizations->get($languageCode);

                return [
                    'language_code' => $languageCode,
                    'localized_name' => $localization->localized_name ?? '',
                    'description' => $localization->description ?? '',
                    'agency' => $localization->agency ?? '',
                    'position' => $localization->position ?? '',
                    'genre' => $localization->genre ?? '',
                    'leader' => $localization->leader ?? '',
                    'aliases' => $storedAliases->get($languageCode, collect())->pluck('alias_name')->all(),
                    'is_existing' => true,
                ];
            })->all();
        }
        $groupIdx = $idol->type === 'individual'
            ? DB::table('idol_group_members')->where('individual_idx', $idx)->value('group_idx')
            : null;
        $groupIdx = old('group_idx', $groupIdx);
        if ($groupIdx === '') $groupIdx = null;
        $selectedGroup = $groupIdx === null ? null : DB::table('idols')
            ->leftJoin('idol_localizations as group_en', function ($join): void {
                $join->on('group_en.idol_idx', '=', 'idols.idx')->where('group_en.language_code', '=', 'en');
            })
            ->leftJoin('idol_localizations as group_ko', function ($join): void {
                $join->on('group_ko.idol_idx', '=', 'idols.idx')->where('group_ko.language_code', '=', 'ko');
            })
            ->where('idols.idx', $groupIdx)->where('idols.type', 'group')->where('idols.gender', $idol->gender)
            ->first([
                'idols.idx', 'idols.name', 'idols.english_name', 'idols.gender', 'idols.status',
                'group_en.localized_name as localized_english_name', 'group_ko.localized_name as localized_korean_name',
            ]);

        return view('admin.idols.edit', compact('idol', 'groupIdx', 'selectedGroup', 'multilingualLocalizations', 'debutDateRows'));
    }

    public function update(Request $request, int $idx)
    {
        if (is_string($request->input('english_name'))) {
            $request->merge(['english_name' => IdolDuplicateMatcher::trimName($request->input('english_name'))]);
        }
        $idol = DB::table('idols')->where('idx', $idx)->first();
        abort_if($idol === null, 404);
        $isIndividual = $idol->type === 'individual';
        $rules = $this->rules($isIndividual);
        if ($request->exists('multilingual_data_present')) {
            $rules = array_merge($rules, $this->multilingualRules($isIndividual));
        }
        $validated = $request->validate($rules, $this->messages(), $this->attributes($isIndividual));
        $debutDates = $request->exists('debut_dates')
            ? IdolDebutDates::validate($request->input('debut_dates'))
            : null;
        $basicValidated = array_diff_key($validated, array_flip(['multilingual_data_present', 'localizations']));
        $localizationRows = $request->exists('multilingual_data_present')
            ? $this->normalizeMultilingualRows($validated['localizations'] ?? [], (string) $validated['english_name'], $isIndividual)
            : null;
        $values = $this->normalize($basicValidated, $isIndividual);
        $groupIdx = $values['group_idx'];
        $visibilityReason = trim((string) ($validated['visibility_reason'] ?? ''));
        unset($values['group_idx']);
        $newImagePath = $idol->image_path;
        if ($request->hasFile('profile_image')) {
            $newImagePath = ManagedImageStorage::store($request->file('profile_image'), 'idols');
        }
        $admin = $request->attributes->get('currentMember');

        try {
            DB::transaction(function () use ($idx, $idol, $values, $groupIdx, $visibilityReason, $newImagePath, $admin, $isIndividual, $localizationRows, $debutDates): void {
                $locked = DB::table('idols')->where('idx', $idx)->lockForUpdate()->first();
                abort_if($locked === null, 404);
                $duplicate = app(IdolDuplicateMatcher::class)
                    ->matchingActive($locked->type, $locked->gender, [$values['english_name']])
                    ->contains(fn (object $candidate): bool => (int) $candidate->idx !== $idx && (bool) $candidate->exact_match);
                if ($duplicate) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['english_name' => '같은 유형의 동일한 영문 이름이 이미 등록되어 있습니다.']);
                }

                DB::table('idols')->where('idx', $idx)->update($values + ['image_path' => $newImagePath, 'updated_at' => now()]);
                if ($debutDates !== null) {
                    IdolDebutDates::replaceForIdol($idx, $debutDates);
                }
                if ($localizationRows !== null) {
                    $this->saveMultilingualData($idx, $localizationRows);
                } elseif ((string) ($locked->english_name ?? '') !== $values['english_name']) {
                    // Keep any existing English preferred-name row consistent with the canonical name.
                    DB::table('idol_localizations')->where('idol_idx', $idx)->where('language_code', 'en')
                        ->whereNotNull('localized_name')->update([
                            'localized_name' => $values['english_name'], 'updated_at' => now(),
                        ]);
                }
                if ($isIndividual) {
                    DB::table('idol_group_members')->where('individual_idx', $idx)->delete();
                    if ($groupIdx !== null) {
                        $validGroup = DB::table('idols')->where('idx', $groupIdx)->where('type', 'group')
                            ->where('gender', $locked->gender)->exists();
                        if (!$validGroup) throw \Illuminate\Validation\ValidationException::withMessages(['group_idx' => '선택한 그룹을 확인할 수 없습니다.']);
                        DB::table('idol_group_members')->insert(['individual_idx' => $idx, 'group_idx' => $groupIdx, 'created_at' => now()]);
                    }
                }
                $target = $locked->type === 'group' ? 'group' : 'idol';
                AdminActivityLogger::record((int) $admin->idx, $target.'_updated', $target, $idx, null, $values['name']);
                if ($locked->status !== $values['status']) {
                    if ($visibilityReason === '') throw \Illuminate\Validation\ValidationException::withMessages(['visibility_reason' => '공개 상태 변경 사유를 입력해 주세요.']);
                    AdminActivityLogger::record((int) $admin->idx,
                        $target.'_'.($values['status'] === 'inactive' ? 'hidden' : 'restored'),
                        $target, $idx, $visibilityReason, $values['name']);
                }
            });
        } catch (\Throwable $exception) {
            if ($newImagePath !== $idol->image_path) {
                ManagedImageStorage::deleteIfUnreferenced($newImagePath);
            }
            throw $exception;
        }
        if ($idol->image_path && $newImagePath !== $idol->image_path) {
            ManagedImageStorage::deleteIfUnreferenced($idol->image_path);
        }

        return redirect()->route('admin.idols.edit', $idx)->with('admin_success', '등록 정보를 저장했습니다.');
    }

    private function multilingualRules(bool $isIndividual): array
    {
        $profileFields = $isIndividual ? ['agency', 'position'] : ['agency', 'genre', 'leader'];
        $allowedFields = array_merge(['language_code', 'localized_name', 'description', 'aliases'], $profileFields);

        return [
            'multilingual_data_present' => ['accepted'],
            'localizations' => ['sometimes', 'array', 'max:30'],
            'localizations.*' => ['array:'.implode(',', $allowedFields)],
            'localizations.*.language_code' => ['required', 'string', 'max:10', 'regex:/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/'],
            'localizations.*.localized_name' => ['nullable', 'string', 'max:200'],
            'localizations.*.description' => ['nullable', 'string', 'max:10000'],
            'localizations.*.agency' => ['nullable', 'string', 'max:200'],
            'localizations.*.position' => $isIndividual ? ['nullable', 'string', 'max:200'] : ['prohibited'],
            'localizations.*.genre' => $isIndividual ? ['prohibited'] : ['nullable', 'string', 'max:200'],
            'localizations.*.leader' => $isIndividual ? ['prohibited'] : ['nullable', 'string', 'max:200'],
            'localizations.*.aliases' => ['sometimes', 'array', 'max:50'],
            'localizations.*.aliases.*' => ['nullable', 'string', 'max:200'],
        ];
    }

    /** @return array<string, array{localized_name: ?string, description: ?string, aliases: array<int, string>}> */
    private function normalizeMultilingualRows(array $rows, string $canonicalEnglishName, bool $isIndividual): array
    {
        $normalized = [];
        foreach ($rows as $index => $row) {
            $languageCode = trim((string) ($row['language_code'] ?? ''));
            if ($languageCode === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "localizations.{$index}.language_code" => '언어 코드를 입력해 주세요.',
                ]);
            }
            if (array_key_exists($languageCode, $normalized)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "localizations.{$index}.language_code" => '같은 언어를 여러 번 입력할 수 없습니다.',
                ]);
            }

            $localizedName = $this->nullableText($row['localized_name'] ?? null);
            if ($languageCode === 'en') {
                $localizedName = $canonicalEnglishName;
            }
            $aliases = [];
            $seenAliases = [];
            foreach ($row['aliases'] ?? [] as $aliasIndex => $alias) {
                if (!is_string($alias) || trim($alias) === '') {
                    continue;
                }
                $key = $this->normalizedAliasKey($alias);
                if (isset($seenAliases[$key])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "localizations.{$index}.aliases.{$aliasIndex}" => '같은 언어에 중복된 별칭을 입력할 수 없습니다.',
                    ]);
                }
                $seenAliases[$key] = true;
                $aliases[] = $alias;
            }

            $normalized[$languageCode] = [];
            if (array_key_exists('localized_name', $row) || $languageCode === 'en') {
                $normalized[$languageCode]['localized_name'] = $localizedName;
            }
            foreach (['description', 'agency', $isIndividual ? 'position' : 'genre'] as $field) {
                if (array_key_exists($field, $row)) {
                    $normalized[$languageCode][$field] = $this->nullableText($row[$field]);
                }
            }
            if (!$isIndividual && array_key_exists('leader', $row)) {
                $normalized[$languageCode]['leader'] = $this->nullableText($row['leader']);
            }
            $normalized[$languageCode]['aliases'] = $aliases;
        }

        return $normalized;
    }

    private function saveMultilingualData(int $idolIdx, array $localizations): void
    {
        $now = now();
        $currentLocalizations = DB::table('idol_localizations')->where('idol_idx', $idolIdx)
            ->get()->keyBy('language_code');
        $currentAliases = DB::table('idol_name_aliases')->where('idol_idx', $idolIdx)->get();
        foreach ($currentLocalizations as $languageCode => $current) {
            if (!array_key_exists($languageCode, $localizations)) {
                DB::table('idol_localizations')->where('idol_idx', $idolIdx)
                    ->where('language_code', $languageCode)->delete();
            }
        }
        $profileFields = ['localized_name', 'description', 'agency', 'position', 'genre', 'leader'];
        foreach ($localizations as $languageCode => $fields) {
            $current = $currentLocalizations->get($languageCode);
            $values = [];
            foreach ($profileFields as $field) {
                $values[$field] = array_key_exists($field, $fields)
                    ? $fields[$field]
                    : ($current->{$field} ?? null);
            }

            if (count(array_filter($values, static fn ($value): bool => $value !== null)) === 0) {
                if ($current !== null) {
                    DB::table('idol_localizations')->where('idol_idx', $idolIdx)
                        ->where('language_code', $languageCode)->delete();
                }
            } elseif ($current === null) {
                DB::table('idol_localizations')->insert($values + [
                    'idol_idx' => $idolIdx, 'language_code' => $languageCode,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            } else {
                $changed = [];
                foreach ($values as $field => $value) {
                    if ($current->{$field} !== $value) {
                        $changed[$field] = $value;
                    }
                }
                if ($changed !== []) {
                    DB::table('idol_localizations')->where('idol_idx', $idolIdx)->where('language_code', $languageCode)
                        ->update($changed + ['updated_at' => $now]);
                }
            }
        }

        $aliasLanguages = $currentAliases->pluck('language_code')->unique();
        foreach ($aliasLanguages as $languageCode) {
            if (!array_key_exists($languageCode, $localizations)) {
                DB::table('idol_name_aliases')->where('idol_idx', $idolIdx)->where('language_code', $languageCode)->delete();
            }
        }
        foreach ($localizations as $languageCode => $fields) {
            $currentByKey = $currentAliases->where('language_code', $languageCode)
                ->keyBy(fn (object $alias): string => $this->normalizedAliasKey($alias->alias_name));
            $submittedKeys = array_fill_keys(array_map($this->normalizedAliasKey(...), $fields['aliases']), true);

            foreach ($currentByKey as $key => $alias) {
                if (!isset($submittedKeys[$key])) {
                    DB::table('idol_name_aliases')->where('idx', $alias->idx)->delete();
                }
            }
            foreach ($fields['aliases'] as $aliasName) {
                $key = $this->normalizedAliasKey($aliasName);
                if (!$currentByKey->has($key)) {
                    DB::table('idol_name_aliases')->insert([
                        'idol_idx' => $idolIdx, 'language_code' => $languageCode,
                        'alias_name' => $aliasName, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                } elseif ($currentByKey->get($key)->alias_name !== $aliasName) {
                    DB::table('idol_name_aliases')->where('idx', $currentByKey->get($key)->idx)
                        ->update(['alias_name' => $aliasName, 'updated_at' => $now]);
                }
            }
        }
    }

    private function nullableText(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private function normalizedAliasKey(string $alias): string
    {
        $normalized = trim($alias);
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($normalized, \Normalizer::FORM_C) ?: $normalized;
        }
        $normalized = preg_replace('/\\s+/u', ' ', $normalized) ?? $normalized;

        return mb_strtolower($normalized);
    }

    private function rules(bool $isIndividual): array
    {
        return [
            'name' => ['nullable','string','max:200'], 'english_name' => ['required','string','min:2','max:200', IdolDuplicateMatcher::englishNameRule()],
            'group_idx' => $isIndividual ? ['nullable','integer'] : ['nullable'],
            'birth_date' => $isIndividual ? ['nullable','date','before_or_equal:today'] : ['nullable'],
            'height_cm' => $isIndividual ? ['nullable','numeric','between:50,300'] : ['nullable'],
            'weight_kg' => $isIndividual ? ['nullable','numeric','between:10,500'] : ['nullable'],
            'blood_type' => $isIndividual ? ['nullable', Rule::in(['A','B','AB','O'])] : ['nullable'],
            'agency' => ['nullable','string','max:200'],
            'position' => $isIndividual ? ['nullable','string','max:200'] : ['nullable'],
            'genre' => ['nullable','string','max:200'], 'leader' => ['nullable','string','max:200'],
            'sns_links' => ['nullable','array','max:3'],
            'sns_links.*' => ['nullable','string','max:2048','url:http,https', static function ($attribute, $value, $fail): void {
                $parts = parse_url((string) $value);
                if (is_array($parts) && (isset($parts['user']) || isset($parts['pass']))) {
                    $fail(__('ui.social_link_invalid'));
                }
            }],
            'content' => ['required','string','max:1000'],
            'profile_image' => ['nullable','file','image','mimes:jpg,jpeg,png','max:5120'],
            'status' => ['required', Rule::in(['active','inactive'])],
            'visibility_reason' => ['nullable','string','max:1000'],
        ];
    }

    private function normalize(array $values, bool $isIndividual): array
    {
        foreach (['agency','birth_date','height_cm','weight_kg','blood_type','position','group_idx','genre','leader'] as $field) $values[$field] = $values[$field] ?? null;
        foreach (['english_name','content'] as $field) $values[$field] = trim((string) $values[$field]);
        $values['english_name'] = IdolDuplicateMatcher::trimName($values['english_name']);
        $values['name'] = trim((string) ($values['name'] ?? ''));
        if ($values['name'] === '') $values['name'] = $values['english_name'];
        foreach (['agency','position','genre','leader'] as $field) $values[$field] = isset($values[$field]) && trim((string)$values[$field]) !== '' ? trim((string)$values[$field]) : null;
        $values['sns_links'] = SocialLinks::encodeSubmitted($values['sns_links'] ?? []);
        $values['group_idx'] = $isIndividual && !empty($values['group_idx']) ? (int) $values['group_idx'] : null;
        if (!$isIndividual) foreach (['birth_date','height_cm','weight_kg','blood_type','position','group_idx'] as $field) $values[$field] = null;
        else foreach (['genre','leader'] as $field) $values[$field] = null;
        unset($values['profile_image']);
        unset($values['visibility_reason']);
        return $values;
    }

    private function messages(): array
    {
        return [
            'required' => ':attribute 항목을 입력해 주세요.', 'max' => ':attribute 항목이 허용 길이를 초과했습니다.',
            'sns_links.*.url' => __('ui.social_link_invalid'), 'sns_links.*.max' => __('ui.social_link_invalid'),
            'date' => ':attribute 날짜를 확인해 주세요.', 'before_or_equal' => ':attribute 항목에는 오늘 이전 날짜를 입력해 주세요.',
            'numeric' => ':attribute 항목에는 숫자를 입력해 주세요.', 'between' => ':attribute 값이 허용 범위를 벗어났습니다.',
            'image' => '프로필 이미지를 확인해 주세요.', 'mimes' => '프로필 이미지는 JPG 또는 PNG 형식이어야 합니다.',
            'multilingual_data_present.accepted' => '다국어 정보 저장 요청을 확인할 수 없습니다.',
            'localizations.array' => '언어별 정보 형식이 올바르지 않습니다.',
            'localizations.*.array' => '언어별 정보 형식이 올바르지 않습니다.',
            'localizations.*.language_code.required' => '언어 코드를 입력해 주세요.',
            'localizations.*.language_code.max' => '언어 코드는 10자 이하여야 합니다.',
            'localizations.*.language_code.regex' => '언어 코드는 en, ko, ja, zh-CN과 같은 형식으로 입력해 주세요.',
            'localizations.*.localized_name.string' => '다국어 이름은 문자열로 입력해 주세요.',
            'localizations.*.localized_name.max' => '다국어 이름은 200자 이하여야 합니다.',
            'localizations.*.description.string' => '다국어 소개는 문자열로 입력해 주세요.',
            'localizations.*.description.max' => '다국어 소개는 10,000자 이하여야 합니다.',
            'localizations.*.aliases.array' => '별칭 목록 형식이 올바르지 않습니다.',
            'localizations.*.aliases.max' => '언어별 별칭은 50개까지 입력할 수 있습니다.',
            'localizations.*.aliases.*.string' => '별칭은 문자열로 입력해 주세요.',
            'localizations.*.aliases.*.max' => '별칭은 200자 이하여야 합니다.',
        ];
    }

    private function attributes(bool $isIndividual): array
    {
        return ['name'=>'이름','english_name'=>'영문 이름','group_idx'=>'그룹','birth_date'=>'생년월일',
            'height_cm'=>'키','weight_kg'=>'몸무게','blood_type'=>'혈액형',
            'agency'=>'소속사','position'=>'포지션','genre'=>'장르','leader'=>'리더','sns_links'=>'SNS 링크','sns_links.*'=>'SNS URL','content'=>'짧은 소개',
            'profile_image'=>'프로필 이미지','status'=>'공개 상태','visibility_reason'=>'상태 변경 사유',
            'localizations.*.language_code'=>'언어 코드','localizations.*.localized_name'=>'다국어 이름',
            'localizations.*.description'=>'다국어 소개','localizations.*.aliases.*'=>'별칭'];
    }
}
