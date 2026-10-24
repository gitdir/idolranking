<div class="my-heart-history-table" role="table" aria-label="{{ __('ui.heart_history_aria') }}">
    <div class="my-heart-history-row my-heart-history-head" role="row"><span role="columnheader">{{ __('ui.date') }}</span><span role="columnheader">{{ __('ui.activity') }}</span><span role="columnheader">{{ __('ui.hearts') }}</span></div>
    @forelse ($heartHistories as $history)
        @php($createdAt = \Illuminate\Support\Carbon::parse($history->created_at))
        <div class="my-heart-history-row" role="row">
            <time role="cell" datetime="{{ $history->created_at }}"><span>{{ $createdAt->format('Y-m-d') }}</span> <span>{{ $createdAt->format('H:i') }}</span></time>
            <span class="my-heart-history-activity" role="cell">
                <span class="my-heart-history-icon my-heart-history-icon--{{ $history->icon }}" aria-hidden="true">
                    @if ($history->icon === 'heart')
                        <svg viewBox="0 0 24 24"><use href="#icon-heart"/></svg>
                    @elseif ($history->icon === 'post')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="1"/><path d="m4 17 5-5 4 4 3-3 4 4"/></svg>
                    @elseif ($history->icon === 'comment')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5Z"/></svg>
                    @elseif ($history->icon === 'people')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2m1-11a2.5 2.5 0 0 1 0 5m2 2a5 5 0 0 1 3 4v1"/></svg>
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 10v12M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h3l3.5-6.5a2 2 0 0 1 4 0v.5Z"/></svg>
                    @endif
                </span>{{ $history->activity }}
            </span>
            <strong @class(['my-heart-history-amount', 'is-positive' => $history->amount >= 0, 'is-negative' => $history->amount < 0]) role="cell"><svg class="heart-icon--default" aria-hidden="true"><use href="#icon-heart"/></svg>{{ $history->amount >= 0 ? '+' : '' }}{{ number_format((int) $history->amount) }}</strong>
        </div>
    @empty
        <p class="my-heart-history-empty">{{ __('ui.no_heart_history') }}</p>
    @endforelse
</div>
<x-number-pagination :paginator="$heartHistories" :label="__('ui.heart_history_pages')" />
