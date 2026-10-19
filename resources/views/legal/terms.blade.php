@extends('layouts.app')

@section('title', __('legal.terms_seo_title'))

@section('content')
<div class="terms-page">
    <header class="terms-page-header">
        <p class="terms-page-label">{{ __('legal.document_label') }}</p>
        <h1>{{ __('legal.terms_title') }}</h1>
        <p class="terms-page-subtitle">{{ __('legal.terms_intro') }}</p>
        <p class="terms-page-date"><strong>{{ __('legal.effective_date_label') }}</strong> {{ __('legal.effective_date_placeholder') }}</p>
    </header>

    <article class="terms-document-card">
        @foreach (__('legal.terms_sections') as $index => $section)
            <section class="terms-document-section" aria-labelledby="terms-section-{{ $index }}">
                <h2 id="terms-section-{{ $index }}">{{ $section['title'] }}</h2>
                <div class="legal-section-body">
                    @foreach ($section['paragraphs'] ?? [] as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                    @if (!empty($section['items']))
                        <ul>
                            @foreach ($section['items'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        @endforeach
    </article>
</div>
@endsection
