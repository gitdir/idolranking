@extends('layouts.app')

@section('title', $seo['title'])

@section('content')
    <div class="idol-detail">
        <header class="idol-detail-heading">
            <h1><a translate="no" href="{{ route('idol.show', ['idx' => $idol['idx']]) }}">{{ $idol['name'] }}</a></h1><span translate="no">@if (!empty($idol['group_idx']))<a href="{{ route('idol.show', ['idx' => $idol['group_idx']]) }}">{{ $idol['group'] }}</a>@else{{ $idol['group'] }}@endif</span>
        </header>
        <div class="idol-detail-columns">
            <div class="idol-detail-main">
                <div class="idol-detail-images" aria-label="{{ __('ui.popular_idol_images') }}">
                    @foreach ($idol['popular_images'] as $image)
                        <img src="{{ $image }}" width="320" height="320" alt="{{ __('ui.popular_fan_post', ['name' => $idol['name']]) }}" loading="lazy">
                    @endforeach
                </div>
                <div class="idol-detail-summary">
                    <dl class="idol-detail-metrics">
                        <div>
                            <dt>{{ __('ui.current_month_rank') }}</dt>
                            <dd><svg class="detail-crown" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m2 6 5 4 5-7 5 7 5-4-3 13H5Z"/><path stroke="currentColor" stroke-width="2" d="M5 22h14"/></svg>{{ $idol['monthly_rank'] }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('ui.monthly_votes') }}</dt>
                            <dd><svg class="detail-heart" aria-hidden="true"><use href="#icon-heart"/></svg><span data-vote-total-display="{{ $idol['idx'] }}">{{ number_format($idol['monthly_votes']) }}</span></dd>
                        </div>
                        <div>
                            <dt>{{ __('ui.total_fan_posts') }}</dt>
                            <dd><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.5"/><path d="m4 17 5-5 4 4 3-3 5 5"/></svg>{{ number_format($idol['fan_posts']) }}</dd>
                        </div>
                    </dl>
                    <button class="idol-ranking-vote idol-detail-vote" type="button" data-heart-vote-open data-vote-target-id="{{ $idol['idx'] }}" data-vote-name="{{ $idol['name'] }}" data-vote-meta="{{ $idol['group'] }}" data-vote-image="{{ \App\Support\ManagedImageStorage::publicUrl($idol['image_url'] ?? null) }}" data-vote-total="{{ $idol['monthly_votes'] }}" data-vote-posts="{{ $idol['fan_posts'] }}" data-vote-month="{{ now()->format('Y-m') }}" aria-label="{{ __('ui.vote_for_idol', ['name' => $idol['name']]) }}" aria-haspopup="dialog" aria-controls="heart-vote-modal"><svg aria-hidden="true"><use href="#icon-heart"/></svg>{{ __('ui.vote_for_idol', ['name' => $idol['name']]) }}</button>
                </div>
                <section class="idol-detail-box idol-profile" aria-labelledby="profile-title">
                    <div class="idol-profile-heading"><h2 id="profile-title">{{ __('ui.profile') }}</h2>
                        @if ($idol['profile_image_reportable'])
                            <button class="idol-profile-image-report" type="button"
                                data-idol-image-report-open data-signed-in="{{ request()->attributes->get('currentMember') ? 'true' : 'false' }}"
                                data-endpoint="{{ route('idol.profile-image.report', ['idx' => $idol['idx']]) }}"
                                data-reported-label="{{ __('ui.profile_image_already_reported_short') }}"
                                @if ($idol['profile_image_already_reported']) disabled @endif
                                aria-haspopup="dialog" aria-controls="idol-image-report-modal">
                                {{ $idol['profile_image_already_reported'] ? __('ui.profile_image_already_reported_short') : __('ui.report_profile_image') }}
                            </button>
                        @endif
                    </div>
                    @if ($idol['profile_image_reportable'])
                        <p class="idol-profile-image-report-success" data-idol-image-report-success role="status" hidden></p>
                    @endif
                    <table>
                        <caption class="sr-only">{{ __('ui.profile_caption', ['name' => $idol['name']]) }}</caption>
                        <colgroup>
                            <col class="idol-profile-label-column">
                            <col>
                        </colgroup>
                        <tbody>
                            @foreach ($profile as $label => $value)
                                <tr>
                                    <th scope="row">{{ __('ui.profile_label_'.preg_replace('/[^a-z0-9]+/', '_', strtolower($label))) }}</th>
                                    <td>
                                        @if ($label === 'SNS')
                                            <x-social-links :links="$value" />
                                        @elseif ($label === 'Debut Dates')
                                            <div class="idol-profile-debut-list">
                                                @foreach ($debutDateEntries as $debut)
                                                    <div class="idol-profile-debut-entry">
                                                        <x-country-flag :code="$debut['country_code']" />
                                                        <span>{{ $debut['country'] }}</span>
                                                        <time datetime="{{ $debut['iso_date'] }}">{{ $debut['date'] }}</time>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @elseif (in_array($label, ['Name', 'English Name'], true))
                                            <a translate="no" href="{{ route('idol.show', ['idx' => $idol['idx']]) }}">{{ $value }}</a>
                                        @elseif ($label === 'Group' && !empty($idol['group_idx']))
                                            <a translate="no" href="{{ route('idol.show', ['idx' => $idol['group_idx']]) }}">{{ $value }}</a>
                                        @elseif ($label === 'Type')
                                            {{ $value === 'Group' ? __('ui.group') : __('ui.individual') }}
                                        @elseif ($label === 'Group' && in_array($value, ['Solo', 'Boy Group', 'Girl Group'], true))
                                            {{ __('ui.'.str_replace(' ', '_', strtolower($value))) }}
                                        @elseif ($label === 'Description')
                                            <span class="idol-profile-description">{{ $value }}</span>
                                        @else
                                            {{ $value }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            </div>
            <div class="idol-detail-sidebar">
                <section class="idol-detail-box top-voters" aria-labelledby="voters-title">
                    <h2 id="voters-title">{{ __('ui.top_voters') }} <span>({{ __('ui.this_month') }})</span></h2>
                    <ol class="top-voters-list">
                        @foreach (array_slice($voters, 0, 5) as $voter)
                            <li>
                                <span class="voter-rank {{ $loop->iteration <= 3 ? 'idol-ranking-badge--'.$loop->iteration : '' }}" aria-label="{{ __('ui.rank_aria', ['rank' => $loop->iteration]) }}">{{ $loop->iteration }}</span>
                                <x-member-link :member-code="$voter['member_code'] ?? null" :label="isset($voter['member_code']) ? __('ui.member_view_posts_label', ['name' => $voter['nickname']]) : null">
                                    <img class="top-voter-avatar" src="{{ \App\Support\ManagedImageStorage::publicUrl($voter['image_url'] ?? null) }}" width="44" height="44" alt="{{ __('ui.profile_image_alt', ['name' => $voter['nickname']]) }}">
                                </x-member-link>
                                <div class="voter-info">
                                    <div class="voter-name"><h3 translate="no"><x-country-flag :code="$voter['country_code'] ?? null" /><x-member-link :member-code="$voter['member_code'] ?? null">{{ $voter['nickname'] }}</x-member-link></h3><span>{{ number_format($voter['votes']) }} <span class="sr-only">{{ __('ui.monthly_votes_aria') }}</span></span></div>
                                    <p>{{ $voter['bio'] }}</p>
                                    <progress value="{{ $voter['votes'] }}" max="{{ max(array_column($voters, 'votes')) }}" aria-label="{{ __('ui.voter_contribution_aria', ['name' => $voter['nickname']]) }}">{{ $voter['votes'] }}</progress>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    @if (count($voters) === 0)
                        <p class="fan-post-empty">{{ __('ui.no_vote_history') }}</p>
                    @endif
                </section>
                <section class="idol-detail-box voting-trend" aria-labelledby="trend-title">
                    <h2 id="trend-title">{{ __('ui.voting_trend') }}</h2>
                    <p class="trend-period">{{ __('ui.daily_votes_period') }}</p>
                    <x-voting-trend :days="$trend" />
                </section>
            </div>
        </div>
        <x-fan-post-list :posts="$posts" :empty-message="__('ui.be_first_fan_post')">
            <x-slot:heading>
                <div class="idol-detail-fan-post-heading">
                    <h2 id="fan-posts-title">{{ __('ui.fan_posts') }}</h2>
                    <div class="idol-detail-fan-post-actions fan-post-toolbar">
                        <form method="get" action="{{ route('idol.show', ['idx' => $idol['idx']]) }}">
                            <label class="sr-only" for="idol-fan-post-sort">{{ __('ui.sort_fan_posts') }}</label>
                            <select id="idol-fan-post-sort" name="sort" onchange="this.form.submit()">
                                <option value="latest" @selected($fanPostSort === 'latest')>{{ __('ui.latest') }}</option>
                                <option value="monthly_liked" @selected($fanPostSort === 'monthly_liked')>{{ __('ui.monthly_liked') }}</option>
                                <option value="liked" @selected($fanPostSort === 'liked')>{{ __('ui.most_liked') }}</option>
                                <option value="commented" @selected($fanPostSort === 'commented')>{{ __('ui.most_commented') }}</option>
                            </select>
                            <noscript><button type="submit">{{ __('ui.apply') }}</button></noscript>
                        </form>
                        @if (request()->attributes->get('currentMember'))
                            <a class="idol-detail-create-post" href="{{ route('fan-post.create', ['idol_idx' => $idol['idx']]) }}">{{ __('ui.more_idol_fan_posts') }}</a>
                        @else
                            <button class="idol-detail-create-post" type="button" data-sign-in-open aria-haspopup="dialog" aria-controls="sign-in-modal">{{ __('ui.more_idol_fan_posts') }}</button>
                        @endif
                    </div>
                </div>
            </x-slot:heading>
        </x-fan-post-list>
    </div>
@endsection
