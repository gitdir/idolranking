<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CommunityScraper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\CssSelector\CssSelectorConverter;

class ScrapingController extends Controller
{
    public function index()
    {
        $ready = $this->ready();
        $sources = $ready ? DB::table('scrape_sources')->whereNull('deleted_at')->orderBy('idx')->get() : collect();
        $nextSchedules = $ready ? DB::table('scrape_days')->where('status', 'pending')
            ->where('collect_date', '>=', now('Asia/Seoul')->toDateString())
            ->selectRaw('source_idx, MIN(scheduled_at) as scheduled_at')->groupBy('source_idx')->pluck('scheduled_at', 'source_idx') : collect();
        $lastSuccesses = $ready ? DB::table('scrape_days')->where('status', 'imported')
            ->selectRaw('source_idx, MAX(finished_at) as finished_at')->groupBy('source_idx')->pluck('finished_at', 'source_idx') : collect();
        $totals = $ready ? DB::table('scrape_imports')->where('status', 'imported')->selectRaw('source_idx, COUNT(*) total')
            ->groupBy('source_idx')->pluck('total', 'source_idx') : collect();
        $imports = $ready ? DB::table('scrape_imports as i')->leftJoin('scrape_sources as s', 's.idx', '=', 'i.source_idx')
            ->orderByDesc('i.idx')->limit(30)->get(['i.*', 's.name as source_name']) : collect();
        $summary = [
            'communities' => $sources->count(),
            'today' => $ready ? DB::table('scrape_days')->where('collect_date', now('Asia/Seoul')->toDateString())->where('status', 'imported')->count() : 0,
            'total' => $ready ? DB::table('scrape_imports')->where('status', 'imported')->count() : 0,
        ];

        $globalEnabled = DB::table('site_settings')->where('setting_key', 'scraping_enabled')->value('setting_value') === '1';
        return view('admin.settings.scraping', compact('sources', 'nextSchedules', 'lastSuccesses', 'totals', 'imports', 'summary', 'ready', 'globalEnabled'));
    }

    public function global(Request $request)
    {
        abort_unless($this->ready(), 503);
        $enabled = $request->validate(['enabled' => ['required', 'boolean']])['enabled'];
        DB::table('site_settings')->updateOrInsert(['setting_key' => 'scraping_enabled'], [
            'setting_value' => (string) (int) $enabled,
            'updated_by_member_idx' => $request->attributes->get('currentMember')->idx,
            'updated_at' => now(),
        ]);
        \App\Support\SiteSettings::forget();
        return back()->with('admin_success', '스크래핑 설정을 저장했습니다.');
    }

