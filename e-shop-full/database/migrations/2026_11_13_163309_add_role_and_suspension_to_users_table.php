<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('merchant')->after('password')->index();
            $table->timestamp('suspended_at')->nullable()->after('role');
            $table->foreignId('created_by')->nullable()->after('suspended_at')->constrained('users')->nullOnDelete();
        });

        DB::table('users')->where('is_admin', true)->update(['role' => 'super_admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('moderated_by')->nullable()->after('moderated_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('moderated_by');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });

        DB::table('users')->where('role', 'super_admin')->update(['is_admin' => true]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['role', 'suspended_at']);
        });
    }
};
