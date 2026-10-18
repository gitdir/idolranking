@extends('layouts.app')
@section('title', __('ui.forgot_password_title').' | IdolRanking')
@section('content')
    @include('auth.partials.password-page', ['reset' => false])
@endsection
