<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request)
    {
        $allowed=['created_at'=>'log.created_at','admin'=>'admin.nickname','action'=>'log.action'];
        $sort=array_key_exists($request->query('sort'),$allowed)?$request->query('sort'):'created_at'; $direction=$request->query('direction')==='asc'?'asc':'desc';
        $logs = DB::table('admin_activity_logs as log')
            ->join('members as admin', 'admin.idx', '=', 'log.actor_member_idx')
            ->orderBy($allowed[$sort],$direction)->orderByDesc('log.idx')
            ->select('log.*', 'admin.nickname as admin_nickname')->paginate(30);

        return view('admin.activity-logs.index', compact('logs','sort','direction'));
    }
}
