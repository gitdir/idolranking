<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\IdolController;
use App\Http\Controllers\FanPostController;
use App\Http\Controllers\MyPageController;
use App\Http\Controllers\MemberFanPostController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\IdolRegistrationRequestController;
use App\Http\Controllers\HeartVoteController;
use App\Http\Controllers\PublicFanPostController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\GetHeartsController;
use App\Http\Middleware\RequireMember;
use App\Http\Middleware\RequireAdmin;
use App\Http\Controllers\Admin\IdolRegistrationRequestController as AdminIdolRegistrationRequestController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FanPostReportController as AdminFanPostReportController;
use App\Http\Controllers\Admin\IdolController as AdminIdolController;
use App\Http\Controllers\Admin\MemberController as AdminMemberController;
use App\Http\Controllers\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Admin\HeartController as AdminHeartController;
use App\Http\Controllers\Admin\RestrictedNicknameController as AdminRestrictedNicknameController;
use App\Http\Controllers\Admin\IpAccessController as AdminIpAccessController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\FanPostController as AdminFanPostController;
use App\Http\Controllers\Admin\MemberAccessLogController as AdminMemberAccessLogController;
use App\Support\PublicRanking;
use App\Support\PublicFanPostList;
use App\Support\DatabaseAvailability;

Route::get('/', function (Request $request, PublicFanPostList $fanPosts) {
    $rankings = app(PublicRanking::class);
    $fanPostSort = $request->string('sort')->toString();
    if (!in_array($fanPostSort, ['latest', 'liked', 'commented'], true)) {
        $fanPostSort = 'latest';
    }

    return view('home', [
        'publicRankings' => collect(['boy','girl','boy-group','girl-group'])->mapWithKeys(fn($category) => [$category => $rankings->category($category)])->all(),
        'posts' => DatabaseAvailability::isAvailable()
            ? $fanPosts->take($fanPostSort, 14) : [],
        'fanPostSort' => $fanPostSort,
    ]);
})->name('home');

Route::get('/idol/{idx}', [IdolController::class, 'show'])->whereNumber('idx')->name('idol.show');
Route::get('/fan-posts', PublicFanPostController::class)->name('fan-posts.index');
Route::get('/search', SearchController::class)->name('search');
Route::get('/get-hearts', GetHeartsController::class)->name('get-hearts');
Route::post('/get-hearts/daily-check-in', [GetHeartsController::class, 'claimDailyCheckIn'])
    ->middleware(RequireMember::class)->name('get-hearts.daily-check-in');
Route::get('/fan-post/{idx}', [FanPostController::class, 'show'])->whereNumber('idx')->name('fan-post.show');
Route::get('/fan-post/create', [FanPostController::class, 'create'])
    ->middleware(RequireMember::class)->name('fan-post.create');
Route::post('/fan-post', [FanPostController::class, 'store'])
    ->middleware(RequireMember::class)->name('fan-post.store');
Route::get('/idol-registration/request', [IdolRegistrationRequestController::class, 'create'])
    ->middleware(RequireMember::class)->name('idol-registration.create');
Route::post('/idol-registration/request', [IdolRegistrationRequestController::class, 'store'])
    ->middleware(RequireMember::class)->name('idol-registration.store');
Route::get('/fan-post/{idx}/edit', [FanPostController::class, 'edit'])
    ->middleware(RequireMember::class)->whereNumber('idx')->name('fan-post.edit');
Route::put('/fan-post/{idx}', [FanPostController::class, 'update'])
    ->middleware(RequireMember::class)->whereNumber('idx')->name('fan-post.update');
Route::get('/fan-post/{idx}/edit', [FanPostController::class, 'edit'])
    ->middleware(RequireMember::class)->whereNumber('idx')->name('fan-post.edit');
Route::put('/fan-post/{idx}', [FanPostController::class, 'update'])
    ->middleware(RequireMember::class)->whereNumber('idx')->name('fan-post.update');
Route::post('/fan-post/{idx}/recommendation', [FanPostController::class, 'toggleRecommendation'])
    ->middleware(RequireMember::class)->whereNumber('idx')->name('fan-post.recommendation');
