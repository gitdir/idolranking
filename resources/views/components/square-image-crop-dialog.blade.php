@props(['id', 'title' => null])

<dialog id="{{ $id }}" class="fan-post-crop-dialog" data-crop-dialog aria-labelledby="{{ $id }}-title">
    <form method="dialog" class="fan-post-crop-modal">
        <header>
            <h2 id="{{ $id }}-title">{{ $title ?: __('ui.crop_image') }}</h2>
            <button type="button" data-crop-cancel aria-label="{{ __('ui.close_crop_image') }}"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
        </header>
        <div class="fan-post-crop-stage" data-crop-stage><canvas data-crop-canvas></canvas><span aria-hidden="true"></span></div>
        <div class="fan-post-crop-zoom">
            <button type="button" data-zoom-out aria-label="{{ __('ui.zoom_out') }}"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 12h14"/></svg></button>
            <input type="range" min="1" max="3" step="0.01" value="1" data-crop-zoom aria-label="{{ __('ui.image_zoom') }}">
            <button type="button" data-zoom-in aria-label="{{ __('ui.zoom_in') }}"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 5v14M5 12h14"/></svg></button>
        </div>
        <div class="fan-post-crop-actions"><button type="button" data-crop-cancel>{{ __('ui.cancel') }}</button><button type="button" data-crop-apply>{{ __('ui.apply') }}</button></div>
    </form>
</dialog>
