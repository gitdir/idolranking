@props(['memberCode' => null, 'label' => null])
@php($highlightDummy = \App\Support\AdminDummyNickname::matches(memberCode: $memberCode))

@if ($memberCode !== null)
    <a translate="no" href="{{ route('member.fan-posts', ['member_code' => $memberCode]) }}" @if ($label) aria-label="{{ $label }}" @endif {{ $attributes->class(['member-identity-link', 'admin-dummy-nickname' => $highlightDummy]) }}>{{ $slot }}</a>
@else
    {{ $slot }}
@endif
