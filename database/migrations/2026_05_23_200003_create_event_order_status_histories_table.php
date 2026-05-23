<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_order_id')->constrained('event_orders')->cascadeOnDelete();

            $table->string('status', 20);
            $table->text('note')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();

            $table->index('event_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_order_status_histories');
    }
};
