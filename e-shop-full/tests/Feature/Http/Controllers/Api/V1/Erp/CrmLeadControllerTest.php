<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Erp;

use App\Enums\LeadStage;
use App\Models\CrmLead;
use App\Models\Shop;
use App\Models\User;
use App\Services\Erp\ModuleRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmLeadControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function crmShop(): Shop
    {
        $shop = Shop::factory()->for(Sanctum::actingAs(User::factory()->create()), 'owner')->create();
        app(ModuleRegistry::class)->install($shop, 'crm');

        return $shop;
    }

    public function test_store_applies_stage_default_probability(): void
    {
        $shop = $this->crmShop();

        $this->postJson("/api/v1/shops/{$shop->id}/erp/leads", [
            'title' => 'Uniformes école Sainte-Marie',
            'contact_name' => 'Mariam Koné',
            'source' => 'whatsapp',
            'expected_revenue' => 450000,
            'stage' => 'qualified',
        ])
            ->assertCreated()
            ->assertJsonPath('data.stage', 'qualified')
            ->assertJsonPath('data.stage_label', 'Qualifié')
            ->assertJsonPath('data.source_label', 'WhatsApp')
            ->assertJsonPath('data.probability', 30)
            ->assertJsonPath('data.weighted_revenue', 135000);
    }

    public function test_store_cannot_create_a_closed_lead(): void
    {
        $shop = $this->crmShop();

        $this->postJson("/api/v1/shops/{$shop->id}/erp/leads", ['title' => 'X', 'contact_name' => 'Y', 'source' => 'other', 'stage' => 'won'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stage');
    }

    public function test_won_lead_is_converted_into_a_customer(): void
    {
        $shop = $this->crmShop();
        $lead = CrmLead::factory()->for($shop)->create(['contact_name' => 'Ibrahim Traoré', 'email' => 'Ibrahim@Example.com']);

        $this->postJson("/api/v1/shops/{$shop->id}/erp/leads/{$lead->id}/stage", ['stage' => 'won'])
            ->assertOk()
            ->assertJsonPath('data.stage', 'won')
            ->assertJsonPath('data.probability', 100);

        $customer = $shop->customers()->where('email', 'ibrahim@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertSame('Ibrahim', $customer->first_name);
        $this->assertSame('Traoré', $customer->last_name);
        $this->assertSame($customer->id, $lead->fresh()->customer_id);
        $this->assertNotNull($lead->fresh()->closed_at);
        $this->assertDatabaseHas('outbox_messages', ['event' => 'lead.won', 'shop_id' => $shop->id]);
    }

    public function test_lost_requires_a_reason(): void
    {
        $shop = $this->crmShop();
        $lead = CrmLead::factory()->for($shop)->create();

        $this->postJson("/api/v1/shops/{$shop->id}/erp/leads/{$lead->id}/stage", ['stage' => 'lost'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lost_reason');

        $this->postJson("/api/v1/shops/{$shop->id}/erp/leads/{$lead->id}/stage", ['stage' => 'lost', 'lost_reason' => 'Prix trop élevé'])
            ->assertOk()
            ->assertJsonPath('data.lost_reason', 'Prix trop élevé')
            ->assertJsonPath('data.probability', 0);
    }

    public function test_pipeline_returns_weighted_forecast_and_win_rate(): void
    {
        $shop = $this->crmShop();
        CrmLead::factory()->for($shop)->create(['expected_revenue' => 100000]);
        CrmLead::factory()->for($shop)->inStage(LeadStage::Proposal)->create(['expected_revenue' => 200000]);
        CrmLead::factory()->for($shop)->inStage(LeadStage::Won)->create(['expected_revenue' => 50000]);
        CrmLead::factory()->for($shop)->inStage(LeadStage::Lost)->create(['expected_revenue' => 80000]);
        CrmLead::factory()->inStage(LeadStage::Proposal)->create(['expected_revenue' => 999999]);

        $this->getJson("/api/v1/shops/{$shop->id}/erp/leads/pipeline")
            ->assertOk()
            ->assertJsonPath('data.open_count', 2)
            ->assertJsonPath('data.weighted_forecast', 130000)
            ->assertJsonPath('data.win_rate', 50)
            ->assertJsonCount(5, 'data.stages')
            ->assertJsonPath('data.stages.2.stage', 'proposal')
            ->assertJsonPath('data.stages.2.weighted_revenue', 120000);
    }

    public function test_index_lists_shop_leads_without_pagination_and_filters(): void
    {
        $shop = $this->crmShop();
        $later = CrmLead::factory()->for($shop)->create(['expected_close_date' => '2026-12-20', 'title' => 'Mariage']);
        $sooner = CrmLead::factory()->for($shop)->create(['expected_close_date' => '2026-12-01', 'title' => 'Baptême']);
        CrmLead::factory()->create();

        $this->getJson("/api/v1/shops/{$shop->id}/erp/leads")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$sooner->id, $later->id]);

        $this->getJson("/api/v1/shops/{$shop->id}/erp/leads?search=Mari")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$later->id]);
    }

    public function test_update_cannot_change_stage_directly(): void
    {
        $shop = $this->crmShop();
        $lead = CrmLead::factory()->for($shop)->create();

        $this->putJson("/api/v1/shops/{$shop->id}/erp/leads/{$lead->id}", ['stage' => 'won'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stage');

        $this->putJson("/api/v1/shops/{$shop->id}/erp/leads/{$lead->id}", ['expected_revenue' => 75000])
            ->assertOk()
            ->assertJsonPath('data.expected_revenue', 75000);
    }

    public function test_lead_of_another_shop_returns_404(): void
    {
        $shop = $this->crmShop();
        $foreign = CrmLead::factory()->create();

        $this->deleteJson("/api/v1/shops/{$shop->id}/erp/leads/{$foreign->id}")->assertNotFound();
        $this->postJson("/api/v1/shops/{$shop->id}/erp/leads/{$foreign->id}/stage", ['stage' => 'qualified'])->assertNotFound();
        $this->assertModelExists($foreign);
    }

    public function test_routes_return_403_when_crm_is_not_installed(): void
    {
        $shop = Shop::factory()->for(Sanctum::actingAs(User::factory()->create()), 'owner')->create();

        $this->getJson("/api/v1/shops/{$shop->id}/erp/leads")->assertForbidden();
    }
}
