<?php

// UI fixtures only. These values are not live profile or voting data.
$dailyVotes = [42300, 51200, 47800, 63100, 55900, 72800, 68400, 58100, 79200, 84500,
    71600, 65000, 92300, 81200, 75400, 96800, 108500, 87400, 93200, 80100,
    99200, 115300, 92316, 102400, 87400, 112500, 98700, 120800, 105400, 128600];
$today = new DateTimeImmutable('today');
$trend = [];
foreach ($dailyVotes as $index => $votes) {
    $trend[] = ['date' => $today->modify('-'.(29 - $index).' days')->format('Y-m-d'), 'votes' => $votes];
}

return [
    'activeMenu' => 'boy',
    'idol' => [
        'idx' => 1, 'name' => 'Jungkook', 'group' => 'BTS',
        'monthly_rank' => 1, 'monthly_votes' => 2847316, 'fan_posts' => 12532,
        // Ordered by fan-post recommendations. Missing slots use the default placeholder.
        'popular_images' => ['portrait-blue', 'portrait-lilac', 'portrait-peach'],
    ],
    // Label/value rows also support group profiles without individual-only view logic.
    'profile' => [
        'Name' => 'Jungkook', 'English Name' => 'Jeon Jung-kook', 'Type' => 'Individual',
        'Group' => 'BTS', 'Date of Birth' => 'September 1, 1997', 'Nationality' => 'South Korea',
        'Height / Weight' => '178 cm / —', 'Blood Type' => 'A', 'Agency' => 'BIGHIT MUSIC',
        'Debut Date' => 'June 13, 2013', 'Position' => 'Vocalist', 'SNS' => 'Instagram · YouTube',
    ],
    'voters' => [
        ['member_code' => 'demoMemberA1', 'nickname' => 'golden_army', 'bio' => 'Every Heart for our golden maknae.', 'image' => 'portrait-rose', 'votes' => 184500],
        ['member_code' => 'demoMemberB2', 'nickname' => 'purple_universe', 'bio' => 'Music brings us together.', 'image' => 'portrait-lilac', 'votes' => 156200],
        ['nickname' => 'jk_forever', 'bio' => 'Cheering from across the world.', 'image' => 'portrait-sage', 'votes' => 128400],
        ['member_code' => 'demoMemberC3', 'nickname' => 'moonlight', 'bio' => 'A little support, every day.', 'image' => 'portrait-peach', 'votes' => 98600],
        ['nickname' => 'still_with_you', 'bio' => 'Making memories with this community.', 'image' => 'portrait-blue', 'votes' => 75400],
    ],
    'trend' => $trend,
    'posts' => [
        ['portrait-blue', 'golden_army', 'His smile always makes my day brighter ✨', 933115, 12841, 320, 'square', 'member_code' => 'demoMemberA1', 'fan_post_idx' => 1],
        ['portrait-peach', 'ocean_day', 'A little sunshine by the sea.', 5421, 923, 27, 'landscape', 'fan_post_idx' => 2],
        ['portrait-lilac', 'purple_universe', 'My favorite moment from the stage.', 9672, 1245, 86, 'square', 'member_code' => 'demoMemberB2', 'fan_post_idx' => 3],
        ['portrait-sage', 'winter_bloom', 'The softest winter mood.', 4882, 781, 36, 'portrait', 'fan_post_idx' => 4],
        ['portrait-blue', 'jk_forever', 'A voice that stays with you.', 11423, 2341, 102, 'square', 'fan_post_idx' => 5],
        ['portrait-rose', 'stage_diary', 'Every stage is a new favorite.', 9334, 2115, 72, 'landscape', 'fan_post_idx' => 6],
        ['portrait-peach', 'moonlight', 'Keeping this one in my favorites.', 8311, 1932, 64, 'square', 'member_code' => 'demoMemberC3', 'fan_post_idx' => 7],
        ['portrait-blue', 'blue_hour', 'This moment deserves a little replay.', 6221, 1054, 41, 'portrait', 'fan_post_idx' => 8],
        ['portrait-lilac', 'still_with_you', 'A work of art ✨', 7905, 1122, 65, 'landscape', 'fan_post_idx' => 9],
        ['portrait-sage', 'spring_note', 'A smile to brighten your day.', 7662, 1988, 59, 'square', 'fan_post_idx' => 10],
        ['portrait-rose', 'golden_days', 'The best kind of concert memories.', 10201, 2884, 91, 'square', 'fan_post_idx' => 11],
        ['portrait-lilac', 'together_7', 'This performance was everything.', 8115, 2421, 67, 'landscape', 'fan_post_idx' => 12],
        ['portrait-peach', 'little_star', 'One more reason to smile today.', 6784, 1432, 38, 'square', 'fan_post_idx' => 13],
        ['portrait-blue', 'midnight_fan', 'Already looking forward to the next stage.', 5934, 1331, 33, 'square', 'fan_post_idx' => 14],
    ],
];
