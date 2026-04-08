<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $column = DB::selectOne("SHOW COLUMNS FROM users LIKE 'role'");

        if ($column !== null && str_starts_with(strtolower($column->Type), 'enum(')) {
            return;
        }

        $allowedValues = implode(", ", array_map(
            static fn(string $value) => sprintf("'%s'", $value),
            UserRole::values(),
        ));

        DB::statement(sprintf(
            'ALTER TABLE users MODIFY role ENUM(%s) NOT NULL DEFAULT %s',
            $allowedValues,
            sprintf("'%s'", UserRole::MEMBER->value),
        ));
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE users MODIFY role VARCHAR(255) NOT NULL DEFAULT %s',
            sprintf("'%s'", UserRole::MEMBER->value),
        ));
    }
};
