<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class AdminDummyNickname
{
    public static function matches(?string $memberCode = null, ?string $nickname = null): bool
    {
        if ($memberCode === null && $nickname === null) {
            return false;
        }

        $admin = request()->attributes->get('currentMember');
        if ($admin === null || (int) $admin->member_level !== 10 || (int) $admin->is_dummy !== 0) {
            return false;
        }

        $members = request()->attributes->get('adminDummyNicknameMembers');
        if ($members === null) {
            $members = ['codes' => [], 'nicknames' => []];
            foreach (DB::table('members')->where('is_dummy', 1)->get(['member_code', 'nickname']) as $member) {
                if ($member->member_code !== null) {
                    $members['codes'][$member->member_code] = true;
                }
                if ($member->nickname !== null) {
                    $members['nicknames'][$member->nickname] = true;
                }
            }
            request()->attributes->set('adminDummyNicknameMembers', $members);
        }

        return ($memberCode !== null && isset($members['codes'][$memberCode]))
            || ($nickname !== null && isset($members['nicknames'][$nickname]));
    }
}
