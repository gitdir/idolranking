<form class="random-dummy-toggle random-dummy-toggle--{{ $location }}" method="post" action="{{ route('admin.acting-dummy-mode') }}">
    @csrf
    <input type="hidden" name="enabled" value="0">
    <label for="acting-dummy-mode-{{ $location }}">
        <input id="acting-dummy-mode-{{ $location }}" type="checkbox" name="enabled" value="1" aria-label="더미회원"
               @checked($activityDummyModeEnabled ?? false) onchange="this.form.requestSubmit()">
        <span>더미회원</span>
    </label>
</form>
