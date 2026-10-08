<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberAccessLogController extends Controller
{
    public function __invoke(Request $request)
    {
        $loginState = in_array($request->query('login_state'), ['member', 'guest', 'bot'], true)
            ? $request->query('login_state')
            : 'all';
        $memberSearch = trim($request->string('member')->toString());
        $ipSearch = trim($request->string('ip')->toString());
        $startDate = $this->validDate($request->query('start_date'));
        $endDate = $this->validDate($request->query('end_date'));

        // Request sort values never reach ORDER BY without this whitelist.
        $allowedSorts = [
            'created_at' => 'log.created_at',
            'login_state' => 'log.member_idx',
            'member' => 'member.nickname',
            'ip' => 'log.ip_address',
        ];
        $sort = array_key_exists($request->query('sort'), $allowedSorts) ? $request->query('sort') : 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $logs = DB::table('member_access_logs as log')
            ->leftJoin('members as member', 'member.idx', '=', 'log.member_idx')
            ->when($loginState === 'member', fn ($query) => $query->where('log.is_bot', 0)->whereNotNull('log.member_idx'))
            ->when($loginState === 'guest', fn ($query) => $query->where('log.is_bot', 0)->whereNull('log.member_idx'))
            ->when($loginState === 'bot', fn ($query) => $query->where('log.is_bot', 1))
            ->when($memberSearch !== '', fn ($query) => $query->where(function ($inner) use ($memberSearch) {
                $inner->where('member.nickname', 'like', '%'.$memberSearch.'%')
                    ->orWhere('member.email', 'like', '%'.$memberSearch.'%');
            }))
            ->when($ipSearch !== '', fn ($query) => $query->where('log.ip_address', 'like', '%'.$ipSearch.'%'))
            ->when($startDate, fn ($query) => $query->whereDate('log.created_at', '>=', $startDate))
            ->when($endDate, fn ($query) => $query->whereDate('log.created_at', '<=', $endDate))
            ->select('log.*', 'member.nickname', 'member.email')
            ->orderBy($allowedSorts[$sort], $direction)
            ->orderByDesc('log.idx')
            ->paginate(50)
            ->withQueryString();

        return view('admin.member-access-logs.index', compact(
            'logs', 'loginState', 'memberSearch', 'ipSearch', 'startDate', 'endDate', 'sort', 'direction'
        ));
    }

    private function validDate(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }
}
