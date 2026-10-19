<?php

namespace App\Http\Controllers;

use App\Support\ManagedImageStorage;
use App\Support\PublicFanPostList;
use App\Support\PublicRanking;
use App\Support\IdolLocalizationResolver;
use App\Support\Countries;
use App\Support\SocialLinks;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IdolController extends Controller
{
    public function show(Request $request, string $idx, PublicRanking $publicRanking, PublicFanPostList $fanPostList, IdolLocalizationResolver $localizations): View
    {
        $idol = DB::table('idols')->where('idx', $idx)->where('status', 'active')->first();
        abort_if($idol === null, 404);

        $sort = $request->string('sort')->toString();
        if (! in_array($sort, ['latest', 'monthly_liked', 'liked', 'commented'], true)) {
            $sort = 'latest';
        }

        $type = $idol->type === 'group' ? 'group' : 'individual';
        $category = ($idol->gender === 'male' ? 'boy' : 'girl').($type === 'group' ? '-group' : '');
        $group = $type === 'individual'
            ? DB::table('idol_group_members as gm')->join('idols as g', 'g.idx', '=', 'gm.group_idx')
                ->where('gm.individual_idx', $idol->idx)->where('g.status', 'active')->orderBy('g.idx')
                ->first(['g.idx', 'g.name', 'g.english_name', 'g.content'])
            : null;

        $resolved = $localizations->resolveMany($group ? [$idol, $group] : [$idol]);
        $name = $resolved[(int) $idol->idx]['name'];
        $description = $resolved[(int) $idol->idx]['description'];
        $agency = $resolved[(int) $idol->idx]['agency'];
        $position = $resolved[(int) $idol->idx]['position'];
        $genre = $resolved[(int) $idol->idx]['genre'];
        $leader = $resolved[(int) $idol->idx]['leader'];
        $groupName = $group ? $resolved[(int) $group->idx]['name'] : null;
        $debutDateEntries = DB::table('idol_debuts')->where('idol_idx', $idol->idx)
            ->orderBy('debut_date')->orderBy('country_code')->get(['country_code', 'debut_date'])
            ->map(fn (object $debut): array => [
                'country_code' => strtoupper($debut->country_code),
                'country' => Countries::localizedName($debut->country_code, app()->getLocale()),
                'date' => Carbon::parse($debut->debut_date)->locale(app()->getLocale())->translatedFormat(
                    app()->getLocale() === 'ko' ? 'Y년 n월 j일' : 'F j, Y'
                ),
                'iso_date' => Carbon::parse($debut->debut_date)->toDateString(),
            ]);
        $debutDates = $debutDateEntries->map(fn (array $debut): string => $debut['country'].' · '.$debut['date'])->implode(' / ');

        $rankings = collect($publicRanking->category($category))->values();
        $rankIndex = $rankings->search(fn (array $row): bool => (int) $row['idx'] === (int) $idol->idx);
        $rankRow = $rankIndex === false ? null : $rankings[$rankIndex];

        $profile = [];
        $addTextField = static function (array &$fields, string $label, mixed $value): void {
            if (!is_string($value) && !is_numeric($value)) {
                return;
            }

            $text = trim((string) $value);
            if ($text === '' || in_array(mb_strtolower($text), ['—', 'n/a', 'solo'], true)) {
                return;
            }

            $fields[$label] = $text;
        };

        $addTextField($profile, 'Name', $name);
        $englishName = $idol->english_name ?? null;
        if (is_string($englishName) && trim($englishName) !== ''
            && mb_strtolower(trim($englishName)) !== mb_strtolower(trim((string) $name))) {
            $addTextField($profile, 'English Name', $englishName);
        }

        if ($type === 'individual') {
            $addTextField($profile, 'Group', $groupName);
            $addTextField($profile, 'Date of Birth', $idol->birth_date ?? null);

            $measurements = [];
            foreach ([['height_cm', ' cm'], ['weight_kg', ' kg']] as [$column, $unit]) {
                $measurement = $idol->{$column} ?? null;
                if (is_numeric($measurement)) {
                    $number = rtrim(rtrim(number_format((float) $measurement, 2, '.', ''), '0'), '.');
                    $measurements[] = $number.$unit;
                }
            }
            if ($measurements !== []) {
                $profile['Height / Weight'] = implode(' / ', $measurements);
            }

            $addTextField($profile, 'Blood Type', $idol->blood_type ?? null);
            $addTextField($profile, 'Agency', $agency);
            if ($debutDates !== '') $profile['Debut Dates'] = $debutDates;
            $addTextField($profile, 'Position', $position);
        } else {
            $addTextField($profile, 'Agency', $agency);
            if ($debutDates !== '') $profile['Debut Dates'] = $debutDates;
            $addTextField($profile, 'Genre', $genre);
            $addTextField($profile, 'Leader', $leader);
        }

        $snsLinks = SocialLinks::entries($idol->sns_links ?? null, $idol->sns ?? null);
        if ($snsLinks !== []) {
            $profile['SNS'] = $snsLinks;
        }
        $addTextField($profile, 'Description', $description);

        $voters = collect($publicRanking->topVoters((int) $idol->idx, now('UTC')->startOfMonth()))
            ->map(fn (array $voter): array => $voter + ['votes' => $voter['hearts']])
            ->all();
        $posts = $fanPostList->forIdol((int) $idol->idx, $sort);
        $imageUrl = ManagedImageStorage::publicUrl($idol->image_path);
        $popularImages = $publicRanking->representativeImages((int) $idol->idx, $imageUrl, $rankIndex === 0 ? 3 : 1);
        $currentMember = $request->attributes->get('activityMember');
        $profileImageReportable = is_string($idol->image_path)
            && str_starts_with($idol->image_path, 'storage/')
            && !str_contains($idol->image_path, '..')
            && ManagedImageStorage::isAvailable($idol->image_path);
        $profileImageAlreadyReported = $profileImageReportable && $currentMember !== null
            && DB::table('idol_profile_image_reports')
                ->where('idol_idx', $idol->idx)
                ->where('actor_member_idx', $currentMember->idx)
                ->where('image_path_hash', hash('sha256', $idol->image_path))
                ->exists();

        $trendEnd = now('UTC')->startOfDay();
        $trendStart = $trendEnd->copy()->subDays(29);
        $trendRows = DB::table('idol_votes as v')->join('members as m', 'm.idx', '=', 'v.member_idx')
            ->where('v.idol_idx', $idol->idx)->where('v.created_at', '>=', $trendStart)
            ->where('v.created_at', '<', $trendEnd->copy()->addDay())
            ->where('m.member_level', '>', 0)->where('m.status', 'active')->whereNull('m.deleted_at')
            ->selectRaw('DATE(v.created_at) as vote_date, SUM(v.heart_amount) as votes')
            ->groupBy('vote_date')->pluck('votes', 'vote_date');
        $trend = [];
        for ($day = $trendStart->copy(); $day->lte($trendEnd); $day->addDay()) {
            $date = $day->toDateString();
            $trend[] = ['date' => $date, 'votes' => (int) ($trendRows[$date] ?? 0)];
        }

        return view('idols.show', [
            'activeMenu' => $category,
            'idol' => [
                'idx' => (int) $idol->idx,
                'name' => $name,
                'group' => $groupName ?? ($type === 'group' ? ($idol->gender === 'male' ? 'Boy Group' : 'Girl Group') : 'Solo'),
                'group_idx' => $group?->idx,
                'monthly_rank' => $rankIndex === false ? '—' : $rankIndex + 1,
                'monthly_votes' => (int) ($rankRow['hearts'] ?? 0),
                'fan_posts' => (int) ($rankRow['posts'] ?? 0),
                'image_url' => $imageUrl,
                'profile_image_reportable' => $profileImageReportable,
                'profile_image_already_reported' => $profileImageAlreadyReported,
                'popular_images' => $popularImages,
            ],
            'profile' => $profile,
            'debutDateEntries' => $debutDateEntries,
            'description' => $description,
            'voters' => $voters,
            'trend' => $trend,
            'posts' => $posts,
            'fanPostSort' => $sort,
            'seo' => [
                'title' => $name.' | IDOL RANKING',
                'description' => $description ?: implode(' · ', array_filter([$name, $groupName, $agency])),
                'image' => $imageUrl,
                'url' => route('idol.show', ['idx' => $idx]),
                'type' => 'profile',
            ],
        ]);
    }
}
