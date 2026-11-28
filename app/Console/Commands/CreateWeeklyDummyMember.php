<?php

namespace App\Console\Commands;

use App\Support\Countries;
use App\Support\MemberCreator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateWeeklyDummyMember extends Command
{
    protected $signature = 'dummy-members:create-weekly';

    protected $description = 'Create one operator-managed dummy member per Seoul calendar week';

    public function handle(): int
    {
        if (DB::table('site_settings')->where('setting_key', 'dummy_auto_create_enabled')->value('setting_value') !== '1') {
            return self::SUCCESS;
        }

        $week = now('Asia/Seoul')->startOfWeek()->toDateString();
        $countryCodes = Countries::codes();
        if ($countryCodes === []) {
            $this->error('No supported countries are available.');
            return self::FAILURE;
        }

        // The unique week key serializes concurrent runs. A failed creation rolls back the key too.
        DB::transaction(function () use ($week, $countryCodes): void {
            $claimed = DB::table('weekly_dummy_member_creations')->insertOrIgnore([
                'week_start_date' => $week,
                'created_at' => now(),
            ]);
            if ($claimed !== 1) {
                return;
            }

            $token = str_replace('-', '', $week);
            $nickname = '운영더미'.$token;
            for ($attempt = 0; DB::table('members')->where('nickname', $nickname)->exists(); $attempt++) {
                if ($attempt === 10) {
                    throw new \RuntimeException('Unable to allocate a unique dummy nickname.');
                }
                $nickname = '운영더미'.$token.Str::upper(Str::random(4));
            }
            $memberIdx = app(MemberCreator::class)->create([
                'email' => 'weekly-'.$token.'@dummy.idolranking.invalid',
                'password_hash' => Hash::make('A'.Str::random(46).'7'),
                'nickname' => $nickname,
                'member_level' => 1,
                'is_dummy' => 1,
                'status' => 'active',
                'country_code' => $countryCodes[random_int(0, count($countryCodes) - 1)],
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('weekly_dummy_member_creations')->where('week_start_date', $week)
                ->update(['member_idx' => $memberIdx]);
        });

        return self::SUCCESS;
    }
}
