<?php

namespace App\Http\Controllers;

use App\Support\PublicRanking;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RankingController extends Controller
{
    public function __invoke(Request $request, string $type, PublicRanking $publicRanking): View
    {
        abort_unless(in_array($type, ['idol', 'group'], true), 404);

        $gender = in_array($request->query('gender'), ['boy', 'girl'], true) ? $request->query('gender') : 'all';
        $currentMonth = now('UTC')->startOfMonth();
        $month = $this->month($request->query('month'), $currentMonth);
        $databaseGender = match ($gender) { 'boy' => 'male', 'girl' => 'female', default => null };
        $idols = $publicRanking->ranking($type === 'idol' ? 'individual' : 'group', $databaseGender, $month);
        $featured = $idols[0] ?? null;
        $featuredImages = $featured
            ? $publicRanking->representativeImages((int) $featured['idx'], $featured['image_url'] ?? null)
            : [];
        $topVoters = $featured ? $publicRanking->topVoters((int) $featured['idx'], $month) : [];

        $canonicalParameters = [];
        if ($gender !== 'all') $canonicalParameters['gender'] = $gender;
        if (!$month->equalTo($currentMonth)) $canonicalParameters['month'] = $month->format('Y-m');
        $rankingTitle = $type === 'idol' ? __('ui.idol').' '.__('ui.ranking') : __('ui.group').' '.__('ui.ranking');

        return view('rankings.index', [
            'title' => $rankingTitle,
            'theme' => $type === 'idol' ? 'blue' : 'pink',
            'activeMenu' => 'ranking-'.$type,
            'heroType' => $type === 'idol'
                ? ($gender === 'girl' ? 'girl' : 'boy')
                : ($gender === 'girl' ? 'girl-group' : 'boy-group'),
            'rankingType' => $type,
            'gender' => $gender,
            'month' => $month,
            'monthValue' => $month->format('Y-m'),
            'monthLabel' => $month->format('Y.m'),
            'previousMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->lt($currentMonth) ? $month->copy()->addMonth()->format('Y-m') : null,
            'idols' => $idols,
            'featured' => $featured,
            'featuredImages' => $featuredImages,
            'topVoters' => $topVoters,
            'seo' => [
                'title' => $rankingTitle.' | IDOL RANKING',
                'url' => route('ranking.'.$type, $canonicalParameters),
            ],
        ]);
    }

    private function month(mixed $value, Carbon $currentMonth): Carbon
    {
        if (!is_string($value) || !preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value, $matches)) {
            return $currentMonth;
        }

        $month = Carbon::create((int) $matches[1], (int) $matches[2], 1, 0, 0, 0, 'UTC')->startOfDay();
        return $month->gt($currentMonth) ? $currentMonth : $month;
    }
}
