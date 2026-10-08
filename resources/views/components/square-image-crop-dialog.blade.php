@props(['id', 'title' => '이미지 자르기'])

<dialog id="{{ $id }}" class="fan-post-crop-dialog" data-crop-dialog aria-labelledby="{{ $id }}-title">
    <form method="dialog" class="fan-post-crop-modal">
        <header>
            <h2 id="{{ $id }}-title">{{ $title }}</h2>
            <button type="button" data-crop-cancel aria-label="이미지 자르기 닫기">×</button>
        </header>
        <div class="fan-post-crop-stage" data-crop-stage><canvas data-crop-canvas></canvas><span aria-hidden="true"></span></div>
        <div class="fan-post-crop-zoom">
            <button type="button" data-zoom-out aria-label="축소">−</button>
            <input type="range" min="1" max="3" step="0.01" value="1" data-crop-zoom aria-label="이미지 확대/축소">
            <button type="button" data-zoom-in aria-label="확대">+</button>
        </div>
        <div class="fan-post-crop-actions"><button type="button" data-crop-cancel>취소</button><button type="button" data-crop-apply>적용</button></div>
    </form>
</dialog>