    public function save(Request $request, ?int $idx = null)
    {
        abort_unless($this->ready(), 503);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'listing_url' => ['required', 'url:http,https', 'max:2048'],
            'collection_method' => ['sometimes', 'in:http,browser'],
            'post_link_selector' => ['required', 'string', 'max:255'],
            'title_selector' => ['required', 'string', 'max:255'],
            'content_selector' => ['required', 'string', 'max:255'],
            'image_selector' => ['nullable', 'string', 'max:255'],
            'video_selector' => ['nullable', 'string', 'max:255'],
            'is_enabled' => ['required', 'boolean'],
        ]);
        $source = $idx === null ? null : DB::table('scrape_sources')->where('idx', $idx)->whereNull('deleted_at')->first();
        if ($idx !== null) abort_if($source === null, 404);
        $data['collection_method'] = $data['collection_method'] ?? ($source->collection_method ?? 'http');
        // Form input cannot grant redistribution permission. A changed board URL needs fresh review.
        $sameUrl = $source !== null && $source->listing_url === $data['listing_url'];
        $data['redistribution_permitted'] = $sameUrl ? (int) $source->redistribution_permitted : 0;
        $data['attribution_required'] = $sameUrl ? (int) $source->attribution_required : 1;
        if (blank($data['image_selector'] ?? null) && blank($data['video_selector'] ?? null)) {
            throw ValidationException::withMessages(['image_selector' => '이미지 또는 유튜브 선택자를 하나 이상 입력해 주세요.']);
        }
        $converter = new CssSelectorConverter();
        foreach (['post_link_selector', 'title_selector', 'content_selector', 'image_selector', 'video_selector'] as $key) {
            if (blank($data[$key] ?? null)) continue;
            try {
                $converter->toXPath($data[$key]);
            } catch (\Throwable $exception) {
                throw ValidationException::withMessages([$key => '올바른 CSS 선택자를 입력해 주세요.']);
            }
        }
        $data['updated_at'] = now();
        if ($idx === null) {
            $data['created_at'] = now();
            DB::table('scrape_sources')->insert($data);
        } else {
            abort_unless(DB::table('scrape_sources')->where('idx', $idx)->whereNull('deleted_at')->update($data), 404);
        }
        return back()->with('admin_success', '커뮤니티 설정을 저장했습니다.');
    }

    public function delete(int $idx)
    {
        abort_unless($this->ready(), 503);
        abort_unless(DB::table('scrape_sources')->where('idx', $idx)->whereNull('deleted_at')
            ->update(['is_enabled' => 0, 'deleted_at' => now(), 'updated_at' => now()]), 404);
        return back()->with('admin_success', '커뮤니티를 삭제했습니다. 수집 이력은 보존됩니다.');
    }

    public function test(Request $request, int $idx, CommunityScraper $scraper)
    {
        abort_unless($this->ready(), 503);
        $source = DB::table('scrape_sources')->where('idx', $idx)->whereNull('deleted_at')->first();
        abort_if($source === null, 404);
        $diagnostic = $scraper->diagnose($source);
        if ($request->expectsJson()) {
            return response()->json(['success' => $diagnostic['reason'] === null, 'message' => $diagnostic['reason']
                ? '수집 테스트에 실패했습니다. '.$diagnostic['reason'] : '수집 테스트를 완료했습니다. 게시물은 등록하지 않았습니다.',
                'diagnostic' => $diagnostic]);
        }
        return back()->with('scraping_diagnostic', $diagnostic);
    }

    public function collectNow(Request $request, int $idx)
    {
        abort_unless($this->ready(), 503);
        $source = DB::table('scrape_sources')->where('idx', $idx)->whereNull('deleted_at')->first();
        abort_if($source === null, 404);
        if (! $source->is_enabled) {
            return $this->collectionFeedback($request, '이 커뮤니티의 수집 사용 설정이 꺼져 있습니다.', 'error');
        }

        $today = now('Asia/Seoul')->toDateString();
        $day = DB::table('scrape_days')->where('source_idx', $idx)->where('collect_date', $today)->first();
        if ($day?->status === 'imported') {
            return $this->collectionFeedback($request, '오늘 수집이 이미 완료되었습니다.', 'skipped');
        }

        $exitCode = Artisan::call('fan-posts:collect-communities', ['--source' => (string) $idx, '--now' => true]);
        return $this->collectionResult($request, $idx, $exitCode);
    }

    public function importHtml(Request $request, int $idx)
    {
        abort_unless($this->ready(), 503);
        $source = DB::table('scrape_sources')->where('idx', $idx)->whereNull('deleted_at')->first();
        abort_if($source === null, 404);
        if (! $source->is_enabled) return back()->withErrors(['scraping' => '이 커뮤니티의 수집 사용 설정이 꺼져 있습니다.']);
        if (DB::table('scrape_days')->where('source_idx', $idx)->where('collect_date', now('Asia/Seoul')->toDateString())
            ->where('status', 'imported')->exists()) {
            return back()->with('admin_success', '오늘 수집이 이미 완료되었습니다.');
        }
        $data = $request->validate([
            'post_url' => ['required', 'url:http,https', 'max:2048'],
            'html_file' => ['required', 'file', 'max:2048', 'extensions:html,htm'],
        ]);
        $exitCode = Artisan::call('fan-posts:collect-communities', [
            '--source' => (string) $idx, '--now' => true,
            '--post-url' => $data['post_url'], '--html-file' => $data['html_file']->getRealPath(),
        ]);
        return $this->collectionResult($request, $idx, $exitCode);
    }

    private function collectionResult(Request $request, int $idx, int $exitCode)
    {
        $today = now('Asia/Seoul')->toDateString();
        $day = DB::table('scrape_days')->where('source_idx', $idx)->where('collect_date', $today)->first();
        if ($day?->status === 'imported') {
            return $this->collectionFeedback($request, '팬포스트 1건을 수집해 바로 등록했습니다.', 'success');
        }
        if ($day?->status === 'skipped') {
            return $this->collectionFeedback($request, '수집을 건너뛰었습니다. '.$day->failure_reason, 'skipped');
        }
        if ($day?->status === 'processing') {
            return $this->collectionFeedback($request, '다른 수집 작업이 진행 중입니다.', 'error');
        }
        $reason = $day?->failure_reason ?: '수집 결과를 확인하지 못했습니다.';
        if ($exitCode !== 0) $reason = '수집 명령을 실행하지 못했습니다.';
        return $this->collectionFeedback($request, '수집에 실패했습니다. '.$reason, 'error');
    }

    private function collectionFeedback(Request $request, string $message, string $status)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => $status === 'success', 'status' => $status, 'message' => $message],
                $status === 'error' ? 422 : 200);
        }
        return $status === 'error' ? back()->withErrors(['scraping' => $message]) : back()->with('admin_success', $message);
    }

    private function ready(): bool
    {
        return Schema::hasTable('scrape_sources') && Schema::hasTable('scrape_days') && Schema::hasTable('scrape_imports');
    }
}
