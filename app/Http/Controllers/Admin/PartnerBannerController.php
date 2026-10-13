<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PartnerBannerController extends Controller
{
    private const IMAGE_PATH_PREFIX = 'storage/partner-banners/';

    public function index()
    {
        $banners = DB::table('partner_banners')
            ->orderBy('display_order')->orderByDesc('idx')->paginate(20)->withQueryString();
        $bannerIds = $banners->getCollection()->pluck('idx')->map(fn ($idx) => (int) $idx)->all();
        $clickStats = collect();

        if ($bannerIds !== []) {
            $clickStats = DB::table('partner_banner_clicks as clicks')
                ->leftJoin('members as members', 'members.idx', '=', 'clicks.member_idx')
                ->whereIn('clicks.banner_idx', $bannerIds)
                ->where(function ($query) {
                    $query->whereNull('clicks.member_idx')->orWhere(function ($memberQuery) {
                        $memberQuery->where('members.member_level', 1)->where('members.is_dummy', 0)
                            ->where('members.status', 'active')->whereNull('members.deleted_at');
                    });
                })
                ->groupBy('clicks.banner_idx')
                ->selectRaw('clicks.banner_idx, COUNT(*) AS total_valid_clicks')
                ->get()->keyBy('banner_idx');
        }

        $banners->getCollection()->transform(function ($banner) use ($clickStats) {
            $stats = $clickStats->get($banner->idx);
            $banner->total_valid_clicks = (int) ($stats->total_valid_clicks ?? 0);

            return $banner;
        });

        return view('admin.partner-banners.index', compact('banners'));
    }

    public function create()
    {
        return view('admin.partner-banners.form', ['banner' => null]);
    }

    public function store(Request $request)
    {
        $values = $this->validatedValues($request, true);
        $imagePath = $this->storeImage($request);
        $admin = $request->attributes->get('currentMember');

        try {
            $idx = DB::transaction(function () use ($values, $imagePath, $admin): int {
                $idx = DB::table('partner_banners')->insertGetId($values + [
                    'image_path' => $imagePath,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                AdminActivityLogger::record((int) $admin->idx, 'partner_banner_created', 'partner_banner', $idx, null, $values['title']);

                return (int) $idx;
            });
        } catch (\Throwable $exception) {
            $this->deleteOwnedImage($imagePath);
            throw $exception;
        }

        return redirect()->route('admin.partner-banners.index')->with('admin_success', '파트너 배너를 등록했습니다.');
    }

    public function edit(int $idx)
    {
        $banner = DB::table('partner_banners')->where('idx', $idx)->first();
        abort_if($banner === null, 404);

        return view('admin.partner-banners.form', compact('banner'));
    }

    public function update(Request $request, int $idx)
    {
        abort_unless(DB::table('partner_banners')->where('idx', $idx)->exists(), 404);
        $values = $this->validatedValues($request, false);
        $newImagePath = $request->hasFile('banner_image') ? $this->storeImage($request) : null;
        $admin = $request->attributes->get('currentMember');

        try {
            $oldImagePath = DB::transaction(function () use ($idx, $values, $newImagePath, $admin): ?string {
                $banner = DB::table('partner_banners')->where('idx', $idx)->lockForUpdate()->first();
                abort_if($banner === null, 404);

                DB::table('partner_banners')->where('idx', $idx)->update($values + [
                    'image_path' => $newImagePath ?? $banner->image_path,
                    'updated_at' => now(),
                ]);
                AdminActivityLogger::record((int) $admin->idx, 'partner_banner_updated', 'partner_banner', $idx, null, $values['title']);

                return $newImagePath !== null ? $banner->image_path : null;
            });
        } catch (\Throwable $exception) {
            $this->deleteOwnedImage($newImagePath);
            throw $exception;
        }

        $this->deleteOwnedImage($oldImagePath, $idx);

        return redirect()->route('admin.partner-banners.index')->with('admin_success', '파트너 배너를 수정했습니다.');
    }

    public function destroy(Request $request, int $idx)
    {
        $admin = $request->attributes->get('currentMember');
        $imagePath = DB::transaction(function () use ($idx, $admin): string {
            $banner = DB::table('partner_banners')->where('idx', $idx)->lockForUpdate()->first();
            abort_if($banner === null, 404);

            DB::table('partner_banners')->where('idx', $idx)->delete();
            AdminActivityLogger::record((int) $admin->idx, 'partner_banner_deleted', 'partner_banner', $idx, null, $banner->title);

            return $banner->image_path;
        });

        $this->deleteOwnedImage($imagePath);

        return redirect()->route('admin.partner-banners.index')->with('admin_success', '파트너 배너를 삭제했습니다.');
    }

    private function validatedValues(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'banner_image' => [$creating ? 'required' : 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'target_url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'heart_reward' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'reward_enabled' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'display_order' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ], [
            'title.required' => '배너 제목을 입력해 주세요.',
            'title.max' => '배너 제목은 255자 이하여야 합니다.',
            'banner_image.required' => '배너 이미지를 선택해 주세요.',
            'banner_image.image' => '이미지 파일을 선택해 주세요.',
            'banner_image.mimes' => 'JPG, PNG, WEBP, GIF 형식의 이미지만 사용할 수 있습니다.',
            'banner_image.max' => '이미지 크기는 5MB 이하여야 합니다.',
            'target_url.required' => '이동할 주소를 입력해 주세요.',
            'target_url.url' => 'HTTP 또는 HTTPS 주소를 입력해 주세요.',
            'target_url.max' => '이동 주소는 2,048자 이하여야 합니다.',
            'heart_reward.required' => '하트 보상량을 입력해 주세요.',
            'heart_reward.integer' => '하트 보상량은 정수로 입력해 주세요.',
            'heart_reward.min' => '하트 보상량은 0 이상이어야 합니다.',
            'heart_reward.max' => '하트 보상량이 허용 범위를 초과했습니다.',
            'reward_enabled.required' => '보상 사용 여부를 선택해 주세요.',
            'reward_enabled.boolean' => '보상 사용 여부를 확인해 주세요.',
            'is_active.required' => '노출 상태를 선택해 주세요.',
            'is_active.boolean' => '노출 상태를 확인해 주세요.',
            'display_order.required' => '노출 순서를 입력해 주세요.',
            'display_order.integer' => '노출 순서는 정수로 입력해 주세요.',
            'display_order.min' => '노출 순서는 0 이상이어야 합니다.',
            'starts_at.date' => '시작 일시를 확인해 주세요.',
            'ends_at.date' => '종료 일시를 확인해 주세요.',
        ], [
            'title' => '배너 제목', 'banner_image' => '배너 이미지', 'target_url' => '이동 주소',
            'heart_reward' => '하트 보상량', 'reward_enabled' => '보상 사용', 'is_active' => '노출 상태',
            'display_order' => '노출 순서', 'starts_at' => '시작 일시', 'ends_at' => '종료 일시',
        ]);

        $startsAt = $data['starts_at'] ?? null;
        $endsAt = $data['ends_at'] ?? null;
        if ($startsAt && $endsAt && Carbon::parse($endsAt)->lt(Carbon::parse($startsAt))) {
            throw ValidationException::withMessages(['ends_at' => '종료 일시는 시작 일시보다 빠를 수 없습니다.']);
        }

        return [
            'title' => trim($data['title']),
            'target_url' => trim($data['target_url']),
            'heart_reward' => (int) $data['heart_reward'],
            'reward_enabled' => (int) $data['reward_enabled'],
            'is_active' => (int) $data['is_active'],
            'display_order' => (int) $data['display_order'],
            'starts_at' => $this->formatDate($startsAt),
            'ends_at' => $this->formatDate($endsAt),
        ];
    }

    private function formatDate(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    private function storeImage(Request $request): string
    {
        $storedPath = $request->file('banner_image')->store('partner-banners', 'public');

        if (!is_string($storedPath) || !Storage::disk('public')->exists($storedPath)) {
            if (is_string($storedPath)) Storage::disk('public')->delete($storedPath);
            throw ValidationException::withMessages(['banner_image' => '배너 이미지를 저장하지 못했습니다. 다시 시도해 주세요.']);
        }

        return self::IMAGE_PATH_PREFIX.substr($storedPath, strlen('partner-banners/'));
    }

    private function deleteOwnedImage(?string $imagePath, ?int $exceptIdx = null): void
    {
        if ($imagePath === null || !str_starts_with($imagePath, self::IMAGE_PATH_PREFIX)) return;

        if (DB::table('partner_banners')->where('image_path', $imagePath)
            ->when($exceptIdx !== null, fn ($query) => $query->where('idx', '<>', $exceptIdx))->exists()) {
            return;
        }

        Storage::disk('public')->delete(substr($imagePath, strlen('storage/')));
    }
}
