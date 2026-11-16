<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Note-only operator records for a business investment (how the partner
 * spent and earned). Never posted to the journal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_sub_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_investment_id')->constrained()->cascadeOnDelete();
            $table->enum('direction', ['expense', 'income']);
            $table->string('category', 100);
            $table->decimal('amount', 12, 2);
            $table->date('entry_date');
            $table->text('note')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cycle_investment_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_sub_entries');
    }
};
