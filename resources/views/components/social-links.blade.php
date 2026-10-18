@if ($links !== [])
    <div class="social-link-icons" aria-label="{{ __('ui.profile_label_sns') }}">
        @foreach ($links as $link)
            <a class="social-link-icon social-link-icon--{{ $link['platform'] }}" href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $link['label'] }}" title="{{ $link['label'] }}">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    @switch($link['platform'])
                        @case('instagram')
                            <rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".8" class="social-link-icon-dot"/>
                            @break
                        @case('youtube')
                            <rect x="2.5" y="5" width="19" height="14" rx="4"/><path class="social-link-icon-fill" d="m10 9 6 3-6 3z"/>
                            @break
                        @case('tiktok')
                            <path class="social-link-icon-fill" d="M14 3h3c.2 2.2 1.5 3.7 4 4v3.1a9 9 0 0 1-4-1.4v6.5a6 6 0 1 1-6-6h.8v3.2H11a2.8 2.8 0 1 0 2.8 2.8z"/>
                            @break
                        @case('x')
                            <path class="social-link-icon-fill" d="M18.9 2H22l-6.8 7.8L23 22h-6.2l-4.9-7.2L5.6 22H2.4l7.3-8.4L2 2h6.4l4.4 6.6zM17.8 20h1.7L7.2 3.9H5.4z"/>
                            @break
                        @case('facebook')
                            <path class="social-link-icon-fill" d="M13.8 21v-8h2.7l.4-3.1h-3.1v-2c0-.9.3-1.5 1.6-1.5H17V3.6c-.7-.1-1.6-.2-2.5-.2-2.5 0-4.2 1.5-4.2 4.3v2.2H7.5V13h2.8v8z"/>
                            @break
                        @case('threads')
                            <path d="M19.5 12.5c-.2-4.8-2.7-8-7.1-8-4.3 0-7 3.1-7 7.5 0 4.5 2.6 7.5 7 7.5 3.2 0 5.2-1.7 5.2-4.2 0-2.1-1.4-3.4-3.7-3.4-2 0-3.2 1-3.2 2.5 0 1.1.8 1.9 2.1 1.9 1.6 0 2.7-1.2 2.7-3.2 0-3.1-1.6-5-4.2-5-2.6 0-4.1 1.8-4.1 4.7"/><path d="M16.4 12.2c1.9.4 3.1 1.6 3.1 3.3"/>
                            @break
                        @case('linkedin')
                            <path class="social-link-icon-fill" d="M5 8.5A1.8 1.8 0 1 0 5 5a1.8 1.8 0 0 0 0 3.5M3.5 10h3v10h-3zm5.2 0h2.9v1.4h.1a3.2 3.2 0 0 1 2.9-1.6c3.1 0 3.7 2 3.7 4.6V20h-3v-5c0-1.2 0-2.7-1.7-2.7s-2 1.3-2 2.6V20h-3z"/>
                            @break
                        @case('twitch')
                            <path d="M5 3h16v12l-4 4h-4l-3 3v-3H6V5z"/><path d="M11 7v5m5-5v5"/>
                            @break
                        @case('pinterest')
                            <path class="social-link-icon-fill" d="M12 2a10 10 0 0 0-3.6 19.3c0-.8 0-1.8.2-2.7l1.3-5.5s-.3-.6-.3-1.5c0-1.4.8-2.4 1.8-2.4.9 0 1.3.7 1.3 1.5 0 .9-.6 2.2-.9 3.4-.3 1 .5 1.8 1.5 1.8 1.8 0 3.2-1.9 3.2-4.7 0-2.4-1.7-4-4.1-4-2.8 0-4.5 2.1-4.5 4.3 0 .9.3 1.9.8 2.4.1.2.2.3.1.5l-.3 1c0 .2-.2.3-.4.2-1.3-.6-2.1-2.4-2.1-3.9 0-3.2 2.3-6.1 6.7-6.1 3.5 0 6.2 2.5 6.2 5.8 0 3.5-2.2 6.4-5.3 6.4-1 0-2-.6-2.4-1.2l-.7 2.7c-.2 1-.9 2-1.3 2.7A10 10 0 1 0 12 2"/>
                            @break
                        @case('snapchat')
                            <path d="M12 3.2c-2.5 0-3.7 1.8-3.7 4.2 0 1-.1 1.7-.2 2.1-.1.4-.7.4-1.2.1-.5-.3-1-.2-1.2.2-.2.4.1.9.7 1.2.6.4 1.1.5 1.1 1.1 0 .5-.7 1.6-2 2.6-1 .8-2 1.1-2 1.6 0 .6 1.2.9 2.1 1.1.6.1.7.5.9 1.1.1.4.4.5 1 .4.8-.1 1.6-.1 2.4.3.6.3 1.1.7 2.1.7s1.5-.4 2.1-.7c.8-.4 1.6-.4 2.4-.3.6.1.9 0 1-.4.2-.6.3-1 1-1.1.8-.2 2-.5 2-1.1 0-.5-1-.8-2-1.6-1.3-1-2-2.1-2-2.6 0-.6.5-.7 1.1-1.1.6-.3.9-.8.7-1.2-.2-.4-.7-.5-1.2-.2-.5.3-1.1.3-1.2-.1-.1-.4-.2-1.1-.2-2.1 0-2.4-1.2-4.2-3.7-4.2z"/>
                            @break
                        @case('reddit')
                            <circle cx="12" cy="13" r="8"/><path d="m9 17 1.5 1h3l1.5-1M8.5 12h.1m6.8 0h.1M10 14.5c1.2.8 2.8.8 4 0M14 5l1-3 3 1"/><circle cx="18" cy="3" r="1"/>
                            @break
                        @case('discord')
                            <path class="social-link-icon-fill" d="M19.6 5.2A18 18 0 0 0 15.2 4l-.5 1a16 16 0 0 0-5.4 0l-.5-1a18 18 0 0 0-4.4 1.2C1.6 9.3.8 13.3 1.2 17.3A18 18 0 0 0 6.6 20l1.2-2a11 11 0 0 1-1.8-.9l.4-.3c3.5 1.6 7.5 1.6 11 0l.4.3a11 11 0 0 1-1.8.9l1.2 2a18 18 0 0 0 5.4-2.7c.5-4.7-.8-8.6-3-12.1ZM8.7 15.2c-1 0-1.8-.9-1.8-2s.8-2 1.8-2 1.8.9 1.8 2-.8 2-1.8 2Zm6.6 0c-1 0-1.8-.9-1.8-2s.8-2 1.8-2 1.8.9 1.8 2-.8 2-1.8 2Z"/>
                            @break
                        @case('weibo')
                            <path d="M5 13c2-4 7-6 11-4M4 16c3-3 8-4 12-2M18 5c2 1 3 3 2 5"/><circle cx="16" cy="16" r="4"/><circle cx="16" cy="16" r="1" class="social-link-icon-fill"/>
                            @break
                        @case('bilibili')
                            <path d="m8 3 3 3m5-3-3 3M4 8h16v12H4zM8 12v2m8-2v2"/>
                            @break
                        @case('telegram')
                            <path class="social-link-icon-fill" d="m22 3-3.3 18-7-5.2-3.7 3.5.5-6.4L20 5 6.1 11.3l-4.8-1.7z"/>
                            @break
                        @case('line')
                            <path d="M21 11c0 4.5-4 8-9 8-.8 0-1.6-.1-2.3-.3L5 21l1.2-3.4C4.2 16.2 3 13.8 3 11c0-4.4 4-8 9-8s9 3.6 9 8Z"/><path d="M7 10v4m2-4v4l3-4v4m2-4v4l3-4v4"/>
                            @break
                        @default
                            <path d="M10 13a5 5 0 0 0 7.1 0l2-2A5 5 0 0 0 12 3.9l-1.1 1.1M14 11a5 5 0 0 0-7.1 0l-2 2A5 5 0 0 0 12 20.1l1.1-1.1"/>
                    @endswitch
                </svg>
            </a>
        @endforeach
    </div>
@endif
