<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\User;
use App\Support\ImageData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Pictures are sent once when chosen; the form only carries a short reference. */
class PictureUploadTest extends TestCase
{
    use RefreshDatabase;

    private function jpg(): string
    {
        $img = imagecreatetruecolor(1000, 600);
        ob_start(); imagejpeg($img); return ob_get_clean();
    }

    public function test_picture_is_uploaded_once_then_saved_with_the_part(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $this->seed();
        $this->actingAs(User::factory()->role(User::ADMIN)->create());

        $token = $this->call('POST', route('pictures.upload'), [], [], [], ['CONTENT_TYPE' => 'image/jpeg', 'HTTP_ACCEPT' => 'application/json'], $this->jpg())
            ->assertOk()->json('token');
        $this->assertMatchesRegularExpression('/^upload:[A-Za-z0-9]{40}\.jpg$/', $token);

        Livewire::test(\App\Livewire\Parts\Index::class)->call('create')
            ->set('form.name', 'Brake Disc Front')->set('form.category', 'Car Parts')->set('form.unit', 'Pcs')
            ->set('form.purchase_price', '10000')->set('form.selling_price', '15000')
            ->set('photoData', $token)->call('save')->assertHasNoErrors();
        $part = Part::where('name', 'Brake Disc Front')->first();
        Storage::disk('public')->assertExists($part->image_path);
        $this->assertSame([], Storage::disk('local')->files(ImageData::HOLDING));   // moved, not copied

        // the small copy for cards is made once
        $thumb = $part->thumbUrl();
        $this->assertStringContainsString('thumbs/', $thumb);
        [$w] = getimagesizefromstring(Storage::disk('public')->get('thumbs/'.preg_replace('/\.\w+$/', '.jpg', $part->image_path)));
        $this->assertSame(480, $w);

        // a used or made-up reference is refused
        $this->expectException(\InvalidArgumentException::class);
        ImageData::store($token, 'parts');
    }

    public function test_upload_rejects_non_pictures_and_customers(): void
    {
        Storage::fake('local');
        $this->seed();
        $this->actingAs(User::factory()->role(User::ADMIN)->create());
        $this->call('POST', route('pictures.upload'), [], [], [], ['CONTENT_TYPE' => 'image/jpeg', 'HTTP_ACCEPT' => 'application/json'], '<?php echo 1;')->assertStatus(422);
        auth()->logout();
        $this->call('POST', route('pictures.upload'), [], [], [], ['CONTENT_TYPE' => 'image/jpeg'], $this->jpg())->assertRedirect();
    }

    public function test_pictures_load_without_the_storage_link_and_on_any_address(): void
    {
        \Illuminate\Support\Facades\Storage::disk('public')->put('parts/test-pic.jpg', $this->jpg());
        $this->assertSame('/storage/parts/test-pic.jpg', \Illuminate\Support\Facades\Storage::disk('public')->url('parts/test-pic.jpg'));
        $this->get('/storage/parts/test-pic.jpg')->assertOk();
        $this->get('/storage/../.env')->assertNotFound();
        $this->get('/storage/parts/missing.jpg')->assertNotFound();
        \Illuminate\Support\Facades\Storage::disk('public')->delete('parts/test-pic.jpg');
    }

    public function test_lipa_namba_payments_become_m_pesa(): void
    {
        $this->seed();
        \Illuminate\Support\Facades\DB::table('list_options')->insert(['list' => 'payment_mode', 'value' => 'M-Pesa (Lipa Namba)', 'sort_order' => 9]);
        (require database_path('migrations/2026_11_24_001500_merge_mpesa_lipa_namba_payment_mode.php'))->up();
        $this->assertFalse(\Illuminate\Support\Facades\DB::table('list_options')->where('value', 'like', '%Lipa%')->exists());
    }
}
