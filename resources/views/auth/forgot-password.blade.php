@extends('layouts.app')
@section('title', 'Forgot Password? | IdolRanking')
@section('content')
    @include('auth.partials.password-page', ['reset' => false])
@endsection
