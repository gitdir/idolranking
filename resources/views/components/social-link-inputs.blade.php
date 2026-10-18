@props(['values' => [], 'disabled' => false, 'label' => null, 'idPrefix' => 'social-link', 'admin' => false])
@php
    $values = old('sns_links', $values);
    $values = is_array($values) ? array_slice(array_values($values), 0, 3) : [];
    if ($values === []) $values = [''];
    $addLabel = $admin ? 'SNS 링크 추가' : __('ui.social_add_link');
    $removeLabel = $admin ? 'SNS 링크 삭제' : __('ui.social_remove_link');
    $placeholder = $admin ? 'https://…' : __('ui.social_url_placeholder');
    $inputLabel = $admin ? 'SNS 링크 :number' : __('ui.social_link_number', ['number' => ':number']);
@endphp
<div class="social-link-fields" data-social-link-list data-id-prefix="{{ $idPrefix }}" data-disabled="{{ $disabled ? 'true' : 'false' }}" data-add-label="{{ $addLabel }}" data-remove-label="{{ $removeLabel }}" data-placeholder="{{ $placeholder }}" data-input-label="{{ $inputLabel }}">
    @if ($label)<label class="social-link-fields-label" for="{{ $idPrefix }}-0">{{ $label }}</label>@endif
    <div class="social-link-items" data-social-link-items>
        @foreach ($values as $index => $value)
            <div class="social-link-input-row" data-social-link-row>
                <input id="{{ $idPrefix }}-{{ $index }}" name="sns_links[]" type="url" value="{{ $value }}" maxlength="2048" placeholder="{{ $placeholder }}" aria-label="{{ str_replace(':number', (string) ($index + 1), $inputLabel) }}" @disabled($disabled)>
                @if ($index > 0)
                    <button type="button" class="social-link-remove" data-social-link-remove aria-label="{{ $removeLabel }}" @disabled($disabled)>×</button>
                @endif
            </div>
        @endforeach
    </div>
    <button type="button" class="social-link-add" data-social-link-add @disabled($disabled || count($values) >= 3)>{{ $addLabel }}</button>
</div>
