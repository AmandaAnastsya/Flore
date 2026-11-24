<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Memproses foto produk agar seragam & elegan:
 *  1. rotasi sesuai EXIF (foto HP), 2. crop tengah 4:3 → 1200×900,
 *  3. sentuhan "clean look" ringan (sedikit lebih terang & kontras),
 *  4. logo "Floré" kecil di kiri bawah, 5. simpan WebP.
 */
class ProductPhoto
{
    public const WIDTH = 1200;
    public const HEIGHT = 900;
    private const QUALITY = 84;
    private const PLUM = [74, 42, 48];          // #4a2a30
    private const SUPERSAMPLE = 4;              // logo digambar 4× lalu diperkecil → tepi halus

    public function __construct(private ?string $font = null)
    {
        $this->font ??= resource_path('fonts/PlayfairDisplay.ttf');
    }

    /** Proses file gambar lalu simpan; mengembalikan path di disk (mis. "bouquets/abc.webp"). */
    public function store(string $sourcePath, string $dir = 'bouquets', string $disk = 'public'): string
    {
        $path = trim($dir, '/') . '/' . Str::random(40) . '.webp';
        Storage::disk($disk)->put($path, $this->render($sourcePath));

        return $path;
    }

    /** Hasilkan biner WebP tanpa menyimpan (dipakai untuk pratinjau/test). */
    public function render(string $sourcePath): string
    {
        $src = @imagecreatefromstring((string) file_get_contents($sourcePath));
        if (! $src) {
            throw new RuntimeException('File bukan gambar yang bisa dibaca.');
        }

        $src = $this->fixOrientation($src, $sourcePath);
        $img = $this->cropToFrame($src);
        imagedestroy($src);

        $this->cleanLook($img);
        $this->stampLogo($img);

        ob_start();
        imagewebp($img, null, self::QUALITY);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    private function fixOrientation(\GdImage $img, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $img;
        }

        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
        $angle = match ($orientation) { 3 => 180, 6 => -90, 8 => 90, default => 0 };

        if ($angle === 0) {
            return $img;
        }

        $rotated = imagerotate($img, $angle, 0);
        imagedestroy($img);

        return $rotated;
    }

    /** Crop tengah ke rasio 4:3, lalu ubah ukuran (tidak memperbesar foto yang lebih kecil). */
    private function cropToFrame(\GdImage $src): \GdImage
    {
        [$w, $h] = [imagesx($src), imagesy($src)];
        $ratio = self::WIDTH / self::HEIGHT;

        if ($w / $h > $ratio) {             // terlalu lebar → potong kiri-kanan
            $cropH = $h;
            $cropW = (int) round($h * $ratio);
        } else {                            // terlalu tinggi → potong atas-bawah
            $cropW = $w;
            $cropH = (int) round($w / $ratio);
        }
        $x = (int) (($w - $cropW) / 2);
        $y = (int) (($h - $cropH) / 2);

        $outW = min(self::WIDTH, $cropW);
        $outH = (int) round($outW / $ratio);

        $out = imagecreatetruecolor($outW, $outH);
        imagecopyresampled($out, $src, 0, 0, $x, $y, $outW, $outH, $cropW, $cropH);

        return $out;
    }

    /** Sentuhan halus: sedikit lebih terang & jernih, tanpa mengubah warna bunga. */
    private function cleanLook(\GdImage $img): void
    {
        imagefilter($img, IMG_FILTER_BRIGHTNESS, 6);
        imagefilter($img, IMG_FILTER_CONTRAST, -6);
    }

    /** Logo "Floré": pil putih semi-transparan + teks plum, kiri bawah. */
    private function stampLogo(\GdImage $img): void
    {
        $w = imagesx($img);
        $s = self::SUPERSAMPLE;

        $fontSize = max(11, $w * 0.026);                 // ±31px pada lebar 1200 → kecil & elegan
        $box = imagettfbbox($fontSize * $s, 0, $this->font, 'Floré');
        $textW = abs($box[2] - $box[0]);
        $textH = abs($box[1] - $box[7]);

        $padX = $fontSize * 0.75 * $s;
        $padY = $fontSize * 0.42 * $s;
        $badgeW = (int) ceil($textW + $padX * 2);
        $badgeH = (int) ceil($textH + $padY * 2);
        $radius = (int) ($badgeH / 2);

        // gambar besar (4×) dengan alpha
        $big = imagecreatetruecolor($badgeW, $badgeH);
        imagealphablending($big, false);
        imagesavealpha($big, true);
        imagefill($big, 0, 0, imagecolorallocatealpha($big, 0, 0, 0, 127));

        $bg = imagecolorallocatealpha($big, 255, 255, 255, 22);    // ±83% putih
        imagefilledrectangle($big, $radius, 0, $badgeW - $radius, $badgeH - 1, $bg);
        imagefilledellipse($big, $radius, $radius, $radius * 2, $badgeH, $bg);
        imagefilledellipse($big, $badgeW - $radius - 1, $radius, $radius * 2, $badgeH, $bg);

        imagealphablending($big, true);
        $plum = imagecolorallocate($big, ...self::PLUM);
        // y = garis dasar teks: padding atas + tinggi bagian di atas baseline ($box[7] bernilai negatif)
        imagettftext($big, $fontSize * $s, 0, (int) ($padX - $box[0]), (int) ($padY - $box[7]), $plum, $this->font, 'Floré');

        // perkecil ke ukuran asli → tepi & teks halus
        $smallW = (int) round($badgeW / $s);
        $smallH = (int) round($badgeH / $s);
        $small = imagecreatetruecolor($smallW, $smallH);
        imagealphablending($small, false);
        imagesavealpha($small, true);
        imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));
        imagecopyresampled($small, $big, 0, 0, 0, 0, $smallW, $smallH, $badgeW, $badgeH);
        imagedestroy($big);

        $margin = (int) round($w * 0.03);
        imagealphablending($img, true);
        imagecopy($img, $small, $margin, imagesy($img) - $smallH - $margin, 0, 0, $smallW, $smallH);
        imagedestroy($small);
    }
}
