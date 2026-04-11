<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_phone', 20)->nullable();
            $table->string('normalized_phone', 20)->nullable();
            $table->text('message');
            $table->string('status', 20);
            $table->string('provider_code', 20)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('response_body')->nullable();
            $table->string('error_message')->nullable();
            $table->nullableMorphs('smsable');
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
