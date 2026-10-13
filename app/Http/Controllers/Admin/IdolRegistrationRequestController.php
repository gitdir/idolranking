<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminActivityLogger;
use App\Support\HeartRewardManager;
use App\Support\ManagedImageStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
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
        $approvalRewardAmount = app(HeartRewardManager::class)->enabledAmount('idol_registration_approved');
        $groups = DB::table('idols')
            ->where('type', 'group')
            ->where('gender', $registrationRequest->gender)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['idx', 'name']);

        return view('admin.idol-requests.show', compact('registrationRequest', 'groups', 'approvalRewardAmount'));
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
            $applicantIdx = DB::transaction(function () use ($request, $idx, $admin, $replacementPath, &$originalImagePath): int {
            $registrationRequest = DB::table('idol_registration_requests')
                ->where('idx', $idx)
                ->lockForUpdate()
                ->first();
            abort_if($registrationRequest === null, 404);
            $this->ensurePending($registrationRequest);
            $originalImagePath = $registrationRequest->image_path;
            $finalImagePath = $replacementPath ?? $registrationRequest->image_path;

            $values = $this->validatedReviewValues($request, $registrationRequest);
            $this->ensureNoDuplicate($registrationRequest, $values);
            $this->validateGroup($registrationRequest, $values['group_idx']);

            // The request row keeps the final reviewed profile, while the approved entity
            // receives the same public fields and remains linked by approved_idol_idx.
            $idolIdx = DB::table('idols')->insertGetId([
                'type' => $registrationRequest->type,
                'gender' => $registrationRequest->gender,
                'name' => $values['name'],
                'english_name' => $values['english_name'],
                'agency' => $values['agency'],
                'debut_date' => $values['debut_date'],
                'birth_date' => $values['birth_date'],
                'nationality' => $values['nationality'],
                'height_cm' => $values['height_cm'],
                'weight_kg' => $values['weight_kg'],
                'blood_type' => $values['blood_type'],
                'position' => $values['position'],
                'sns' => $values['sns'],
                'image_path' => $finalImagePath,
                'content' => $values['content'],
                'status' => 'active',
                'view_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($registrationRequest->type === 'individual' && $values['group_idx'] !== null) {
                DB::table('idol_group_members')->insert([
                    'individual_idx' => $idolIdx,
                    'group_idx' => $values['group_idx'],
                    'created_at' => now(),
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
            return (int) $registrationRequest->member_idx;
        });
        } catch (\Throwable $exception) {
            ManagedImageStorage::deleteIfUnreferenced($replacementPath);
            throw $exception;
        }
        try {
            // The approved Idol/Group stays committed if the separate Heart award fails.
            DB::transaction(fn () => app(HeartRewardManager::class)->award(
                $applicantIdx, 'idol_registration_approved', 'idol_registration_approved:'.$idx,
                ['actor_member_idx' => (int) $admin->idx, 'memo' => 'Registration approval reward']
            ));
        } catch (\Throwable $exception) {
            report($exception);
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
        $this->validateGroup($registrationRequest, $values['group_idx']);
        $replacementPath = $request->hasFile('profile_image')
            ? ManagedImageStorage::store($request->file('profile_image'), 'idol-registration-requests') : null;
        $admin = $request->attributes->get('currentMember');
        try {
            DB::transaction(function () use ($idx,$registrationRequest,$values,$replacementPath,$admin): void {
                $locked = DB::table('idol_registration_requests')->where('idx',$idx)->lockForUpdate()->first();
                abort_if($locked === null,404); $this->ensurePending($locked);
                DB::table('idol_registration_requests')->where('idx',$idx)->update($values + [
                    'image_path'=>$replacementPath ?? $locked->image_path, 'updated_at'=>now(),
                ]);
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

    private function validatedReviewValues(Request $request, object $registrationRequest): array
    {
        $isIndividual = $registrationRequest->type === 'individual';
        $rules = [
            'name' => ['required', 'string', 'max:200'],
            'english_name' => ['required', 'string', 'max:200'],
            'agency' => ['nullable', 'string', 'max:200'],
            'debut_date' => ['nullable', 'date', 'before_or_equal:today'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'sns' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string', 'max:1000'],
            'group_idx' => $isIndividual ? ['nullable', 'integer'] : ['nullable'],
            'birth_date' => $isIndividual ? ['nullable', 'date', 'before_or_equal:today'] : ['nullable'],
            'height_cm' => $isIndividual ? ['nullable', 'numeric', 'between:50,300'] : ['nullable'],
            'weight_kg' => $isIndividual ? ['nullable', 'numeric', 'between:10,500'] : ['nullable'],
            'blood_type' => $isIndividual ? ['nullable', 'in:A,B,AB,O'] : ['nullable'],
            'position' => $isIndividual ? ['nullable', 'string', 'max:200'] : ['nullable'],
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
        ];
        $attributes = [
            'name' => '이름', 'english_name' => '영문 이름', 'agency' => '소속사',
            'debut_date' => '데뷔일', 'nationality' => $isIndividual ? '국적' : '국가',
            'sns' => 'SNS', 'content' => '짧은 소개', 'group_idx' => '그룹',
            'birth_date' => '생년월일', 'height_cm' => '키', 'weight_kg' => '몸무게',
            'blood_type' => '혈액형', 'position' => '포지션',
        ];
        $values = Validator::make($request->all(), $rules, $messages, $attributes)->validate();

        foreach (['agency', 'debut_date', 'birth_date', 'nationality', 'height_cm', 'weight_kg', 'blood_type', 'position', 'sns', 'group_idx'] as $field) {
            $values[$field] = $values[$field] ?? null;
        }

        foreach (['agency', 'nationality', 'sns', 'position'] as $field) {
            $values[$field] = isset($values[$field]) && trim((string) $values[$field]) !== ''
                ? trim((string) $values[$field])
                : null;
        }
        $values['name'] = trim($values['name']);
        $values['english_name'] = trim($values['english_name']);
        $values['content'] = trim($values['content']);
        $values['group_idx'] = $isIndividual && !empty($values['group_idx']) ? (int) $values['group_idx'] : null;

        if (!$isIndividual) {
            foreach (['birth_date', 'height_cm', 'weight_kg', 'blood_type', 'position'] as $field) {
                $values[$field] = null;
            }
        }

        return $values;
    }

    private function ensurePending(object $registrationRequest): void
    {
        if ($registrationRequest->status !== 'pending') {
            throw ValidationException::withMessages(['status' => '이미 처리된 등록 신청입니다.']);
        }
    }

    private function ensureNoDuplicate(object $registrationRequest, array $values): void
    {
        $duplicate = DB::table('idols')
            ->where('type', $registrationRequest->type)
            ->where('gender', $registrationRequest->gender)
            ->where('name', $values['name'])
            ->where('english_name', $values['english_name'])
            ->lockForUpdate()
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => '같은 유형의 동일한 이름과 영문 이름을 가진 아이돌 또는 그룹이 이미 등록되어 있습니다.',
            ]);
        }
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
