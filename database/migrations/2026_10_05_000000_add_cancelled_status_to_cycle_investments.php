<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cycle_investments', function (Blueprint $table) {
            $table->enum('status', ['active', 'closed', 'cancelled'])->default('active')->change();
        });
    }

    public function down(): void
    {
        Schema::table('cycle_investments', function (Blueprint $table) {
            $table->enum('status', ['active', 'closed'])->default('active')->change();
        });
    }
};
