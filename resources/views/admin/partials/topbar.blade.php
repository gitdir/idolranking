@php($adminMember = request()->attributes->get('currentMember'))
<header class="admin-topbar">
    <a class="admin-brand" href="{{ route('admin.index') }}" aria-label="IDOL RANKING 관리자 홈">
        <svg viewBox="0 0 48 40" aria-hidden="true"><path fill="#ff4596" d="m5 11 11 7L24 3l8 15 11-7-6 23H11Z"/><path fill="#ff80b6" d="m5 11 11 7L24 3v31H11Z"/><path stroke="#ff4596" stroke-width="3" d="M11 38h26"/><g fill="#ff80b6"><circle cx="4" cy="10" r="3"/><circle cx="24" cy="3" r="3"/><circle cx="44" cy="10" r="3"/></g></svg>
        <strong translate="no"><span>IDOL</span> RANKING</strong>
        <i aria-hidden="true"></i>
        <em>관리자</em>
    </a>
    <div class="admin-account" data-admin-account>
        <button class="admin-account-trigger" type="button" aria-expanded="false" aria-controls="admin-account-menu">
            <img src="{{ \App\Support\ManagedImageStorage::defaultUrl() }}" width="42" height="42" alt="">
            <span translate="no">{{ $adminMember?->nickname ?: '관리자' }}</span>
            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m3 6 5 5 5-5"/></svg>
        </button>
        <div id="admin-account-menu" class="admin-account-menu" role="group" aria-label="관리자 계정 메뉴" hidden>
            <a href="{{ route('my-page') }}" data-admin-account-item>{{ __('ui.my_page', [], 'ko') }}</a>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button type="submit" data-admin-account-item>{{ __('ui.log_out', [], 'ko') }}</button>
            </form>
        </div>
    </div>
    <button class="admin-nav-toggle" type="button" data-admin-nav-open aria-controls="admin-sidebar" aria-expanded="false" aria-label="관리자 메뉴 열기">
        <span></span><span></span><span></span>
    </button>
</header>