Route::post('/fan-post/{idx}/report', [FanPostController::class, 'report'])
    ->middleware(RequireMember::class)->whereNumber('idx')->name('fan-post.report');
Route::post('/fan-post/{idx}/comments', [FanPostController::class, 'storeComment'])
    ->middleware(RequireMember::class)->whereNumber('idx')->name('fan-post.comments.store');
Route::delete('/fan-post/{idx}/comments/{commentIdx}', [FanPostController::class, 'destroyComment'])
    ->middleware(RequireMember::class)->whereNumber('idx')->whereNumber('commentIdx')->name('fan-post.comments.destroy');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register/email-verification/send', [AuthController::class, 'sendVerificationCode'])
    ->name('register.verification.send');
Route::post('/register/email-verification/verify', [AuthController::class, 'verifyEmailCode'])
    ->name('register.verification.verify');
Route::post('/register/email-verification/reset', [AuthController::class, 'resetEmailVerification'])
    ->name('register.verification.reset');
Route::post('/register', [AuthController::class, 'register'])->name('register.store');
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
Route::get('/auth/google/complete', [GoogleAuthController::class, 'showCompletion'])->name('auth.google.complete');
Route::post('/auth/google/complete', [GoogleAuthController::class, 'complete'])->name('auth.google.complete.store');
Route::post('/logout', [AuthController::class, 'logout'])->middleware(RequireMember::class)->name('logout');
Route::post('/votes', [HeartVoteController::class, 'store'])
    ->middleware(RequireMember::class)->name('votes.store');
Route::view('/forgot-password', 'auth.forgot-password')->name('password.forgot');
Route::get('/reset-password/{token}', function (string $token) {
    // UI preview only: no token verification or password change.
    return view('auth.reset-password');
})->name('password.reset');
Route::get('/my-page', [MyPageController::class, 'show'])->middleware(RequireMember::class)->name('my-page');
Route::get('/my-page/fan-posts', [MemberFanPostController::class, 'mine'])
    ->middleware(RequireMember::class)->name('my-page.fan-posts');
Route::get('/member/{member_code}/fan-posts', [MemberFanPostController::class, 'show'])
    ->name('member.fan-posts');
Route::post('/my-page/password', [MyPageController::class, 'changePassword'])
    ->middleware([RequireMember::class, 'throttle:change-password'])->name('my-page.password');
Route::put('/my-page/profile', [MyPageController::class, 'updateProfile'])
    ->middleware(RequireMember::class)->name('my-page.profile');
Route::post('/my-page/nickname/check', [MyPageController::class, 'checkNickname'])
    ->middleware(RequireMember::class)->name('my-page.nickname.check');

Route::get('/ranking/idol', RankingController::class)->defaults('type', 'idol')->name('ranking.idol');
Route::get('/ranking/group', RankingController::class)->defaults('type', 'group')->name('ranking.group');

foreach ([
    'boy' => ['ranking.idol', 'boy'],
    'girl' => ['ranking.idol', 'girl'],
    'boy-group' => ['ranking.group', 'boy'],
    'girl-group' => ['ranking.group', 'girl'],
] as $oldCategory => [$routeName, $gender]) {
    Route::get('/ranking/'.$oldCategory, function (Request $request) use ($routeName, $gender) {
        $parameters = ['gender' => $gender];
        if (is_string($request->query('month'))) $parameters['month'] = $request->query('month');
        return redirect()->route($routeName, $parameters, 301);
    })->name('ranking.'.$oldCategory);
}

