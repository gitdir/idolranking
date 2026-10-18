@extends('layouts.app')
@section('title', __('ui.reset_your_password').' | IdolRanking')
@section('content')
    @include('auth.partials.password-page', ['reset' => true])
@endsection
