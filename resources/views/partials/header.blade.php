    <header class="site-header">
        <div class="page-width header-inner">
            <a class="brand" href="{{ url('/') }}" aria-label="IdolRanking home">
                <svg class="brand-crown" viewBox="0 0 48 40" aria-hidden="true">
                    <path fill="#ff4596" d="m5 11 11 7L24 3l8 15 11-7-6 23H11Z"/>
                    <path fill="#ff80b6" d="m5 11 11 7L24 3v31H11Z"/>
                    <path stroke="#ff4596" stroke-width="3" d="M11 38h26"/>
                    <g fill="#ff80b6"><circle cx="4" cy="10" r="3"/><circle cx="24" cy="3" r="3"/><circle cx="44" cy="10" r="3"/></g>
                </svg>
                <span><span class="brand-accent">IDOL</span> RANKING</span>
            </a>
            <div class="site-search" role="search">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10" cy="10" r="6.5"/><path d="m15 15 5 5"/></svg>
                <label class="sr-only" for="idol-search">Search for idols, groups, or fan posts</label>
                <input id="idol-search" type="search" placeholder="Search for idols, groups, or fan posts..." readonly aria-disabled="true" title="Search is not available in this preview">
            </div>
            <button class="menu-toggle" type="button" aria-controls="main-navigation" aria-expanded="false" aria-label="Toggle navigation">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            </button>
            <nav id="main-navigation" class="main-nav" aria-label="Main navigation">
                <div class="drawer-content">
                @foreach (['boy' => 'Boy', 'boy-group' => 'Boy Group', 'girl' => 'Girl', 'girl-group' => 'Girl Group'] as $key => $label)
                    @php
                        // Future pages may pass activeMenu or use ranking.{category} route names.
                        $routeName = 'ranking.'.$key;
                        $isActive = isset($activeMenu)
                            ? $activeMenu === $key
                            : request()->routeIs($routeName, $routeName.'.*');
                        $menuUrl = \Illuminate\Support\Facades\Route::has($routeName)
                            ? route($routeName)
                            : url('/').'#ranking-'.$key;
                    @endphp
                    <a href="{{ $menuUrl }}" @if ($isActive) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
                <button class="desktop-sign-in" type="button" aria-disabled="true" title="Sign in is not available in this preview">Sign In</button>
                <div class="drawer-login" role="group" aria-label="Sign in options">
                    <button class="drawer-login-button drawer-login-google" type="button" aria-disabled="true" title="Google sign in is not available in this preview">Google Sign In</button>
                    <button class="drawer-login-button drawer-login-email" type="button" aria-disabled="true" title="Email sign in is not available in this preview">Email Sign In</button>
                </div>
                </div>
                <button class="drawer-close" type="button" aria-label="Close navigation">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </nav>
        </div>
    </header>
    <div class="modal-overlay" id="navigation-overlay" hidden aria-hidden="true"></div>
