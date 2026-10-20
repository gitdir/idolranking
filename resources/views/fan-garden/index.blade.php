@extends('layouts.app')

@section('title', $seo['title'])

@section('content')
    <div class="fan-garden-page">
        <div class="fan-garden-layout">
            <section class="fan-garden-scene" aria-labelledby="fan-garden-title">
                <div class="fan-garden-scene-scroll" tabindex="0" aria-label="{{ __('fan_garden.explore_garden') }}">
                    <div class="fan-garden-canvas">
                        <header class="fan-garden-heading-card">
                            <img src="{{ asset('images/fan-garden/sample-avatar.svg') }}" width="56" height="56" alt="">
                            <div>
                                <span class="fan-garden-preview-tag">{{ __('fan_garden.sample_preview') }}</span>
                                <h1 id="fan-garden-title">{{ __('fan_garden.garden_title', ['idol' => $garden['idol_name']]) }}</h1>
                                <p>{{ __('fan_garden.garden_description', ['idol' => $garden['idol_name']]) }} <span aria-hidden="true">✎</span></p>
                            </div>
                        </header>
                        <button type="button" class="fan-garden-edit-button" disabled>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 5 5 5M4 20l4.2-.8L19 8.4a2.1 2.1 0 0 0-3-3L5.2 16.2 4 20Z"/><path d="M12 21h9"/></svg>
                            {{ __('fan_garden.edit_garden') }}
                        </button>
                        <div class="fan-garden-scene-decoration" aria-hidden="true">
                            <span class="garden-sparkle garden-sparkle--one">✦</span>
                            <span class="garden-sparkle garden-sparkle--two">✧</span>
                            <span class="garden-butterfly">✿</span>
                        </div>
                        <div class="fan-garden-plots" aria-label="{{ __('fan_garden.flower_plots') }}">
                            @foreach ($garden['flowers'] as $flower)
                                <article class="fan-garden-flower-plot">
                                    <span class="fan-garden-level">{{ __('fan_garden.level_short', ['level' => $flower['level']]) }}</span>
                                    <img class="fan-garden-flower-art" src="{{ asset('images/fan-garden/'.$flower['image']) }}" width="150" height="150" alt="{{ $flower['name'] }}">
                                    <div class="fan-garden-pot" aria-hidden="true"><span></span></div>
                                    <div class="fan-garden-growth-card">
                                        <strong>{{ $flower['name'] }}</strong>
                                        <div class="fan-garden-exp-track" role="progressbar" aria-label="{{ __('fan_garden.exp_progress', ['name' => $flower['name']]) }}" aria-valuemin="0" aria-valuemax="{{ $flower['next_level'] }}" aria-valuenow="{{ $flower['experience'] }}"><span style="width: {{ min(100, ($flower['experience'] / $flower['next_level']) * 100) }}%"></span></div>
                                        <small>{{ number_format($flower['experience']) }} / {{ number_format($flower['next_level']) }} EXP</small>
                                    </div>
                                </article>
                            @endforeach
                            @for ($slot = 0; $slot < 3; $slot++)
                                <div class="fan-garden-empty-plot">
                                    <button type="button" disabled aria-label="{{ __('fan_garden.plant_a_flower') }}">
                                        <span>{{ __('fan_garden.plant_a_flower') }}</span>
                                        <i aria-hidden="true">+</i>
                                    </button>
                                    <div class="fan-garden-soil" aria-hidden="true"><span></span></div>
                                </div>
                            @endfor
                        </div>
                    </div>
                </div>
                <p class="fan-garden-scroll-hint">{{ __('fan_garden.mobile_explore_hint') }}</p>
            </section>

            <div class="fan-garden-toolbar">
                <div class="fan-garden-toolbar-actions">
                    <button type="button" class="fan-garden-button fan-garden-button--outline" disabled>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h15m0 0-4-4m4 4-4 4M20 17H5m0 0 4 4m-4-4 4-4"/></svg>
                        {{ __('fan_garden.random_garden') }}
                    </button>
                    <a class="fan-garden-button fan-garden-button--pink" href="#garden-shop">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h2l2.2 12.2a2 2 0 0 0 2 1.8h8.9a2 2 0 0 0 2-1.6L22 8H6"/><circle cx="10" cy="21" r="1"/><circle cx="18" cy="21" r="1"/></svg>
                        {{ __('fan_garden.garden_shop') }}
                    </a>
                </div>
            </div>

            <aside class="fan-garden-sidebar" aria-label="{{ __('fan_garden.my_garden_info') }}">
                <section class="fan-garden-info-card">
                    <h2><span class="fan-garden-info-icon" aria-hidden="true">🪴</span>{{ __('fan_garden.my_garden_info') }}</h2>
                    <div class="fan-garden-owner">
                        <img src="{{ asset('images/fan-garden/sample-avatar.svg') }}" width="108" height="108" alt="">
                        <div class="fan-garden-owner-copy">
                            <strong>{{ $garden['member_nickname'] }} <span aria-hidden="true">✎</span></strong>
                            <span class="fan-garden-country"><x-country-flag :code="$garden['country_code']" />{{ $garden['country_name'] }} <span aria-hidden="true">·</span> KO</span>
                        </div>
                    </div>
                    <p class="fan-garden-introduction">{{ $garden['introduction'] }}</p>
                </section>
            </aside>
        </div>

        <div class="fan-garden-bottom-grid">
            <section class="fan-garden-bottom-card fan-garden-shop-card" id="garden-shop" aria-labelledby="garden-shop-title">
                <header class="fan-garden-bottom-heading">
                    <h2 id="garden-shop-title"><span aria-hidden="true">🛍️</span>{{ __('fan_garden.garden_shop') }}</h2>
                    <span class="fan-garden-more">{{ __('fan_garden.more') }} <b aria-hidden="true">›</b></span>
                </header>
                <div class="fan-garden-shop-tabs" role="group" aria-label="{{ __('fan_garden.shop_categories') }}">
                    @foreach (['all', 'plants', 'decorations', 'backgrounds'] as $category)
                        <button type="button" @class(['is-active' => $category === 'all']) disabled>{{ __('fan_garden.category_'.$category) }}</button>
                    @endforeach
                </div>
                <div class="fan-garden-shop-items">
                    @foreach ($garden['shop_items'] as $item)
                        <article class="fan-garden-shop-item">
                            <div class="fan-garden-shop-art"><img src="{{ asset('images/fan-garden/'.$item['image']) }}" width="92" height="78" alt="{{ $item['name'] }}"></div>
                            <strong>{{ $item['name'] }}</strong>
                            <button type="button" disabled>{{ __('fan_garden.purchase') }}</button>
                        </article>
                    @endforeach
                </div>
                <p class="fan-garden-preview-footnote">{{ __('fan_garden.preview_unavailable') }}</p>
            </section>

            <section class="fan-garden-bottom-card fan-garden-guestbook-card" aria-labelledby="garden-guestbook-title">
                <header class="fan-garden-bottom-heading">
                    <h2 id="garden-guestbook-title"><span aria-hidden="true">💬</span>{{ __('fan_garden.garden_guestbook') }}</h2>
                    <span class="fan-garden-more">{{ __('fan_garden.more') }} <b aria-hidden="true">›</b></span>
                </header>
                <div class="fan-garden-guestbook-compose">
                    <img src="{{ asset('images/fan-garden/sample-avatar.svg') }}" width="40" height="40" alt="">
                    <label class="sr-only" for="garden-guestbook-message">{{ __('fan_garden.guestbook_placeholder') }}</label>
                    <textarea id="garden-guestbook-message" rows="1" maxlength="100" placeholder="{{ __('fan_garden.guestbook_placeholder') }}" disabled></textarea>
                    <span>0/100</span>
                    <button type="button" disabled>{{ __('fan_garden.submit') }}</button>
                </div>
                <div class="fan-garden-guestbook-entries">
                    @foreach ($garden['guestbook_entries'] as $entry)
                        <article class="fan-garden-guestbook-entry">
                            <img src="{{ asset('images/fan-garden/'.$entry['avatar']) }}" width="40" height="40" alt="">
                            <div><header><strong>{{ $entry['nickname'] }}</strong><time>{{ $entry['age'] }}</time></header><p>{{ $entry['message'] }}</p></div>
                            <div class="fan-garden-entry-actions"><button type="button" disabled>{{ __('fan_garden.report') }}</button><button type="button" disabled>{{ __('fan_garden.delete') }}</button></div>
                        </article>
                    @endforeach
                </div>
                <p class="fan-garden-preview-footnote">{{ __('fan_garden.preview_guestbook_note') }}</p>
            </section>

            <section class="fan-garden-bottom-card fan-garden-visitors-card" aria-labelledby="garden-visitors-title">
                <header class="fan-garden-bottom-heading">
                    <h2 id="garden-visitors-title"><span aria-hidden="true">◉</span>{{ __('fan_garden.recent_visitors') }}</h2>
                    <span class="fan-garden-more">{{ __('fan_garden.more') }} <b aria-hidden="true">›</b></span>
                </header>
                <ul class="fan-garden-visitors">
                    @foreach ($garden['visitors'] as $visitor)
                        <li>
                            <img src="{{ asset('images/fan-garden/'.$visitor['avatar']) }}" width="34" height="34" alt="">
                            <strong>{{ $visitor['nickname'] }}</strong>
                            <span class="fan-garden-visitor-action" aria-hidden="true">{{ $visitor['icon'] }}</span>
                            <span class="fan-garden-visitor-copy">{{ $visitor['action'] }}</span>
                            <time>{{ $visitor['time'] }}</time>
                        </li>
                    @endforeach
                </ul>
                <p class="fan-garden-preview-footnote">{{ __('fan_garden.preview_visitors_note') }}</p>
            </section>
        </div>
    </div>
@endsection
