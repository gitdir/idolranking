<?php

namespace App\Http\Controllers;

use App\Support\Countries;

class FanGardenController extends Controller
{
    public function __invoke()
    {
        // Static preview data keeps this UI separate from the future garden game backend.
        return view('fan-garden.index', [
            'garden' => [
                'idol_name' => 'ENHYPEN',
                'member_nickname' => 'SampleFan',
                'country_code' => 'KR',
                'country_name' => Countries::localizedName('KR', app()->getLocale()),
                'introduction' => __('fan_garden.sample_introduction'),
                'flowers' => [
                    ['name' => __('fan_garden.flower_cherry'), 'level' => 3, 'experience' => 320, 'next_level' => 500, 'image' => 'cherry-blossom.svg'],
                    ['name' => __('fan_garden.flower_rose'), 'level' => 2, 'experience' => 180, 'next_level' => 300, 'image' => 'rose.svg'],
                    ['name' => __('fan_garden.flower_hydrangea'), 'level' => 1, 'experience' => 50, 'next_level' => 100, 'image' => 'hydrangea.svg'],
                ],
                'shop_items' => [
                    ['name' => __('fan_garden.flower_cherry'), 'image' => 'cherry-blossom.svg'],
                    ['name' => __('fan_garden.flower_rose'), 'image' => 'rose.svg'],
                    ['name' => __('fan_garden.flower_hydrangea'), 'image' => 'hydrangea.svg'],
                    ['name' => __('fan_garden.flower_sunflower'), 'image' => 'sunflower.svg'],
                ],
                'guestbook_entries' => [
                    ['nickname' => 'HappyFan', 'age' => __('fan_garden.time_two_hours'), 'message' => __('fan_garden.sample_guestbook_one'), 'avatar' => 'sample-avatar-blue.svg'],
                    ['nickname' => 'IdolLove', 'age' => __('fan_garden.time_five_hours'), 'message' => __('fan_garden.sample_guestbook_two'), 'avatar' => 'sample-avatar.svg'],
                    ['nickname' => 'StarFan', 'age' => __('fan_garden.time_yesterday'), 'message' => __('fan_garden.sample_guestbook_three'), 'avatar' => 'sample-avatar-peach.svg'],
                ],
                'visitors' => [
                    ['nickname' => 'Dreamer', 'action' => __('fan_garden.action_watered'), 'time' => __('fan_garden.time_two_minutes'), 'icon' => '🌸', 'avatar' => 'sample-avatar-peach.svg'],
                    ['nickname' => 'LovelyFan', 'action' => __('fan_garden.action_gift'), 'time' => __('fan_garden.time_fifteen_minutes'), 'icon' => '🎁', 'avatar' => 'sample-avatar-blue.svg'],
                    ['nickname' => 'Starry', 'action' => __('fan_garden.action_message'), 'time' => __('fan_garden.time_one_hour'), 'icon' => '💬', 'avatar' => 'sample-avatar.svg'],
                    ['nickname' => 'KpopLove', 'action' => __('fan_garden.action_visited'), 'time' => __('fan_garden.time_two_hours'), 'icon' => '💗', 'avatar' => 'sample-avatar-green.svg'],
                    ['nickname' => 'Moonlight', 'action' => __('fan_garden.action_nutrient'), 'time' => __('fan_garden.time_three_hours'), 'icon' => '🌱', 'avatar' => 'sample-avatar-blue.svg'],
                    ['nickname' => 'Blossom', 'action' => __('fan_garden.action_gift'), 'time' => __('fan_garden.time_eight_hours'), 'icon' => '🎁', 'avatar' => 'sample-avatar-peach.svg'],
                    ['nickname' => 'SunnyFan', 'action' => __('fan_garden.action_visited'), 'time' => __('fan_garden.time_twelve_hours'), 'icon' => '💗', 'avatar' => 'sample-avatar-green.svg'],
                    ['nickname' => 'ENHYPEN♡', 'action' => __('fan_garden.action_watered'), 'time' => __('fan_garden.time_yesterday'), 'icon' => '🌱', 'avatar' => 'sample-avatar.svg'],
                ],
            ],
            'seo' => [
                'title' => __('fan_garden.page_title').' | IDOL RANKING',
                'description' => __('fan_garden.page_description'),
                'type' => 'website',
            ],
        ]);
    }
}
