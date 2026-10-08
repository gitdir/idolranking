@props(['member'])

<div class="my-profile member-post-summary-frame">
<section class="my-profile-columns member-post-summary" aria-labelledby="member-post-summary-name">
    <div class="member-post-identity">
        <x-member-link :member-code="$member['member_code'] ?? null" :label="'View '.$member['nickname'].' Fan Posts'" class="member-post-avatar-link">
            <img class="member-post-avatar" src="{{ $member['profile_image_url'] }}" width="136" height="136" alt="{{ $member['nickname'] }} profile image">
        </x-member-link>
        <div>
            <h1 id="member-post-summary-name"><x-member-link :member-code="$member['member_code'] ?? null">{{ $member['nickname'] }}</x-member-link></h1>
            <p class="member-post-country">
                @if ($member['country_code'])<span class="member-country-code" aria-hidden="true">{{ $member['country_code'] }}</span>@endif
                {{ $member['country'] }}
            </p>
            <p class="member-post-bio">{{ $member['bio'] ?: 'No bio provided.' }}</p>
        </div>
    </div>
    <x-member-stats :member="$member" />
</section>
</div>
