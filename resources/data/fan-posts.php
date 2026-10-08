<?php

// Static UI fixtures only; identifiers do not refer to database records.
$titles = [
    'A golden moment worth remembering', 'Sunshine after the show',
    'My favorite moment from the stage', 'A quiet moment in blue',
    'A voice that stays with you', 'Every stage is a new favorite',
    'Keeping this one in my favorites', 'One more beautiful memory',
    'The art of a perfect performance', 'A smile to brighten your day',
    'Our favorite concert memories', 'This performance was everything',
    'A little reason to smile', 'Until the next stage',
];
$images = ['portrait-blue', 'portrait-peach', 'portrait-lilac', 'portrait-sage', 'portrait-rose'];
$authors = [
    ['idx' => 1, 'member_code' => 'demoMemberA1', 'nickname' => 'golden_army', 'image' => 'portrait-rose', 'country_code' => 'US', 'bio' => 'Collecting little moments of music and joy. Cheering for Jungkook from around the world.'],
    ['idx' => 2, 'member_code' => 'demoMemberB2', 'nickname' => 'purple_universe', 'image' => 'portrait-lilac', 'country_code' => 'KR', 'bio' => 'Music brings us together. Sharing my favorite stage memories.'],
    ['idx' => 3, 'member_code' => 'demoMemberC3', 'nickname' => 'moonlight', 'image' => 'portrait-peach', 'country_code' => 'JP', 'bio' => 'A little sunshine and a lot of music.'],
];
$posts = [];
foreach ($titles as $offset => $title) {
    $fixtureIdx = $offset + 1;
    $posts[$fixtureIdx] = [
        'idx' => $fixtureIdx, 'idol_idx' => 1, 'title' => $title,
        'author' => $authors[$offset % count($authors)],
        'image' => $images[$offset % count($images)],
        'shape' => ['square', 'landscape', 'portrait'][$offset % 3],
        'body' => $title.".\n\nThere is something special about sharing these moments with people who love the same music. The energy, the smile, and the memories stay with me long after the performance. What was your favorite moment?",
        'tags' => ['Jungkook', 'BTS', 'StageMemories'],
        'views' => 12532 + $offset * 731, 'recommendations' => 1284 + $offset * 57,
        'comments' => [
            ['nickname' => 'still_with_you', 'image' => 'portrait-sage', 'body' => 'Such a beautiful memory. Thank you for sharing!'],
            ['nickname' => 'blue_hour', 'image' => 'portrait-blue', 'body' => 'The whole performance was incredible. I keep coming back to this moment.'],
            ['nickname' => 'little_star', 'image' => 'portrait-peach', 'body' => 'This made my day a little brighter.'],
        ],
    ];
}
return $posts;
