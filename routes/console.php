<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

/*
 * 예약 작업 관리 안내
 * - Hostinger에는 `artisan schedule:run` 크론 하나만 두고, 개별 작업은 이 파일에 등록합니다.
 * - 새 작업은 실행 주기와 함께 아래에 추가하세요. 복잡한 처리 로직은 Artisan 명령 클래스에 둡니다.
 * - 이름 지정과 `withoutOverlapping()`을 적용하고, 시간대가 중요하면 `timezone()`도 지정합니다.
 * - 등록된 작업과 다음 실행 시각은 `php artisan schedule:list`로 확인할 수 있습니다.
 */

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 매일 03:20: 보관 기간(기본 90일)이 지난 member_access_logs 상세 기록을 삭제합니다.
// 보관 일수는 tracking.access_log_retention_days 설정으로 바꿀 수 있으며, 장기 방문자 집계 데이터는 삭제하지 않습니다.
Schedule::call(function (): void {
    if (Schema::hasTable('member_access_logs')) {
        // Detailed operational logs expire; long-term visitor aggregates remain untouched.
        DB::table('member_access_logs')
            ->where('created_at', '<', now()->subDays((int) config('tracking.access_log_retention_days', 90)))
            ->delete();
    }
})->dailyAt('03:20')->name('member-access-log-retention')->withoutOverlapping();

// 매분: 관리자가 설정한 게시 시간·간격·최대 게시 수에 따라 대기열에서 가장 오래된 Fan Post를 공개합니다.
// 삭제된 게시물은 건너뛰며, 실제 처리 로직은 app/Console/Commands/PublishQueuedFanPosts.php에 있습니다.
Schedule::command('fan-posts:publish-queued')->everyMinute()->name('fan-post-distribution')->withoutOverlapping();

// 매분(관리 설정에서 켠 경우): 공개된 팬포스트 전체 중 하나를 무작위로 골라 조회수를 1~5 올립니다.
// 공개되지 않았거나 삭제된 글, 비활성 아이돌의 글은 제외하며 실제 처리 로직은 명령 클래스에 있습니다.
Schedule::command('fan-posts:boost-views')->everyMinute()->name('fan-post-view-boost')->withoutOverlapping();

// 매분 설정을 확인합니다. 자동투표가 켜져 있으면 설정한 간격마다 활성 더미 계정 한 명이 투표합니다.
// 활성 아이돌 또는 그룹 중 무작위 한 명에게 설정 범위의 하트를 투표하며, 잔액 부족분은 지급 이력에 남깁니다.
// 실제 처리 로직은 app/Console/Commands/AutoVoteForDummyMembers.php에 있습니다.
Schedule::command('dummy-members:auto-vote')->everyMinute()->name('dummy-member-auto-vote')->withoutOverlapping();

Schedule::command('dummy-members:create-weekly')->mondays()->at('04:00')
    ->timezone('Asia/Seoul')->name('dummy-member-weekly-creation')->withoutOverlapping();

// 매주 월요일 00:05 (한국 시간): 지난주의 참여 완료 수 기준 상위 3개 퀴즈를 정산합니다.
// 각 퀴즈 제작자에게 보상 설정에 따른 하트를 지급하고 결과를 기록합니다.
// 실제 처리 로직은 app/Console/Commands/SettleQuizWeeklyRanking.php에 있습니다.
Schedule::command('quizzes:settle-weekly-ranking')->mondays()->at('00:05')
    ->timezone('Asia/Seoul')->name('quiz-weekly-ranking-settlement')->withoutOverlapping();
