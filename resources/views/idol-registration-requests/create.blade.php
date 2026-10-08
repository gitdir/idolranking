@extends('layouts.app')

@section('title', 'Register Idol | IdolRanking')

@section('content')
<div class="fan-post-create idol-request-create">
    <header class="fan-post-create-header">
        <h1>Idol Registration Request</h1>
        <p>Share a new idol or group with the IdolRanking community.</p>
    </header>

    @if (session('success'))<div class="idol-request-success" role="status">{{ session('success') }}</div>@endif

    <div class="fan-post-create-layout">
        <form class="fan-post-create-form" method="post" action="{{ route('idol-registration.store') }}" enctype="multipart/form-data" data-idol-request-form novalidate>
            @csrf
            <input type="hidden" name="request_key" value="{{ $requestKey }}">
            @if ($errors->any())<div class="fan-post-create-errors" role="alert"><strong>Please check the form.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="fan-post-create-field idol-request-type-row">
                <div class="fan-post-create-label">Type <span>*</span></div>
                <div class="idol-request-type-options">
                    @foreach (['boy' => 'Boy', 'boy_group' => 'Boy Group', 'girl' => 'Girl', 'girl_group' => 'Girl Group'] as $value => $label)
                        <label><input type="radio" name="request_type" value="{{ $value }}" @checked(old('request_type', 'boy') === $value) required><span>{{ $label }}</span></label>
                    @endforeach
                </div>
            </div>

            <div class="idol-request-fields-grid">
            <div class="fan-post-create-field"><label for="idol-request-name">Name (Korean) <span>*</span></label><div><input id="idol-request-name" name="name" value="{{ old('name') }}" maxlength="200" required placeholder="Enter Korean name"></div></div>
            <div class="fan-post-create-field"><label for="idol-request-english-name">English Name <span>*</span></label><div><input id="idol-request-english-name" name="english_name" value="{{ old('english_name') }}" maxlength="200" required placeholder="Enter English name"></div></div>
            <div class="fan-post-create-field idol-request-field-full" data-individual-only data-group-field><label for="idol-request-group">Group</label><div><select id="idol-request-group" name="group_idx"><option value="">No group / Select group</option>@foreach ($groups as $group)<option value="{{ $group->idx }}" data-gender="{{ $group->gender }}" @selected((string) old('group_idx') === (string) $group->idx)>{{ $group->name }}</option>@endforeach</select><small>Optional if the idol is not in a group.</small></div></div>
            <div class="fan-post-create-field" data-individual-only><label for="idol-request-birth-date">Date of Birth</label><div><input id="idol-request-birth-date" type="date" name="birth_date" value="{{ old('birth_date') }}" max="{{ now()->toDateString() }}"></div></div>
            <div class="fan-post-create-field"><label for="idol-request-nationality" data-nationality-label>Nationality</label><div><input id="idol-request-nationality" name="nationality" value="{{ old('nationality') }}" maxlength="100" placeholder="e.g. South Korean" data-nationality-input></div></div>
            <div class="fan-post-create-field" data-individual-only><label for="idol-request-height">Height (cm)</label><div><input id="idol-request-height" type="number" name="height_cm" value="{{ old('height_cm') }}" min="50" max="300" step="0.1" placeholder="e.g. 178"></div></div>
            <div class="fan-post-create-field" data-individual-only><label for="idol-request-weight">Weight (kg)</label><div><input id="idol-request-weight" type="number" name="weight_kg" value="{{ old('weight_kg') }}" min="10" max="500" step="0.1" placeholder="e.g. 65"></div></div>
            <div class="fan-post-create-field" data-individual-only><label for="idol-request-blood">Blood Type</label><div><select id="idol-request-blood" name="blood_type"><option value="">Select blood type</option>@foreach (['A', 'B', 'AB', 'O'] as $blood)<option value="{{ $blood }}" @selected(old('blood_type') === $blood)>{{ $blood }}</option>@endforeach</select></div></div>
            <div class="fan-post-create-field"><label for="idol-request-agency">Agency</label><div><input id="idol-request-agency" name="agency" value="{{ old('agency') }}" maxlength="200" placeholder="Enter agency"></div></div>
            <div class="fan-post-create-field" data-group-full><label for="idol-request-debut-date">Debut Date</label><div><input id="idol-request-debut-date" type="date" name="debut_date" value="{{ old('debut_date') }}" max="{{ now()->toDateString() }}"></div></div>
            <div class="fan-post-create-field" data-individual-only><label for="idol-request-position">Position</label><div><input id="idol-request-position" name="position" value="{{ old('position') }}" maxlength="200" placeholder="e.g. Vocalist, Dancer"></div></div>
            <div class="fan-post-create-field idol-request-field-full"><label for="idol-request-sns">SNS</label><div><input id="idol-request-sns" name="sns" value="{{ old('sns') }}" maxlength="1000" placeholder="URL or public handle"></div></div>
            </div>

            <div class="fan-post-create-field fan-post-media-field">
                <div class="fan-post-create-label">Profile Image <span>*</span></div>
                <div>
                    <label class="fan-post-upload" for="idol-request-image"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m4 18 5-5 4 4 3-3 4 4"/></svg><strong>Tap to upload an image</strong><span>JPG or PNG. Max 5MB.</span></label>
                    <input id="idol-request-image" class="visually-hidden" type="file" name="profile_image" accept="image/jpeg,image/png" data-image-input required>
                    <div class="fan-post-image-selection" data-image-selection hidden><img alt="Cropped profile image" data-image-preview><div><button type="button" data-crop-open>Edit / Crop</button><button type="button" data-image-remove>Remove</button></div></div>
                </div>
            </div>
            <div class="fan-post-create-field"><label for="idol-request-content">Short Introduction <span>*</span></label><div><textarea id="idol-request-content" name="content" maxlength="1000" rows="6" required placeholder="Tell us briefly about this idol or group">{{ old('content') }}</textarea><output class="fan-post-counter" for="idol-request-content" data-intro-count>{{ mb_strlen(old('content', '')) }}/1000</output></div></div>

            <div class="fan-post-create-actions"><button type="submit">Submit Request</button><a href="{{ route('home') }}">Cancel</a></div>
        </form>

        <aside class="fan-post-guidelines" aria-label="Registration guidelines">
            <section>
                <h2><span>Before You Submit</span></h2>
                <ul><li>Check that the idol or group is not already registered.</li><li>Use official, accurate profile information.</li><li>Requests are reviewed before appearing on IdolRanking.</li></ul>
                @if ($approvalRewardAmount > 0)
                    <div class="idol-request-reward-notice">
                        <strong>♥ Approval Reward: {{ number_format($approvalRewardAmount) }} Hearts</strong>
                        <p>Hearts are awarded after your request is approved.</p>
                    </div>
                @endif
            </section>
            <section><h2><span>Profile Image</span></h2><ul><li>Use a clear JPG or PNG image up to 5MB.</li><li>Only one image can be uploaded.</li><li>The final image uses a fixed square crop.</li></ul></section>
            <section><h2><span>Community Guidelines</span></h2><ul><li>Keep introductions respectful and factual.</li><li>Do not include private or sensitive information.</li></ul></section>
        </aside>
    </div>
</div>

<dialog class="fan-post-crop-dialog" data-crop-dialog aria-labelledby="idol-request-crop-title">
    <form method="dialog" class="fan-post-crop-modal">
        <header><h2 id="idol-request-crop-title">Crop Image</h2><button type="button" data-crop-cancel aria-label="Close crop image">×</button></header>
        <div class="fan-post-crop-stage" data-crop-stage><canvas data-crop-canvas></canvas><span aria-hidden="true"></span></div>
        <div class="fan-post-crop-zoom"><button type="button" data-zoom-out aria-label="Zoom out">−</button><input type="range" min="1" max="3" step="0.01" value="1" data-crop-zoom aria-label="Image zoom"><button type="button" data-zoom-in aria-label="Zoom in">+</button></div>
        <div class="fan-post-crop-actions"><button type="button" data-crop-cancel>Cancel</button><button type="button" data-crop-apply>Apply</button></div>
    </form>
</dialog>
@endsection
