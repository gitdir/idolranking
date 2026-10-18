@props(['myHearts' => 12540, 'signedIn' => false, 'randomDummyMode' => false])

<dialog id="heart-vote-modal" class="heart-vote-modal" role="dialog" aria-modal="true" aria-labelledby="heart-vote-title" aria-describedby="heart-vote-description" data-my-hearts="{{ $randomDummyMode ? 0 : (int) $myHearts }}" data-signed-in="{{ $signedIn ? 'true' : 'false' }}" data-random-dummy-mode="{{ $randomDummyMode ? 'true' : 'false' }}">
    <button class="heart-vote-close" type="button" data-heart-vote-close aria-label="{{ __('ui.heart_vote_dialog_close') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
    </button>
    <header class="heart-vote-header">
        <div class="heart-vote-title-icon" aria-hidden="true"><i></i><svg><use href="#icon-heart"/></svg><i></i></div>
        <h2 id="heart-vote-title">{{ __('ui.vote_with_hearts') }}</h2>
        <p id="heart-vote-description">{{ __('ui.support_idol_hearts') }}</p>
    </header>
    <section class="heart-vote-target" aria-label="{{ __('ui.vote_target') }}">
        <img data-heart-vote-image src="{{ \App\Support\ManagedImageStorage::defaultUrl() }}" width="150" height="150" alt="">
        <div class="heart-vote-target-details">
            <div class="heart-vote-target-heading"><strong data-heart-vote-name>Idol</strong><span data-heart-vote-meta></span></div>
            <div class="heart-vote-target-stats">
                <span><svg aria-hidden="true"><use href="#icon-heart"/></svg><b data-heart-vote-total>0</b></span>
                <span><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.5"/><path d="m4 17 5-5 4 4 3-3 5 5"/></svg><b data-heart-vote-posts>0</b></span>
            </div>
        </div>
    </section>
    <form class="heart-vote-form" method="post" action="{{ route('votes.store') }}" novalidate>
        @csrf
        <fieldset class="heart-vote-presets">
            <legend>{{ __('ui.select_vote_amount') }}</legend>
            <div>
                @foreach ([1, 10, 50, 100, 500, 1000, 5000, 10000] as $amount)
                    <button type="button" data-heart-preset="{{ $amount }}" @class(['is-active' => $amount === 1])>
                        <svg aria-hidden="true"><use href="#icon-heart"/></svg><span>{{ number_format($amount) }}</span>
                    </button>
                @endforeach
            </div>
        </fieldset>
        <div class="heart-vote-custom">
            <label for="heart-vote-amount">{{ __('ui.enter_vote_amount') }}</label>
            <div class="heart-vote-custom-layout">
                <div class="heart-vote-amount-field">
                    <span class="heart-vote-icon-column"><svg aria-hidden="true"><use href="#icon-heart"/></svg></span>
                    <input id="heart-vote-amount" name="heart_amount" type="number" value="1" min="1" step="1" inputmode="numeric" aria-describedby="heart-vote-help heart-vote-error">
                </div>
                <p id="heart-vote-help">{{ $randomDummyMode ? __('ui.random_dummy_vote_notice') : __('ui.vote_amount_help', ['amount' => number_format($myHearts)]) }}</p>
            </div>
            <p id="heart-vote-error" class="heart-vote-error" role="alert" hidden></p>
        </div>
        <div class="heart-vote-balance">
            <span class="heart-vote-icon-column"><svg aria-hidden="true"><use href="#icon-heart"/></svg></span>
            @if($randomDummyMode)
                <strong>{{ __('ui.random_dummy_vote_mode') }}</strong>
            @else
                <strong>{{ __('ui.my_hearts') }}</strong>
                <b data-heart-vote-balance>{{ number_format($myHearts) }}</b>
            @endif
        </div>
        <div class="heart-vote-actions">
            <button type="button" data-heart-vote-close>{{ __('ui.cancel') }}</button>
            <button type="submit" class="heart-vote-submit"><svg aria-hidden="true"><use href="#icon-heart"/></svg><span data-heart-vote-submit-label>Vote 1 Heart</span></button>
        </div>
    </form>
</dialog>
