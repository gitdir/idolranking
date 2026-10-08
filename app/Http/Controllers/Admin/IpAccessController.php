<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IpAccessController extends Controller
{
    public function index(Request $request)
    {
        $allowed=['ip'=>'b.ip_address','created_at'=>'b.created_at','expires_at'=>'b.expires_at','status'=>'b.is_active'];
        $sort=array_key_exists($request->query('sort'),$allowed)?$request->query('sort'):'created_at'; $direction=$request->query('direction')==='asc'?'asc':'desc';
        $blocks=DB::table('blocked_ips as b')->join('members as m','m.idx','=','b.created_by_member_idx')->select('b.*','m.nickname as admin_nickname')->orderBy($allowed[$sort],$direction)->orderByDesc('b.idx')->paginate(30)->withQueryString();
        $prefillIp = $request->query('ip_address');
        $prefillIp = is_string($prefillIp) && filter_var($prefillIp, FILTER_VALIDATE_IP) ? $prefillIp : '';
        return view('admin.ip-access.index',compact('blocks','sort','direction','prefillIp'));
    }
    public function store(Request $request)
    {
        $data=$request->validate(['ip_address'=>['required','ip'],'reason'=>['required','string','max:1000'],'expires_at'=>['nullable','date','after:now']]);
        abort_if($data['ip_address']===$request->ip(),422,'현재 관리자 IP는 차단할 수 없습니다.');
        $idx=DB::table('blocked_ips')->insertGetId($data+['created_by_member_idx'=>$request->attributes->get('currentMember')->idx,'is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
        AdminActivityLogger::record($request->attributes->get('currentMember')->idx,'ip_blocked','blocked_ip',$idx,$data['reason'],$data['ip_address']);
        return back()->with('admin_success','IP 주소를 차단했습니다.');
    }
    public function toggle(Request $request,int $idx)
    {
        $block=DB::table('blocked_ips')->where('idx',$idx)->first(); abort_if(!$block,404);
        $active=!$block->is_active; abort_if($active && $block->ip_address===$request->ip(),422,'현재 관리자 IP는 차단할 수 없습니다.');
        DB::table('blocked_ips')->where('idx',$idx)->update(['is_active'=>(int)$active,'updated_at'=>now()]);
        AdminActivityLogger::record($request->attributes->get('currentMember')->idx,$active?'ip_blocked':'ip_unblocked','blocked_ip',$idx,$block->reason,$block->ip_address);
        return back()->with('admin_success',$active?'IP 차단을 활성화했습니다.':'IP 차단을 해제했습니다.');
    }
}
