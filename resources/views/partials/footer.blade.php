@php($footerMember = request()->attributes->get('currentMember'))
<footer class="site-footer">
    <div class="page-width site-footer-inner">
        <div class="site-footer-main">
            <a class="footer-brand" href="{{ route('home') }}" aria-label="IdolRanking home">
                <svg class="footer-brand-crown" viewBox="0 0 48 40" aria-hidden="true">
                    <path fill="#ff4596" d="m5 11 11 7L24 3l8 15 11-7-6 23H11Z"/>
                    <path fill="#ff80b6" d="m5 11 11 7L24 3v31H11Z"/>
                    <path stroke="#ff4596" stroke-width="3" d="M11 38h26"/>
                    <g fill="#ff80b6"><circle cx="4" cy="10" r="3"/><circle cx="24" cy="3" r="3"/><circle cx="44" cy="10" r="3"/></g>
                </svg>
                <span><span class="brand-accent">IDOL</span> RANKING</span>
            </a>

            <nav class="footer-navigation" aria-label="Footer ranking navigation">
                @foreach (['boy' => 'Boy', 'boy-group' => 'Boy Group', 'girl' => 'Girl', 'girl-group' => 'Girl Group'] as $category => $label)
                    <a href="{{ route('ranking.'.$category) }}">{{ $label }}</a>
                @endforeach
                @if ((int) ($footerMember?->member_level ?? 0) === 10)
                    <a href="{{ route('admin.index') }}" target="_blank">Admin</a>
                @endif
            </nav>

            <a class="footer-email" href="mailto:help@idolranking.com">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
                <span>help@idolranking.com</span>
            </a>
        </div>
        <p class="site-footer-copyright">&copy; 2026 IDOL RANKING. All rights reserved.</p>
    </div>
</footer>
