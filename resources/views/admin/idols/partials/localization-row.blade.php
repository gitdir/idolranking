@php
    $languageCode = (string) ($localization['language_code'] ?? '');
    $isEnglish = $languageCode === 'en';
    $isOtherLanguage = $languageCode !== '' && !in_array($languageCode, ['en', 'ko', 'ja', 'zh-CN', 'zh-TW'], true);
    $isExistingLanguage = !empty($localization['is_existing']) && empty($template);
    $localizedName = $isEnglish ? $canonicalEnglishName : ($localization['localized_name'] ?? '');
    $aliases = array_values($localization['aliases'] ?? []);
    $languageLabels = ['en' => '영어', 'ko' => '한국어', 'ja' => '일본어', 'zh-CN' => '중국어 간체', 'zh-TW' => '중국어 번체'];
    $languageLabel = $languageLabels[$languageCode] ?? ($languageCode !== '' ? '기타' : '새 언어');
@endphp
<article class="admin-multilingual-row" data-localization-row data-row-index="{{ $index }}" data-existing-language="{{ $isExistingLanguage ? 'true' : 'false' }}">
    <header class="admin-multilingual-card-header">
        <h3 data-language-label>{{ $languageLabel }}{{ $languageCode !== '' ? ' ('.$languageCode.')' : '' }}</h3>
        @if($isExistingLanguage)
            <span>기존 언어</span>
        @endif
    </header>
    <div class="admin-multilingual-fields">
        <label class="admin-multilingual-language-field">
            <span>언어</span>
            <select data-language-choice @disabled($isExistingLanguage)>
                <option value="">언어 선택</option>
                <option value="en" @selected($languageCode === 'en')>영어 (en)</option>
                <option value="ko" @selected($languageCode === 'ko')>한국어 (ko)</option>
                <option value="ja" @selected($languageCode === 'ja')>일본어 (ja)</option>
                <option value="zh-CN" @selected($languageCode === 'zh-CN')>중국어 간체 (zh-CN)</option>
                <option value="zh-TW" @selected($languageCode === 'zh-TW')>중국어 번체 (zh-TW)</option>
                <option value="other" @selected($isOtherLanguage)>기타</option>
            </select>
            <input type="text" value="{{ $isOtherLanguage ? $languageCode : '' }}" maxlength="10" pattern="[a-z]{2,3}(-[A-Za-z0-9]{2,8})*" placeholder="예: fr" aria-label="사용자 지정 언어 코드" data-custom-language-code @if(!$isOtherLanguage) hidden @endif @disabled($isExistingLanguage)>
            <input type="hidden" name="localizations[{{ $index }}][language_code]" value="{{ $languageCode }}" data-language-code>
        </label>
        <label>
            <span>선호 이름</span>
            <input name="localizations[{{ $index }}][localized_name]" value="{{ $localizedName }}" maxlength="200" @readonly($isEnglish) data-localized-name>
            <small data-english-name-help @if(!$isEnglish) hidden @endif>영어 선호 이름은 위 기본 정보의 영문 이름과 동일하게 저장됩니다.</small>
        </label>
        <label class="admin-multilingual-description">
            <span>소개</span>
            <textarea name="localizations[{{ $index }}][description]" rows="4" maxlength="10000" placeholder="해당 언어의 소개를 입력하세요">{{ $localization['description'] ?? '' }}</textarea>
        </label>
        <label>
            <span>소속사</span>
            <input name="localizations[{{ $index }}][agency]" value="{{ $localization['agency'] ?? '' }}" maxlength="200" placeholder="해당 언어의 소속사 이름">
        </label>
        @if($isIndividual)
            <label>
                <span>포지션</span>
                <input name="localizations[{{ $index }}][position]" value="{{ $localization['position'] ?? '' }}" maxlength="200" placeholder="해당 언어의 포지션">
            </label>
        @else
            <label>
                <span>장르</span>
                <input name="localizations[{{ $index }}][genre]" value="{{ $localization['genre'] ?? '' }}" maxlength="200" placeholder="해당 언어의 장르">
            </label>
            <label>
                <span>리더</span>
                <input name="localizations[{{ $index }}][leader]" value="{{ $localization['leader'] ?? '' }}" maxlength="200" placeholder="해당 언어의 리더 이름">
            </label>
        @endif
    </div>
    <section class="admin-multilingual-aliases">
        <header><strong>별칭</strong><button class="admin-action-button" type="button" data-add-alias>+ 별칭 추가</button></header>
        <div data-alias-items>
            @foreach($aliases as $alias)
                <div class="admin-multilingual-alias" data-alias-row>
                    <input name="localizations[{{ $index }}][aliases][]" value="{{ $alias }}" maxlength="200" aria-label="별칭 이름">
                    <button class="admin-alias-remove" type="button" data-remove-alias aria-label="별칭 삭제" title="별칭 삭제">×</button>
                </div>
            @endforeach
        </div>
    </section>
    @if(!$isEnglish)
        <footer class="admin-multilingual-row-actions"><button class="admin-multilingual-delete" type="button" data-remove-localization>이 언어 삭제</button></footer>
    @else
        <footer class="admin-multilingual-row-actions"><small>영어 이름은 기본 정보에서 관리합니다.</small></footer>
    @endif
</article>
