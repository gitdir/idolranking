@props(['voters', 'idolName', 'monthLabel' => 'This Month'])

<aside class="ranking-top-voters" aria-labelledby="ranking-top-voters-title">
    <h2 id="ranking-top-voters-title">Top Voters <span>({{ $monthLabel }})</span></h2>
    <ol aria-label="Top voters for {{ $idolName }} in {{ $monthLabel }}">
        @foreach ($voters as $voter)
            <li class="ranking-voter">
                <x-ranking-badge :rank="$loop->iteration" />
                @if (! empty($voter['member_code']))
                    <a class="ranking-voter-avatar-link" href="{{ route('member.fan-posts', ['member_code' => $voter['member_code']]) }}" aria-label="View {{ $voter['nickname'] }} Fan Posts">
                        <img class="ranking-voter-avatar" src="{{ \App\Support\ManagedImageStorage::publicUrl($voter['image_url'] ?? null) }}" width="56" height="56" alt="" loading="lazy">
                    </a>
                @else
                    <img class="ranking-voter-avatar" src="{{ \App\Support\ManagedImageStorage::publicUrl($voter['image_url'] ?? null) }}" width="56" height="56" alt="" loading="lazy">
                @endif
                <div class="ranking-voter-info">
                    <div class="ranking-voter-heading">
                        <h3>
                            @if (! empty($voter['member_code']))
                                <a class="ranking-voter-name-link" href="{{ route('member.fan-posts', ['member_code' => $voter['member_code']]) }}">{{ $voter['nickname'] }}</a>
                            @else
                                {{ $voter['nickname'] }}
                            @endif
                        </h3>
                        <span aria-label="{{ number_format($voter['hearts']) }} Hearts">{{ number_format($voter['hearts']) }}</span>
                    </div>
                    <p>{{ $voter['bio'] }}</p>
                    {{-- Relative contribution: the leading voter represents 100%. --}}
                    <progress max="{{ $voters[0]['hearts'] }}" value="{{ $voter['hearts'] }}" aria-label="{{ $voter['nickname'] }} contribution relative to the top voter"></progress>
                </div>
            </li>
        @endforeach
    </ol>
</aside>
