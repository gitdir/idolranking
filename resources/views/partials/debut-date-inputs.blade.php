@php($debutDateRows = is_array($debutDateRows ?? null) && $debutDateRows !== [] ? $debutDateRows : [['country_code' => '', 'debut_date' => '']])
@php($nextDebutDateIndex = collect(array_keys($debutDateRows))->filter(fn ($index) => is_numeric($index))->map(fn ($index) => (int) $index)->max() + 1)
<div class="idol-debut-date-editor" data-debut-date-editor data-next-index="{{ $nextDebutDateIndex }}">
    <div class="idol-debut-date-list" data-debut-date-list>
        @foreach ($debutDateRows as $index => $debutDate)
            @include('partials.debut-date-row', ['index' => $index, 'debutDate' => $debutDate, 'idPrefix' => $idPrefix, 'namePrefix' => $namePrefix, 'admin' => $admin ?? false, 'isFirst' => $loop->first, 'dateInputLang' => $dateInputLang ?? null])
        @endforeach
    </div>
    <button class="idol-debut-date-add" type="button" data-add-debut-date>+ {{ $admin ?? false ? '국가 추가' : __('ui.add_debut_country') }}</button>
    <template data-debut-date-template>
        @include('partials.debut-date-row', ['index' => '__INDEX__', 'debutDate' => [], 'idPrefix' => $idPrefix, 'namePrefix' => $namePrefix, 'admin' => $admin ?? false, 'isFirst' => false, 'dateInputLang' => $dateInputLang ?? null])
    </template>
</div>
