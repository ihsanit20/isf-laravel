<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('title');
            $table->unsignedInteger('default_amount');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('charge_categories')->insert([
            'code' => 'registration_fee',
            'title' => 'Registration Fee',
            'default_amount' => 100,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_categories');
    }
};
