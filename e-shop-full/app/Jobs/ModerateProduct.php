<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\AI\ProductModerationAgent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ModerateProduct implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public function __construct(public Product $product) {}

    public function uniqueId(): string
    {
        return (string) $this->product->id;
    }

    public function handle(ProductModerationAgent $agent): void
    {
        $agent->moderate($this->product->loadMissing('shop', 'category'));
    }
}
