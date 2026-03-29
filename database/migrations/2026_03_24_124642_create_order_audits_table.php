<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->json('changes');
            $table->text('reason')->nullable();
            $table->decimal('old_total', 10, 2)->nullable();
            $table->decimal('new_total', 10, 2)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('cancelled_at');
            $table->unsignedInteger('edit_count')->default(0)->after('edited_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['edited_at', 'edit_count']);
        });

        Schema::dropIfExists('order_audits');
    }
};
