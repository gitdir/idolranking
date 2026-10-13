@extends('layouts.app')

@section('title', $idol['name'].' | IdolRanking')

@section('content')
    <div class="idol-detail">
        <header class="idol-detail-heading">
            <h1><a href="{{ route('idol.show', ['idx' => $idol['idx']]) }}">{{ $idol['name'] }}</a></h1><span>@if (!empty($idol['group_idx']))<a href="{{ route('idol.show', ['idx' => $idol['group_idx']]) }}">{{ $idol['group'] }}</a>@else{{ $idol['group'] }}@endif</span>
        </header>
        <div class="idol-detail-columns">
            <div class="idol-detail-main">
                <div class="idol-detail-images" aria-label="Top three fan-post images by recommendations">
                    @forelse ($idol['popular_images'] as $image)
                        <img src="{{ $image }}" width="320" height="320" alt="Popular Fan Post for {{ $idol['name'] }}" loading="lazy">
                    @empty
                        <p class="fan-post-empty">No public Fan Post images yet.</p>
                    @endforelse
                </div>
                <div class="idol-detail-summary">
                    <dl class="idol-detail-metrics">
                        <div>
                            <dt>Current Monthly Rank</dt>
                            <dd><svg class="detail-crown" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m2 6 5 4 5-7 5 7 5-4-3 13H5Z"/><path stroke="currentColor" stroke-width="2" d="M5 22h14"/></svg>{{ $idol['monthly_rank'] }}</dd>
                        </div>
                        <div>
                            <dt>Monthly Cumulative Votes</dt>
                            <dd><svg class="detail-heart" aria-hidden="true"><use href="#icon-heart"/></svg><span data-vote-total-display="{{ $idol['idx'] }}">{{ number_format($idol['monthly_votes']) }}</span></dd>
                        </div>
                        <div>
                            <dt>Total Fan Posts</dt>
                            <dd><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.5"/><path d="m4 17 5-5 4 4 3-3 5 5"/></svg>{{ number_format($idol['fan_posts']) }}</dd>
                        </div>
                    </dl>
                    <button class="idol-ranking-vote idol-detail-vote" type="button" data-heart-vote-open data-vote-target-id="{{ $idol['idx'] }}" data-vote-name="{{ $idol['name'] }}" data-vote-meta="{{ $idol['group'] }}" data-vote-image="{{ \App\Support\ManagedImageStorage::publicUrl($idol['image_url'] ?? null) }}" data-vote-total="{{ $idol['monthly_votes'] }}" data-vote-posts="{{ $idol['fan_posts'] }}" data-vote-month="{{ now()->format('Y-m') }}" aria-label="Vote for {{ $idol['name'] }}" aria-haspopup="dialog" aria-controls="heart-vote-modal"><svg aria-hidden="true"><use href="#icon-heart"/></svg>Vote for {{ $idol['name'] }}</button>
                </div>
                <section class="idol-detail-box idol-profile" aria-labelledby="profile-title">
                    <h2 id="profile-title">Profile</h2>
                    <table>
                        <caption class="sr-only">{{ $idol['name'] }} profile</caption>
                        <tbody>
                            @foreach ($profile as $label => $value)
                                <tr><th scope="row">{{ $label }}</th><td>@if (in_array($label, ['Name', 'English Name'], true))<a href="{{ route('idol.show', ['idx' => $idol['idx']]) }}">{{ $value }}</a>@elseif ($label === 'Group' && !empty($idol['group_idx']))<a href="{{ route('idol.show', ['idx' => $idol['group_idx']]) }}">{{ $value }}</a>@else{{ $value }}@endif</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            </div>
            <div class="idol-detail-sidebar">
                <section class="idol-detail-box top-voters" aria-labelledby="voters-title">
                    <h2 id="voters-title">Top Voters <span>(This Month)</span></h2>
                    <ol class="top-voters-list">
                        @foreach (array_slice($voters, 0, 5) as $voter)
                            <li>
                                <span class="voter-rank {{ $loop->iteration <= 3 ? 'idol-ranking-badge--'.$loop->iteration : '' }}" aria-label="Rank {{ $loop->iteration }}">{{ $loop->iteration }}</span>
                                <x-member-link :member-code="$voter['member_code'] ?? null" :label="isset($voter['member_code']) ? 'View '.$voter['nickname'].' Fan Posts' : null">
                                    <img src="{{ \App\Support\ManagedImageStorage::publicUrl($voter['image_url'] ?? null) }}" width="44" height="44" alt="{{ $voter['nickname'] }} profile image">
                                </x-member-link>
                                <div class="voter-info">
                                    <div class="voter-name"><h3><x-member-link :member-code="$voter['member_code'] ?? null">{{ $voter['nickname'] }}</x-member-link></h3><span>{{ number_format($voter['votes']) }} <span class="sr-only">monthly votes</span></span></div>
                                    <p>{{ $voter['bio'] }}</p>
                                    <progress value="{{ $voter['votes'] }}" max="{{ max(array_column($voters, 'votes')) }}" aria-label="{{ $voter['nickname'] }} contribution relative to the top voter">{{ $voter['votes'] }}</progress>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    @if (count($voters) === 0)
                        <p class="fan-post-empty">No votes have been recorded this month.</p>
                    @endif
                </section>
                <section class="idol-detail-box voting-trend" aria-labelledby="trend-title">
                    <h2 id="trend-title">Voting Trend</h2>
                    <p class="trend-period">Daily votes · Last 30 days</p>
                    <x-voting-trend :days="$trend" />
                </section>
            </div>
        </div>
        <x-fan-post-list :posts="$posts" empty-message="Be the first to create a Fan Post for this idol!">
            <x-slot:heading>
                <div class="idol-detail-fan-post-heading">
                    <h2 id="fan-posts-title">Fan Posts</h2>
                    <div class="idol-detail-fan-post-actions fan-post-toolbar">
                        <form method="get" action="{{ route('idol.show', ['idx' => $idol['idx']]) }}">
                            <label class="sr-only" for="idol-fan-post-sort">Sort fan posts</label>
                            <select id="idol-fan-post-sort" name="sort" onchange="this.form.submit()">
                                <option value="latest" @selected($fanPostSort === 'latest')>Latest</option>
                                <option value="liked" @selected($fanPostSort === 'liked')>Most Liked</option>
                                <option value="commented" @selected($fanPostSort === 'commented')>Most Commented</option>
                            </select>
                            <noscript><button type="submit">Apply</button></noscript>
                        </form>
                        @if (request()->attributes->get('currentMember'))
                            <a class="idol-detail-create-post" href="{{ route('fan-post.create', ['idol_idx' => $idol['idx']]) }}">+ Create Fan Post</a>
                        @else
                            <button class="idol-detail-create-post" type="button" data-sign-in-open aria-haspopup="dialog" aria-controls="sign-in-modal">+ Create Fan Post</button>
                        @endif
                    </div>
                </div>
            </x-slot:heading>
        </x-fan-post-list>
    </div>
@endsection
