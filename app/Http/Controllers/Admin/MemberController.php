<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Rules\AllowedNickname;
use App\Support\AdminActivityLogger;
use App\Support\Countries;
use App\Support\MemberCreator;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $search = trim($request->string('q')->toString());
        $dummyOnly = $request->query('dummy') === '1';
        $allowed=['nickname'=>'m.nickname','created_at'=>'m.created_at','last_login'=>'m.last_login_at','hearts'=>'m.heart_balance','status'=>'m.status'];
        $sort=array_key_exists($request->query('sort'),$allowed)?$request->query('sort'):'created_at'; $direction=$request->query('direction')==='asc'?'asc':'desc';
        $members = DB::table('members as m')->whereNull('m.deleted_at')
            ->when($dummyOnly, fn ($q) => $q->where('m.is_dummy', 1))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('m.nickname', 'like', '%'.$search.'%');
                    if (preg_match('/^[\x00-\x7F]+$/', $search)) {
                        $inner->orWhere('m.email', 'like', '%'.$search.'%')
                            ->orWhere('m.member_code', 'like', '%'.$search.'%');
                    }
                });
            })
            ->select('m.*')->selectSub(DB::table('fan_posts as p')->selectRaw('COUNT(*)')->whereColumn('p.member_idx','m.idx')->whereNull('p.deleted_at'),'fan_post_count')
            ->orderBy($allowed[$sort],$direction)->orderByDesc('m.idx')->paginate(20)->withQueryString();

        return view('admin.members.index', compact('members', 'search','sort','direction','dummyOnly'));
    }

    public function createDummy()
    {
        return view('admin.members.dummy-create', [
            'countries' => Countries::records(),
            'favoriteIdols' => DB::table('idols')->where('status', 'active')->orderBy('name')->get(['idx', 'name', 'type', 'status']),
        ]);
    }

    public function storeDummy(Request $request)
    {
        $email = mb_strtolower(trim((string) $request->input('email')));
        $nickname = trim((string) $request->input('nickname'));
        $request->merge(['email' => $email, 'nickname' => $nickname]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254', Rule::unique('members', 'email')],
            'nickname' => ['bail', 'required', 'string', 'min:2', 'max:20', new AllowedNickname, Rule::unique('members', 'nickname')],
            'country_code' => ['required', 'string', Rule::in(Countries::codes())],
            ...$this->favoriteRules(),
            'password' => [
                'required', 'string', 'confirmed', 'min:8', 'max:64', 'regex:/[A-Za-z]/', 'regex:/[0-9]/',
                function ($attribute, $value, $fail) {
                    if (is_string($value) && config('hashing.driver', 'bcrypt') === 'bcrypt'
                        && (strlen($value) > 72 || str_contains($value, "\0"))) {
                        $fail('비밀번호는 72바이트 이하여야 하며 null 문자를 포함할 수 없습니다.');
                    }
                },
            ],
        ], [
            'email.string' => '이메일은 문자열이어야 합니다.',
            'email.required' => '이메일을 입력해 주세요.',
            'email.email' => '올바른 이메일 주소를 입력해 주세요.',
            'email.max' => '이메일은 254자 이하여야 합니다.',
            'email.unique' => '이미 사용 중인 이메일입니다.',
            'nickname.required' => '닉네임을 입력해 주세요.',
            'nickname.string' => '닉네임 형식이 올바르지 않습니다.',
            'nickname.min' => '닉네임은 2~20자로 입력해 주세요.',
            'nickname.max' => '닉네임은 2~20자로 입력해 주세요.',
            'nickname.unique' => '이미 사용 중인 닉네임입니다.',
            'nickname' => '사용할 수 없는 닉네임입니다.',
            'country_code.required' => '국가를 선택해 주세요.',
            'country_code.in' => '올바른 국가를 선택해 주세요.',
            'password.required' => '비밀번호를 입력해 주세요.',
            'password.string' => '비밀번호 형식이 올바르지 않습니다.',
            'password.confirmed' => '비밀번호가 일치하지 않습니다.',
            'password.min' => '비밀번호는 8~64자로 입력해 주세요.',
            'password.max' => '비밀번호는 8~64자로 입력해 주세요.',
            'password.regex' => '비밀번호는 영문자와 숫자를 각각 하나 이상 포함해야 합니다.',
        ]);

        $admin = $request->attributes->get('currentMember');
        $adminIdx = (int) $admin->idx;

        try {
            $memberIdx = DB::transaction(function () use ($validated, $adminIdx): int {
                $memberIdx = app(MemberCreator::class)->create([
                    'email' => $validated['email'],
                    'password_hash' => Hash::make($validated['password']),
                    'nickname' => $validated['nickname'],
                    'member_level' => 1,
                    'is_dummy' => 1,
                    'status' => 'active',
                    'country_code' => $validated['country_code'],
                    'favorite_idol_1_idx' => $validated['favorite_idol_1_idx'] ?? null,
                    'favorite_idol_2_idx' => $validated['favorite_idol_2_idx'] ?? null,
                    'favorite_idol_3_idx' => $validated['favorite_idol_3_idx'] ?? null,
                    'email_verified_at' => now(),
                    'created_by_member_idx' => $adminIdx,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                AdminActivityLogger::record($adminIdx, 'dummy_member_created', 'dummy_member', $memberIdx, null, $validated['nickname']);

                return $memberIdx;
            });
        } catch (QueryException $exception) {
            $errors = [];
            if (DB::table('members')->where('email', $email)->exists()) {
                $errors['email'] = '이미 사용 중인 이메일입니다.';
            }
            if (DB::table('members')->where('nickname', $nickname)->exists()) {
                $errors['nickname'] = '이미 사용 중인 닉네임입니다.';
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            report($exception);
            throw $exception;
        }

        return redirect()->route('admin.members.show', $memberIdx)->with('admin_success', '더미 계정을 생성했습니다.');
    }

    public function show(int $idx)
    {
        $member=DB::table('members')->where('idx',$idx)->whereNull('deleted_at')->first(); abort_if(!$member,404);
        $favoriteIdols = (int) $member->is_dummy === 1
            ? DB::table('idols')->where(function ($query) use ($member): void {
                $query->where('status', 'active')->orWhereIn('idx', array_filter([
                    $member->favorite_idol_1_idx, $member->favorite_idol_2_idx, $member->favorite_idol_3_idx,
                ]));
            })->orderBy('name')->get(['idx', 'name', 'type', 'status'])
            : collect();
        $hearts=DB::table('heart_histories')->where('member_idx',$idx)->latest('created_at')->limit(20)->get();
        $posts=DB::table('fan_posts')->where('member_idx',$idx)->whereNull('deleted_at')->latest('created_at')->limit(20)->get(['idx','title','status','created_at']);
        $logins=DB::table('member_login_histories')->where('member_idx',$idx)->latest('created_at')->limit(20)->get();
        $actions=DB::table('admin_activity_logs as l')->join('members as a','a.idx','=','l.actor_member_idx')->whereIn('l.target_type',['member','dummy_member'])->where('l.target_idx',$idx)->latest('l.created_at')->get(['l.*','a.nickname as admin_nickname']);
        return view('admin.members.show',compact('member','hearts','posts','logins','actions','favoriteIdols'));
    }

    public function updateFavorites(Request $request, int $idx)
    {
        $validated = $request->validate($this->favoriteRules());
        $admin = $request->attributes->get('currentMember');

        DB::transaction(function () use ($idx, $validated, $admin): void {
            $member = DB::table('members')->where('idx', $idx)->whereNull('deleted_at')
                ->lockForUpdate()->first(['idx', 'nickname', 'member_level', 'is_dummy']);
            abort_if($member === null || (int) $member->member_level !== 1 || (int) $member->is_dummy !== 1, 404);

            DB::table('members')->where('idx', $idx)->update([
                'favorite_idol_1_idx' => $validated['favorite_idol_1_idx'] ?? null,
                'favorite_idol_2_idx' => $validated['favorite_idol_2_idx'] ?? null,
                'favorite_idol_3_idx' => $validated['favorite_idol_3_idx'] ?? null,
                'updated_at' => now(),
            ]);
            AdminActivityLogger::record((int) $admin->idx, 'dummy_favorites_changed', 'dummy_member', $idx, null, $member->nickname);
        });

        return back()->with('admin_success', '더미 계정의 최애 아이돌을 저장했습니다.');
    }

    private function favoriteRules(): array
    {
        $idol = fn () => Rule::exists('idols', 'idx')->where('status', 'active');

        return [
            'favorite_idol_1_idx' => ['nullable', 'integer', $idol()],
            'favorite_idol_2_idx' => ['nullable', 'integer', $idol(), 'different:favorite_idol_1_idx'],
            'favorite_idol_3_idx' => ['nullable', 'integer', $idol(), 'different:favorite_idol_1_idx', 'different:favorite_idol_2_idx'],
        ];
    }

    public function updateStatus(Request $request, int $idx)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'reason' => ['required', 'string', 'max:1000'],
        ], ['status.required' => '회원 상태를 선택해 주세요.', 'status.in' => '올바른 회원 상태를 선택해 주세요.', 'reason.required' => '조치 사유를 입력해 주세요.', 'reason.max' => '조치 사유는 1,000자 이하여야 합니다.']);
        $admin = $request->attributes->get('currentMember');
        abort_if((int) $admin->idx === $idx, 422, '자신의 관리자 계정 상태는 변경할 수 없습니다.');

        DB::transaction(function () use ($idx, $validated, $admin) {
            $member = DB::table('members')->where('idx', $idx)->whereNull('deleted_at')->lockForUpdate()->first(['idx', 'member_level', 'status', 'nickname']);
            abort_if($member === null, 404);
            abort_if((int) $member->member_level === 10, 422, '관리자 계정은 이 화면에서 제한할 수 없습니다.');
            DB::table('members')->where('idx', $idx)->update(['status' => $validated['status'], 'updated_at' => now()]);
            $action = $validated['status'] === 'suspended' ? 'member_restricted' : 'member_restored';
            AdminActivityLogger::record((int) $admin->idx, $action, 'member', $idx, $validated['reason'], $member->nickname);
        });

        return back()->with('admin_success', '회원 상태를 변경했습니다.');
    }
}
