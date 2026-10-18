<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class ManagedImageStorage
{
    private const PUBLIC_PREFIX = 'storage/';
    public const DEFAULT_IMAGE_PATH = 'images/no-image.png';

    public static function store(UploadedFile $file, string $directory): string
    {
        $storedPath = $file->store($directory, 'public');

        if (!is_string($storedPath) || !Storage::disk('public')->exists($storedPath)) {
            if (is_string($storedPath)) {
                Storage::disk('public')->delete($storedPath);
            }

            throw ValidationException::withMessages([
                'profile_image' => '프로필 이미지를 저장하지 못했습니다. 다시 시도해 주세요.',
            ]);
        }

        return self::PUBLIC_PREFIX.$storedPath;
    }

    public static function deleteIfUnreferenced(?string $imagePath): void
    {
        if (!$imagePath || !str_starts_with($imagePath, self::PUBLIC_PREFIX)) {
            return;
        }

        // Individual idols and groups share the idols table, so this protects both types.
        $isStillReferenced = DB::table('idols')->where('image_path', $imagePath)->exists()
            || DB::table('idol_registration_requests')->where('image_path', $imagePath)->exists()
            || DB::table('members')->where('profile_image_path', $imagePath)->exists()
            || (Schema::hasTable('idol_profile_image_reports')
                && DB::table('idol_profile_image_reports')->where('reported_image_path', $imagePath)->exists());

        if (!$isStillReferenced) {
            Storage::disk('public')->delete(substr($imagePath, strlen(self::PUBLIC_PREFIX)));
        }
    }

    public static function isAvailable(?string $imagePath): bool
    {
        if (!$imagePath) {
            return false;
        }

        if (!str_starts_with($imagePath, self::PUBLIC_PREFIX)) {
            return true;
        }

        return Storage::disk('public')->exists(substr($imagePath, strlen(self::PUBLIC_PREFIX)));
    }

    public static function defaultUrl(): string
    {
        return asset(self::DEFAULT_IMAGE_PATH);
    }

    public static function publicUrl(?string $imagePath): string
    {
        if (!$imagePath) {
            return self::defaultUrl();
        }

        if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            return $imagePath;
        }

        $normalizedPath = ltrim($imagePath, '/');
        if (str_starts_with($normalizedPath, self::PUBLIC_PREFIX)) {
            return self::isAvailable($normalizedPath) ? asset($normalizedPath) : self::defaultUrl();
        }

        return is_file(public_path($normalizedPath)) ? asset($normalizedPath) : self::defaultUrl();
    }
}
