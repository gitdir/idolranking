@extends('layouts.admin')

@section('title', '회원 접속 로그 | IDOL RANKING 관리자')

@section('content')
    <header class="admin-page-heading">
        <div>
            <h1>회원 접속 로그</h1>
            <p>최근 사이트 접속 기록에서 로그인 회원과 비로그인 방문자를 확인합니다.</p>
        </div>
    </header>

    <form class="admin-request-filters admin-access-log-filters" method="get">
        <label>
            <span class="admin-sr-only">로그인 구분</span>
            <select name="login_state">
                <option value="all" @selected($loginState === 'all')>전체</option>
                <option value="member" @selected($loginState === 'member')>로그인 회원</option>
                <option value="guest" @selected($loginState === 'guest')>비로그인 방문자</option>
                <option value="bot" @selected($loginState === 'bot')>봇</option>
            </select>
        </label>
        <label><span class="admin-sr-only">회원 검색</span><input name="member" value="{{ $memberSearch }}" placeholder="닉네임 또는 이메일"></label>
        <label><span class="admin-sr-only">IP 검색</span><input name="ip" value="{{ $ipSearch }}" placeholder="IP 검색"></label>
        <label><span class="admin-sr-only">시작일</span><input type="date" name="start_date" value="{{ $startDate }}" aria-label="시작일"></label>
        <label><span class="admin-sr-only">종료일</span><input type="date" name="end_date" value="{{ $endDate }}" aria-label="종료일"></label>
        <button type="submit">검색</button>
    </form>

    <section class="admin-request-table-card">
        <div class="admin-table-scroll">
            <table class="admin-management-table admin-access-log-table">
                <thead><tr>
                    <th><x-admin-sort-link column="created_at" label="접속 일시" :$sort :$direction /></th>
                    <th><x-admin-sort-link column="login_state" label="로그인 구분" :$sort :$direction /></th>
                    <th><x-admin-sort-link column="member" label="회원" :$sort :$direction /></th>
                    <th>이메일</th>
                    <th><x-admin-sort-link column="ip" label="IP" :$sort :$direction /></th>
                    <th>접속 페이지</th>
                    <th>브라우저 정보</th>
                </tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td><time datetime="{{ $log->created_at }}">{{ \Illuminate\Support\Carbon::parse($log->created_at)->format('Y-m-d H:i:s') }}</time></td>
                        <td><span class="admin-access-badge {{ $log->is_bot ? 'is-bot' : ($log->member_idx ? 'is-member' : 'is-guest') }}">{{ $log->is_bot ? '봇' : ($log->member_idx ? '로그인 회원' : '비로그인 방문자') }}</span></td>
                        <td>@if($log->member_idx)<a class="admin-text-link" href="{{ route('admin.members.show', $log->member_idx) }}">{{ $log->nickname ?: '탈퇴/비활성 회원' }}</a>@else - @endif</td>
                        <td>{{ $log->member_idx ? ($log->email ?: '-') : '-' }}</td>
                        <td>@if($log->ip_address)<a class="admin-ip-link" href="{{ route('admin.ip-access.index', ['ip_address' => $log->ip_address]) }}" title="IP 접근 제한 화면에서 확인">{{ $log->ip_address }}</a>@else - @endif</td>
                        <td><code>{{ $log->request_path }}</code></td>
                        <td><span class="admin-user-agent" title="{{ $log->user_agent }}">{{ $log->user_agent ?: '-' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="admin-table-empty">조회된 접속 기록이 없습니다.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            @php
                $currentPage = $logs->currentPage();
                $lastPage = $logs->lastPage();
                $windowStart = max(1, $currentPage - 2);
                $windowEnd = min($lastPage, $currentPage + 2);
                $pageNumbers = collect([1, $lastPage])
                    ->merge(range($windowStart, $windowEnd))
                    ->unique()
                    ->sort()
                    ->values();
                $previousPageNumber = null;
            @endphp

            <footer class="admin-table-footer admin-access-pagination">
                <nav aria-label="회원 접속 로그 페이지 이동">
                    @if ($logs->onFirstPage())
                        <span aria-disabled="true" aria-label="이전 페이지">&lsaquo;</span>
                    @else
                        <a href="{{ $logs->previousPageUrl() }}" rel="prev" aria-label="이전 페이지">&lsaquo;</a>
                    @endif

                    @foreach ($pageNumbers as $pageNumber)
                        @if ($previousPageNumber !== null && $pageNumber > $previousPageNumber + 1)
                            <span class="admin-pagination-ellipsis" aria-hidden="true">&hellip;</span>
                        @endif

                        @if ($pageNumber === $currentPage)
                            <strong aria-current="page">{{ $pageNumber }}</strong>
                        @else
                            <a href="{{ $logs->url($pageNumber) }}" aria-label="{{ $pageNumber }}페이지로 이동">{{ $pageNumber }}</a>
                        @endif

                        @php($previousPageNumber = $pageNumber)
                    @endforeach

                    @if ($logs->hasMorePages())
                        <a href="{{ $logs->nextPageUrl() }}" rel="next" aria-label="다음 페이지">&rsaquo;</a>
                    @else
                        <span aria-disabled="true" aria-label="다음 페이지">&rsaquo;</span>
                    @endif
                </nav>
            </footer>
        @endif
    </section>
@endsection
