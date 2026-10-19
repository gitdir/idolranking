@extends('layouts.app')

@section('title', __('legal.privacy_seo_title'))

@section('content')
<article class="legal-document">
    <header class="legal-document-header">
        <p class="legal-document-label">{{ __('legal.document_label') }}</p>
        <h1>{{ __('legal.privacy_title') }}</h1>
        <p class="legal-document-intro">{{ __('legal.privacy_intro') }}</p>
        <p class="legal-document-effective-date"><strong>{{ __('legal.effective_date_label') }}</strong> {{ __('legal.effective_date_placeholder') }}</p>
    </header>

    @foreach (__('legal.privacy_sections') as $index => $section)
        <section aria-labelledby="privacy-section-{{ $index }}">
            <h2 id="privacy-section-{{ $index }}">{{ $section['title'] }}</h2>
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
@endsection
