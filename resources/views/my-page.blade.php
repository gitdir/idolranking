@extends('layouts.app')
@section('title', __('ui.my_page_title').' | IdolRanking')
@section('content')
    @php($profileImageUrl = \App\Support\ManagedImageStorage::publicUrl($currentMember?->profile_image_path))
    <div class="my-page">
        <header class="my-page-title">
            <h1>{{ __('ui.my_page') }} <svg viewBox="0 0 32 40" aria-hidden="true"><path fill="currentColor" d="M16 0q0 20 16 20-16 0-16 20Q16 20 0 20 16 20 16 0"/></svg></h1>
            <p>{{ __('ui.manage_profile') }}</p>
            <svg class="my-page-decoration" viewBox="0 0 160 100" aria-hidden="true"><path fill="currentColor" d="m40 20 28 25L90 6l22 39 36-19-19 60-81-12Z"/><path fill="currentColor" d="M18 40S-5 26 5 16q8-8 13 1 6-9 14-1 10 10-14 24"/></svg>
        </header>
        <div class="my-page-content">
            <p id="password-change-success" class="my-password-success" role="status" hidden></p>
{{--            @if (!$currentMember)<p class="my-page-preview-note">Profile and fan posts are sample previews. Sign in is required to change a password.</p>@endif--}}
            <section class="my-profile" aria-labelledby="edit-profile-title">
                <h2 id="edit-profile-title">{{ __('ui.profile') }}</h2>
                <p class="my-profile-description">{{ __('ui.update_profile') }}</p>
                <div class="my-profile-columns">
                    <div class="my-profile-picture">
                        <div class="my-profile-avatar">
                            <img src="{{ $profileImageUrl }}" width="200" height="200" alt="{{ __('ui.profile_image_alt', ['name' => $currentMember->nickname]) }}" data-image-preview>
                            <button class="my-profile-camera" type="button" data-profile-image-select aria-label="{{ __('ui.choose_profile_image') }}"><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h5l2-3h4l2 3h5v14H3Z"/><circle cx="12" cy="13" r="4"/></svg></button>
                        </div>
                        <button class="my-profile-upload" type="button" data-profile-image-select><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 16V3m-5 5 5-5 5 5M4 15v6h16v-6"/></svg>{{ __('ui.upload_image') }}</button>
                        <input id="my-profile-image" type="file" name="profile_image" accept="image/jpeg,image/png,image/gif" form="my-profile-form" data-image-input hidden>
                        <div class="my-profile-image-actions" data-image-selection hidden><button type="button" data-crop-open>{{ __('ui.edit_crop') }}</button><button type="button" data-image-remove>{{ __('ui.remove') }}</button></div>
                        <p class="my-profile-image-help">{{ __('ui.image_help') }}</p>
                    </div>
                    <div class="my-profile-details">
                        <x-member-stats :member="$memberStats" class="my-profile-stats" />
                        <div class="my-hearts">
                            <div class="my-balance-item my-balance-item--hearts">
                                <div class="my-balance-item-heading">
                                    <span class="my-hearts-icon"><svg aria-hidden="true"><use href="#icon-heart"/></svg></span>
                                    <span>{{ __('ui.balance_hearts') }}</span>
                                </div>
                                <div class="my-balance-item-values">
                                    <strong data-member-heart-balance>{{ number_format($currentMember?->heart_balance ?? 12540) }}</strong>
                                </div>
                            </div>
                        </div>
                        <p id="profile-update-status" class="my-password-success" role="status" hidden></p>
                        <form id="my-profile-form" class="my-profile-form" method="post" action="{{ route('my-page.profile') }}" enctype="multipart/form-data" novalidate>
                            @csrf
                            @method('put')
                            <div class="my-profile-field"><label for="profile-email">{{ __('ui.email_address') }}</label><div><input id="profile-email" type="email" value="{{ $currentMember?->email ?? 'golden_army@example.com' }}" readonly aria-describedby="profile-email-help"><p id="profile-email-help">{{ __('ui.email_cannot_change') }}</p></div></div>
                            <div class="my-profile-field"><label for="profile-nickname">{{ __('ui.nickname') }} <span>*</span></label><div><div class="my-profile-nickname"><input id="profile-nickname" name="nickname" value="{{ $currentMember?->nickname ?? 'golden_army' }}" autocomplete="nickname" required minlength="2" maxlength="20"><button type="button" data-nickname-check data-endpoint="{{ route('my-page.nickname.check') }}">{{ __('ui.check') }}</button></div><p class="my-profile-nickname-result" data-nickname-check-result role="status" hidden></p></div></div>
                            <div class="my-profile-field"><label for="profile-country">{{ __('ui.country') }} <span>*</span></label><x-country-select id="profile-country" :selected="$currentMember?->country_code" /></div>
                            <div class="my-profile-field"><label for="profile-bio">{{ __('ui.short_bio') }}</label><div><textarea id="profile-bio" rows="3" maxlength="160">{{ $currentMember ? '' : 'A fan who loves music, positive vibes, and supporting amazing artists!' }}</textarea><output class="my-profile-count" for="profile-bio">69/160</output></div></div>
                            <div class="my-profile-actions"><button type="submit">{{ __('ui.save_changes') }}</button><button type="button" data-change-password-open aria-haspopup="dialog" aria-controls="change-password-modal">{{ __('ui.change_password') }}</button></div>
                        </form>
                    </div>
                </div>
            </section>
            <nav id="my-page-lower-tabs" class="my-page-lower-tabs" aria-label="{{ __('ui.my_page') }}">
                <a href="{{ route('my-page', ['tab' => 'hearts']) }}#my-page-lower-tabs" @class(['is-active' => $activeTab === 'hearts']) @if($activeTab === 'hearts') aria-current="page" @endif><svg aria-hidden="true"><use href="#icon-heart"/></svg>{{ __('ui.heart_tab') }}</a>
                <a href="{{ route('my-page', ['tab' => 'posts']) }}#my-page-lower-tabs" @class(['is-active' => $activeTab === 'posts']) @if($activeTab === 'posts') aria-current="page" @endif><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="1"/><path d="m4 17 5-5 4 4 3-3 4 4"/></svg>{{ __('ui.fan_posts') }}</a>
                <a href="{{ route('my-page', ['tab' => 'invites']) }}#my-page-lower-tabs" @class(['is-active' => $activeTab === 'invites']) @if($activeTab === 'invites') aria-current="page" @endif><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2"/><path d="M3 20v-2a6 6 0 0 1 12 0v2m1-7a5 5 0 0 1 5 5v2"/></svg>{{ __('ui.invite_tab') }}</a>
            </nav>
            @if ($activeTab === 'hearts')
                <section class="my-heart-history" aria-labelledby="my-heart-history-title">
                    <h2 id="my-heart-history-title">{{ __('ui.heart_history') }}</h2>
                    <p class="my-heart-history-note">{{ __('ui.heart_history_period') }}</p>
                    @include('my-page.partials.heart-history-entries', ['heartHistories' => $heartHistories])
                </section>
            @elseif ($activeTab === 'posts')
                <x-fan-post-list :posts="$posts" heading-id="my-fan-posts-title" layout="my-page" :empty-message="__('ui.no_fan_posts')">
                    <x-slot:heading><div class="my-posts-heading"><h2 id="my-fan-posts-title" translate="no">Fan Posts</h2>@if (count($posts))<a href="{{ route('member.fan-posts', ['member_code' => $currentMember->member_code]) }}">{{ __('ui.view_all') }} <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m6 4 4 4-4 4"/></svg></a>@endif</div></x-slot:heading>
                </x-fan-post-list>
            @else
                @php($myInviteUrl = url('/?ref='.rawurlencode($currentMember->member_code)))
                <section class="my-invites" aria-labelledby="my-invites-title">
                    <div class="my-invites-top">
                        <div class="my-invites-heading"><h2 id="my-invites-title">{{ __('ui.invite_friends') }}</h2><p>{{ __('ui.invite_members_prompt') }}</p></div>
                        <div class="my-invites-summary">
                            <div class="my-invites-total"><span>{{ __('ui.total_invited') }}</span><strong>{{ number_format($invitedMembers->total()) }}</strong></div>
                            <div class="my-invites-link"><label for="my-invite-url">{{ __('ui.your_invite_link') }}</label><div><input id="my-invite-url" type="text" value="{{ $myInviteUrl }}" readonly><button type="button" data-my-invite-copy aria-label="{{ __('js.copy_invite_link') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg></button></div></div>
                        </div>
                    </div>
                    <p class="my-invites-copy-status" data-my-invite-copy-status role="status" hidden></p>
                    <div class="my-invites-table" role="table" aria-label="{{ __('ui.invited_members') }}">
                        <div class="my-invites-row my-invites-head" role="row"><span role="columnheader">{{ __('ui.number') }}</span><span role="columnheader">{{ __('ui.joined') }}</span><span role="columnheader">{{ __('ui.member') }}</span><span role="columnheader">{{ __('ui.reward') }}</span></div>
                        @forelse ($invitedMembers as $invited)
                            <div class="my-invites-row" role="row">
                                <span role="cell">{{ $invitedMembers->firstItem() + $loop->index }}</span>
                                <time role="cell" datetime="{{ $invited->created_at }}">{{ \Illuminate\Support\Carbon::parse($invited->created_at)->format('Y-m-d H:i') }}</time>
                                <span class="my-invites-member" role="cell"><x-member-link :member-code="$invited->member_code" :label="__('ui.member_view_posts_label', ['name' => $invited->nickname])"><img src="{{ \App\Support\ManagedImageStorage::publicUrl($invited->profile_image_path) }}" width="30" height="30" alt=""><span translate="no">{{ $invited->nickname }}</span></x-member-link></span>
                                <strong @class(['my-invites-reward', 'is-earned' => $invited->reward_amount !== null]) role="cell">{{ $invited->reward_amount === null ? __('ui.reward_pending') : __('ui.reward_hearts', ['amount' => number_format($invited->reward_amount)]) }}</strong>
                            </div>
                        @empty
                            <p class="my-heart-history-empty">{{ __('ui.no_invited_members') }}</p>
                        @endforelse
                    </div>
                    <x-number-pagination :paginator="$invitedMembers" :label="__('ui.invited_member_pages')" />
                </section>
            @endif
        </div>
        @include('partials.change-password-modal')
        <x-square-image-crop-dialog id="my-profile-crop-dialog" :title="__('ui.profile_image_crop')" />
    </div>
@endsection
