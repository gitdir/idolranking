<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminActivityLogger;
use App\Support\ManagedImageStorage;
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
        $groupIdx = $idol->type === 'individual'
            ? DB::table('idol_group_members')->where('individual_idx', $idx)->value('group_idx')
            : null;
        $groups = DB::table('idols')->where('type', 'group')->where('gender', $idol->gender)
            ->orderBy('name')->orderBy('idx')->get(['idx', 'name', 'status']);

        return view('admin.idols.edit', compact('idol', 'groupIdx', 'groups'));
    }

    public function update(Request $request, int $idx)
    {
        $idol = DB::table('idols')->where('idx', $idx)->first();
        abort_if($idol === null, 404);
        $isIndividual = $idol->type === 'individual';
        $validated = $request->validate($this->rules($isIndividual), $this->messages(), $this->attributes($isIndividual));
        $values = $this->normalize($validated, $isIndividual);
        $groupIdx = $values['group_idx'];
        $visibilityReason = trim((string) ($validated['visibility_reason'] ?? ''));
        unset($values['group_idx']);
        $newImagePath = $idol->image_path;
        if ($request->hasFile('profile_image')) {
            $newImagePath = ManagedImageStorage::store($request->file('profile_image'), 'idols');
        }
        $admin = $request->attributes->get('currentMember');

        try {
            DB::transaction(function () use ($idx, $idol, $values, $groupIdx, $visibilityReason, $newImagePath, $admin, $isIndividual): void {
                $locked = DB::table('idols')->where('idx', $idx)->lockForUpdate()->first();
                abort_if($locked === null, 404);
                $duplicate = DB::table('idols')->where('idx', '!=', $idx)->where('type', $locked->type)
                    ->where('gender', $locked->gender)->where('name', $values['name'])
                    ->where('english_name', $values['english_name'])->exists();
                if ($duplicate) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['name' => '같은 유형의 동일한 이름과 영문 이름이 이미 등록되어 있습니다.']);
                }

                DB::table('idols')->where('idx', $idx)->update($values + ['image_path' => $newImagePath, 'updated_at' => now()]);
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

    private function rules(bool $isIndividual): array
    {
        return [
            'name' => ['required','string','max:200'], 'english_name' => ['required','string','max:200'],
            'group_idx' => $isIndividual ? ['nullable','integer'] : ['nullable'],
            'birth_date' => $isIndividual ? ['nullable','date','before_or_equal:today'] : ['nullable'],
            'nationality' => ['nullable','string','max:100'],
            'height_cm' => $isIndividual ? ['nullable','numeric','between:50,300'] : ['nullable'],
            'weight_kg' => $isIndividual ? ['nullable','numeric','between:10,500'] : ['nullable'],
            'blood_type' => $isIndividual ? ['nullable', Rule::in(['A','B','AB','O'])] : ['nullable'],
            'agency' => ['nullable','string','max:200'], 'debut_date' => ['nullable','date','before_or_equal:today'],
            'position' => $isIndividual ? ['nullable','string','max:200'] : ['nullable'],
            'sns' => ['nullable','string','max:1000'], 'content' => ['required','string','max:1000'],
            'profile_image' => ['nullable','file','image','mimes:jpg,jpeg,png','max:5120'],
            'status' => ['required', Rule::in(['active','inactive'])],
            'visibility_reason' => ['nullable','string','max:1000'],
        ];
    }

    private function normalize(array $values, bool $isIndividual): array
    {
        foreach (['agency','debut_date','birth_date','nationality','height_cm','weight_kg','blood_type','position','sns','group_idx'] as $field) $values[$field] = $values[$field] ?? null;
        foreach (['name','english_name','content'] as $field) $values[$field] = trim((string) $values[$field]);
        foreach (['agency','nationality','position','sns'] as $field) $values[$field] = isset($values[$field]) && trim((string)$values[$field]) !== '' ? trim((string)$values[$field]) : null;
        $values['group_idx'] = $isIndividual && !empty($values['group_idx']) ? (int) $values['group_idx'] : null;
        if (!$isIndividual) foreach (['birth_date','height_cm','weight_kg','blood_type','position','group_idx'] as $field) $values[$field] = null;
        unset($values['profile_image']);
        unset($values['visibility_reason']);
        return $values;
    }

    private function messages(): array
    {
        return ['required'=>':attribute 항목을 입력해 주세요.','max'=>':attribute 항목이 허용 길이를 초과했습니다.','date'=>':attribute 날짜를 확인해 주세요.','before_or_equal'=>':attribute 항목에는 오늘 이전 날짜를 입력해 주세요.','numeric'=>':attribute 항목에는 숫자를 입력해 주세요.','between'=>':attribute 값이 허용 범위를 벗어났습니다.','image'=>'프로필 이미지를 확인해 주세요.','mimes'=>'프로필 이미지는 JPG 또는 PNG 형식이어야 합니다.'];
    }

    private function attributes(bool $isIndividual): array
    {
        return ['name'=>'이름','english_name'=>'영문 이름','group_idx'=>'그룹','birth_date'=>'생년월일',
            'nationality'=>$isIndividual?'국적':'국가','height_cm'=>'키','weight_kg'=>'몸무게','blood_type'=>'혈액형',
            'agency'=>'소속사','debut_date'=>'데뷔일','position'=>'포지션','sns'=>'SNS','content'=>'짧은 소개',
            'profile_image'=>'프로필 이미지','status'=>'공개 상태','visibility_reason'=>'상태 변경 사유'];
    }
}
