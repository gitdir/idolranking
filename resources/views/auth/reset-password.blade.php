@extends('layouts.app')
@section('title', 'Reset Your Password | IdolRanking')
@section('content')
    @include('auth.partials.password-page', ['reset' => true])
@endsection
