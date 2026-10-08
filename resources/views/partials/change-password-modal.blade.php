<dialog id="change-password-modal" class="profile-image-modal change-password-modal" aria-labelledby="change-password-title">
    <div class="change-password-panel">
        <button type="button" class="profile-image-close" data-change-password-close aria-label="Close change password dialog">&#215;</button>
        <h2 id="change-password-title">Change Password</h2>
        <form action="{{ route('my-page.password') }}" method="post" id="change-password-form" novalidate>
            @csrf
            @foreach (['current_password' => 'Current Password', 'password' => 'New Password', 'password_confirmation' => 'Confirm New Password'] as $name => $label)
                <div class="my-profile-field">
                    <label for="change-{{ $name }}">{{ $label }} <span>*</span></label>
                    <input id="change-{{ $name }}" name="{{ $name }}" type="password" autocomplete="{{ $name === 'current_password' ? 'current-password' : 'new-password' }}" required aria-describedby="change-{{ $name }}-error{{ $name === 'password' ? ' change-password-help' : '' }}">
                    <p class="my-password-error" id="change-{{ $name }}-error" data-password-error="{{ $name }}" hidden></p>
                </div>
            @endforeach
            <p id="change-password-help">Use at least 8 characters with letters, numbers, and symbols.</p>
            <p class="my-password-error" id="change-password-message" role="alert" hidden></p>
            <div class="my-password-actions">
                <button type="button" data-change-password-close>Cancel</button>
                <button type="submit">Change Password</button>
            </div>
        </form>
    </div>
</dialog>
