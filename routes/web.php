<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\IdolController;
use App\Http\Controllers\FanPostController;
use App\Http\Controllers\MyPageController;
use App\Http\Controllers\MemberFanPostController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\IdolRegistrationRequestController;
use App\Http\Controllers\HeartVoteController;
use App\Http\Controllers\PublicFanPostController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\GetHeartsController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\IdolProfileImageReportController;
use App\Http\Middleware\RequireMember;
use App\Http\Middleware\RequireAdmin;
use App\Http\Controllers\Admin\IdolRegistrationRequestController as AdminIdolRegistrationRequestController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FanPostReportController as AdminFanPostReportController;
use App\Http\Controllers\Admin\FanPostCommentReportController as AdminFanPostCommentReportController;
use App\Http\Controllers\Admin\IdolProfileImageReportController as AdminIdolProfileImageReportController;
use App\Http\Controllers\Admin\IdolController as AdminIdolController;
use App\Http\Controllers\Admin\MemberController as AdminMemberController;
use App\Http\Controllers\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Admin\HeartController as AdminHeartController;
use App\Http\Controllers\Admin\RestrictedNicknameController as AdminRestrictedNicknameController;
use App\Http\Controllers\Admin\IpAccessController as AdminIpAccessController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\FanPostController as AdminFanPostController;
use App\Http\Controllers\Admin\MemberAccessLogController as AdminMemberAccessLogController;
use App\Http\Controllers\Admin\PartnerBannerController as AdminPartnerBannerController;
use App\Http\Controllers\Admin\ActingDummyMemberController;
use App\Support\PublicRanking;
use App\Support\PublicFanPostList;

Route::post('/language', [LocaleController::class, 'update'])->name('language.update');

Route::get('/', function (Request $request, PublicFanPostList $fanPosts) {
    $rankings = app(PublicRanking::class);
    $fanPostSort = $request->string('sort')->toString();
    if (!in_array($fanPostSort, ['latest', 'monthly_liked', 'liked', 'commented'], true)) {
        $fanPostSort = 'latest';
    }
    $publicRankings = collect(['boy', 'girl', 'boy-group', 'girl-group'])
        ->mapWithKeys(fn ($category) => [$category => $rankings->category($category)])->all();
    $rankingImages = collect($publicRankings)->mapWithKeys(fn (array $rows, string $category) => [
        $category => isset($rows[0])
            ? $rankings->representativeImages((int) $rows[0]['idx'], $rows[0]['image_url'] ?? null)
            : [],
    ])->all();

    return view('home', [
        'publicRankings' => $publicRankings,
        'rankingImages' => $rankingImages,
        'posts' => $fanPosts->take($fanPostSort, 14),
        'fanPostSort' => $fanPostSort,
    ]);
})->name('home');

Route::get('/idol/{idx}', [IdolController::class, 'show'])->whereNumber('idx')->name('idol.show');
Route::post('/idol/{idx}/profile-image/report', [IdolProfileImageReportController::class, 'store'])
    ->middleware(RequireMember::class)->whereNumber('idx')->name('idol.profile-image.report');
Route::get('/fan-posts', PublicFanPostController::class)->name('fan-posts.index');
Route::get('/search', SearchController::class)->name('search');
Route::get('/get-hearts', GetHeartsController::class)->name('get-hearts');
Route::get('/fan-garden', fn () => abort(404))->name('fan-garden.index');
Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance');
Route::get('/attendance/{idx}/translation', [AttendanceController::class, 'translation'])
    ->whereNumber('idx')->name('attendance.translation');
Route::get('/partner-banners/{idx}/click', [GetHeartsController::class, 'clickPartnerBanner'])
    ->whereNumber('idx')->name('partner-banners.click');
Route::post('/get-hearts/daily-check-in', [GetHeartsController::class, 'claimDailyCheckIn'])
    ->middleware(RequireMember::class)->name('get-hearts.daily-check-in');
Route::post('/get-hearts/partner-banners/{idx}/claim', [GetHeartsController::class, 'claimPartnerBanner'])
    ->whereNumber('idx')->middleware(RequireMember::class)->name('get-hearts.partner-banners.claim');
Route::get('/fan-post/{idx}', [FanPostController::class, 'show'])->whereNumber('idx')->name('fan-post.show');
Route::get('/fan-post/{idx}/translation', [FanPostController::class, 'translation'])
    ->whereNumber('idx')->name('fan-post.translation');
