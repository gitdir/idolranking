<dialog id="change-password-modal" class="profile-image-modal change-password-modal" aria-labelledby="change-password-title">
    <div class="change-password-panel">
        <button type="button" class="profile-image-close" data-change-password-close aria-label="{{ __('ui.change_password_dialog_close') }}">&#215;</button>
        <h2 id="change-password-title">{{ __('ui.change_password') }}</h2>
        <form action="{{ route('my-page.password') }}" method="post" id="change-password-form" novalidate>
            @csrf
            @foreach (['current_password' => __('ui.current_password'), 'password' => __('ui.new_password'), 'password_confirmation' => __('ui.confirm_new_password')] as $name => $label)
                <div class="my-profile-field">
                    <label for="change-{{ $name }}">{{ $label }} <span>*</span></label>
                    <input id="change-{{ $name }}" name="{{ $name }}" type="password" autocomplete="{{ $name === 'current_password' ? 'current-password' : 'new-password' }}" required aria-describedby="change-{{ $name }}-error{{ $name === 'password' ? ' change-password-help' : '' }}">
                    <p class="my-password-error" id="change-{{ $name }}-error" data-password-error="{{ $name }}" hidden></p>
                </div>
            @endforeach
            <p id="change-password-help">{{ __('ui.change_password_help') }}</p>
            <p class="my-password-error" id="change-password-message" role="alert" hidden></p>
            <div class="my-password-actions">
                <button type="button" data-change-password-close>{{ __('ui.cancel') }}</button>
                <button type="submit">{{ __('ui.change_password') }}</button>
            </div>
        </form>
    </div>
</dialog>
