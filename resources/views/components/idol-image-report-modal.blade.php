<dialog id="idol-image-report-modal" class="fan-post-report-modal" aria-labelledby="idol-image-report-title">
    <button class="fan-post-report-close" type="button" data-idol-image-report-cancel aria-label="{{ __('ui.report_dialog_close') }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
    </button>
    <h2 id="idol-image-report-title">{{ __('ui.report_profile_image_title') }}</h2>
    <p class="idol-image-report-description">{{ __('ui.report_profile_image_description') }}</p>
    <form data-idol-image-report-form method="post">
        @csrf
        <fieldset>
            <legend>{{ __('ui.reason') }} <span>*</span></legend>
            <div class="fan-post-report-reasons">
                <label><input type="radio" name="reason" value="copyright" required><span>{{ __('ui.report_copyright') }}</span></label>
                <label><input type="radio" name="reason" value="portrait_rights" required><span>{{ __('ui.report_portrait_rights') }}</span></label>
                <label><input type="radio" name="reason" value="other" required><span>{{ __('ui.report_other') }}</span></label>
            </div>
        </fieldset>
        <label class="fan-post-report-details" for="idol-image-report-details">{{ __('ui.details') }}</label>
        <textarea id="idol-image-report-details" name="details" rows="5" maxlength="2000" placeholder="{{ __('ui.report_details_placeholder') }}"></textarea>
        <p class="fan-post-report-error" data-idol-image-report-error role="alert" hidden></p>
        <div class="fan-post-report-actions">
            <button type="button" data-idol-image-report-cancel>{{ __('ui.cancel') }}</button>
            <button type="submit">{{ __('ui.submit_report') }}</button>
        </div>
    </form>
</dialog>
