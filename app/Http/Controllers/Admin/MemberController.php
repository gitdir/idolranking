<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $search = trim($request->string('q')->toString());
        $allowed=['nickname'=>'m.nickname','created_at'=>'m.created_at','last_login'=>'m.last_login_at','hearts'=>'m.heart_balance','status'=>'m.status'];
        $sort=array_key_exists($request->query('sort'),$allowed)?$request->query('sort'):'created_at'; $direction=$request->query('direction')==='asc'?'asc':'desc';
        $members = DB::table('members as m')->whereNull('m.deleted_at')
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('m.nickname', 'like', '%'.$search.'%')->orWhere('m.email', 'like', '%'.$search.'%')->orWhere('m.member_code', 'like', '%'.$search.'%')))
            ->select('m.*')->selectSub(DB::table('fan_posts as p')->selectRaw('COUNT(*)')->whereColumn('p.member_idx','m.idx')->whereNull('p.deleted_at'),'fan_post_count')
            ->orderBy($allowed[$sort],$direction)->orderByDesc('m.idx')->paginate(20)->withQueryString();

        return view('admin.members.index', compact('members', 'search','sort','direction'));
    }

    public function show(int $idx)
    {
        $member=DB::table('members')->where('idx',$idx)->whereNull('deleted_at')->first(); abort_if(!$member,404);
        $hearts=DB::table('heart_histories')->where('member_idx',$idx)->latest('created_at')->limit(20)->get();
        $posts=DB::table('fan_posts')->where('member_idx',$idx)->whereNull('deleted_at')->latest('created_at')->limit(20)->get(['idx','title','status','created_at']);
        $logins=DB::table('member_login_histories')->where('member_idx',$idx)->latest('created_at')->limit(20)->get();
        $actions=DB::table('admin_activity_logs as l')->join('members as a','a.idx','=','l.actor_member_idx')->where('l.target_type','member')->where('l.target_idx',$idx)->latest('l.created_at')->get(['l.*','a.nickname as admin_nickname']);
        return view('admin.members.show',compact('member','hearts','posts','logins','actions'));
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
