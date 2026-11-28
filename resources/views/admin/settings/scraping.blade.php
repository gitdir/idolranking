@extends('layouts.admin')

@section('title', '스크래핑 설정')

@section('content')
<div class="admin-scraping">
    <header class="admin-page-heading"><div><h1>스크래핑</h1><p>재배포가 허용된 커뮤니티의 미디어 게시물을 관리합니다.</p></div></header>
    <nav class="admin-settings-tabs" aria-label="관리 설정">
        <a href="{{ route('admin.settings.edit', 'general') }}">기본 설정</a>
        <a href="{{ route('admin.settings.edit', 'seo') }}">SEO 설정</a>
        <a href="{{ route('admin.settings.edit', 'management') }}">기능 관리</a>
        <a href="{{ route('admin.scraping.index') }}" aria-current="page">스크래핑</a>
        <a href="{{ route('admin.settings.edit', 'custom-code') }}">추가 코드 설정</a>
    </nav>

    @if (session('admin_success')) <div class="admin-alert-success">{{ session('admin_success') }}</div> @endif
    @if ($errors->has('scraping')) <div class="admin-alert-error" role="alert">{{ $errors->first('scraping') }}</div> @endif
    @unless ($ready)
        <div class="admin-alert-error">스크래핑 SQL 패치를 적용해야 설정할 수 있습니다.</div>
    @else
        <div class="admin-scraping-summary" aria-label="스크래핑 현황" data-scraping-summary>
            <div><span>등록 커뮤니티</span><strong>{{ number_format($summary['communities']) }}</strong></div>
            <div><span>오늘 수집</span><strong>{{ number_format($summary['today']) }}</strong></div>
            <div><span>누적 등록</span><strong>{{ number_format($summary['total']) }}</strong></div>
        </div>

        <form class="admin-scraping-global" method="post" action="{{ route('admin.scraping.global') }}">
            @csrf @method('put')
            <div><strong>전체 스크래핑</strong><p>켜면 활성화된 커뮤니티를 예약 시각에 수집합니다.</p></div>
            <input type="hidden" name="enabled" value="0">
            <label class="admin-scraping-switch"><input type="checkbox" name="enabled" value="1" @checked($globalEnabled)><span>사용</span></label>
            <button class="admin-scraping-button admin-scraping-button--primary" type="submit">저장</button>
        </form>

        <section class="admin-scraping-section" aria-labelledby="scraping-sources-title">
            <div class="admin-scraping-section-heading"><h2 id="scraping-sources-title">등록 커뮤니티</h2><span>{{ number_format($summary['communities']) }}곳</span></div>
            <div class="admin-table-scroll"><table class="admin-scraping-table">
                <thead><tr><th>커뮤니티</th><th>게시판 URL</th><th>상태</th><th>다음 예정</th><th>마지막 성공</th><th>누적 등록</th><th>관리</th></tr></thead>
                <tbody>
                @forelse ($sources as $source)
                    <tr>
                        <td><strong>{{ $source->name }}</strong></td>
                        <td class="admin-scraping-url" title="{{ $source->listing_url }}"><a href="{{ $source->listing_url }}" target="_blank" rel="noopener noreferrer">{{ $source->listing_url }}</a></td>
                        <td><span class="admin-scraping-status {{ $source->is_enabled ? 'is-active' : 'is-inactive' }}">{{ $source->is_enabled ? '사용 중' : '중지' }}</span></td>
                        <td>{{ isset($nextSchedules[$source->idx]) ? \Illuminate\Support\Carbon::parse($nextSchedules[$source->idx], 'UTC')->timezone('Asia/Seoul')->format('m.d H:i') : '미정' }}</td>
                        <td>{{ isset($lastSuccesses[$source->idx]) ? \Illuminate\Support\Carbon::parse($lastSuccesses[$source->idx], 'UTC')->timezone('Asia/Seoul')->format('m.d H:i') : '없음' }}</td>
                        <td>{{ number_format($totals[$source->idx] ?? 0) }}건</td>
                        <td><div class="admin-scraping-actions">
                            <button class="admin-scraping-button" type="button" data-scraping-edit="{{ $source->idx }}" aria-controls="scraping-edit-{{ $source->idx }}" aria-expanded="{{ old('_scraping_source_idx') === (string) $source->idx ? 'true' : 'false' }}">수정</button>
                            <form method="post" action="{{ route('admin.scraping.sources.test', $source->idx) }}" data-scraping-action="test">@csrf <button class="admin-scraping-button" type="submit">수집 테스트</button></form>
                            <form method="post" action="{{ route('admin.scraping.sources.collect', $source->idx) }}" data-scraping-action="collect">@csrf <button class="admin-scraping-button admin-scraping-button--primary" type="submit">지금 바로 수집</button></form>
                            <form method="post" action="{{ route('admin.scraping.sources.delete', $source->idx) }}" onsubmit="return confirm('이 커뮤니티를 삭제하시겠습니까? 수집 이력은 보존됩니다.')">@csrf @method('delete') <button class="admin-scraping-button admin-scraping-button--danger" type="submit">삭제</button></form>
                        </div><div class="admin-scraping-inline-result" data-scraping-result role="status" aria-live="polite" hidden></div>
                        <details class="admin-scraping-html-import"><summary>저장한 HTML 가져오기</summary>
                            <form method="post" enctype="multipart/form-data" action="{{ route('admin.scraping.sources.import-html', $source->idx) }}">
                                @csrf
                                <label>원본 게시물 URL<input type="url" name="post_url" required placeholder="https://example.com/post/123"></label>
                                <label>브라우저에서 저장한 HTML<input type="file" name="html_file" accept=".html,.htm,text/html" required></label>
                                <button class="admin-scraping-button" type="submit">HTML 가져오기</button>
                            </form>
                        </details></td>
                    </tr>
                    <tr id="scraping-edit-{{ $source->idx }}" class="admin-scraping-edit-row" @if (old('_scraping_source_idx') !== (string) $source->idx) hidden @endif><td colspan="7">
                        <h3>{{ $source->name }} 설정 수정</h3>
                        @include('admin.settings.scraping-source-form', ['source' => $source])
                    </td></tr>
                @empty
                    <tr><td class="admin-scraping-empty" colspan="7">등록된 커뮤니티가 없습니다.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>

        <section class="admin-scraping-section" aria-labelledby="scraping-add-title">
            <div class="admin-scraping-section-heading"><h2 id="scraping-add-title">커뮤니티 추가</h2></div>
            @include('admin.settings.scraping-source-form', ['source' => null])
        </section>

        <section class="admin-scraping-section" aria-labelledby="scraping-history-title">
            <div class="admin-scraping-section-heading"><h2 id="scraping-history-title">최근 수집 이력</h2><span>최근 30건</span></div>
            <div class="admin-table-scroll" data-scraping-history><table class="admin-scraping-table admin-scraping-history">
                <thead><tr><th>수집 시각</th><th>커뮤니티</th><th>상태</th><th>원문 URL</th><th>팬포스트</th><th>더미 회원</th><th>실패 사유</th></tr></thead>
                <tbody>
                @forelse ($imports as $import)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($import->collected_at, 'UTC')->timezone('Asia/Seoul')->format('Y-m-d H:i') }}</td>
                        <td>{{ $import->source_name ?? '삭제된 커뮤니티' }}</td>
                        <td><span class="admin-scraping-status {{ $import->status === 'imported' ? 'is-active' : ($import->status === 'failed' ? 'is-failed' : 'is-inactive') }}">{{ ['imported' => '등록', 'skipped' => '건너뜀', 'failed' => '실패'][$import->status] ?? $import->status }}</span></td>
                        <td class="admin-scraping-url" title="{{ $import->original_url }}">{{ $import->original_url }}</td>
                        <td>{{ $import->fan_post_idx ?? '-' }}</td><td>{{ $import->dummy_member_idx ?? '-' }}</td><td>{{ $import->failure_reason ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td class="admin-scraping-empty" colspan="7">수집 이력이 없습니다.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    @endunless
</div>
<script>
document.querySelectorAll('[data-scraping-edit]').forEach((button) => {
    button.addEventListener('click', () => {
        const row = document.getElementById('scraping-edit-' + button.dataset.scrapingEdit);
        row.hidden = !row.hidden;
        button.setAttribute('aria-expanded', String(!row.hidden));
    });
});
document.querySelectorAll('[data-scraping-action]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        const result = form.closest('td').querySelector('[data-scraping-result]');
        const originalText = button.textContent;
        result.replaceChildren();
        result.hidden = true;
        button.disabled = true;
        button.textContent = form.dataset.scrapingAction === 'test' ? '테스트 중...' : '수집 중...';
        form.setAttribute('aria-busy', 'true');
        const addLine = (container, label, value) => {
            const line = document.createElement('p');
            line.textContent = label + ': ' + (value ?? '확인 불가');
            container.append(line);
        };
        try {
            const response = await fetch(form.action, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
            });
            const data = await response.json();
            result.classList.toggle('is-error', !response.ok || data.status === 'error' || data.success === false);
            const message = document.createElement('strong');
            message.textContent = data.message || '요청 결과를 확인하지 못했습니다.';
            result.append(message);
            if (data.diagnostic) {
                const d = data.diagnostic;
                addLine(result, '게시판 URL', d.board_url);
                addLine(result, 'HTTP 상태', d.board_status ?? '요청 전 중단');
                addLine(result, 'HTML 크기', d.board_bytes === null ? '미수신' : d.board_bytes + '바이트');
                addLine(result, '페이지 제목', d.page_title);
                addLine(result, '게시물 링크 일치', d.post_link_count + '개');
                addLine(result, '후보 URL', d.candidates.length ? d.candidates.join(' · ') : '없음');
                d.details.forEach((detail) => {
                    const block = document.createElement('div');
                    block.className = 'admin-scraping-diagnostic-detail';
                    addLine(block, '상세 URL / HTTP 상태', detail.url + ' / ' + (detail.status ?? '요청 전 중단'));
                    addLine(block, '제목 일치 / 추출 제목', detail.title_count + '개 / ' + (detail.title || '없음'));
                    addLine(block, '본문 일치', detail.content_count + '개');
                    addLine(block, '이미지 일치 / URL', detail.image_count + '개 / ' + (detail.image_urls.join(' · ') || '없음'));
                    addLine(block, '유튜브 일치 / URL', detail.video_count + '개 / ' + (detail.video_urls.join(' · ') || '없음'));
                    if (detail.reason) addLine(block, '제외 이유', detail.reason);
                    result.append(block);
                });
            }
            const dismiss = document.createElement('button');
            dismiss.type = 'button';
            dismiss.className = 'admin-scraping-result-dismiss';
            dismiss.setAttribute('aria-label', '결과 닫기');
            dismiss.textContent = '×';
            dismiss.addEventListener('click', () => { result.hidden = true; result.replaceChildren(); });
            result.prepend(dismiss);
            result.hidden = false;
            if (form.dataset.scrapingAction === 'collect') {
                try {
                    const page = await fetch(window.location.href, { credentials: 'same-origin' });
                    if (page.ok) {
                        const html = new DOMParser().parseFromString(await page.text(), 'text/html');
                        for (const selector of ['[data-scraping-summary]', '[data-scraping-history]']) {
                            const current = document.querySelector(selector);
                            const updated = html.querySelector(selector);
                            if (current && updated) current.replaceWith(updated);
                        }
                    } else {
                        addLine(result, '현황 갱신', '새로고침 후 확인해 주세요.');
                    }
                } catch (error) {
                    addLine(result, '현황 갱신', '새로고침 후 확인해 주세요.');
                }
            }
        } catch (error) {
            result.classList.add('is-error');
            result.textContent = '요청 중 연결 오류가 발생했습니다. 다시 시도해 주세요.';
            const dismiss = document.createElement('button');
            dismiss.type = 'button';
            dismiss.className = 'admin-scraping-result-dismiss';
            dismiss.setAttribute('aria-label', '결과 닫기');
            dismiss.textContent = '×';
            dismiss.addEventListener('click', () => { result.hidden = true; result.replaceChildren(); });
            result.prepend(dismiss);
            result.hidden = false;
        } finally {
            button.disabled = false;
            button.textContent = originalText;
            form.removeAttribute('aria-busy');
        }
    });
});
</script>
@endsection