Route::get('/fan-post/{idx}/comments/{commentIdx}/translation', [FanPostController::class, 'commentTranslation'])
    ->whereNumber('idx')->whereNumber('commentIdx')->name('fan-post.comments.translation');
Route::get('/fan-post/create', [FanPostController::class, 'create'])
    ->middleware(RequireMember::class)->name('fan-post.create');
Route::get('/fan-post', fn () => redirect()->route('fan-posts.index'));
Route::post('/fan-post', [FanPostController::class, 'store'])
    ->middleware(RequireMember::class)->name('fan-post.store');
Route::get('/idol-registration/request', [IdolRegistrationRequestController::class, 'create'])
    ->middleware(RequireMember::class)->name('idol-registration.create');
Route::get('/idol-registration/duplicates', [IdolRegistrationRequestController::class, 'duplicateSearch'])
    ->middleware(RequireMember::class)->name('idol-registration.duplicates');
Route::get('/idol-registration/groups', [IdolRegistrationRequestController::class, 'groupSearch'])
    ->middleware(RequireMember::class)->name('idol-registration.groups');
Route::post('/idol-registration/request', [IdolRegistrationRequestController::class, 'store'])
    ->middleware(RequireMember::class)->name('idol-registration.store');
Route::get('/fan-post/{idx}/edit', [FanPostController::class, 'edit'])
    ->middleware(RequireMember::class)->whereNumber('idx')->name('fan-post.edit');
Route::put('/fan-post/{idx}', [FanPostController::class, 'update'])
    ->middleware(RequireMember::class)->whereNumber('idx')->name('fan-post.update');
Route::delete('/fan-post/{idx}', [FanPostController::class, 'destroy'])
    ->middleware(RequireMember::class)->whereNumber('idx')->name('fan-post.destroy');
Route::get('/fan-post/{idx}/edit', [FanPostController::class, 'edit'])
    ->middleware(RequireMember::class)->whereNumber('idx')->name('fan-post.edit');
Route::put('/fan-post/{idx}', [FanPostController::class, 'update'])
    ->middleware(RequireMember::class)->whereNumber('idx')->name('fan-post.update');
Route::post('/fan-post/{idx}/recommendation', [FanPostController::class, 'toggleRecommendation'])
    ->middleware(RequireMember::class)->whereNumber('idx')->name('fan-post.recommendation');
Route::post('/fan-post/{idx}/report', [FanPostController::class, 'report'])
    ->middleware(RequireMember::class)->whereNumber('idx')->name('fan-post.report');
