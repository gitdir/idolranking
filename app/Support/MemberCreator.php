<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MemberCreator
{
    public function __construct(private readonly MemberCodeGenerator $codes)
    {
    }

    public function create(array $attributes): int
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            try {
                return DB::table('members')->insertGetId([
                    ...$attributes,
                    'member_code' => $this->codes->generate(),
                ]);
            } catch (QueryException $exception) {
                if (! $this->isMemberCodeCollision($exception)) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Unable to allocate a unique member code.');
    }

    private function isMemberCodeCollision(QueryException $exception): bool
    {
        $message = strtolower((string) ($exception->errorInfo[2] ?? ''));

        return str_contains($message, 'uq_members_member_code')
            || str_contains($message, 'members.member_code');
    }
}
