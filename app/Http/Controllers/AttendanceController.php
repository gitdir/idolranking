<?php

namespace App\Http\Controllers;

use App\Support\Countries;
use App\Support\FanPostLanguages;
use App\Support\ManagedImageStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $member = $request->attributes->get('activityMember');
        $today = now('UTC')->toDateString();
        $todayCheckIn = $member === null ? null : DB::table('daily_check_ins')
            ->where('member_idx', $member->idx)->where('check_in_date', $today)
            ->first(['content', 'language_code', 'awarded_hearts']);
        if ($todayCheckIn !== null) {
            $reward = DB::table('heart_histories')
                ->where('member_idx', $member->idx)
                ->where('reason', 'daily_attendance')
                ->where('operation_key', 'daily_attendance:'.$member->idx.':'.$today)
                ->first(['amount']);
            $todayCheckIn->reward_awarded = $reward !== null;
            if ($reward !== null) {
                $todayCheckIn->awarded_hearts = (int) $reward->amount;
            }
        }
        $attendanceReward = DB::table('heart_reward_settings')
            ->where('action_code', 'daily_attendance')
            ->first(['is_enabled', 'heart_amount', 'min_heart_amount', 'max_heart_amount']);
        $checkInEnabled = (bool) ($attendanceReward?->is_enabled ?? false);

        $checkIns = $this->publicCheckIns()
            ->select([
                'check_in.idx', 'check_in.content', 'check_in.language_code', 'check_in.awarded_hearts', 'check_in.created_at',
                'check_in.member_idx', 'check_in.check_in_date',
                'member.member_code', 'member.nickname', 'member.country_code', 'member.profile_image_path',
            ])
            ->orderByDesc('check_in.created_at')->orderByDesc('check_in.idx')
            ->paginate(24);
        $operationKeys = $checkIns->getCollection()->map(
            fn (object $checkIn): string => 'daily_attendance:'.$checkIn->member_idx.':'.$checkIn->check_in_date
        );
        $rewardAmounts = $operationKeys->isEmpty() ? collect() : DB::table('heart_histories')
            ->where('reason', 'daily_attendance')->whereIn('operation_key', $operationKeys)
            ->pluck('amount', 'operation_key');

        $checkIns->through(function ($checkIn) use ($rewardAmounts) {
                $operationKey = 'daily_attendance:'.$checkIn->member_idx.':'.$checkIn->check_in_date;
                $rewardAwarded = $rewardAmounts->has($operationKey);

                return [
                    'idx' => (int) $checkIn->idx,
                    'nickname' => $checkIn->nickname,
                    'member_url' => route('member.fan-posts', ['member_code' => $checkIn->member_code]),
                    'profile_image_url' => ManagedImageStorage::publicUrl($checkIn->profile_image_path),
                    'country_name' => Countries::localizedName($checkIn->country_code, app()->getLocale()),
                    'country_code' => $checkIn->country_code,
                    'language_code' => FanPostLanguages::isValid($checkIn->language_code)
                        ? strtolower($checkIn->language_code) : null,
                    'content' => $checkIn->content,
                    'reward_awarded' => $rewardAwarded,
                    'awarded_hearts' => $rewardAwarded ? (int) $rewardAmounts->get($operationKey) : null,
                    'created_at' => $checkIn->created_at,
                ];
            });

        return view('attendance.index', [
            'member' => $member,
            'todayCheckIn' => $todayCheckIn,
            'checkInEnabled' => $checkInEnabled,
            'attendanceReward' => $attendanceReward,
            'checkIns' => $checkIns,
            'seo' => [
                'title' => __('ui.attendance_title').' | IdolRanking',
                'description' => __('ui.attendance_description'),
                'url' => route('attendance'),
            ],
        ]);
    }

    public function translation(Request $request, string $idx)
    {
        $targetLanguage = $request->query('language', app()->getLocale());
        if (!is_string($targetLanguage) || !in_array($targetLanguage, ['ko', 'en'], true)
            || $targetLanguage !== app()->getLocale()) {
            return $this->translationError('translation_target_invalid', 422);
        }

        $checkIn = $this->publicCheckIns()->where('check_in.idx', $idx)
            ->first(['check_in.content', 'check_in.language_code']);
        if ($checkIn === null) {
            return $this->translationError('attendance_not_found', 404);
        }

        $sourceLanguage = strtolower((string) $checkIn->language_code);
        if (!FanPostLanguages::isValid($sourceLanguage)) {
            return $this->translationError('translation_unavailable', 404);
        }
        if ($sourceLanguage === $targetLanguage || $checkIn->content === '') {
            return $this->translationResponse($checkIn->content, $targetLanguage);
        }

        // Hash the exact stored message; even whitespace changes make the cache stale.
        $sourceHash = hash('sha256', $checkIn->content);
        $cached = $this->validTranslation($idx, $sourceLanguage, $targetLanguage, $sourceHash);
        if ($cached !== null) {
            return $this->translationResponse($cached->translated_content, $targetLanguage);
        }

        $lock = Cache::lock('attendance-translation:'.hash('sha256', $idx.'|'.$sourceLanguage.'|'.$targetLanguage.'|'.$sourceHash), 60);
        if (!$lock->get()) {
            return $this->translationError('translation_retry', 503);
        }

        try {
            // Recheck after acquiring the lock so concurrent requests share the saved result.
            $cached = $this->validTranslation($idx, $sourceLanguage, $targetLanguage, $sourceHash);
            if ($cached !== null) {
                return $this->translationResponse($cached->translated_content, $targetLanguage);
            }

            $apiKey = config('services.google_translate.api_key');
            if (!is_string($apiKey) || trim($apiKey) === '') {
                Log::warning('Daily Check-in translation is missing its API key.', ['failure_category' => 'missing_api_key']);
                return $this->translationError('translation_error', 503);
            }

            try {
                $response = Http::asJson()->withQueryParameters(['key' => $apiKey])
                    ->connectTimeout(3)->timeout(10)
                    ->post('https://translation.googleapis.com/language/translate/v2', [
                        'q' => $checkIn->content,
                        'source' => $sourceLanguage,
                        'target' => $targetLanguage,
                        'format' => 'text',
                    ]);
            } catch (\Throwable $exception) {
                Log::warning('Daily Check-in translation transport failed.', [
                    'failure_category' => 'transport_error',
                    'exception_type' => class_basename($exception),
                    'source_language' => $sourceLanguage,
                    'target_language' => $targetLanguage,
                ]);
                return $this->translationError('translation_error', 503);
            }

            $translatedContent = $response->json('data.translations.0.translatedText');
            if (!$response->successful() || !is_string($translatedContent) || $translatedContent === '') {
                Log::warning('Daily Check-in translation returned an unusable response.', [
                    'failure_category' => $response->successful() ? 'invalid_response' : 'http_error',
                    'http_status' => $response->status(),
                    'source_language' => $sourceLanguage,
                    'target_language' => $targetLanguage,
                ]);
                return $this->translationError('translation_error', 503);
            }

            $saved = DB::transaction(function () use ($idx, $sourceLanguage, $targetLanguage, $sourceHash, $translatedContent): bool {
                $current = $this->publicCheckIns()->where('check_in.idx', $idx)->lockForUpdate()
                    ->first(['check_in.content', 'check_in.language_code']);
                if ($current === null || strtolower((string) $current->language_code) !== $sourceLanguage
                    || !hash_equals($sourceHash, hash('sha256', $current->content))) {
                    return false;
                }

                $now = now('UTC');
                DB::table('daily_check_in_translations')->updateOrInsert(
                    [
                        'daily_check_in_idx' => $idx,
                        'source_language_code' => $sourceLanguage,
                        'target_language_code' => $targetLanguage,
                    ],
                    [
                        'translated_content' => $translatedContent,
                        'source_hash' => $sourceHash,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                return true;
            });

            return $saved
                ? $this->translationResponse($translatedContent, $targetLanguage)
                : $this->translationError('translation_error', 503);
        } finally {
            $lock->release();
        }
    }

    private function publicCheckIns()
    {
        return DB::table('daily_check_ins as check_in')
            ->join('members as member', 'member.idx', '=', 'check_in.member_idx')
            ->where('member.member_level', '>', 0)->where('member.status', 'active')->whereNull('member.deleted_at');
    }

    private function validTranslation(string $idx, string $sourceLanguage, string $targetLanguage, string $sourceHash): ?object
    {
        $translation = DB::table('daily_check_in_translations')
            ->where('daily_check_in_idx', $idx)->where('source_language_code', $sourceLanguage)
            ->where('target_language_code', $targetLanguage)
            ->first(['translated_content', 'source_hash']);

        return $translation !== null && hash_equals($sourceHash, $translation->source_hash) ? $translation : null;
    }

    private function translationResponse(string $content, string $targetLanguage)
    {
        return response()->json([
            'success' => true,
            'data' => ['content' => $content, 'language_code' => $targetLanguage],
        ])->header('Cache-Control', 'no-store');
    }

    private function translationError(string $code, int $status)
    {
        $key = match ($code) {
            'translation_target_invalid' => 'ui.translation_target_invalid',
            'translation_unavailable' => 'ui.translation_unavailable',
            'attendance_not_found' => 'ui.attendance_not_found',
            default => 'ui.attendance_translation_error',
        };

        return response()->json([
            'success' => false,
            'code' => $code,
            'message' => __($key),
        ], $status)->header('Cache-Control', 'no-store');
    }
}
