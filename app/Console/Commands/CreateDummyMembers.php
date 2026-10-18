<?php

namespace App\Console\Commands;

use App\Rules\AllowedNickname;
use App\Support\AdminActivityLogger;
use App\Support\MemberCreator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class CreateDummyMembers extends Command
{
    private const MEMBER_COUNT = 50;

    protected $signature = 'idolranking:create-dummy-members
        {--batch-id= : Stable one-time identifier for this batch}
        {--admin-id= : Active administrator member ID recorded as creator}
        {--dry-run : Preview without creating accounts}
        {--confirm : Explicitly confirm creation of all 50 accounts}';

    protected $description = 'Create 50 audited Korean dummy member accounts';

    public function handle(): int
    {
        $batchId = strtolower(trim((string) $this->option('batch-id')));
        if (! preg_match('/^[a-z0-9][a-z0-9_-]{2,63}$/', $batchId)) {
            $this->error('--batch-id must be 3-64 characters using letters, numbers, hyphens, or underscores.');

            return self::FAILURE;
        }

        $adminIdx = filter_var($this->option('admin-id'), FILTER_VALIDATE_INT);
        $admin = $adminIdx ? DB::table('members')->where('idx', $adminIdx)
            ->where('member_level', 10)->where('is_dummy', 0)->where('status', 'active')
            ->whereNull('deleted_at')->first(['idx']) : null;
        if ($admin === null) {
            $this->error('--admin-id must identify an active, non-dummy administrator.');

            return self::FAILURE;
        }

        $batchReason = 'dummy_batch:'.$batchId;
        if ($this->batchWasCreated($batchReason)) {
            $this->error('This batch identifier has already been used. Choose a new batch ID.');

            return self::FAILURE;
        }

        $accountNames = $this->accountNames($batchId);
        if (count($accountNames) !== self::MEMBER_COUNT) {
            $this->error('Could not generate 50 available Korean nicknames. No accounts were created.');

            return self::FAILURE;
        }

        $batchHash = substr(hash('sha256', $batchId), 0, 16);
        $emails = [];
        foreach (range(1, self::MEMBER_COUNT) as $number) {
            $emails[] = sprintf('idolranking.dummy.%s.%02d@gmail.com', $batchHash, $number);
        }

        if (DB::table('members')->whereIn('email', $emails)->exists()) {
            $this->error('One or more generated email addresses already exist. No accounts were created.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('미리보기: 한국 더미 회원 50개를 생성할 수 있습니다.');
            foreach ($accountNames as $index => $nickname) {
                $this->line(sprintf('%02d. %s — %s', $index + 1, $nickname, $emails[$index]));
            }
            $this->comment('실제 생성 시 --confirm 옵션이 필요합니다.');

            return self::SUCCESS;
        }

        if (! $this->option('confirm')) {
            $this->error('계정 생성을 확인하려면 --confirm 옵션을 명시해 주세요.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($admin, $accountNames, $emails, $batchReason, $batchId): void {
                // The deterministic email addresses also serialize concurrent retries of this batch
                // through the members.email unique key; keep the audit marker in the same transaction.
                if ($this->batchWasCreated($batchReason)
                    || DB::table('members')->whereIn('email', $emails)->exists()) {
                    throw new RuntimeException('This batch has already been created or its email addresses are in use.');
                }

                $createdAt = now();
                $firstMemberIdx = null;
                foreach ($accountNames as $index => $nickname) {
                    $password = 'A'.Str::random(46).'7';
                    $memberIdx = app(MemberCreator::class)->create([
                        'email' => $emails[$index],
                        'password_hash' => Hash::make($password),
                        'nickname' => $nickname,
                        'member_level' => 1,
                        'is_dummy' => 1,
                        'status' => 'active',
                        'country_code' => 'KR',
                        'email_verified_at' => $createdAt,
                        'created_by_member_idx' => $admin->idx,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);

                    $firstMemberIdx ??= $memberIdx;
                }

                AdminActivityLogger::record(
                    (int) $admin->idx,
                    'dummy_member_batch_created',
                    'dummy_member',
                    (int) $firstMemberIdx,
                    $batchReason,
                    '한국 더미 회원 50개 일괄 생성 (배치 ID: '.$batchId.')'
                );
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('한국 더미 회원 50개를 생성했습니다. 비밀번호는 무작위 생성 후 해시되어 저장되며 출력되지 않습니다.');

        return self::SUCCESS;
    }

    private function batchWasCreated(string $batchReason): bool
    {
        return DB::table('admin_activity_logs')
            ->where('action', 'dummy_member_batch_created')
            ->where('reason', $batchReason)
            ->exists();
    }

    /** @return list<string> */
    private function accountNames(string $batchId): array
    {
        $prefixes = ['별빛', '달빛', '은하', '햇살', '노래', '무대', '응원', '보라', '분홍', '반짝', '푸른', '새벽', '온기', '설렘', '반달'];
        $suffixes = ['팬', '요정', '친구', '구름', '토끼', '별', '하늘', '마음', '산책', '노트', '정원', '리본', '멜로디', '별님', '응원단', '빛', '여행', '꽃', '반짝이', '하루'];
        $candidates = [];
        foreach ($prefixes as $prefix) {
            foreach ($suffixes as $suffix) {
                $candidates[] = $prefix.$suffix;
            }
        }
        usort($candidates, fn (string $left, string $right): int => strcmp(
            hash('sha256', $batchId.'|'.$left), hash('sha256', $batchId.'|'.$right)
        ));

        $existing = DB::table('members')->pluck('nickname')->filter()
            ->map(fn (string $nickname): string => mb_strtolower($nickname))->flip();
        $names = [];
        foreach ($candidates as $nickname) {
            $normalized = mb_strtolower($nickname);
            if (isset($existing[$normalized])) {
                continue;
            }

            if (! Validator::make(['nickname' => $nickname], [
                'nickname' => ['required', 'string', 'min:2', 'max:20', new AllowedNickname],
            ])->passes()) {
                continue;
            }

            $names[] = $nickname;
            $existing[$normalized] = true;
            if (count($names) === self::MEMBER_COUNT) {
                break;
            }
        }

        return $names;
    }
}
