<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('theme');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->string('tagline')->nullable()->after('name');
            $table->string('sector', 50)->nullable()->after('description');
            $table->string('template', 50)->default('minimal')->after('sector');
            $table->json('theme')->nullable()->after('template');
            $table->string('logo_url')->nullable()->after('theme');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->unique(['shop_id', 'slug']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropUnique(['sku']);
            $table->unique(['shop_id', 'slug']);
            $table->unique(['shop_id', 'sku']);
            $table->json('tags')->nullable()->after('image_url');
            $table->string('moderation_status', 30)->default('pending')->after('tags');
            $table->unsignedTinyInteger('moderation_risk_score')->default(0)->after('moderation_status');
            $table->json('moderation_reasons')->nullable()->after('moderation_risk_score');
            $table->string('moderation_engine', 20)->nullable()->after('moderation_reasons');
            $table->text('moderation_note')->nullable()->after('moderation_engine');
            $table->timestamp('moderated_at')->nullable()->after('moderation_note');
            $table->index(['moderation_status', 'status']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->unique(['shop_id', 'email']);
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['shop_id', 'code']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('customer_id')->constrained('coupons')->nullOnDelete();
            $table->string('currency', 10)->default('XOF')->after('total');
            $table->string('payment_method', 30)->default('cash_on_delivery')->after('currency');
            $table->string('payment_status', 30)->default('pending')->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['currency', 'payment_method', 'payment_status']);
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->index('shop_id');
            $table->dropUnique(['shop_id', 'code']);
            $table->unique('code');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index('shop_id');
            $table->dropUnique(['shop_id', 'email']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['moderation_status', 'status']);
            $table->index('shop_id');
            $table->dropUnique(['shop_id', 'slug']);
            $table->dropUnique(['shop_id', 'sku']);
            $table->unique('slug');
            $table->unique('sku');
            $table->dropColumn(['tags', 'moderation_status', 'moderation_risk_score', 'moderation_reasons', 'moderation_engine', 'moderation_note', 'moderated_at']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index('shop_id');
            $table->dropUnique(['shop_id', 'slug']);
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['tagline', 'sector', 'template', 'theme', 'logo_url']);
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->string('theme')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
