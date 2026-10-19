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
                <span><span class="brand-accent">IDOL</span> RANKING</span>
            </a>

            <div class="footer-navigation-group">
                <nav class="footer-navigation" aria-label="{{ __('ui.footer_ranking_navigation') }}">
                    <span class="footer-navigation-row">
                        <a href="{{ route('ranking.idol') }}">{{ __('ui.idol') }}</a>
                        <a href="{{ route('ranking.group') }}">{{ __('ui.group') }}</a>
                        <a href="{{ route('fan-posts.index') }}">{{ __('ui.fan_posts') }}</a>
                    </span>
                    <span class="footer-navigation-row">
                        <a href="{{ route('get-hearts') }}">{{ __('ui.get_hearts') }}</a>
                        <a href="{{ route('attendance') }}">{{ __('ui.attendance') }}</a>
                    </span>
                    @if ((int) ($footerMember?->member_level ?? 0) === 10)
                        <span class="footer-navigation-row"><a href="{{ route('admin.index') }}" target="_blank">{{ __('ui.admin') }}</a></span>
                    @endif
                </nav>
                @if ($footerIsAdmin)
                    <p class="footer-visitor-stats" aria-label="{{ __('ui.footer_visitor_statistics') }}">
                        {{ __('ui.footer_yesterday_uv', ['count' => number_format($footerVisitorStats['yesterdayUv'])]) }}
                        · {{ __('ui.footer_yesterday_pv', ['count' => number_format($footerVisitorStats['yesterdayPv'])]) }}
                        · {{ __('ui.footer_today_uv', ['count' => number_format($footerVisitorStats['todayUv'])]) }}
                        · {{ __('ui.footer_today_pv', ['count' => number_format($footerVisitorStats['todayPv'])]) }}
                    </p>
                @endif
            </div>

            <a class="footer-email" href="mailto:help@idolranking.com">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
                <span>help@idolranking.com</span>
            </a>
        </div>
        <p class="site-footer-copyright">&copy; 2026 IDOL RANKING. {{ __('ui.all_rights_reserved') }}</p>
    </div>
</footer>
