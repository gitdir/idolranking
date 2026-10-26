@props(['voters', 'idolName', 'monthLabel' => null])
@php($monthLabel = $monthLabel ?: 'This Month')

<aside class="ranking-top-voters" aria-labelledby="ranking-top-voters-title">
<h2 id="ranking-top-voters-title">{{ __('ui.top_voters') }} <span>({{ $monthLabel }})</span></h2>
    <ol aria-label="{{ __('ui.top_voters_for', ['name' => $idolName, 'month' => $monthLabel]) }}">
        @foreach ($voters as $voter)
            <li class="ranking-voter">
                <x-ranking-badge :rank="$loop->iteration" />
                @if (! empty($voter['member_code']))
                    <x-member-link :member-code="$voter['member_code']" :label="__('ui.member_fan_posts_label', ['name' => $voter['nickname']])" class="ranking-voter-avatar-link">
                        <img class="ranking-voter-avatar" src="{{ \App\Support\ManagedImageStorage::publicUrl($voter['image_url'] ?? null) }}" width="56" height="56" alt="" loading="lazy">
                    </x-member-link>
                @else
                    <img class="ranking-voter-avatar" src="{{ \App\Support\ManagedImageStorage::publicUrl($voter['image_url'] ?? null) }}" width="56" height="56" alt="" loading="lazy">
                @endif
                <div class="ranking-voter-info">
                    <div class="ranking-voter-heading">
                        <h3 translate="no">
                            <x-country-flag :code="$voter['country_code'] ?? null" />
                            @if (! empty($voter['member_code']))
                                <x-member-link :member-code="$voter['member_code']" class="ranking-voter-name-link">{{ $voter['nickname'] }}</x-member-link>
                            @else
                                <span class="ranking-voter-name-link">{{ $voter['nickname'] }}</span>
                            @endif
                        </h3>
                        <span class="ranking-voter-heart-count" aria-label="{{ __('ui.voter_hearts_aria', ['amount' => number_format($voter['hearts'])]) }}"><svg class="ranking-voter-heart-icon heart-icon--small" aria-hidden="true"><use href="#icon-heart"/></svg>{{ number_format($voter['hearts']) }}</span>
                    </div>
                    {{-- Relative contribution: the leading voter represents 100%. --}}
                    <progress max="{{ $voters[0]['hearts'] }}" value="{{ $voter['hearts'] }}" aria-label="{{ __('ui.voter_contribution_aria', ['name' => $voter['nickname']]) }}"></progress>
                    <p>{{ $voter['bio'] }}</p>
                </div>
            </li>
        @endforeach
    </ol>
    @if (count($voters) === 0)
        <p class="fan-post-empty">{{ __('ui.no_month_votes') }}</p>
    @endif
</aside>
