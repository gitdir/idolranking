@extends('layouts.app')
@section('title', __('ui.member_not_found_title').' | IdolRanking')
@section('content')
    <div class="member-fan-posts-page">
        <section class="fan-posts" aria-labelledby="member-not-found-title">
            <h1 id="member-not-found-title">{{ __('ui.member_not_found_title') }}</h1>
            <p class="fan-post-empty">{{ __('ui.member_not_found_description') }}</p>
        </section>
    </div>
@endsection
