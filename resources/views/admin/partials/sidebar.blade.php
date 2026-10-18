@php
    $adminMenu = [
        ['admin.index', '대시보드', 'home'],
        ['admin.idol-requests.index', '아이돌 등록 신청', 'document'],
        ['admin.idols.index', '아이돌 관리', 'people'],
        ['admin.groups.index', '아이돌 그룹 관리', 'groups'],
        ['admin.fan-posts.index', '팬포스트 관리', 'document'],
        ['admin.fan-post-comment-reports.index', '댓글 신고 관리', 'shield'],
        ['admin.fan-post-reports.index', '팬포스트 신고', 'shield'],
        ['admin.idol-profile-image-reports.index', '프로필 이미지 권리 신고', 'shield'],
        ['admin.partner-banners.index', '파트너 배너 관리', 'document'],
        ['admin.members.index', '회원 관리', 'people', [], [
            ['admin.members.index', '회원 목록'],
            ['admin.member-access-logs.index', '회원 접속 로그'],
        ]],
        ['admin.hearts.index', '하트 관리', 'heart', [], [
            ['admin.hearts.index', '회원 하트 내역'],
            ['admin.hearts.settings', '하트 지급 설정'],
        ]],
        ['admin.restricted-nicknames.index', '닉네임 금지어 관리', 'block'],
        ['admin.ip-access.index', 'IP 접근 제한', 'shield'],
        ['admin.activity-logs.index', '관리자 활동 로그', 'clock'],
        ['admin.settings.edit', '관리 설정', 'settings', ['section' => 'general']],
    ];
    $adminRoutePatterns = [
        'admin.index' => ['admin.index'],
        'admin.idol-requests.index' => ['admin.idol-requests.*'],
        'admin.idols.index' => ['admin.idols.*'],
        'admin.groups.index' => ['admin.groups.*'],
        'admin.fan-posts.index' => ['admin.fan-posts.*'],
        'admin.fan-post-comment-reports.index' => ['admin.fan-post-comment-reports.*', 'admin.fan-post-comments.*'],
        'admin.fan-post-reports.index' => ['admin.fan-post-reports.*'],
        'admin.idol-profile-image-reports.index' => ['admin.idol-profile-image-reports.*'],
        'admin.partner-banners.index' => ['admin.partner-banners.*'],
        'admin.members.index' => ['admin.members.*', 'admin.member-access-logs.*'],
        'admin.member-access-logs.index' => ['admin.member-access-logs.*'],
        'admin.hearts.index' => ['admin.hearts.index', 'admin.hearts.show', 'admin.hearts.settings*'],
        'admin.hearts.settings' => ['admin.hearts.settings*'],
        'admin.restricted-nicknames.index' => ['admin.restricted-nicknames.*'],
        'admin.ip-access.index' => ['admin.ip-access.*'],
        'admin.activity-logs.index' => ['admin.activity-logs.*'],
        'admin.settings.edit' => ['admin.settings.*'],
    ];
    $isAdminMenuRouteActive = fn (string $routeName): bool => request()->routeIs(...($adminRoutePatterns[$routeName] ?? [$routeName]));
    $activeAdminChildPatterns = [
        'admin.members.index' => ['admin.members.index', 'admin.members.show'],
        'admin.member-access-logs.index' => ['admin.member-access-logs.*'],
        'admin.hearts.index' => ['admin.hearts.index', 'admin.hearts.show'],
        'admin.hearts.settings' => ['admin.hearts.settings*'],
    ];
