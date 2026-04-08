<?php

use App\Models\Member;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->unsignedInteger('registration_fee_amount')->default(Member::REGISTRATION_FEE_AMOUNT)->after('units');
            $table->string('registration_fee_payment_method', 30)->nullable()->after('registration_fee_amount');
            $table->string('registration_fee_reference_no', 100)->nullable()->after('registration_fee_payment_method');
            $table->string('registration_fee_proof_path')->nullable()->after('registration_fee_reference_no');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'registration_fee_amount',
                'registration_fee_payment_method',
                'registration_fee_reference_no',
                'registration_fee_proof_path',
            ]);
        });
    }
};
