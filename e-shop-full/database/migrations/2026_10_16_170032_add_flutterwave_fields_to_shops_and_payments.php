<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->text('flutterwave_secret_key')->nullable();
            $table->text('flutterwave_webhook_secret_hash')->nullable();
            $table->boolean('flutterwave_enabled')->default(false);
            $table->boolean('cash_on_delivery_enabled')->default(true);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider_transaction_id')->nullable()->unique();
            $table->unique('order_id');
            $table->index(['provider', 'transaction_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['provider_transaction_id']);
            $table->dropUnique(['order_id']);
            $table->dropIndex(['provider', 'transaction_reference']);
            $table->dropColumn('provider_transaction_id');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'flutterwave_secret_key',
                'flutterwave_webhook_secret_hash',
                'flutterwave_enabled',
                'cash_on_delivery_enabled',
            ]);
        });
    }
};
