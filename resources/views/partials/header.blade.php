    @php
        $headerMember = request()->attributes->get('currentMember');
        $headerActivityMember = request()->attributes->get('activityMember') ?? $headerMember;
        $isHeaderAdmin = $headerMember !== null && (int) $headerMember->member_level === 10;
        $dummyMembers = $isHeaderAdmin
            ? \Illuminate\Support\Facades\DB::table('members')->where('member_level', 1)->where('is_dummy', 1)
                ->where('status', 'active')->whereNull('deleted_at')->orderBy('nickname')->orderBy('idx')
                ->get(['idx', 'nickname'])
            : collect();
        $selectedDummyIdx = $isHeaderAdmin && (int) $headerActivityMember->idx !== (int) $headerMember->idx
            ? (int) $headerActivityMember->idx : null;
        $headerAvatar = \App\Support\ManagedImageStorage::publicUrl($headerMember?->profile_image_path);
        $inviteRewardAmount = $headerActivityMember
            ? number_format(app(\App\Support\HeartRewardManager::class)->configuredAmount('invite_friend'))
            : '0';
        $drawerStats = $headerMember ? [
            'ranking_rate' => '12.5%',
            'fan_posts' => 28,
            'comments' => 56,
            'hearts' => number_format((int) ($headerActivityMember->heart_balance ?? 0)),
        ] : null;
    @endphp
    <header class="site-header">
        @if ($headerMember)
            <form id="header-logout-form" method="post" action="{{ route('logout') }}" hidden>@csrf</form>
        @endif
        <div class="modal-overlay" id="navigation-overlay" hidden aria-hidden="true"></div>
        <div class="page-width header-inner">
            <a class="brand" href="{{ url('/') }}" aria-label="IdolRanking home">
                <svg class="brand-crown" viewBox="0 0 48 40" aria-hidden="true">
                    <path fill="#ff4596" d="m5 11 11 7L24 3l8 15 11-7-6 23H11Z"/>
                    <path fill="#ff80b6" d="m5 11 11 7L24 3v31H11Z"/>
                    <path stroke="#ff4596" stroke-width="3" d="M11 38h26"/>
                    <g fill="#ff80b6"><circle cx="4" cy="10" r="3"/><circle cx="24" cy="3" r="3"/><circle cx="44" cy="10" r="3"/></g>
                </svg>
                <span><span class="brand-accent brand-accent--animated">IDOL</span> RANKING</span>
            </a>
            <div class="site-search" role="search">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10" cy="10" r="6.5"/><path d="m15 15 5 5"/></svg>
                <label class="sr-only" for="idol-search">Search for idols, groups, or fan posts</label>
                <input id="idol-search" type="search" placeholder="Search for idols, groups, or fan posts..." spellcheck="false">
            </div>
            <button class="menu-toggle" type="button" aria-controls="main-navigation" aria-expanded="false" aria-label="Toggle navigation">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            </button>
            @php
                $detailCategory = request()->routeIs('idol.show') ? ($activeMenu ?? null) : null;
                $activeMainMenu = match (true) {
                    request()->routeIs('ranking.idol') || in_array($detailCategory, ['boy', 'girl'], true) => 'idol',
                    request()->routeIs('ranking.group') || in_array($detailCategory, ['boy-group', 'girl-group'], true) => 'group',
                    request()->routeIs('fan-posts.index', 'fan-post.*') => 'fan-posts',
                    request()->routeIs('get-hearts', 'get-hearts.*') => 'get-hearts',
                    default => null,
                };
            @endphp
            <nav id="main-navigation" class="main-nav" aria-label="Main navigation">
                <div class="drawer-content">
                @foreach (['idol' => 'Idol', 'group' => 'Group'] as $key => $label)
                    @php
                        $routeName = 'ranking.'.$key;
                        $isActive = $activeMainMenu === $key;
                    @endphp
                    <a class="drawer-category-link" href="{{ route($routeName) }}" @if ($isActive) aria-current="page" @endif>
                        <svg class="drawer-category-icon" viewBox="0 0 24 24" aria-hidden="true">
                            @if ($key === 'idol')
                                <path d="m4 8 4 3 4-7 4 7 4-3-2 10H6Z"/><path d="M6 21h12"/>
                            @else
                                <circle cx="9" cy="8" r="4"/><circle cx="16" cy="9" r="3"/><path d="M2.5 21c.5-5 2.8-7.5 6.5-7.5s6 2.5 6.5 7.5M14 14c4.3 0 6.7 2.3 7 7"/>
                            @endif
                        </svg>
                        <span>{{ $label }}</span>
                        <svg class="drawer-category-chevron" viewBox="0 0 16 16" aria-hidden="true"><path d="m6 3 5 5-5 5"/></svg>
                    </a>
                @endforeach
                <a class="drawer-category-link header-fan-posts" href="{{ route('fan-posts.index') }}" @if ($activeMainMenu === 'fan-posts') aria-current="page" @endif>
                    <svg class="drawer-category-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h14v18H5Z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                    <span>Fan Posts</span>
                    <svg class="drawer-category-chevron" viewBox="0 0 16 16" aria-hidden="true"><path d="m6 3 5 5-5 5"/></svg>
                </a>
                <a class="drawer-category-link header-get-hearts" href="{{ route('get-hearts') }}" @if ($activeMainMenu === 'get-hearts') aria-current="page" @endif>
                    <svg class="drawer-category-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21S3 15.5 3 9.5A5 5 0 0 1 12 6a5 5 0 0 1 9 3.5C21 15.5 12 21 12 21Z"/></svg>
                    <span>Get Hearts</span>
                    <svg class="drawer-category-chevron" viewBox="0 0 16 16" aria-hidden="true"><path d="m6 3 5 5-5 5"/></svg>
                </a>
                @if ($headerMember)
                    <a class="drawer-category-link header-register-idol drawer-register-idol-cta" href="{{ route('idol-registration.create') }}" @if (request()->routeIs('idol-registration.*')) aria-current="page" @endif>
                        <svg class="drawer-category-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c.7-4.2 3.4-6.3 8-6.3s7.3 2.1 8 6.3M19 3v6m-3-3h6"/></svg>
                        <span>Register Idol</span>
                        <svg class="drawer-category-chevron" viewBox="0 0 16 16" aria-hidden="true"><path d="m6 3 5 5-5 5"/></svg>
                    </a>
                @else
                    <button class="drawer-category-link header-register-idol drawer-register-idol-cta" type="button" data-sign-in-open aria-haspopup="dialog" aria-controls="sign-in-modal">
                        <svg class="drawer-category-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c.7-4.2 3.4-6.3 8-6.3s7.3 2.1 8 6.3M19 3v6m-3-3h6"/></svg>
                        <span>Register Idol</span>
                        <svg class="drawer-category-chevron" viewBox="0 0 16 16" aria-hidden="true"><path d="m6 3 5 5-5 5"/></svg>
                    </button>
                @endif
                <button class="drawer-invite-card" type="button" data-invite-friends-open data-signed-in="{{ $headerMember ? 'true' : 'false' }}" @unless($headerMember) data-sign-in-open aria-controls="sign-in-modal" @else aria-controls="invite-friends-dialog" @endunless aria-haspopup="dialog">
                    <svg class="drawer-invite-gift" viewBox="0 0 48 48" aria-hidden="true"><path d="M7 20h34v23H7Zm-3-8h40v10H4Z"/><path d="M24 12v31M9 20h30"/><path d="M24 12c-8 0-12-2-12-6 0-3 3-4 5-3 4 1 7 9 7 9Zm0 0c8 0 12-2 12-6 0-3-3-4-5-3-4 1-7 9-7 9Z"/></svg>
                    <span class="drawer-invite-content"><strong>Invite Friends</strong><small>Invite your friends and get <b>{{ $inviteRewardAmount }} Hearts!</b></small></span>
                    <span class="desktop-invite-label">Invite</span>
                    <svg class="drawer-invite-chevron" viewBox="0 0 16 16" aria-hidden="true"><path d="m6 3 5 5-5 5"/></svg>
                </button>
                @if ($headerMember)
                    <section class="drawer-member" aria-label="Account">
                        <x-member-link :member-code="$headerMember->member_code" :label="'View '.$headerMember->nickname.' Fan Posts'" class="drawer-member-avatar-link">
                            <img class="drawer-member-avatar" src="{{ $headerAvatar }}" width="54" height="54" alt="{{ $headerMember->nickname }} profile image">
                        </x-member-link>
                        <div class="drawer-member-details">
                            <strong title="{{ $headerMember->nickname }}"><x-member-link :member-code="$headerMember->member_code">{{ $headerMember->nickname }}</x-member-link></strong>
                            <span title="{{ $headerMember->email }}">{{ $headerMember->email }}</span>
                            <div class="drawer-member-actions">
                                <a href="{{ route('my-page') }}">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c.7-4.2 3.4-6.3 8-6.3s7.3 2.1 8 6.3"/></svg>
                                    My Page
                                </a>
                                <button type="button" data-drawer-logout-open aria-haspopup="dialog" aria-controls="drawer-logout-dialog">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 5V3H4v18h6v-2M14 8l4 4-4 4m4-4H8"/></svg>
                                    Log Out
                                </button>
                            </div>
                            @if ($isHeaderAdmin)
                                @include('partials.acting-dummy-selector', ['location' => 'drawer', 'dummyMembers' => $dummyMembers, 'selectedDummyIdx' => $selectedDummyIdx])
                            @endif
                        </div>
                    </section>
                    <section class="drawer-stats" aria-label="Account statistics">
                        <div class="drawer-stat drawer-stat--pink">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4h8v5a4 4 0 0 1-8 0ZM8 6H4v2a4 4 0 0 0 4 4m8-6h4v2a4 4 0 0 1-4 4M12 13v4m-4 3h8m-6-3h4"/></svg>
                            <strong>{{ $drawerStats['ranking_rate'] }}</strong><span>Ranking Rate</span>
                        </div>
                        <div class="drawer-stat drawer-stat--blue">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6Z"/><path d="M15 3v5h4M9 12h7M9 16h7"/></svg>
                            <strong>{{ $drawerStats['fan_posts'] }}</strong><span>Fan Posts</span>
                        </div>
                        <div class="drawer-stat drawer-stat--blue">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.5 8.5 0 0 1-12.7 7.4L3 21l1.7-5A8.5 8.5 0 1 1 21 11.5Z"/><path d="M8 12h.01M12 12h.01M16 12h.01"/></svg>
                            <strong>{{ $drawerStats['comments'] }}</strong><span>Comments</span>
                        </div>
                        <div class="drawer-stat drawer-stat--pink drawer-stat--heart">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21S3 15.5 3 9.5A5 5 0 0 1 12 6a5 5 0 0 1 9 3.5C21 15.5 12 21 12 21Z"/></svg>
                            <strong data-member-heart-balance>{{ $drawerStats['hearts'] }}</strong><span>My Hearts</span>
                        </div>
                    </section>
                    <div class="header-account" data-header-account>
                        <button class="header-account-trigger" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="header-account-menu">
                            <img class="header-account-trigger-avatar" src="{{ $headerAvatar }}" width="40" height="40" alt="{{ $headerMember->nickname }} profile image">
{{--                            <span class="header-account-trigger-name" title="{{ $headerMember->nickname }}">{{ $headerMember->nickname }}</span>--}}
                            <svg class="header-account-chevron" viewBox="0 0 16 16" aria-hidden="true"><path d="m3 6 5 5 5-5"/></svg>
                        </button>
                        <div id="header-account-menu" class="header-account-menu" role="menu" hidden>
                            <div class="header-account-summary">
                                <x-member-link :member-code="$headerMember->member_code" :label="'View '.$headerMember->nickname.' Fan Posts'" class="header-account-summary-avatar">
                                    <img src="{{ $headerAvatar }}" width="42" height="42" alt="">
                                </x-member-link>
                                <div class="header-account-identity">
                                    <strong title="{{ $headerMember->nickname }}"><x-member-link :member-code="$headerMember->member_code">{{ $headerMember->nickname }}</x-member-link></strong>
                                    <span title="{{ $headerMember->email }}">{{ $headerMember->email }}</span>
                                    <div class="header-account-hearts" aria-label="{{ number_format((int) ($headerActivityMember->heart_balance ?? 0)) }} Hearts">
                                        <span class="header-account-heart-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><use href="#icon-heart"/></svg></span>
                                        <span>{{ number_format((int) ($headerActivityMember->heart_balance ?? 0)) }} Hearts</span>
                                    </div>
                                </div>
                            </div>
                            @if ($isHeaderAdmin)
                                @include('partials.acting-dummy-selector', ['location' => 'desktop', 'dummyMembers' => $dummyMembers, 'selectedDummyIdx' => $selectedDummyIdx])
                            @endif
                            <a class="header-account-action" href="{{ route('my-page') }}" role="menuitem">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c.7-4.2 3.4-6.3 8-6.3s7.3 2.1 8 6.3"/></svg>
                                <span>My Page</span>
                            </a>
                            <button class="header-account-action header-account-logout" type="submit" form="header-logout-form" role="menuitem">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 5V3H4v18h6v-2M14 8l4 4-4 4m4-4H8"/></svg>
                                <span>Log Out</span>
                            </button>
                        </div>
                    </div>
                @else
                    <button class="desktop-sign-in" type="button" data-sign-in-open aria-haspopup="dialog" aria-controls="sign-in-modal">Sign In</button>
                @endif
                <div class="drawer-login" role="group" aria-label="Sign in options">
                    @unless ($headerMember)
                        <a class="drawer-login-button drawer-login-google" href="{{ route('auth.google.redirect', ['return_url' => request()->fullUrl()]) }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285f4" d="M21.6 12.2c0-.7-.1-1.5-.2-2.2H12v4.2h5.4a4.6 4.6 0 0 1-2 3v2.5h3.2c1.9-1.8 3-4.3 3-7.5Z"/><path fill="#34a853" d="M12 22c2.7 0 5-.9 6.6-2.3l-3.2-2.5c-.9.6-2 1-3.4 1-2.6 0-4.9-1.8-5.7-4.2H3v2.6A10 10 0 0 0 12 22Z"/><path fill="#fbbc05" d="M6.3 14a6 6 0 0 1 0-4V7.4H3a10 10 0 0 0 0 9.2Z"/><path fill="#ea4335" d="M12 5.8c1.5 0 2.8.5 3.8 1.5l2.9-2.9A9.6 9.6 0 0 0 12 2a10 10 0 0 0-9 5.4L6.3 10c.8-2.4 3.1-4.2 5.7-4.2Z"/></svg>Google Sign In</a>
                        <button class="drawer-login-button drawer-login-email" type="button" data-sign-in-open aria-haspopup="dialog" aria-controls="sign-in-modal"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/></svg>Email Sign In</button>
                        <div class="drawer-sign-up"><span>Don't have an account?</span><a href="{{ route('register') }}">Sign Up</a></div>
                    @endunless
                </div>
                </div>
                <button class="drawer-close" type="button" aria-label="Close navigation">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </nav>
        </div>
        @if ($headerMember)
            @php($referralUrl = url('/?ref='.rawurlencode($headerActivityMember->member_code)))
            <dialog id="invite-friends-dialog" class="invite-friends-dialog" aria-labelledby="invite-friends-title">
                <button class="invite-friends-close" type="button" data-invite-friends-close aria-label="Close Invite Friends"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
                <h2 id="invite-friends-title">Invite Friends</h2>
                <p>Invite your friends and get {{ $inviteRewardAmount }} Hearts!</p>
                <div class="invite-referral-link">
                    <svg class="invite-referral-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10.5 13.5a4 4 0 0 0 5.7.1l3-3a4 4 0 0 0-5.7-5.7l-1.7 1.7M13.5 10.5a4 4 0 0 0-5.7-.1l-3 3a4 4 0 0 0 5.7 5.7l1.7-1.7"/></svg>
                    <div class="invite-referral-copy">
                        <span>Your Invite Link</span>
                        <input type="text" value="{{ $referralUrl }}" readonly data-referral-url aria-label="Your referral URL">
                    </div>
                    <button type="button" data-referral-copy aria-label="Copy invite link">
                        <svg data-referral-copy-icon viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg>
                        <span data-referral-copied hidden>✓ Copied</span>
                    </button>
                </div>
                <p class="invite-copy-status" data-referral-copy-status role="status" hidden></p>
                <div class="invite-share-actions" aria-label="Share invitation">
                    <a href="https://social-plugins.line.me/lineit/share?url={{ urlencode($referralUrl) }}" target="_blank" rel="noopener">LINE</a>
                    <a href="https://twitter.com/intent/tweet?url={{ urlencode($referralUrl) }}" target="_blank" rel="noopener">X</a>
                    <button type="button" data-invite-share="kakao">KakaoTalk</button>
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($referralUrl) }}" target="_blank" rel="noopener">Facebook</a>
                </div>
                <p class="invite-reward-message">When your friend signs up through your link, you'll get {{ $inviteRewardAmount }} Hearts!</p>
            </dialog>
            <dialog id="drawer-logout-dialog" class="drawer-logout-dialog" aria-labelledby="drawer-logout-title" aria-describedby="drawer-logout-description">
                <div class="drawer-logout-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M10 5V3H4v18h6v-2M14 8l4 4-4 4m4-4H8"/></svg></div>
                <h2 id="drawer-logout-title">Log out?</h2>
                <p id="drawer-logout-description">You will need to sign in again<br>to use your account.</p>
                <div class="drawer-logout-actions">
                    <button type="button" data-drawer-logout-cancel>Cancel</button>
                    <button type="submit" form="header-logout-form">Log Out</button>
                </div>
            </dialog>
        @endif
    </header>
