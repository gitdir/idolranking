@php
    $footerMember = request()->attributes->get('currentMember');
    $footerIsAdmin = (int) ($footerMember?->member_level ?? 0) === 10
        && (int) ($footerMember?->is_dummy ?? 0) !== 1;
@endphp
<footer class="site-footer">
    <div class="page-width site-footer-inner">
        <div class="site-footer-main">
            <a class="footer-brand" href="{{ route('home') }}" aria-label="{{ __('ui.home_aria') }}">
                <svg class="footer-brand-crown" viewBox="0 0 48 40" aria-hidden="true">
                    <path fill="#ff4596" d="m5 11 11 7L24 3l8 15 11-7-6 23H11Z"/>
                    <path fill="#ff80b6" d="m5 11 11 7L24 3v31H11Z"/>
                    <path stroke="#ff4596" stroke-width="3" d="M11 38h26"/>
                    <g fill="#ff80b6"><circle cx="4" cy="10" r="3"/><circle cx="24" cy="3" r="3"/><circle cx="44" cy="10" r="3"/></g>
                </svg>
                <span translate="no"><span class="brand-accent">IDOL</span> RANKING</span>
            </a>

            <div class="footer-navigation-group">
                <nav class="footer-navigation" aria-label="{{ __('ui.footer_ranking_navigation') }}">
                    <span class="footer-navigation-row">
                        <a translate="no" href="{{ route('ranking.idol') }}">{{ __('ui.idol') }}</a>
                        <a translate="no" href="{{ route('ranking.group') }}">{{ __('ui.group') }}</a>
                        <a translate="no" href="{{ route('fan-posts.index') }}">{{ __('ui.fan_posts') }}</a>
                    </span>
                    <span class="footer-navigation-row">
                        <a translate="no" href="{{ route('get-hearts') }}">{{ __('ui.get_hearts') }}</a>
                        <a translate="no" href="{{ route('attendance') }}">{{ __('ui.attendance') }}</a>
                    </span>
                    @if ((int) ($footerMember?->member_level ?? 0) === 10)
                        <span class="footer-navigation-row"><a translate="no" href="{{ route('admin.index') }}" target="_blank">{{ __('ui.admin') }}</a></span>
                    @endif
                </nav>
                @if ($footerIsAdmin)
                    <p class="footer-visitor-stats" translate="no" aria-label="방문 통계">
                        어제 UV : {{ number_format($footerVisitorStats['yesterdayUv']) }}
                        · 어제 PV : {{ number_format($footerVisitorStats['yesterdayPv']) }}
                        · 오늘 UV : {{ number_format($footerVisitorStats['todayUv']) }}
                        · 오늘 PV : {{ number_format($footerVisitorStats['todayPv']) }}
                    </p>
                @endif
            </div>

            <div class="footer-language-selector-slot" data-mobile-language-selector-slot></div>

            <div class="footer-contact-group">
                <a class="footer-email" href="mailto:help@idolranking.com">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
                    <span>help@idolranking.com</span>
                </a>
                <div class="footer-language-selector-slot" data-desktop-language-selector-slot>
                    <form class="language-selector" method="post" action="{{ route('language.update') }}" data-language-selector>
                        @csrf
                        <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                        <span class="language-selector-label">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18m0-18a14 14 0 0 0 0 18"/></svg>
                            <span>{{ __('ui.language_settings') }}</span>
                        </span>
                        <select id="public-language" name="locale" aria-label="{{ __('ui.select_language') }}" onchange="this.form.submit()">
                            @foreach (config('locales.public', []) as $localeCode => $localeName)
                                <option value="{{ $localeCode }}" @selected(app()->getLocale() === $localeCode)>{{ $localeName }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>
        </div>
        <nav class="footer-legal-links" aria-label="{{ __('legal.footer_navigation') }}">
            <a href="{{ route('privacy') }}">{{ __('legal.footer_privacy') }}</a>
            <a href="{{ route('terms') }}">{{ __('legal.footer_terms') }}</a>
        </nav>
        <p class="site-footer-copyright">&copy; {{ date('Y') }} <span translate="no">IDOL RANKING.</span> {{ __('ui.all_rights_reserved') }}</p>
    </div>
</footer>
