<form class="admin-scraping-form" method="post" action="{{ $source ? route('admin.scraping.sources.update', $source->idx) : route('admin.scraping.sources.store') }}">
    @csrf
    @if ($source) @method('put') @endif
    @php($formKey = $source ? (string) $source->idx : 'new')
    @php($showErrors = old('_scraping_source_idx') === $formKey)
    <input type="hidden" name="_scraping_source_idx" value="{{ $formKey }}">

    <div class="admin-scraping-fields">
        <label>커뮤니티 이름<input name="name" required maxlength="100" placeholder="예: 팬 커뮤니티" value="{{ $showErrors ? old('name') : ($source->name ?? '') }}">@if ($showErrors) @error('name') <small class="admin-scraping-field-error">{{ $message }}</small> @enderror @endif</label>
        <label>게시판 목록 URL<input name="listing_url" required type="url" maxlength="2048" placeholder="https://example.com/board" value="{{ $showErrors ? old('listing_url') : ($source->listing_url ?? '') }}">@if ($showErrors) @error('listing_url') <small class="admin-scraping-field-error">{{ $message }}</small> @enderror @endif</label>
    </div>
    <label>수집 방식
        <select name="collection_method">
            <option value="http" @selected(($showErrors ? old('collection_method') : ($source->collection_method ?? 'http')) === 'http')>HTTP</option>
            <option value="browser" @selected(($showErrors ? old('collection_method') : ($source->collection_method ?? 'http')) === 'browser')>브라우저</option>
        </select>
        @if ($showErrors) @error('collection_method') <small class="admin-scraping-field-error">{{ $message }}</small> @enderror @endif
    </label>

    <div class="admin-scraping-selector-group">
        <h3>CSS 선택자 설정</h3>
        @foreach ([
            'post_link_selector' => ['게시물 링크', 'a.post-link'],
            'title_selector' => ['제목', 'h1.post-title'],
            'content_selector' => ['본문', '.post-content'],
            'image_selector' => ['본문 이미지', '.post-content img'],
            'video_selector' => ['유튜브 링크 또는 영상', '.post-content a.video'],
        ] as $field => [$label, $example])
            <label>{{ $label }}<input name="{{ $field }}" maxlength="255" placeholder="예: {{ $example }}" @required(!in_array($field, ['image_selector', 'video_selector'], true)) value="{{ $showErrors ? old($field) : ($source->{$field} ?? '') }}">@if ($showErrors) @error($field) <small class="admin-scraping-field-error">{{ $message }}</small> @enderror @endif</label>
        @endforeach
    </div>

    <div class="admin-scraping-permissions">
        <input type="hidden" name="is_enabled" value="0">
        <label><input type="checkbox" name="is_enabled" value="1" @checked($showErrors ? old('is_enabled') : ($source->is_enabled ?? false))> 이 커뮤니티 수집 사용</label>
        @if ($showErrors) @error('is_enabled') <small class="admin-scraping-field-error">{{ $message }}</small> @enderror @endif
    </div>
    <div class="admin-scraping-form-actions"><button class="admin-scraping-button admin-scraping-button--primary" type="submit">{{ $source ? '수정 저장' : '커뮤니티 추가' }}</button></div>
</form>
