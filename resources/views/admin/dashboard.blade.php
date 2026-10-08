@extends('layouts.admin')

@section('title', '대시보드 | IDOL RANKING 관리자')

@section('content')
    <div class="admin-page-heading admin-dashboard-heading">
        <div><h1>대시보드</h1><p>서비스 활동과 운영 현황을 확인합니다.</p></div>
        <form class="admin-period-filter" action="{{ route('admin.index') }}" method="get">
            <label><span>시작일</span><input type="date" name="start_date" value="{{ $startDate }}" required></label>
            <label><span>종료일</span><input type="date" name="end_date" value="{{ $endDate }}" required></label>
            <button type="submit">검색</button>
        </form>
    </div>

    <p class="admin-period-label"><strong>기간 통계</strong> {{ $periodLabel }}</p>
    @php
        $metricCards = [
            ['PV', $metrics['page_views'], 'visitor', '기간 내 의미 있는 공개 페이지 조회 수입니다.'],
            ['UV', $metrics['unique_visitors'], 'visitor', '선택 기간 전체의 중복을 제거한 방문자 수입니다.'],
            ['신규 회원', $metrics['new_members'], 'member', null],
            ['팬포스트', $metrics['fan_posts'], 'post', null],
            ['댓글', $metrics['comments'], 'comment', null],
            ['투표', $metrics['votes'], 'vote', null],
            ['획득 하트', $metrics['hearts_earned'], 'earned', null],
            ['사용 하트', $metrics['hearts_used'], 'used', null],
        ];
    @endphp
    <section class="admin-metric-grid" aria-label="기간별 요약">
        @foreach ($metricCards as [$label, $value, $tone, $note])
            <article class="admin-metric-card admin-metric-{{ $tone }}">
                <span class="admin-metric-icon" aria-hidden="true">
                    @if (in_array($tone, ['earned', 'used'], true)) ♥
                    @elseif ($tone === 'member') ◉
                    @elseif ($tone === 'post') ▤
                    @elseif ($tone === 'comment') ◌
                    @elseif ($tone === 'vote') ✓
                    @else ◇
                    @endif
                </span>
                <div><p>{{ $label }}</p><strong>{{ $value === null ? '집계 안 함' : number_format($value) }}</strong>@if ($note)<small>{{ $note }}</small>@endif</div>
            </article>
        @endforeach
    </section>

    <section class="admin-dashboard-card admin-visitor-chart-card">
        <header>
            <div>
                <h2>일별 방문 추이</h2>
                <p>{{ $periodLabel }}의 일별 PV와 일별 UV입니다.</p>
            </div>
        </header>
        <div class="admin-visitor-chart-wrap">
            <canvas data-admin-visitor-chart role="img" aria-label="선택한 기간의 일별 PV 및 UV 선 차트">일별 방문 추이 차트</canvas>
        </div>
        <script type="application/json" data-admin-visitor-chart-data>@json($trafficDaily)</script>
        <p class="admin-data-source">일별 UV는 같은 방문자를 하루에 한 번만 계산합니다. 기간 UV는 일별 UV 합계가 아닌 선택 기간 전체의 중복 제거 값입니다. 방문자 식별 데이터 도입 이전 기록은 기간 UV에서 제외됩니다.</p>
    </section>

    <div class="admin-dashboard-columns">
        <section class="admin-dashboard-card admin-heart-overview">
            <header><div><h2>하트 현황</h2><p>{{ $periodLabel }}의 하트 잔액과 이용 내역입니다.</p></div><a href="{{ route('admin.hearts.index') }}">하트 관리</a></header>
            <div class="admin-heart-summary">
                <div><span>획득 하트</span><strong>{{ number_format($metrics['hearts_earned']) }}</strong></div>
                <div><span>사용 하트</span><strong>{{ number_format($metrics['hearts_used']) }}</strong></div>
                <div><span>현재 전체 하트 잔액</span><strong>{{ number_format($heartSummary['current_balance']) }}</strong></div>
                <div><span>하트 획득 회원</span><strong>{{ number_format($heartSummary['members_earned']) }}</strong></div>
                <div><span>하트 사용 회원</span><strong>{{ number_format($heartSummary['members_used']) }}</strong></div>
            </div>
            <p class="admin-data-source">기존 하트 이용 내역과 현재 회원 잔액을 기준으로 집계합니다.</p>
        </section>

        <section class="admin-dashboard-card admin-attention-card">
            <header><div><h2>확인 필요</h2><p>현재 처리가 필요한 운영 항목입니다.</p></div></header>
            <div class="admin-attention-list">
                <a href="{{ route('admin.idol-requests.index') }}"><span>대기 중인 아이돌 등록 신청</span><strong>{{ number_format($attention['idol_requests']) }}</strong></a>
                <a href="{{ route('admin.fan-post-reports.index') }}"><span>대기 중인 팬포스트 신고</span><strong>{{ number_format($attention['fan_post_reports']) }}</strong></a>
                <a href="{{ route('admin.members.index') }}"><span>정지 회원</span><strong>{{ number_format($attention['suspended_members']) }}</strong></a>
                <a href="{{ route('admin.ip-access.index') }}"><span>현재 차단된 IP</span><strong>{{ $attention['blocked_ips'] === null ? '집계 안 함' : number_format($attention['blocked_ips']) }}</strong></a>
            </div>
        </section>
    </div>

    <section class="admin-dashboard-card admin-recent-activity">
        <header><div><h2>최근 활동</h2><p>최근 서비스 및 운영 활동입니다.</p></div><a href="{{ route('admin.activity-logs.index') }}">관리자 활동 로그</a></header>
        @if ($activities)
            <ol>
                @foreach ($activities as $activity)
                    <li class="admin-activity-{{ $activity['type'] }}"><i aria-hidden="true"></i><div><strong>{{ $activity['title'] }}</strong><span>{{ $activity['description'] }}</span></div><time datetime="{{ $activity['created_at'] }}">{{ \Illuminate\Support\Carbon::parse($activity['created_at'])->format('Y-m-d H:i') }}</time></li>
                @endforeach
            </ol>
        @else
            <p class="admin-empty-activity">최근 운영 활동이 없습니다.</p>
        @endif
    </section>
@endsection
