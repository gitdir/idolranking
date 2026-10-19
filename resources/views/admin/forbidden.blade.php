<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>접근 권한 없음 | IDOL RANKING</title>
    @vite('resources/css/admin.css')
</head>
<body class="admin-body admin-access-body">
    <main class="admin-access-card">
        <span aria-hidden="true">!</span>
        <h1>접근 권한이 없습니다</h1>
        <p>관리자만 이 페이지에 접근할 수 있습니다.</p>
        <a href="{{ route('home') }}">홈으로 돌아가기</a>
    </main>
</body>
</html>
