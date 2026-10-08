@extends('layouts.app')
@php($editing = $editingPost !== null)
@section('title', ($editing ? 'Edit' : 'Create').' Fan Post | IdolRanking')

@section('content')
<div class="fan-post-create">
    <header class="fan-post-create-header">
        <h1>{{ $editing ? 'Edit Fan Post' : 'Create Fan Post' }}</h1>
        <p>Share your love and support for your favorite idol with fans around the world!</p>
    </header>
    <div class="fan-post-create-layout">
        <form class="fan-post-create-form" method="POST" action="{{ $editing ? route('fan-post.update', ['idx' => $editingPost['idx']]) : route('fan-post.store') }}" enctype="multipart/form-data" data-fan-post-create data-existing-image="{{ $editingPost['image_url'] ?? '' }}" novalidate>
            @csrf
            @if($editing) @method('PUT') @endif
            @if ($errors->any())<div class="fan-post-create-errors" role="alert"><strong>Please check the form.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="fan-post-create-field">
                <label for="fan-post-idol">Idol <span>*</span></label>
                <div><select id="fan-post-idol" name="idol_idx" required><option value="">Select an idol</option>@foreach($idols as $idol)<option value="{{ $idol->idx }}" @selected((string) old('idol_idx', $editingPost['idol_idx'] ?? request('idol_idx')) === (string) $idol->idx)>{{ $idol->name }}{{ $idol->type === 'group' ? ' (Group)' : '' }}</option>@endforeach</select><small>Choose the idol this post is about.</small></div>
            </div>
            <div class="fan-post-create-field">
                <label for="fan-post-title">Title <span>*</span></label>
                <div><input id="fan-post-title" name="title" maxlength="100" value="{{ old('title', $editingPost['title'] ?? '') }}" placeholder="Enter a title for your fan post..." required><output class="fan-post-counter" for="fan-post-title">{{ mb_strlen(old('title', $editingPost['title'] ?? '')) }}/100</output></div>
            </div>
            <div class="fan-post-create-field fan-post-media-field">
                <div id="fan-post-media-label" class="fan-post-create-label">Media</div>
                <div role="group" aria-labelledby="fan-post-media-label">
                    <input type="hidden" name="media_type" value="{{ old('media_type', $editingPost['media_type'] ?? 'text') }}" data-media-type>
                    <div class="fan-post-media-tabs" role="tablist" aria-label="Media type">
                        <button type="button" role="tab" data-media-tab="image"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m4 18 5-5 4 4 3-3 4 4"/></svg>Image</button>
                        <button type="button" role="tab" data-media-tab="youtube"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3Z"/></svg>YouTube Video</button>
                    </div>
                    <section data-media-panel="image">
                        <label class="fan-post-upload" for="fan-post-image"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m4 18 5-5 4 4 3-3 4 4"/></svg><strong>Tap to upload an image</strong><span>JPG, PNG or GIF. Max 5MB.</span></label>
                        <input id="fan-post-image" class="visually-hidden" type="file" name="image" accept="image/jpeg,image/png,image/gif" data-image-input>
                        <div class="fan-post-image-selection" data-image-selection @if(empty($editingPost['image_url'])) hidden @endif><img src="{{ $editingPost['image_url'] ?? '' }}" alt="Selected Fan Post image" data-image-preview><div><button type="button" data-crop-open>Edit / Crop</button><button type="button" data-image-remove>Remove</button></div></div>
                    </section>
                    <section data-media-panel="youtube" hidden><label class="visually-hidden" for="fan-post-youtube">YouTube URL</label><input id="fan-post-youtube" name="youtube_url" value="{{ old('youtube_url', $editingPost['youtube_url'] ?? '') }}" placeholder="https://www.youtube.com/watch?v=..."><small>Paste one YouTube video URL.</small></section>
                </div>
            </div>
            <div class="fan-post-create-field">
                <label for="fan-post-content">Content <span>*</span></label>
                <div><textarea id="fan-post-content" name="content" maxlength="2000" rows="6" placeholder="Share your thoughts, feelings, or stories..." required>{{ old('content', $editingPost['content'] ?? '') }}</textarea><output class="fan-post-counter" for="fan-post-content">{{ mb_strlen(old('content', $editingPost['content'] ?? '')) }}/2000</output></div>
            </div>
            <div class="fan-post-create-field">
                <label for="fan-post-tag-entry">Tags</label>
                <div><div class="fan-post-tags-input" data-tags-input><div data-tag-chips></div><input id="fan-post-tag-entry" placeholder="Add tags and press Enter..."></div><input type="hidden" name="tags" value="{{ old('tags', $editingPost['tags'] ?? '') }}" data-tags-value><small>You can add multiple tags. Letters, numbers, underscores, and hyphens are supported.</small></div>
            </div>
            <div class="fan-post-create-actions"><button type="submit">{{ $editing ? 'Save Changes' : 'Post Fan Post' }}</button><a href="{{ $editing ? route('fan-post.show', ['idx' => $editingPost['idx']]) : route('home') }}">Cancel</a></div>
        </form>

        <aside class="fan-post-guidelines" aria-label="Fan Post guidelines">
            <section><h2>💡 <span>Posting Tips</span></h2><ul><li>Share your own photos, videos, or creations.</li><li>Write your honest thoughts and feelings.</li><li>Be respectful and positive.</li><li>Use relevant tags to help other fans find your post.</li><li>Do not allow harmful, inappropriate, or copyrighted content.</li></ul></section>
            <section><h2>🖼️ <span>Media Guidelines</span></h2><h3>Image</h3><ul><li>JPG, PNG or GIF files</li><li>Maximum 5MB</li><li>Only one image</li><li>Images can be cropped or edited</li></ul><h3>YouTube Video</h3><ul><li>Paste one YouTube URL</li><li>Only one video</li><li>Rendered as an embedded player</li></ul></section>
            <section><h2>🛡️ <span>Community Guidelines</span></h2><ul><li>Be respectful to idols, other fans, and all users.</li><li>No hate speech, harassment, or abusive content.</li><li>Do not post illegal, explicit, or copyrighted material.</li><li>Keep our community friendly and positive.</li></ul></section>
        </aside>
    </div>
</div>

<dialog class="fan-post-crop-dialog" data-crop-dialog aria-labelledby="crop-image-title">
    <form method="dialog" class="fan-post-crop-modal">
        <header><h2 id="crop-image-title">Crop Image</h2><button type="button" data-crop-cancel aria-label="Close crop image">×</button></header>
        <div class="fan-post-crop-stage" data-crop-stage><canvas data-crop-canvas></canvas><span aria-hidden="true"></span></div>
        <div class="fan-post-crop-zoom"><button type="button" data-zoom-out aria-label="Zoom out">−</button><input type="range" min="1" max="3" step="0.01" value="1" data-crop-zoom aria-label="Image zoom"><button type="button" data-zoom-in aria-label="Zoom in">+</button></div>
        <div class="fan-post-crop-actions"><button type="button" data-crop-cancel>Cancel</button><button type="button" data-crop-apply>Apply</button></div>
    </form>
</dialog>
@endsection
