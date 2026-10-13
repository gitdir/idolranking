<?php

namespace App\Http\Controllers;

use App\Support\HeartRewardManager;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class IdolRegistrationRequestController extends Controller
{
    public function create(Request $request, HeartRewardManager $heartRewards)
    {
        $groups = DB::table('idols')->where('type', 'group')->where('status', 'active')
            ->orderBy('name')->orderBy('idx')->get(['idx', 'name', 'gender']);
        $requestKey = bin2hex(random_bytes(32));
        $request->session()->put('idol_registration_request_key', $requestKey);
        $approvalRewardAmount = $heartRewards->enabledAmount('idol_registration_approved');

        return view('idol-registration-requests.create', compact('groups', 'requestKey', 'approvalRewardAmount'));
    }

    public function store(Request $request)
    {
        $member = $request->attributes->get('activityMember');
        $actor = $request->attributes->get('activityActor');
        $requestType = $request->string('request_type')->toString();
        $isGroup = in_array($requestType, ['boy_group', 'girl_group'], true);
        $request->merge(['request_type' => $requestType]);
        if ($isGroup) {
            $request->merge([
                'group_idx' => null,
                'birth_date' => null,
                'height_cm' => null,
                'weight_kg' => null,
                'blood_type' => null,
                'position' => null,
            ]);
        }
        $validated = $request->validate([
            'request_type' => ['required', Rule::in(['boy', 'boy_group', 'girl', 'girl_group'])],
            'name' => ['required', 'string', 'max:200'],
            'english_name' => ['required', 'string', 'max:200'],
            'group_idx' => ['nullable', 'integer'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'height_cm' => ['nullable', 'numeric', 'between:50,300'],
            'weight_kg' => ['nullable', 'numeric', 'between:10,500'],
            'blood_type' => ['nullable', Rule::in(['A', 'B', 'AB', 'O'])],
            'agency' => ['nullable', 'string', 'max:200'],
            'debut_date' => ['nullable', 'date', 'before_or_equal:today'],
            'position' => ['nullable', 'string', 'max:200'],
            'sns' => ['nullable', 'string', 'max:1000'],
            'profile_image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'content' => ['required', 'string', 'max:1000'],
            'request_key' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
        ]);

        $gender = str_starts_with($requestType, 'boy') ? 'male' : 'female';
        if (!$isGroup && !empty($validated['group_idx'])) {
            $validGroup = DB::table('idols')->where('idx', $validated['group_idx'])
                ->where('type', 'group')->where('gender', $gender)->where('status', 'active')->exists();
            if (!$validGroup) {
                return back()->withInput()->withErrors(['group_idx' => 'Choose an active group matching the selected type.']);
            }
        }

        $existingRequest = DB::table('idol_registration_requests')
            ->where('actor_member_idx', $actor->idx)
            ->where('request_key', $validated['request_key'])
            ->first(['idx', 'member_idx']);
        if ($existingRequest) {
            if ((int) $existingRequest->member_idx !== (int) $member->idx) {
                return back()->withInput()->withErrors([
                    'request' => 'This submission key was already used for another account.',
                ]);
            }

            return redirect()->route('idol-registration.create')
                ->with('success', 'Your idol registration request has been submitted for review.');
        }
        $sessionRequestKey = (string) $request->session()->get('idol_registration_request_key', '');
        if ($sessionRequestKey === '' || !hash_equals($sessionRequestKey, $validated['request_key'])) {
            return back()->withInput()->withErrors([
                'request' => 'This submission has expired. Please review the form and submit it again.',
            ]);
        }

        $stored = $request->file('profile_image')->store('idol-registration-requests', 'public');
        $imagePath = 'storage/'.$stored;
        try {
            DB::transaction(function () use ($member, $actor, $isGroup, $gender, $validated, $imagePath): void {
                DB::table('idol_registration_requests')->insert([
                    'member_idx' => $member->idx, 'actor_member_idx' => $actor->idx,
                    'type' => $isGroup ? 'group' : 'individual', 'gender' => $gender,
                    'name' => $validated['name'], 'english_name' => $validated['english_name'],
                    'group_idx' => $isGroup ? null : ($validated['group_idx'] ?? null),
                    'birth_date' => $isGroup ? null : ($validated['birth_date'] ?? null), 'nationality' => $validated['nationality'] ?? null,
                    'height_cm' => $isGroup ? null : ($validated['height_cm'] ?? null), 'weight_kg' => $isGroup ? null : ($validated['weight_kg'] ?? null),
                    'blood_type' => $isGroup ? null : ($validated['blood_type'] ?? null), 'agency' => $validated['agency'] ?? null,
                    'debut_date' => $validated['debut_date'] ?? null, 'position' => $isGroup ? null : ($validated['position'] ?? null),
                    'sns' => $validated['sns'] ?? null, 'image_path' => $imagePath,
                    'content' => $validated['content'], 'reference_url' => null, 'note' => null,
                    'request_key' => $validated['request_key'], 'status' => 'pending',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            });
        } catch (QueryException $exception) {
            Storage::disk('public')->delete($stored);
            if ((string) $exception->getCode() === '23000' && DB::table('idol_registration_requests')
                ->where('actor_member_idx', $actor->idx)->where('request_key', $validated['request_key'])
                ->where('member_idx', $member->idx)->exists()) {
                return redirect()->route('idol-registration.create')
                    ->with('success', 'Your idol registration request has been submitted for review.');
            }
            throw $exception;
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($stored);
            throw $exception;
        }

        $request->session()->forget('idol_registration_request_key');

        return redirect()->route('idol-registration.create')
            ->with('success', 'Your idol registration request has been submitted for review.');
    }
}
