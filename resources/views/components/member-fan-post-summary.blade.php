@props(['member'])

<div class="my-profile member-post-summary-frame">
<section class="my-profile-columns member-post-summary" aria-labelledby="member-post-summary-name">
    <div class="member-post-identity">
        <x-member-link :member-code="$member['member_code'] ?? null" :label="__('ui.member_fan_posts_label', ['name' => $member['nickname']])" class="member-post-avatar-link">
            <img class="member-post-avatar" src="{{ $member['profile_image_url'] }}" width="136" height="136" alt="{{ __('ui.profile_image_alt', ['name' => $member['nickname']]) }}">
        </x-member-link>
        <div>
            <h1 id="member-post-summary-name" translate="no"><x-member-link :member-code="$member['member_code'] ?? null">{{ $member['nickname'] }}</x-member-link></h1>
            <p class="member-post-country">
                <x-country-flag :code="$member['country_code'] ?? null" />
                {{ $member['country'] }}
            </p>
            <p class="member-post-bio">{{ $member['bio'] ?: __('ui.no_bio') }}</p>
        </div>
    </div>
    <x-member-stats :member="$member" />
</section>
</div>
