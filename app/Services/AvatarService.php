<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Foto profil: crop tengah jadi persegi, 256×256 px, WebP — metadata (EXIF/lokasi) ikut terbuang. */
class AvatarService
{
    public const SIZE = 256;
    public const DISK = 'public';

    public function store(User $user, UploadedFile $file): string
    {
        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $src) {
            throw new RuntimeException('Gambar tidak bisa dibaca.');
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $dst = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), self::SIZE, self::SIZE, $side, $side);

        ob_start();
        imagewebp($dst, null, 85);
        $binary = ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        $path = 'avatars/' . $user->id . '-' . Str::lower(Str::random(12)) . '.webp';
        Storage::disk(self::DISK)->put($path, $binary);

        $this->delete($user);
        $user->forceFill(['avatar' => $path])->save();

        return $path;
    }

    public function delete(User $user): void
    {
        if ($user->avatar) {
            Storage::disk(self::DISK)->delete($user->avatar);
            $user->forceFill(['avatar' => null])->save();
        }
    }
}
