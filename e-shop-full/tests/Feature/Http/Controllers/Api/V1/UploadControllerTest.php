<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UploadControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_store_saves_image_in_the_shop_folder_and_returns_its_url(): void
    {
        Storage::fake('public');
        $shop = Shop::factory()->for(Sanctum::actingAs(User::factory()->create()), 'owner')->create();

        $response = $this->postJson("/api/v1/shops/{$shop->id}/uploads", [
            'image' => UploadedFile::fake()->image('robe.jpg', 800, 800),
        ])->assertCreated();

        $files = Storage::disk('public')->files("shops/{$shop->id}");
        $this->assertCount(1, $files);
        $this->assertSame(Storage::disk('public')->url($files[0]), $response->json('data.url'));
    }

    public function test_store_rejects_non_image_file_with_422(): void
    {
        Storage::fake('public');
        $shop = Shop::factory()->for(Sanctum::actingAs(User::factory()->create()), 'owner')->create();

        $this->postJson("/api/v1/shops/{$shop->id}/uploads", [
            'image' => UploadedFile::fake()->create('script.php', 10, 'application/x-php'),
        ])->assertUnprocessable()->assertJsonValidationErrors('image');

        Storage::disk('public')->assertDirectoryEmpty("shops/{$shop->id}");
    }

    public function test_store_forbids_uploading_to_another_merchants_shop_with_403(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());
        $shop = Shop::factory()->create();

        $this->postJson("/api/v1/shops/{$shop->id}/uploads", ['image' => UploadedFile::fake()->image('x.png')])->assertForbidden();
    }
}
