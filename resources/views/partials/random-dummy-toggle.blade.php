<form class="random-dummy-toggle random-dummy-toggle--{{ $location }}" method="post" action="{{ route('admin.random-dummy-mode') }}">
    @csrf
    <label for="random-dummy-{{ $location }}">
        <input id="random-dummy-{{ $location }}" type="checkbox" name="enabled" value="1" aria-label="랜덤 더미" @checked(app(\App\Support\RandomDummyActivity::class)->enabled(request())) onchange="this.form.requestSubmit()">
        @if ($location === 'desktop')
            <span>랜덤더미</span>
        @elseif ($location === 'drawer')
            <span>랜덤 더미</span>
        @endif
    </label>
</form>
