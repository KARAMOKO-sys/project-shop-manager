<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Erp;

use App\Models\Expense;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Erp\ModuleRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function expensesShop(): Shop
    {
        $shop = Shop::factory()->for(Sanctum::actingAs(User::factory()->create()), 'owner')->create();
        app(ModuleRegistry::class)->install($shop, 'expenses');

        return $shop;
    }

    public function test_store_creates_expense_in_shop_currency(): void
    {
        $shop = $this->expensesShop();

        $this->postJson("/api/v1/shops/{$shop->id}/erp/expenses", [
            'category' => 'rent',
            'label' => 'Loyer novembre',
            'amount' => 150000,
            'spent_at' => '2026-11-01',
        ])
            ->assertCreated()
            ->assertJsonPath('data.category', 'rent')
            ->assertJsonPath('data.category_label', 'Loyer')
            ->assertJsonPath('data.amount', 150000)
            ->assertJsonPath('data.currency', 'XOF')
            ->assertJsonPath('data.supplier', null);

        $this->assertDatabaseHas('activity_logs', ['shop_id' => $shop->id, 'event' => 'expense.created']);
    }

    public function test_store_rejects_supplier_of_another_shop(): void
    {
        $shop = $this->expensesShop();
        $foreignSupplier = Supplier::create(['shop_id' => Shop::factory()->create()->id, 'name' => 'Autre']);

        $this->postJson("/api/v1/shops/{$shop->id}/erp/expenses", [
            'category' => 'supplies',
            'label' => 'Cartons',
            'amount' => 5000,
            'spent_at' => '2026-11-01',
            'supplier_id' => $foreignSupplier->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('supplier_id');
    }

    public function test_index_returns_summary_of_filtered_expenses(): void
    {
        $shop = $this->expensesShop();
        Expense::factory()->for($shop)->create(['category' => 'rent', 'amount' => 100000, 'spent_at' => '2026-11-01']);
        Expense::factory()->for($shop)->create(['category' => 'transport', 'amount' => 5000, 'spent_at' => '2026-11-05']);
        Expense::factory()->for($shop)->create(['category' => 'transport', 'amount' => 2500, 'spent_at' => '2026-11-06']);
        Expense::factory()->for($shop)->create(['category' => 'rent', 'amount' => 100000, 'spent_at' => '2026-10-01']);
        Expense::factory()->create(['amount' => 999999, 'spent_at' => '2026-11-02']);

        $this->getJson("/api/v1/shops/{$shop->id}/erp/expenses?from=2026-11-01&to=2026-11-30")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('summary.total', 107500)
            ->assertJsonPath('summary.by_category.rent', 100000)
            ->assertJsonPath('summary.by_category.transport', 7500);

        $this->getJson("/api/v1/shops/{$shop->id}/erp/expenses?category=transport")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('summary.total', 7500);
    }

    public function test_update_and_destroy(): void
    {
        $shop = $this->expensesShop();
        $expense = Expense::factory()->for($shop)->create(['amount' => 1000]);

        $this->putJson("/api/v1/shops/{$shop->id}/erp/expenses/{$expense->id}", ['amount' => 2000])
            ->assertOk()
            ->assertJsonPath('data.amount', 2000);

        $this->deleteJson("/api/v1/shops/{$shop->id}/erp/expenses/{$expense->id}")->assertNoContent();
        $this->assertModelMissing($expense);
    }

    public function test_expense_of_another_shop_returns_404(): void
    {
        $shop = $this->expensesShop();
        $foreign = Expense::factory()->create();

        $this->deleteJson("/api/v1/shops/{$shop->id}/erp/expenses/{$foreign->id}")->assertNotFound();
        $this->assertModelExists($foreign);
    }

    public function test_routes_return_403_when_expenses_is_not_installed(): void
    {
        $shop = Shop::factory()->for(Sanctum::actingAs(User::factory()->create()), 'owner')->create();

        $this->getJson("/api/v1/shops/{$shop->id}/erp/expenses")->assertForbidden();
    }
}
