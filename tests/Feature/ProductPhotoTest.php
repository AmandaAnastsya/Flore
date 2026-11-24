<?php

namespace Tests\Feature;

use App\Models\Bouquet;
use App\Models\Category;
use App\Models\User;
use App\Services\ProductPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductPhotoTest extends TestCase
{
    use RefreshDatabase;

    private Bouquet $bouquet;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');

        $cat = Category::create(['name' => 'Hand Bouquet', 'slug' => 'hand-bouquet', 'is_active' => true]);
        $this->bouquet = Bouquet::create([
            'category_id' => $cat->id, 'name' => 'The Sweet Blush Romance', 'slug' => 'the-sweet-blush-romance',
            'description' => 'Pink Spray Roses & Peonies', 'price' => 300000, 'stock' => 5,
        ]);
    }

    /** Gambar polos berwarna gelap seukuran w×h (logo putih jadi mudah dideteksi). */
    private function jpeg(int $w, int $h): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 60, 30, 40));
        $path = tempnam(sys_get_temp_dir(), 'img') . '.jpg';
        imagejpeg($img, $path, 90);

        return $path;
    }

    private function decode(string $binary): \GdImage
    {
        return imagecreatefromstring($binary);
    }

    private function brightness(\GdImage $img, int $x, int $y): int
    {
        $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));

        return (int) (($c['red'] + $c['green'] + $c['blue']) / 3);
    }

    public function test_photo_is_cropped_to_4_3_resized_and_saved_as_webp(): void
    {
        $out = app(ProductPhoto::class)->render($this->jpeg(3000, 1000));   // sangat lebar

        $this->assertStringStartsWith('RIFF', $out);
        $this->assertSame('WEBP', substr($out, 8, 4));
        $img = $this->decode($out);
        $this->assertSame([1200, 900], [imagesx($img), imagesy($img)]);

        $tall = $this->decode(app(ProductPhoto::class)->render($this->jpeg(900, 2000)));   // sangat tinggi
        $this->assertSame([900, 675], [imagesx($tall), imagesy($tall)]);                     // 4:3, tidak diperbesar
    }

    public function test_flore_logo_is_stamped_bottom_left_only(): void
    {
        $img = $this->decode(app(ProductPhoto::class)->render($this->jpeg(1600, 1200)));

        // area pil logo (kiri bawah) terang, pojok lain tetap gelap
        $this->assertGreaterThan(180, $this->brightness($img, 60, 900 - 50));
        $this->assertLessThan(90, $this->brightness($img, 1200 - 60, 900 - 50));   // kanan bawah
        $this->assertLessThan(90, $this->brightness($img, 60, 50));                // kiri atas
    }

    public function test_admin_upload_is_processed_with_logo(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->put("/admin/bouquet-images/{$this->bouquet->id}", ['image' => UploadedFile::fake()->image('foto.jpg', 2000, 1500)])
            ->assertSessionHasNoErrors();

        $path = $this->bouquet->fresh()->image;
        $this->assertStringEndsWith('.webp', $path);
        $img = $this->decode(Storage::disk('public')->get($path));
        $this->assertSame([1200, 900], [imagesx($img), imagesy($img)]);
    }

    /** Folder sementara berisi foto dengan nama file tertentu. */
    private function folderWith(array $files): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flore-import-' . uniqid();
        mkdir($dir);
        foreach ($files as $name => $content) {
            file_put_contents("{$dir}/{$name}", $content ?? file_get_contents($this->jpeg(1600, 1200)));
        }

        return $dir;
    }

    public function test_import_matches_file_names_to_bouquets_regardless_of_case_and_spaces(): void
    {
        $dir = $this->folderWith(['THE SWEET BLUSH ROMANCE.JPG' => null]);

        $this->artisan('bouquets:import-photos', ['folder' => $dir])
            ->expectsOutputToContain('THE SWEET BLUSH ROMANCE.JPG  →  The Sweet Blush Romance')
            ->assertSuccessful();

        $path = $this->bouquet->fresh()->image;
        $this->assertStringEndsWith('.webp', $path);
        $img = $this->decode(Storage::disk('public')->get($path));
        $this->assertSame([1200, 900], [imagesx($img), imagesy($img)]);
        $this->assertGreaterThan(180, $this->brightness($img, 60, 900 - 50));   // logo Floré terpasang
    }

    public function test_import_replaces_old_photo_and_deletes_old_file(): void
    {
        Storage::disk('public')->put('bouquets/lama.webp', 'x');
        $this->bouquet->update(['image' => 'bouquets/lama.webp']);

        $this->artisan('bouquets:import-photos', ['folder' => $this->folderWith(['the-sweet-blush-romance.png' => null])])->assertSuccessful();

        Storage::disk('public')->assertMissing('bouquets/lama.webp');
        $this->assertNotSame('bouquets/lama.webp', $this->bouquet->fresh()->image);
    }

    public function test_import_reports_typos_unreadable_files_and_dry_run_changes_nothing(): void
    {
        $dir = $this->folderWith([
            'Sweet Blush Romance.jpg' => null,          // salah ketik (tanpa "The")
            'The Sweet Blush Romance.jpg' => 'bukan gambar',
            'catatan.txt' => 'diabaikan',
        ]);

        $this->artisan('bouquets:import-photos', ['folder' => $dir])
            ->expectsOutputToContain('maksudnya "The Sweet Blush Romance"?')
            ->expectsOutputToContain('bukan gambar yang bisa dibaca')
            ->assertSuccessful();
        $this->assertNull($this->bouquet->fresh()->image);

        $ok = $this->folderWith(['The Sweet Blush Romance.jpg' => null]);
        $this->artisan('bouquets:import-photos', ['folder' => $ok, '--dry-run' => true])
            ->expectsOutputToContain('akan dipasang ke  The Sweet Blush Romance')->assertSuccessful();
        $this->assertNull($this->bouquet->fresh()->image);
    }

    public function test_import_fails_clearly_when_folder_missing(): void
    {
        $this->artisan('bouquets:import-photos', ['folder' => 'folder-yang-tidak-ada'])
            ->expectsOutputToContain('Folder tidak ditemukan')->assertFailed();
    }
}
