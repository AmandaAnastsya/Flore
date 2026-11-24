<?php

namespace App\Console\Commands;

use App\Models\Bouquet;
use App\Services\ProductPhoto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Memasang foto bouquet milik sendiri dari satu folder.
 * Nama file = nama bouquet (huruf besar/kecil, spasi & simbol bebas), mis. "The Sweet Blush Romance.jpg".
 * Setiap foto diproses ProductPhoto: crop 4:3, clean look, logo Floré kiri bawah, WebP.
 */
class ImportBouquetPhotos extends Command
{
    protected $signature = 'bouquets:import-photos
        {folder=foto-bouquet : Folder berisi foto (relatif ke folder project)}
        {--dry-run : Hanya tampilkan pencocokan, tanpa mengubah apa pun}';

    protected $description = 'Pasang foto bouquet dari folder (nama file = nama bouquet) + logo Floré';

    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    private const MAX_BYTES = 20 * 1024 * 1024;

    public function handle(ProductPhoto $photos): int
    {
        $folder = $this->resolveFolder($this->argument('folder'));
        if (! is_dir($folder)) {
            $this->error("Folder tidak ditemukan: {$folder}");

            return self::FAILURE;
        }

        $bouquets = Bouquet::orderBy('name')->get();
        $bySlug = $bouquets->keyBy(fn ($b) => Str::slug($b->name));
        $files = collect(scandir($folder))
            ->filter(fn ($f) => in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), self::EXTENSIONS, true))
            ->values();

        if ($files->isEmpty()) {
            $this->warn("Belum ada foto (.jpg/.jpeg/.png/.webp) di {$folder}");

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $done = [];

        foreach ($files as $file) {
            $key = Str::slug(pathinfo($file, PATHINFO_FILENAME));
            $bouquet = $bySlug->get($key);
            $path = $folder . DIRECTORY_SEPARATOR . $file;

            if (! $bouquet) {
                $this->warn("✗ {$file}: tidak ada bouquet bernama ini" . $this->suggest($key, $bySlug->keys()->all(), $bouquets));
                continue;
            }
            if (isset($done[$bouquet->id])) {
                $this->warn("✗ {$file}: {$bouquet->name} sudah diisi dari file \"{$done[$bouquet->id]}\"");
                continue;
            }
            if (filesize($path) > self::MAX_BYTES) {
                $this->warn("✗ {$file}: file terlalu besar (maks 20 MB)");
                continue;
            }

            if ($dry) {
                $this->line("→ {$file}  akan dipasang ke  {$bouquet->name}");
                $done[$bouquet->id] = $file;
                continue;
            }

            try {
                $stored = $photos->store($path);
            } catch (Throwable $e) {
                $this->warn("✗ {$file}: bukan gambar yang bisa dibaca ({$e->getMessage()})");
                continue;
            }

            if ($bouquet->image) {
                Storage::disk('public')->delete($bouquet->image);
            }
            $bouquet->update(['image' => $stored]);
            $done[$bouquet->id] = $file;
            $this->line("✓ {$file}  →  {$bouquet->name}");
        }

        $missing = $bouquets->reject(fn ($b) => isset($done[$b->id]) || ($b->image && ! $dry))->pluck('name');
        $this->newLine();
        $this->info(($dry ? 'Pratinjau: ' : '') . count($done) . ' foto ' . ($dry ? 'cocok' : 'terpasang') . '.');
        if ($missing->isNotEmpty()) {
            $this->line('Bouquet yang belum punya foto: ' . $missing->implode(', '));
        }

        return self::SUCCESS;
    }

    private function resolveFolder(string $folder): string
    {
        return preg_match('/^([A-Za-z]:[\\\\\/]|\/)/', $folder) ? $folder : base_path($folder);
    }

    /** Saran nama terdekat untuk salah ketik. */
    private function suggest(string $key, array $slugs, $bouquets): string
    {
        $best = collect($slugs)->sortBy(fn ($s) => levenshtein($key, $s))->first();

        if ($best === null || levenshtein($key, $best) > max(4, (int) (strlen($best) * 0.35))) {
            return '';
        }

        return ' — maksudnya "' . $bouquets->first(fn ($b) => Str::slug($b->name) === $best)->name . '"?';
    }
}
