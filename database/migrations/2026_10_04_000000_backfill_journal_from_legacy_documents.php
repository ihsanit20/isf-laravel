<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * One-time move of pre-journal money documents into the journal, so a deploy
 * (`migrate --force`) leaves no window with empty balances. The command is
 * all-or-nothing and skips anything already posted, so this is a no-op on
 * databases that were backfilled by hand or started with the journal.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exitCode = Artisan::call('ledger:backfill');
        $output = Artisan::output();

        if ($exitCode !== 0) {
            throw new RuntimeException("Journal backfill failed, nothing was saved:\n".$output);
        }

        logger()->info('Journal backfill migration finished', ['report' => $output]);
    }

    /**
     * Journal entries are immutable; restore the pre-deploy database backup
     * to undo this migration.
     */
    public function down(): void {}
};
