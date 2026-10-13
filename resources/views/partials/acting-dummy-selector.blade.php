<form class="acting-dummy-selector" method="post" action="{{ route('admin.acting-dummy-member') }}">
    @csrf
    <label for="acting-dummy-{{ $location }}">Act as dummy member</label>
    <div>
        <select id="acting-dummy-{{ $location }}" name="dummy_member_idx" onchange="this.form.requestSubmit()">
            <option value="">Use Admin account</option>
            @foreach ($dummyMembers as $dummyMember)
                <option value="{{ $dummyMember->idx }}" @selected((int) ($selectedDummyIdx ?? 0) === (int) $dummyMember->idx)>
                    {{ $dummyMember->nickname }}
                </option>
            @endforeach
        </select>
    </div>
</form>
