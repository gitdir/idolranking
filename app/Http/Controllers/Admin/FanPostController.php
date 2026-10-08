<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FanPostController extends Controller
{
    public function __invoke(Request $request)
    {
        $allowed=['title'=>'p.title','views'=>'p.view_count','likes'=>'likes','comments'=>'comments','created_at'=>'p.created_at','status'=>'p.status'];
        $sort=array_key_exists($request->query('sort'),$allowed)?$request->query('sort'):'created_at'; $direction=$request->query('direction')==='asc'?'asc':'desc';
        $search=trim($request->string('q')->toString());
        $posts=DB::table('fan_posts as p')->join('members as m','m.idx','=','p.member_idx')->whereNull('p.deleted_at')
            ->when($search!=='',fn($q)=>$q->where(fn($n)=>$n->where('p.title','like','%'.$search.'%')->orWhere('m.nickname','like','%'.$search.'%')))
            ->select('p.idx','p.title','p.view_count','p.status','p.created_at','m.nickname')
            ->selectSub(DB::table('fan_post_likes as l')->selectRaw('COUNT(*)')->whereColumn('l.fan_post_idx','p.idx'),'likes')
            ->selectSub(DB::table('fan_post_comments as c')->selectRaw('COUNT(*)')->whereColumn('c.fan_post_idx','p.idx')->whereNull('c.deleted_at'),'comments')
            ->orderBy($allowed[$sort],$direction)->orderByDesc('p.idx')->paginate(20)->withQueryString();
        return view('admin.fan-posts.index',compact('posts','search','sort','direction'));
    }
}
