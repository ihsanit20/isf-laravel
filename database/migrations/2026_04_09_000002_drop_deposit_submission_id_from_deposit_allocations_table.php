<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposit_allocations', function (Blueprint $table) {
            $table->dropForeign(['deposit_submission_id']);
            $table->dropIndex(['deposit_submission_id', 'member_id']);
            $table->dropColumn('deposit_submission_id');
        });
    }

    public function down(): void
    {
        Schema::table('deposit_allocations', function (Blueprint $table) {
            $table->foreignId('deposit_submission_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->restrictOnDelete();

            $table->index(['deposit_submission_id', 'member_id']);
        });
    }
};