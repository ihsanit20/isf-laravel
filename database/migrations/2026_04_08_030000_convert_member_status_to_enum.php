<?php

use App\Enums\MemberStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('members')
            ->where('status', 'inactive')
            ->update(['status' => MemberStatus::Exited->value]);

        $statusList = implode("','", MemberStatus::values());

        DB::statement(
            "ALTER TABLE members MODIFY status ENUM('{$statusList}') NOT NULL DEFAULT '" . MemberStatus::Pending->value . "'"
        );
    }

    public function down(): void
    {
        DB::table('members')
            ->where('status', MemberStatus::Exited->value)
            ->update(['status' => 'inactive']);

        DB::statement("ALTER TABLE members MODIFY status VARCHAR(20) NOT NULL DEFAULT 'pending'");
    }
};
