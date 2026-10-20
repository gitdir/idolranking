@props(['memberCode' => null, 'label' => null])

@if ($memberCode !== null)
    <a translate="no" href="{{ route('member.fan-posts', ['member_code' => $memberCode]) }}" @if ($label) aria-label="{{ $label }}" @endif {{ $attributes->class('member-identity-link') }}>{{ $slot }}</a>
@else
    {{ $slot }}
@endif
