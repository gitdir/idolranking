<?php

// My Page-only samples, newest first. These are not database posts or detail IDs.
$posts = [];
for ($idx = 10; $idx >= 1; $idx--) {
    $posts[] = [
        ['portrait-blue', 'portrait-peach', 'portrait-lilac', 'portrait-sage', 'portrait-rose'][$idx % 5],
        'golden_army', 'A favorite music memory to share with fellow fans. #'.$idx,
        1000 + $idx * 73, 100 + $idx * 12, $idx * 2, 'square',
    ];
}
return $posts;
