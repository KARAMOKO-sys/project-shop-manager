<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->boolean('delivery_enabled')->default(true)->after('cash_on_delivery_enabled');
            $table->decimal('delivery_fee', 10, 2)->default(0)->after('delivery_enabled');
            $table->decimal('free_delivery_threshold', 10, 2)->nullable()->after('delivery_fee');
            $table->string('delivery_zone', 160)->nullable()->after('free_delivery_threshold');
            $table->string('delivery_delay', 80)->nullable()->after('delivery_zone');
            $table->boolean('pickup_enabled')->default(false)->after('delivery_delay');
            $table->string('pickup_address', 255)->nullable()->after('pickup_enabled');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_method', 20)->default('delivery')->after('payment_status');
            $table->string('delivery_address', 255)->nullable()->after('delivery_method');
            $table->string('delivery_city', 120)->nullable()->after('delivery_address');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_method', 'delivery_address', 'delivery_city']);
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['delivery_enabled', 'delivery_fee', 'free_delivery_threshold', 'delivery_zone', 'delivery_delay', 'pickup_enabled', 'pickup_address']);
        });
    }
};
