@extends('layouts.admin')

@section('title', '아이돌 등록 신청 | IDOL RANKING 관리자')

@section('content')
    <div class="admin-page-heading">
        <div><h1>아이돌 등록 신청</h1><p>회원이 제출한 아이돌 및 그룹 등록 신청을 관리합니다.</p></div>
        <nav aria-label="현재 위치"><span>관리자</span><svg viewBox="0 0 16 16" aria-hidden="true"><path d="m6 3 5 5-5 5"/></svg><strong>아이돌 등록 신청</strong></nav>
    </div>

    <nav class="admin-request-tabs" aria-label="신청 상태">
        @foreach (['pending' => '대기', 'approved' => '승인', 'rejected' => '반려'] as $value => $label)
            <a href="{{ route('admin.idol-requests.index', ['tab' => $value]) }}" @if ($tab === $value) aria-current="page" @endif>{{ $label }} <span>{{ number_format((int) ($statusCounts[$value] ?? 0)) }}</span></a>
        @endforeach
    </nav>

    <form class="admin-request-filters" action="{{ route('admin.idol-requests.index') }}" method="get">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <label class="admin-search-field"><span class="admin-sr-only">신청 검색</span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10" cy="10" r="6.5"/><path d="m15 15 5 5"/></svg><input type="search" name="search" value="{{ request('search') }}" placeholder="이름, 영문 이름 또는 신청자 검색"></label>
        <label><span class="admin-sr-only">유형</span><select name="type"><option value="">전체 유형</option><option value="boy" @selected($type === 'boy')>남자</option><option value="boy_group" @selected($type === 'boy_group')>남자 그룹</option><option value="girl" @selected($type === 'girl')>여자</option><option value="girl_group" @selected($type === 'girl_group')>여자 그룹</option></select></label>
        <label><span class="admin-sr-only">상태</span><select name="status"><option value="">전체 상태</option><option value="pending" @selected($filterStatus === 'pending')>대기</option><option value="approved" @selected($filterStatus === 'approved')>승인</option><option value="rejected" @selected($filterStatus === 'rejected')>반려</option></select></label>
        <label class="admin-date-field"><span class="admin-sr-only">시작일</span><input type="date" name="start_date" value="{{ request('start_date') }}" aria-label="시작일"></label>
        <label class="admin-date-field"><span class="admin-sr-only">종료일</span><input type="date" name="end_date" value="{{ request('end_date') }}" aria-label="종료일"></label>
        <button type="submit">검색</button>
    </form>

    <section class="admin-request-table-card" aria-label="아이돌 등록 신청 목록">
        <div class="admin-table-scroll">
            <table>
                <thead><tr><th><input type="checkbox" aria-label="전체 신청 선택"></th><th>NO</th><th><span class="admin-sr-only">이미지</span></th><th>유형</th><th><x-admin-sort-link column="name" label="이름" :$sort :$direction/></th><th><x-admin-sort-link column="english_name" label="영문 이름" :$sort :$direction/></th><th>신청자</th><th><x-admin-sort-link column="created_at" label="신청일" :$sort :$direction/></th><th><x-admin-sort-link column="status" label="상태" :$sort :$direction/></th></tr></thead>
                <tbody>
                    @forelse ($requests as $requestRow)
                        @php
                            $typeLabel = ($requestRow->gender === 'male' ? '남자' : '여자').($requestRow->type === 'group' ? ' 그룹' : '');
                            $statusClass = strtolower($requestRow->status);
                        @endphp
                        <tr>
                            <td><input type="checkbox" aria-label="{{ $requestRow->idx }}번 신청 선택"></td>
                            <td><x-admin-row-number :paginator="$requests" :index="$loop->index" /></td>
                            <td><x-admin-request-image :path="$requestRow->image_path" :type="$requestRow->type" width="52" height="52" alt="" /></td>
                            <td><span class="admin-type-badge admin-type-{{ $requestRow->gender === 'female' ? 'girl' : 'boy' }}">{{ $typeLabel }}</span></td>
                            <td class="admin-name-cell"><a class="admin-record-link" href="{{ route('admin.idol-requests.show', $requestRow->idx) }}">{{ $requestRow->name }}</a></td>
                            <td>{{ $requestRow->english_name }}</td>
                            <td><strong>{{ $requestRow->applicant ?: '회원' }}</strong><small>{{ $requestRow->applicant_email }}</small></td>
                            <td><time datetime="{{ \Illuminate\Support\Carbon::parse($requestRow->created_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($requestRow->created_at)->format('Y-m-d H:i') }}</time></td>
                            <td><span class="admin-status-badge admin-status-{{ $statusClass }}">{{ ['pending' => '대기', 'approved' => '승인', 'rejected' => '반려'][$requestRow->status] ?? $requestRow->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td class="admin-table-empty" colspan="9">조건에 맞는 등록 신청이 없습니다.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <footer class="admin-table-footer">
            <p>전체 {{ number_format($requests->total()) }}건 중 {{ $requests->firstItem() ?? 0 }}–{{ $requests->lastItem() ?? 0 }}건 표시</p>
            <nav aria-label="페이지 이동">
                @if ($requests->onFirstPage())<span aria-disabled="true">‹</span>@else<a href="{{ $requests->previousPageUrl() }}" aria-label="이전 페이지">‹</a>@endif
                <strong aria-current="page">{{ $requests->currentPage() }}</strong>
                @if ($requests->hasMorePages())<a href="{{ $requests->nextPageUrl() }}" aria-label="다음 페이지">›</a>@else<span aria-disabled="true">›</span>@endif
            </nav>
        </footer>
    </section>
@endsection
