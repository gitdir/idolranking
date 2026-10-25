<button class="quiz-like-button {{ $quizLiked ? 'is-liked' : '' }}" type="button" data-quiz-like
    data-endpoint="{{ route('quizzes.like', $quiz->idx) }}" data-csrf="{{ csrf_token() }}"
    data-signed-in="{{ $actor !== null ? 'true' : 'false' }}" data-liked="{{ $quizLiked ? 'true' : 'false' }}"
    @if($randomDummyMode && $randomRequestKey) data-request-key="{{ $randomRequestKey }}" data-quiz-version="{{ $quizLikeQuestionVersion }}" @endif
    aria-pressed="{{ $quizLiked ? 'true' : 'false' }}">
    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 10v11H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3Zm0 0 5-8c2 0 3 1.5 2.5 3.5L13.5 10H19a3 3 0 0 1 2.9 3.8l-1.6 5.5a3.7 3.7 0 0 1-3.5 2.7H7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>{{ __('quiz.like_button') }}</span>
    <span class="quiz-like-count" data-quiz-like-count>{{ number_format($quizLikeCount) }}</span>
</button>