@endphp
<aside class="admin-sidebar" aria-label="관리자 메뉴">
    <nav>
        @foreach ($adminMenu as $menuItem)
            @php
                [$routeName, $label, $icon, $parameters, $children] = array_pad($menuItem, 5, []);
            @endphp
            @if ($children)
                @php
                    $groupIsOpen = $isAdminMenuRouteActive($routeName);
                @endphp
                <div class="admin-sidebar-group" data-sidebar-group data-open="{{ $groupIsOpen ? 'true' : 'false' }}" @if($groupIsOpen) data-active="true" @endif>
                    <button type="button" class="admin-sidebar-group-title" data-sidebar-group-toggle aria-expanded="{{ $groupIsOpen ? 'true' : 'false' }}" aria-controls="admin-sidebar-submenu-{{ str_replace('.', '-', $routeName) }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true">@if($icon === 'heart')<path d="M12 21S3 15.5 3 9.5A5 5 0 0 1 12 6a5 5 0 0 1 9 3.5C21 15.5 12 21 12 21Z"/>@else<circle cx="8" cy="8" r="4"/><circle cx="17" cy="9" r="3"/><path d="M2 21c.5-5 2.5-7 6-7s5.5 2 6 7m1-6c4 0 6 2 7 6"/>@endif</svg>
                        <span>{{ $label }}</span>
                        <svg class="admin-sidebar-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>
                    </button>
                    <div id="admin-sidebar-submenu-{{ str_replace('.', '-', $routeName) }}" class="admin-sidebar-submenu" @if(!$groupIsOpen) hidden @endif>
                        @foreach($children as [$childRoute, $childLabel])
                            @php($childIsActive = request()->routeIs(...($activeAdminChildPatterns[$childRoute] ?? [$childRoute])))
                            <a href="{{ route($childRoute) }}" @if($childIsActive) aria-current="page" @endif>{{ $childLabel }}</a>
                        @endforeach
                    </div>
                </div>
            @else
                <a href="{{ route($routeName, $parameters) }}" @if ($isAdminMenuRouteActive($routeName)) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        @if ($icon === 'home')
                            <path d="M3 11 12 3l9 8v10h-6v-7H9v7H3Z"/>
                        @elseif ($icon === 'people')
                            <circle cx="8" cy="8" r="4"/><circle cx="17" cy="9" r="3"/><path d="M2 21c.5-5 2.5-7 6-7s5.5 2 6 7m1-6c4 0 6 2 7 6"/>
                        @elseif ($icon === 'groups')
                            <circle cx="7" cy="7" r="3"/><circle cx="17" cy="7" r="3"/><circle cx="12" cy="15" r="3"/><path d="M2 12c0-2 2-3 5-3m10 0c3 0 5 1 5 3M6 21c0-3 2-4 6-4s6 1 6 4"/>
                        @elseif ($icon === 'heart')
                            <path d="M12 21S3 15.5 3 9.5A5 5 0 0 1 12 6a5 5 0 0 1 9 3.5C21 15.5 12 21 12 21Z"/>
                        @elseif ($icon === 'shield')
                            <path d="M12 3 20 6v6c0 5-3.4 8-8 10-4.6-2-8-5-8-10V6Z"/><path d="M12 8v5m0 3h.01"/>
                        @elseif ($icon === 'block')
                            <circle cx="12" cy="12" r="9"/><path d="m6 18 12-12"/>
                        @elseif ($icon === 'clock')
                            <circle cx="12" cy="12" r="9"/><path d="M12 7v6l4 2"/>
                        @elseif ($icon === 'settings')
                            <circle cx="12" cy="12" r="3"/><path d="M19 13.5v-3l-2-.7-.7-1.7.9-1.9-2.1-2.1-1.9.9-1.7-.7-.7-2h-3l-.7 2-1.7.7-1.9-.9L2.1 6.2 3 8.1l-.7 1.7-2 .7v3l2 .7.7 1.7-.9 1.9 2.1 2.1 1.9-.9 1.7.7.7 2h3l.7-2 1.7-.7 1.9.9 2.1-2.1-.9-1.9.7-1.7Z"/>
                        @else
                            <rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h6M9 11h6M9 15h4"/>
                        @endif
                    </svg>
                    <span>{{ $label }}</span>
                </a>
            @endif
        @endforeach
    </nav>
</aside>
