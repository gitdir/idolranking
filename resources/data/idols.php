<?php

// Shared static identity catalogue. These idx values are fixtures, not database records.
$rankings = require __DIR__.'/rankings.php';
$idols = [];
foreach ($rankings as $category => $ranking) {
    foreach ($ranking['idols'] as $position => $idol) {
        $idols[$idol['idx']] = $idol + [
            'category' => $category,
            'type' => str_ends_with($category, '-group') ? 'Group' : 'Individual',
            'monthly_rank' => $position + 1,
        ];
    }
}
// Referenced by Cha Eun-woo's group label, but not present in the current ranking preview.
$idols[213] = [
    'idx' => 213, 'name' => 'ASTRO', 'group' => 'Boy Group', 'category' => 'boy-group',
    'type' => 'Group', 'monthly_rank' => '—', 'hearts' => 0, 'posts' => 0, 'image' => 'group-blue',
];

return $idols;
