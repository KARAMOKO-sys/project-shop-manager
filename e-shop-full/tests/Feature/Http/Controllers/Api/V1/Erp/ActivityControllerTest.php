<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Erp;

use App\Models\Invoice;
use App\Models\Shop;
use App\Models\User;
use App\Services\Erp\ActivityLogger;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_shop_history_and_filters_by_record(): void
    {
        $user = Sanctum::actingAs(User::factory()->create(['name' => 'Kadi']));
        $shop = Shop::factory()->for($user, 'owner')->create();
        $invoice = Invoice::factory()->for($shop)->create();
        $logger = app(ActivityLogger::class);
        $logger->log($shop, 'note', 'Note générale');
        $logger->log($shop, 'invoice.created', 'Facture créée', $invoice);
        $logger->log(Shop::factory()->create(), 'other', 'Autre boutique');

        $this->getJson("/api/v1/shops/{$shop->id}/erp/activities")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.description', 'Facture créée')
            ->assertJsonPath('data.0.user', 'Kadi')
            ->assertJsonPath('meta.total', 2);

        $this->getJson("/api/v1/shops/{$shop->id}/erp/activities?subject_type=invoice&subject_id={$invoice->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event', 'invoice.created');
    }

    public function test_rejects_unknown_subject_type(): void
    {
        $shop = Shop::factory()->for(Sanctum::actingAs(User::factory()->create()), 'owner')->create();

        $this->getJson("/api/v1/shops/{$shop->id}/erp/activities?subject_type=user&subject_id=1")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subject_type');
    }
}
