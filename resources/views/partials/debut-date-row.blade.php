<div class="idol-debut-date-row" data-debut-date-row>
    @if ($admin)<div class="admin-review-country-field">@endif
    <x-country-select
        id="{{ $idPrefix }}-{{ $index }}"
        name="{{ $namePrefix }}[{{ $index }}][country_code]"
        :selected="$debutDate['country_code'] ?? ''"
        :placeholder="$admin ? '국가 선택' : __('ui.select_debut_country')"
        :search-label="$admin ? '국가 검색' : __('ui.search_country')"
        :list-label="$admin ? '국가 목록' : __('ui.countries')"
        :empty-label="$admin ? '검색 결과가 없습니다.' : __('ui.no_countries')"
        :options="\App\Support\IdolDebutCountries::records(app()->getLocale())"
        :required-selection="false" />
    @if ($admin)</div>@endif
    <input type="date" name="{{ $namePrefix }}[{{ $index }}][debut_date]" value="{{ $debutDate['debut_date'] ?? '' }}" max="{{ now()->toDateString() }}" aria-label="{{ $admin ? '데뷔일' : __('ui.profile_label_debut_date') }}">
    @if (!($isFirst ?? ($index === 0)))
        <button class="idol-debut-date-remove" type="button" data-remove-debut-date aria-label="{{ $admin ? '데뷔일 행 삭제' : __('ui.remove_debut_country') }}" title="{{ $admin ? '삭제' : __('ui.remove') }}">×</button>
    @else
        <span class="idol-debut-date-remove-placeholder" aria-hidden="true"></span>
    @endif
</div>
