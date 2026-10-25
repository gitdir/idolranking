@props(['approvalRewardAmount'])

<div class="fan-post-registration-help" data-idol-registration-help>
    <button class="fan-post-registration-toggle" type="button" data-registration-toggle aria-expanded="false">{{ __('ui.fan_post_registration_prompt') }}</button>
    <section class="fan-post-registration-panel" data-registration-panel hidden>
        <h3>{{ __('ui.fan_post_registration_title') }}</h3>
        <p>{{ __('ui.fan_post_registration_description') }}</p>
        <div class="idol-request-reward-notice fan-post-registration-reward">
            <strong><span class="idol-request-reward-label">{{ __('ui.approval_reward') }}</span><span class="idol-request-reward-amount"><svg class="heart-icon--small" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><use href="#icon-heart"/></svg><span>+{{ number_format($approvalRewardAmount) }}</span></span></strong>
            <p>{{ __('ui.reward_after_approval') }}</p>
        </div>
        <a class="fan-post-registration-link" href="{{ route('idol-registration.create') }}">{{ __('ui.fan_post_registration_button') }}</a>
    </section>
</div>
