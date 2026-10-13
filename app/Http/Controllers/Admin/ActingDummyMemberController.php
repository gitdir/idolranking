<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveActivityMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActingDummyMemberController extends Controller
{
    public function update(Request $request)
    {
        abort_unless((int) $request->attributes->get('currentMember')?->member_level === 10, 403);

        $selectedIdx = $request->input('dummy_member_idx');
        if ($selectedIdx === null || $selectedIdx === '') {
            $request->session()->forget(ResolveActivityMember::SESSION_KEY);
            return redirect()->route('home')->with('admin_success', 'Dummy activity selection cleared.');
        }

        abort_unless(is_scalar($selectedIdx) && ctype_digit((string) $selectedIdx), 422);
        $dummy = DB::table('members')->where('idx', $selectedIdx)
            ->where('member_level', 1)->where('is_dummy', 1)
            ->where('status', 'active')->whereNull('deleted_at')
            ->first(['idx']);
        abort_if($dummy === null, 422, 'Select an active dummy member.');

        $request->session()->put(ResolveActivityMember::SESSION_KEY, (int) $dummy->idx);

        return redirect()->route('home')->with('admin_success', 'Dummy member activity selected.');
    }
}
