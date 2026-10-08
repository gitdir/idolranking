<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RestrictedNicknameController extends Controller
{
    public function index() { return view('admin.restricted-nicknames.index', ['rules' => DB::table('restricted_nicknames')->orderByDesc('idx')->paginate(30)]); }

    public function store(Request $request)
    {
        $data = $request->validate(['word' => ['required','string','max:50'], 'match_type' => ['required', Rule::in(['exact','contains'])]], [], ['word'=>'금지어','match_type'=>'일치 방식']);
        $idx = DB::table('restricted_nicknames')->insertGetId(['word'=>trim($data['word']),'match_type'=>$data['match_type'],'is_enabled'=>1,'created_at'=>now(),'updated_at'=>now()]);
        AdminActivityLogger::record($request->attributes->get('currentMember')->idx, 'restricted_nickname_added', 'restricted_nickname', $idx, null, trim($data['word']));
        return back()->with('admin_success', '닉네임 금지어를 추가했습니다.');
    }

    public function update(Request $request, int $idx)
    {
        $data = $request->validate(['word'=>['required','string','max:50'],'match_type'=>['required',Rule::in(['exact','contains'])],'is_enabled'=>['required','boolean']]);
        abort_unless(DB::table('restricted_nicknames')->where('idx',$idx)->exists(),404);
        DB::table('restricted_nicknames')->where('idx',$idx)->update(['word'=>trim($data['word']),'match_type'=>$data['match_type'],'is_enabled'=>(int)$data['is_enabled'],'updated_at'=>now()]);
        AdminActivityLogger::record($request->attributes->get('currentMember')->idx, 'restricted_nickname_changed', 'restricted_nickname', $idx, null, trim($data['word']));
        return back()->with('admin_success', '닉네임 금지어를 변경했습니다.');
    }

    public function destroy(Request $request, int $idx)
    {
        $rule = DB::table('restricted_nicknames')->where('idx',$idx)->first(); abort_if(!$rule,404);
        DB::table('restricted_nicknames')->where('idx',$idx)->delete();
        AdminActivityLogger::record($request->attributes->get('currentMember')->idx, 'restricted_nickname_removed', 'restricted_nickname', $idx, null, $rule->word);
        return back()->with('admin_success', '닉네임 금지어를 삭제했습니다.');
    }
}
