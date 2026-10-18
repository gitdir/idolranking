@props(['voters', 'idolName', 'monthLabel' => null])
@php($monthLabel = $monthLabel ?: __('ui.this_month'))

<aside class="ranking-top-voters" aria-labelledby="ranking-top-voters-title">
    <h2 id="ranking-top-voters-title">{{ __('ui.top_voters') }} <span>({{ $monthLabel }})</span></h2>
    <ol aria-label="{{ __('ui.top_voters_for', ['name' => $idolName, 'month' => $monthLabel]) }}">
        @foreach ($voters as $voter)
            <li class="ranking-voter">
                <x-ranking-badge :rank="$loop->iteration" />
                @if (! empty($voter['member_code']))
                    <a class="ranking-voter-avatar-link" href="{{ route('member.fan-posts', ['member_code' => $voter['member_code']]) }}" aria-label="{{ __('ui.member_fan_posts_label', ['name' => $voter['nickname']]) }}">
                        <img class="ranking-voter-avatar" src="{{ \App\Support\ManagedImageStorage::publicUrl($voter['image_url'] ?? null) }}" width="56" height="56" alt="" loading="lazy">
                    </a>
                @else
                    <img class="ranking-voter-avatar" src="{{ \App\Support\ManagedImageStorage::publicUrl($voter['image_url'] ?? null) }}" width="56" height="56" alt="" loading="lazy">
                @endif
                <div class="ranking-voter-info">
                    <div class="ranking-voter-heading">
                        <h3>
                            <x-country-flag :code="$voter['country_code'] ?? null" />
                            @if (! empty($voter['member_code']))
                                <a class="ranking-voter-name-link" href="{{ route('member.fan-posts', ['member_code' => $voter['member_code']]) }}">{{ $voter['nickname'] }}</a>
                            @else
                                {{ $voter['nickname'] }}
                            @endif
                        </h3>
                        <span aria-label="{{ __('ui.voter_hearts_aria', ['amount' => number_format($voter['hearts'])]) }}">{{ number_format($voter['hearts']) }}</span>
                    </div>
                    <p>{{ $voter['bio'] }}</p>
                    {{-- Relative contribution: the leading voter represents 100%. --}}
                    <progress max="{{ $voters[0]['hearts'] }}" value="{{ $voter['hearts'] }}" aria-label="{{ __('ui.voter_contribution_aria', ['name' => $voter['nickname']]) }}"></progress>
                </div>
            </li>
        @endforeach
    </ol>
    @if (count($voters) === 0)
        <p class="fan-post-empty">{{ __('ui.no_month_votes') }}</p>
    @endif
</aside>