Route::prefix('admin')->name('admin.')->middleware(RequireAdmin::class)->group(function () {
    Route::get('/', AdminDashboardController::class)->name('index');
    Route::get('/idol-requests', AdminIdolRegistrationRequestController::class)->name('idol-requests.index');
    Route::get('/idol-requests/{idx}', [AdminIdolRegistrationRequestController::class, 'show'])->whereNumber('idx')->name('idol-requests.show');
    Route::post('/idol-requests/{idx}/save', [AdminIdolRegistrationRequestController::class, 'save'])->whereNumber('idx')->name('idol-requests.save');
    Route::post('/idol-requests/{idx}/approve', [AdminIdolRegistrationRequestController::class, 'approve'])->whereNumber('idx')->name('idol-requests.approve');
    Route::post('/idol-requests/{idx}/reject', [AdminIdolRegistrationRequestController::class, 'reject'])->whereNumber('idx')->name('idol-requests.reject');
    Route::get('/fan-post-reports', [AdminFanPostReportController::class, 'index'])->name('fan-post-reports.index');
    Route::patch('/fan-post-reports/{idx}/status', [AdminFanPostReportController::class, 'updateStatus'])->whereNumber('idx')->name('fan-post-reports.status');
    Route::get('/fan-posts/{idx}', [AdminFanPostReportController::class, 'showPost'])->whereNumber('idx')->name('fan-posts.show');
    Route::patch('/fan-posts/{idx}/visibility', [AdminFanPostReportController::class, 'updateVisibility'])->whereNumber('idx')->name('fan-posts.visibility');
    Route::get('/idols', fn (Request $request) => app(AdminIdolController::class)->index($request, 'individual'))->name('idols.index');
    Route::get('/groups', fn (Request $request) => app(AdminIdolController::class)->index($request, 'group'))->name('groups.index');
    Route::get('/idols/{idx}/edit', [AdminIdolController::class, 'edit'])->whereNumber('idx')->name('idols.edit');
    Route::put('/idols/{idx}', [AdminIdolController::class, 'update'])->whereNumber('idx')->name('idols.update');
    Route::patch('/idols/{idx}/visibility', [AdminIdolController::class, 'updateVisibility'])->whereNumber('idx')->name('idols.visibility');
    Route::get('/members', [AdminMemberController::class, 'index'])->name('members.index');
    Route::get('/member-access-logs', AdminMemberAccessLogController::class)->name('member-access-logs.index');
    Route::get('/members/{idx}', [AdminMemberController::class, 'show'])->whereNumber('idx')->name('members.show');
    Route::patch('/members/{idx}/status', [AdminMemberController::class, 'updateStatus'])->whereNumber('idx')->name('members.status');
    Route::get('/activity-logs', AdminActivityLogController::class)->name('activity-logs.index');
    Route::get('/fan-posts', AdminFanPostController::class)->name('fan-posts.index');
    Route::get('/hearts', [AdminHeartController::class, 'index'])->name('hearts.index');
    Route::get('/hearts/settings', [AdminHeartController::class, 'settings'])->name('hearts.settings');
    Route::put('/hearts/settings/{actionCode}', [AdminHeartController::class, 'updateSetting'])->name('hearts.settings.update');
    Route::post('/hearts/{idx}/credit', [AdminHeartController::class, 'credit'])->whereNumber('idx')->name('hearts.credit');
    Route::get('/hearts/{idx}', [AdminHeartController::class, 'show'])->whereNumber('idx')->name('hearts.show');
    Route::get('/restricted-nicknames', [AdminRestrictedNicknameController::class, 'index'])->name('restricted-nicknames.index');
    Route::post('/restricted-nicknames', [AdminRestrictedNicknameController::class, 'store'])->name('restricted-nicknames.store');
    Route::put('/restricted-nicknames/{idx}', [AdminRestrictedNicknameController::class, 'update'])->whereNumber('idx')->name('restricted-nicknames.update');
    Route::delete('/restricted-nicknames/{idx}', [AdminRestrictedNicknameController::class, 'destroy'])->whereNumber('idx')->name('restricted-nicknames.destroy');
    Route::get('/ip-access', [AdminIpAccessController::class, 'index'])->name('ip-access.index');
    Route::post('/ip-access', [AdminIpAccessController::class, 'store'])->name('ip-access.store');
    Route::patch('/ip-access/{idx}', [AdminIpAccessController::class, 'toggle'])->whereNumber('idx')->name('ip-access.toggle');
    Route::get('/settings/{section?}', [AdminSettingsController::class, 'edit'])->where('section', 'general|seo|custom-code')->name('settings.edit');
    Route::put('/settings/{section}', [AdminSettingsController::class, 'update'])->where('section', 'general|seo|custom-code')->name('settings.update');
});
