<?php

namespace Tests\Feature;

use App\Http\Middleware\SetPublicLocale;
use Illuminate\Http\Request;
use Tests\TestCase;

class PublicLocaleTest extends TestCase
{
    public function test_language_selection_is_saved_in_the_session_and_returns_to_the_current_page(): void
    {
        $response = $this->post('/language', [
            'locale' => 'ko',
            'return_to' => '/ranking/idol?gender=girl',
        ]);

        $response->assertRedirect('/ranking/idol?gender=girl');
        $this->assertSame('ko', session('locale'));
    }

    public function test_language_selection_rejects_unsupported_locales(): void
    {
        $this->post('/language', ['locale' => 'ja'])->assertNotFound();
    }

    public function test_public_locale_applies_to_rendered_copy_and_javascript_messages(): void
    {
        $request = Request::create('/fan-posts/1');
        $request->setLaravelSession(app('session')->driver());
        $request->session()->put('locale', 'ko');

        $response = app(SetPublicLocale::class)->handle($request, fn () => response(
            '<html lang="'.app()->getLocale().'">'.__('ui.report_fan_post').'|'.__('js.report_failed').'</html>'
        ));

        $this->assertStringContainsString('lang="ko"', $response->getContent());
        $this->assertStringContainsString(__('ui.report_fan_post', [], 'ko'), $response->getContent());
        $this->assertStringContainsString(__('js.report_failed', [], 'ko'), $response->getContent());
    }

    public function test_public_locale_defaults_to_english_without_a_saved_preference(): void
    {
        $request = Request::create('/');
        $request->setLaravelSession(app('session')->driver());

        app(SetPublicLocale::class)->handle($request, fn () => response('ok'));

        $this->assertSame('en', app()->getLocale());
    }
}
