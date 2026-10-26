<?php

namespace Tests\Feature;

use App\Http\Middleware\SetPublicLocale;
use Illuminate\Http\Request;
use Tests\TestCase;

class PublicLocaleTest extends TestCase
{
    public function test_language_selection_is_saved_in_the_session_and_returns_to_the_current_page(): void
    {
        foreach (['en', 'ko', 'ja', 'es', 'id', 'pt-BR', 'zh-CN'] as $locale) {
            $response = $this->post('/language', [
                'locale' => $locale,
                'return_to' => '/ranking/idol?gender=girl',
            ]);

            $response->assertRedirect('/ranking/idol?gender=girl');
            $this->assertSame($locale, session('locale'));
        }
    }

    public function test_language_selection_rejects_unsupported_locales(): void
    {
        $this->post('/language', ['locale' => 'fr'])->assertNotFound();
    }

    public function test_each_supported_locale_loads_its_public_translation_files(): void
    {
        $keyPaths = function (array $messages, string $prefix = '') use (&$keyPaths): array {
            $paths = [];
            foreach ($messages as $key => $value) {
                $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
                if (is_array($value)) {
                    $paths = array_merge($paths, $keyPaths($value, $path));
                } else {
                    $paths[] = $path;
                }
            }

            sort($paths);
            return $paths;
        };

        foreach (array_keys(config('locales.public')) as $locale) {
            $this->assertNotSame('quiz.list_title', __('quiz.list_title', [], $locale), $locale);
            $this->assertNotSame('messages.login_success', __('messages.login_success', [], $locale), $locale);
            $this->assertNotSame('js.report_failed', __('js.report_failed', [], $locale), $locale);
            $this->assertNotSame('legal.footer_navigation', __('legal.footer_navigation', [], $locale), $locale);

            foreach (['ui', 'quiz', 'messages', 'js', 'legal', 'fan_garden', 'validation'] as $group) {
                if ($group === 'validation' && in_array($locale, ['en', 'ko'], true)) {
                    continue;
                }
                $this->assertSame($keyPaths(trans($group, [], 'en')), $keyPaths(trans($group, [], $locale)), $locale.'/'.$group.' translation keys');
            }
        }
    }

    public function test_public_locale_applies_to_rendered_copy_and_javascript_messages(): void
    {
        foreach (array_keys(config('locales.public')) as $locale) {
            $request = Request::create('/fan-posts/1');
            $request->setLaravelSession(app('session')->driver());
            $request->session()->put('locale', $locale);

            $response = app(SetPublicLocale::class)->handle($request, fn () => response(
                '<html lang="'.app()->getLocale().'">'.__('quiz.list_title').'|'.__('js.report_failed').'</html>'
            ));

            $this->assertStringContainsString('lang="'.$locale.'"', $response->getContent());
            $this->assertStringContainsString(__('quiz.list_title', [], $locale), $response->getContent());
            $this->assertStringContainsString(__('js.report_failed', [], $locale), $response->getContent());
        }
    }

    public function test_public_locale_defaults_to_english_without_a_saved_preference(): void
    {
        $request = Request::create('/');
        $request->setLaravelSession(app('session')->driver());

        app(SetPublicLocale::class)->handle($request, fn () => response('ok'));

        $this->assertSame('en', app()->getLocale());
    }

    public function test_active_get_hearts_and_accessibility_copy_has_korean_translations(): void
    {
        foreach ([
            'get_hearts_title', 'hearts_earned_not_bought', 'get_hearts_intro',
            'invite_reward_description', 'activity_unavailable', 'watch_ad', 'watch_ads',
            'watch_ad_intro', 'heart_reward_settings', 'status', 'partner_banner',
            'banner_reward_confirm', 'claim_reward', 'skip_to_content',
            'member_not_found_title', 'member_not_found_description',
        ] as $key) {
            $this->assertNotSame(__('ui.'.$key, [], 'en'), __('ui.'.$key, [], 'ko'), $key);
        }
    }

    public function test_public_translation_routes_use_the_shared_rate_limit(): void
    {
        foreach ([
            'attendance.translation', 'fan-post.translation', 'fan-post.comments.translation',
        ] as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);
            $this->assertNotNull($route, $routeName);
            $this->assertContains('throttle:public-translations', $route->gatherMiddleware(), $routeName);
        }
    }
}