Route::post('/fan-post/{idx}/comments/{commentIdx}/report', [FanPostController::class, 'reportComment'])
    ->middleware(RequireMember::class)->whereNumber('idx')->whereNumber('commentIdx')->name('fan-post.comments.report');
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
Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email');
Route::get('/reset-password', [PasswordResetController::class, 'show'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
Route::get('/my-page', [MyPageController::class, 'show'])->middleware(RequireMember::class)->name('my-page');
Route::get('/my-page/heart-history', [MyPageController::class, 'heartHistory'])
    ->middleware(RequireMember::class)->name('my-page.heart-history');
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
    Route::post('/acting-dummy-member', [ActingDummyMemberController::class, 'update'])->name('acting-dummy-member');
    Route::post('/random-dummy-mode', [ActingDummyMemberController::class, 'toggleRandom'])->name('random-dummy-mode');
    Route::get('/idol-requests', AdminIdolRegistrationRequestController::class)->name('idol-requests.index');
    Route::get('/idol-requests/{idx}', [AdminIdolRegistrationRequestController::class, 'show'])->whereNumber('idx')->name('idol-requests.show');
    Route::post('/idol-requests/{idx}/save', [AdminIdolRegistrationRequestController::class, 'save'])->whereNumber('idx')->name('idol-requests.save');
    Route::post('/idol-requests/{idx}/approve', [AdminIdolRegistrationRequestController::class, 'approve'])->whereNumber('idx')->name('idol-requests.approve');
    Route::post('/idol-requests/{idx}/reject', [AdminIdolRegistrationRequestController::class, 'reject'])->whereNumber('idx')->name('idol-requests.reject');
    Route::get('/fan-post-reports', [AdminFanPostReportController::class, 'index'])->name('fan-post-reports.index');
    Route::patch('/fan-post-reports/{idx}/status', [AdminFanPostReportController::class, 'updateStatus'])->whereNumber('idx')->name('fan-post-reports.status');
    Route::get('/fan-post-comment-reports', [AdminFanPostCommentReportController::class, 'index'])->name('fan-post-comment-reports.index');
    Route::patch('/fan-post-comment-reports/{idx}/status', [AdminFanPostCommentReportController::class, 'updateStatus'])->whereNumber('idx')->name('fan-post-comment-reports.status');
    Route::patch('/fan-post-comments/{idx}/visibility', [AdminFanPostCommentReportController::class, 'hideComment'])->whereNumber('idx')->name('fan-post-comments.visibility');
    Route::patch('/fan-post-comments/{idx}/restore', [AdminFanPostCommentReportController::class, 'restoreComment'])->whereNumber('idx')->name('fan-post-comments.restore');
    Route::get('/idol-profile-image-reports', [AdminIdolProfileImageReportController::class, 'index'])->name('idol-profile-image-reports.index');
    Route::get('/idol-profile-image-reports/{idx}', [AdminIdolProfileImageReportController::class, 'show'])->whereNumber('idx')->name('idol-profile-image-reports.show');
    Route::get('/idol-profile-image-reports/{idx}/evidence', [AdminIdolProfileImageReportController::class, 'evidence'])->whereNumber('idx')->name('idol-profile-image-reports.evidence');
    Route::patch('/idol-profile-image-reports/{idx}/status', [AdminIdolProfileImageReportController::class, 'updateStatus'])->whereNumber('idx')->name('idol-profile-image-reports.status');
    Route::patch('/idol-profile-image-reports/{idx}/hide-image', [AdminIdolProfileImageReportController::class, 'hideImage'])->whereNumber('idx')->name('idol-profile-image-reports.hide-image');
    Route::get('/fan-posts/{idx}', [AdminFanPostReportController::class, 'showPost'])->whereNumber('idx')->name('fan-posts.show');
    Route::patch('/fan-posts/{idx}/visibility', [AdminFanPostReportController::class, 'updateVisibility'])->whereNumber('idx')->name('fan-posts.visibility');
    Route::get('/idols', fn (Request $request) => app(AdminIdolController::class)->index($request, 'individual'))->name('idols.index');
    Route::get('/groups', fn (Request $request) => app(AdminIdolController::class)->index($request, 'group'))->name('groups.index');
    Route::get('/idols/{idx}/edit', [AdminIdolController::class, 'edit'])->whereNumber('idx')->name('idols.edit');
    Route::put('/idols/{idx}', [AdminIdolController::class, 'update'])->whereNumber('idx')->name('idols.update');
    Route::patch('/idols/{idx}/visibility', [AdminIdolController::class, 'updateVisibility'])->whereNumber('idx')->name('idols.visibility');
    Route::get('/members', [AdminMemberController::class, 'index'])->name('members.index');
    Route::get('/member-access-logs', AdminMemberAccessLogController::class)->name('member-access-logs.index');
    Route::get('/members/dummy/create', [AdminMemberController::class, 'createDummy'])->name('members.dummy.create');
    Route::post('/members/dummy', [AdminMemberController::class, 'storeDummy'])->name('members.dummy.store');
    Route::get('/members/{idx}', [AdminMemberController::class, 'show'])->whereNumber('idx')->name('members.show');
    Route::patch('/members/{idx}/status', [AdminMemberController::class, 'updateStatus'])->whereNumber('idx')->name('members.status');
    Route::get('/activity-logs', AdminActivityLogController::class)->name('activity-logs.index');
    Route::get('/fan-posts', AdminFanPostController::class)->name('fan-posts.index');
    Route::get('/partner-banners', [AdminPartnerBannerController::class, 'index'])->name('partner-banners.index');
    Route::get('/partner-banners/create', [AdminPartnerBannerController::class, 'create'])->name('partner-banners.create');
    Route::post('/partner-banners', [AdminPartnerBannerController::class, 'store'])->name('partner-banners.store');
    Route::get('/partner-banners/{idx}/edit', [AdminPartnerBannerController::class, 'edit'])->whereNumber('idx')->name('partner-banners.edit');
    Route::put('/partner-banners/{idx}', [AdminPartnerBannerController::class, 'update'])->whereNumber('idx')->name('partner-banners.update');
    Route::delete('/partner-banners/{idx}', [AdminPartnerBannerController::class, 'destroy'])->whereNumber('idx')->name('partner-banners.destroy');
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
